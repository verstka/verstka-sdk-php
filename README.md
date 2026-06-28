# verstka/sdk

PHP SDK for [Verstka](https://verstka.org) API v2. Open the editor, handles signatures, callback processing, ZIP download, and media/font persistence through a storage adapter, and invoke site hooks.

## Installation

```bash
composer require verstka/sdk
```

Requirements:

| Requirement | Value |
| --- | --- |
| PHP | 8.2+ |
| Extensions | `ext-json`, `ext-hash`, `ext-zip` |
| HTTP client | Guzzle 7 |

## Configuration

```php
use Verstka\Sdk\Config\VerstkaConfig;

$config = new VerstkaConfig(
    apiKey: 'verstka-api-key',
    apiSecret: 'verstka-api-secret',
    callbackUrl: 'https://site.example/verstka/callback',
    apiUrl: 'https://api.r2.verstka.org/integration',
    maxContentSize: 200 * 1024 * 1024,
    requestTimeout: 60.0,
    downloadTimeout: 120.0,
    debug: false,
);
```

`apiUrl` defaults to `https://api.r2.verstka.org/integration`.
`maxContentSize` defaults to 200 MiB when omitted.

## Main methods

| Method | Purpose |
| --- | --- |
| `VerstkaClient::getEditorUrl(...)` | Opens a session via `POST /session/open` and returns the editor URL. |
| `VerstkaClient::processMaterialCallback(...)` | Handles `article_saved`: signature, ZIP, media, `onFinalize`. |
| `VerstkaClient::processFontsCallback(...)` | Handles `site_fonts_updated`: signature, fonts ZIP, font files, manifests. |
| `StorageAdapter::saveMedia(...)` | Saves a file from `vms_media/*` and returns a public URL. |
| `StorageAdapter::saveFontFile(...)` | Saves a font file and returns a public URL. |
| `StorageAdapter::saveFontsManifest(...)` | Saves `vms_fonts.css` or `vms_fonts.json` and returns a URL. |
| `LocalStorageAdapter` | Filesystem reference storage adapter. |
| `SignatureService::signMaterial(...)` | Builds HMAC for `material_id:url`. |
| `SignatureService::verifySignature(...)` | Verifies HMAC. |
| `UrlBuilder::buildAuthorizedContentUrl(...)` | Adds `api_key` and `material_id` to `content_url`. |

## Open editor

```php
use Verstka\Sdk\Client\VerstkaClient;
use Verstka\Sdk\Config\VerstkaConfig;

$client = new VerstkaClient(new VerstkaConfig(...));

$editorUrl = $client->getEditorUrl(
    materialId: '42',
    vmsJson: $storedVmsJson,
    metadata: [
        // optional: 'anySiteAdditionalKey' => 'anySiteAdditionalValue',
        // optional: 'timeLimitedAuthToken' => 'cms-scope-token',
        // optional: 'customContainers' => [],
        // optional: 'webhook_auth_user' => 'callback-user', // (see Callback Authorization)
        // optional: 'webhook_auth_password' => 'callback-password',
    ],
);
```

Both `vmsJson` and `metadata` accept an array or a JSON string. The SDK sends
`metadata` as a JSON object and automatically adds
`version: "php_<sdk-version>"`. For Basic Auth or a Bearer token, pass
`webhook_auth_user` and optionally `webhook_auth_password` in `metadata` when
calling `getEditorUrl` (see
[Callback Authorization](https://docs.r2.verstka.org/ru/dev/api-integration.md#callback-authorization)).

Sites usually pass `timeLimitedAuthToken` and `customContainers`, plus any site-specific keys (e.g. `anySiteAdditionalKey`: `anySiteAdditionalValue`).
Other custom keys are allowed — see
[`metadata`](https://docs.r2.verstka.org/ru/dev/api-integration.md#metadata) in the API
docs.

Service keys: `version_id`, `version_cdate`, `user_email`, `user_ip` — Verstka
adds or updates them in the callback after save; `webhook_auth_user` and
`webhook_auth_password` authorize the outgoing callback (see
[Callback Authorization](https://docs.r2.verstka.org/ru/dev/api-integration.md#callback-authorization)).
Your callback handler usually ignores `webhook_auth_*`.

Open the editor in a separate tab:

```html
<a href="<?= htmlspecialchars($getEditorUrlScript) ?>" target="_blank" rel="noopener noreferrer">
  Edit in Verstka
</a>
```

## StorageAdapter

```php
use Verstka\Sdk\Storage\StorageAdapter;

final class CmsStorage implements StorageAdapter
{
    public function saveMedia(
        string $filename,
        string $tempPath,
        string $materialId,
        array $metadata,
    ): string {
        return $this->saveToCdn("materials/$materialId/$filename", $tempPath);
    }

    public function saveFontFile(
        string $filename,
        string $tempPath,
        string $materialId,
        array $metadata,
    ): string {
        return $this->saveToCdn("verstka/fonts/$filename", $tempPath);
    }

    public function saveFontsManifest(
        string $filename,
        string $tempPath,
        string $materialId,
        array $metadata,
    ): string {
        return $this->saveToCdn("verstka/fonts/$filename", $tempPath);
    }
}
```

## Material callback

```php
use Verstka\Sdk\Finalize\ContentFinalizeContext;
use Verstka\Sdk\Finalize\ContentFinalizeResult;

$result = $client->processMaterialCallback(
    callbackData: $requestPayload,
    signature: $request->headers->get('X-Verstka-Signature', ''),
    storage: $storage,
    onFinalize: function (ContentFinalizeContext $ctx): ContentFinalizeResult {
        ArticleRepository::saveVerstkaContent(
            materialId: $ctx->materialId,
            html: $ctx->vmsHtml,
            vmsJson: $ctx->vmsJson,
            metadata: $ctx->metadata,
        );

        return new ContentFinalizeResult(success: true, vmsJson: $ctx->vmsJson);
    },
);

return json_response($result->toResponse());
```

`ContentFinalizeContext` contains `materialId`, `metadata`, `vmsJson`, `vmsHtml`,
and `savedMediaUrls`. By the time the hook runs, the SDK has already replaced
`dummy-*` URLs with public URLs from `StorageAdapter`.

## Fonts callback

```php
use Verstka\Sdk\Finalize\FontsFinalizeContext;
use Verstka\Sdk\Finalize\FontsFinalizeResult;

$result = $client->processFontsCallback(
    callbackData: $payload,
    signature: $signature,
    storage: $storage,
    onFinalize: function (FontsFinalizeContext $ctx): FontsFinalizeResult {
        SiteSettings::set('verstka_fonts_css_url', $ctx->cssUrl);
        SiteSettings::set('verstka_fonts_json_url', $ctx->jsonUrl);

        return new FontsFinalizeResult(success: true, fonts: $ctx->fonts);
    },
);
```

`onFinalize` for fonts may be omitted if saving files via `storage` and returning
the updated `fonts` tree to Verstka is enough.

## PreSave hooks

`processMaterialCallback` and `processFontsCallback` accept an optional
`onPreSave`. It runs before ZIP download when your site needs extra validation
of Verstka callback requests.

```php
use Verstka\Sdk\Finalize\ContentPreSaveContext;
use Verstka\Sdk\Finalize\PreSaveDecision;

$result = $client->processMaterialCallback(
    callbackData: $payload,
    signature: $signature,
    storage: $storage,
    onFinalize: $onFinalize,
    onPreSave: function (ContentPreSaveContext $ctx): PreSaveDecision {
        if (!UserPolicy::canSave($ctx->metadata['timeLimitedAuthToken'] ?? null, $ctx->materialId)) {
            return new PreSaveDecision(allow: false, reason: 'Access denied');
        }

        return new PreSaveDecision(allow: true);
    },
);
```

If `allow=false`, the ZIP is not downloaded, no files are written, and Verstka
receives `rc: 0`.

## Symfony and Laravel

| Framework | What the SDK provides |
| --- | --- |
| Symfony | Bundle, DI configuration, `CallbackController`, exception subscriber. |
| Laravel | Service provider, config publish, auto route `POST /verstka/callback`, exception renderer. |

Both framework layers use a single callback endpoint. The dispatcher reads
`event` and runs the material or fonts flow.

## Documentation

Full integration guide (Russian):
[frontend/docs/ru/dev/sdk-php.md](https://docs.r2.verstka.org/ru/dev/ru/dev/sdk-php.md)

Related: [API integration](https://docs.r2.verstka.org/ru/dev/api-integration.md),
[site integration](https://docs.r2.verstka.org/ru/dev/site-integration.md).

## License

MIT — see [LICENSE](LICENSE).
