# verstka/sdk

PHP SDK for [Verstka](https://verstka.org) API v2. Open editor sessions, verify and
process callbacks (material content + site fonts), and plug the result into
Symfony or Laravel.

## How it works

The SDK is a bridge between your CMS and the Verstka visual editor. It handles two main flows:

1. **Open the editor** — your backend requests a session URL for a specific material.
2. **Receive the result** — Verstka POSTs a signed callback to your `callback_url` after the user saves.

```
Your CMS  →  SDK (getEditorUrl)  →  Verstka API  →  editor URL
User edits in Verstka
Verstka   →  POST /your/callback  →  SDK (process*Callback)  →  your DB + storage
```

The SDK does **not** persist business data for you. It handles API communication, signature verification, ZIP download/extraction, media URL patching, and file storage via `StorageAdapter`. You provide the callback endpoint, storage, and hooks to save HTML/JSON into your database.

## Features

- Sync `VerstkaClient` on Guzzle.
- Framework-agnostic core with optional **Symfony** bundle and **Laravel** service provider.
- HMAC-SHA256 signature verification for incoming callbacks.
- Streaming ZIP download with a configurable size cap and path-traversal protection.
- Automatic extraction of `vms_media/*`, `vms_json.json`, `vms_html.html`, and font bundles.
- Automatic `dummy-*` replacement in HTML/CSS and `clientUrl` updates inside `vms_json.assets` and the fonts tree.
- Storage adapter interface with a reference filesystem implementation.
- Optional **pre-save** and **finalize** hooks for access control and CMS persistence.

## What you need to integrate

### Requirements

```bash
composer require verstka/sdk
```

- PHP **8.1+**
- Extensions: `ext-json`, `ext-hash`, `ext-zip`
- Guzzle (installed automatically)

Optional framework integrations:

```bash
composer require verstka/sdk symfony/framework-bundle   # Symfony
composer require verstka/sdk laravel/framework          # Laravel
```

### Verstka credentials

| Parameter | Purpose |
|-----------|---------|
| `apiKey` | Identifies your project in Verstka |
| `apiSecret` | Signs outgoing requests and verifies incoming callbacks |
| `callbackUrl` | Public URL of your callback endpoint (must be reachable by Verstka) |

### What you implement in your project

| Piece | Responsibility |
|-------|----------------|
| **Callback endpoint** | Public route that receives Verstka POSTs (e.g. `POST /verstka/callback`) |
| **`StorageAdapter`** | Where media and font files are stored (disk, S3, CDN, etc.) |
| **`onFinalize` hook** | Save `vmsHtml` / `vmsJson` (or font metadata) into your CMS database |
| **“Edit in Verstka” UI** | Call `getEditorUrl()` and open the returned URL for the user |

## Configuration

```php
use Verstka\Sdk\Config\VerstkaConfig;

$config = new VerstkaConfig(
    apiKey: 'verstka-api-key',
    apiSecret: 'verstka-api-secret',
    callbackUrl: 'https://app.example.com/verstka/callback',
    apiUrl: 'https://api.r2.verstka.org/integration',
    maxContentSize: 100 * 1024 * 1024,
    requestTimeout: 60.0,
    downloadTimeout: 120.0,
    basicAuthUser: null,
    basicAuthPassword: null,
    debug: false,
);
```

## Quickstart

### Open an editor session

```php
use Verstka\Sdk\Client\VerstkaClient;
use Verstka\Sdk\Config\VerstkaConfig;

$client = new VerstkaClient(new VerstkaConfig(...));

$url = $client->getEditorUrl(
    materialId: '42',
    vmsJson: ['blocks' => []],
    metadata: [
        'userId' => 11,
        'user_email' => 'user@example.com',
        'user_ip' => '127.0.0.1',
        'AnyOtherKey' => 'value',
    ],
);
```

Both `vmsJson` and `metadata` accept either an array or a JSON string.

The SDK returns a URL string only — **how you open it is up to your UI**. Opening the editor in a **new tab or window** is the recommended approach:

- Verstka is a full-screen standalone editor, not a typical admin form embedded in your layout.
- The user stays in your CMS while the editor runs separately.
- After save, Verstka sends a **server-side callback** to your backend; the browser window can simply be closed.

HTML example:

```html
<a href="{{ $editorUrl }}" target="_blank" rel="noopener noreferrer">
  Edit in Verstka
</a>
```

JavaScript example:

```javascript
window.open(editorUrl, '_blank', 'noopener,noreferrer');
```

### Process a material callback

```php
use Verstka\Sdk\Finalize\ContentFinalizeContext;
use Verstka\Sdk\Finalize\ContentFinalizeResult;
use Verstka\Sdk\Storage\LocalStorageAdapter;

$storage = new LocalStorageAdapter('/var/www/storage', 'https://cdn.example.com');

$result = $client->processMaterialCallback(
    $request->all(),
    signature: $request->header('X-Verstka-Signature', ''),
    storage: $storage,
    onFinalize: function (ContentFinalizeContext $ctx): ContentFinalizeResult {
        // Persist $ctx->vmsHtml / $ctx->vmsJson in your CMS
        return new ContentFinalizeResult(success: true, vmsJson: $ctx->vmsJson);
    },
);

return response()->json($result->toResponse());
```

### Process a fonts callback

```php
$result = $client->processFontsCallback(
    $payload,
    signature: $signature,
    storage: $storage,
    onFinalize: null, // optional — SDK still persists fonts via storage
);
```

## Callback hooks

The SDK exposes four hooks — two per callback type. They answer different questions at different stages of processing.

| Hook | When | Question it answers |
|------|------|---------------------|
| `onPreSave` | **Before** ZIP download | “Should this operation proceed?” |
| `onFinalize` | **After** file processing | “Should the result be persisted in my CMS?” |

Material callbacks: `onContentPreSave` + `onContentFinalize`  
Fonts callbacks: `onFontsPreSave` + `onFontsFinalize`

In Laravel/Symfony, all four are wired through the `VerstkaCallbacks` class.

### Material callback flow

```
1. Verify X-Verstka-Signature
2. onPreSave (optional)          ← reject before any download
3. Download ZIP, extract files
4. StorageAdapter::saveMedia()   ← SDK saves files automatically
5. Patch dummy-* URLs in HTML/JSON
6. onFinalize (required)         ← your CMS persistence logic
7. Return { rc, rm, data } to Verstka
```

### `onPreSave` — gate before heavy work

Called immediately after signature verification, **before** the ZIP is downloaded. This is a cheap checkpoint to reject a callback early.

**`ContentPreSaveContext` fields:**

| Field | Description |
|-------|-------------|
| `materialId` | Material ID in your CMS |
| `metadata` | Metadata you passed when opening the editor session |
| `contentUrl` | Verstka ZIP URL (not yet downloaded) |

**`FontsPreSaveContext` fields:** same as above, plus `fonts` — the font tree from the callback payload.

**Return `PreSaveDecision`:**

```php
new PreSaveDecision(allow: true);
new PreSaveDecision(allow: false, reason: 'Access denied');
```

If `allow` is `false`:

- the ZIP is **not** downloaded;
- files are **not** saved;
- Verstka receives `rc: 0` and your `reason` (or `"Operation rejected"` by default).

**Typical use cases:**

- Permission checks (`metadata['userId']` cannot edit this material).
- Publication state (published articles require an editor role).
- Edit locks (another user is already editing).
- Storage quotas (skip download when over limit).
- Metadata validation (unexpected or missing fields).

### `onFinalize` — persist structured data in your CMS

Called **after** the SDK has:

- downloaded and extracted the ZIP;
- saved media/font files via `StorageAdapter`;
- replaced `dummy-*` placeholders with real public URLs in HTML/CSS/JSON.

**`ContentFinalizeContext` fields (required hook for material callbacks):**

| Field | Description |
|-------|-------------|
| `materialId` | Material ID |
| `metadata` | Session metadata from your CMS |
| `vmsJson` | Parsed `vms_json` (or `null`) |
| `vmsHtml` | Ready-to-use HTML (or `null`) |
| `savedMediaUrls` | `['image.jpg' => 'https://cdn.../image.jpg', ...]` |

**Return `ContentFinalizeResult`:**

```php
new ContentFinalizeResult(success: true, vmsJson: $ctx->vmsJson);
new ContentFinalizeResult(success: false);
```

If `success` is `false`, Verstka receives `rc: 0` with message `"Operation failed"`.

**Typical use cases:**

- Write `vmsHtml` and `vmsJson` to your articles table.
- Create a revision before overwriting.
- Post-process HTML, generate excerpts, update search indexes.
- Update `updated_at`, editor ID, draft status.
- Wrap DB writes in a transaction; return `success: false` on failure.

> **Note:** `onFinalize` is for **structured CMS data**. Binary files (images, fonts) are already saved by `StorageAdapter` before this hook runs.

### `onFontsFinalize` — optional

For font callbacks, `onFinalize` is **optional** (`null` by default). Without it, the SDK still:

- saves font files via `StorageAdapter`;
- updates `vms_fonts.css` and `vms_fonts.json`;
- sets `clientUrl` in the font tree.

**`FontsFinalizeContext` fields:**

| Field | Description |
|-------|-------------|
| `materialId` | Material ID |
| `metadata` | Session metadata |
| `fonts` | Font tree with `clientUrl` already set |
| `cssUrl` | Public URL of `vms_fonts.css` |
| `jsonUrl` | Public URL of `vms_fonts.json` |
| `savedFontUrls` | `['font-id' => 'https://cdn.../font.woff2', ...]` |

**Use `onFontsFinalize` when you need to:**

- store font references in site settings;
- invalidate CDN cache;
- audit who updated fonts;
- return a modified `fonts` tree in the Verstka response.

### `StorageAdapter` vs hooks

| Responsibility | Who handles it | When |
|----------------|----------------|------|
| Save media/font files | SDK via `StorageAdapter` | Between preSave and finalize |
| Access control | You via `onPreSave` | Before ZIP download |
| Save HTML/JSON to DB | You via `onFinalize` | After file processing |

`StorageAdapter` decides **where files go** (disk, S3).  
`onFinalize` decides **where structured content goes** (MySQL, Elasticsearch, etc.).

### Full example with both hooks

```php
use Verstka\Sdk\Finalize\ContentFinalizeContext;
use Verstka\Sdk\Finalize\ContentFinalizeResult;
use Verstka\Sdk\Finalize\ContentPreSaveContext;
use Verstka\Sdk\Finalize\PreSaveDecision;

$result = $client->processMaterialCallback(
    $payload,
    signature: $signature,
    storage: $storage,

    onPreSave: function (ContentPreSaveContext $ctx): PreSaveDecision {
        $userId = $ctx->metadata['userId'] ?? null;
        if (!$userId || !User::canEdit($userId, $ctx->materialId)) {
            return new PreSaveDecision(allow: false, reason: 'Access denied');
        }
        return new PreSaveDecision(allow: true);
    },

    onFinalize: function (ContentFinalizeContext $ctx): ContentFinalizeResult {
        try {
            Article::updateOrCreate(
                ['id' => $ctx->materialId],
                [
                    'html' => $ctx->vmsHtml,
                    'json' => json_encode($ctx->vmsJson),
                ],
            );
            return new ContentFinalizeResult(success: true, vmsJson: $ctx->vmsJson);
        } catch (\Throwable) {
            return new ContentFinalizeResult(success: false);
        }
    },
);
```

### Hook reference

| Hook | Required? | Before/after ZIP | Your decision |
|------|-----------|------------------|---------------|
| `onContentPreSave` | No | Before | Allow or reject the callback |
| `onContentFinalize` | **Yes** | After | Persist HTML/JSON in CMS |
| `onFontsPreSave` | No | Before | Allow or reject font update |
| `onFontsFinalize` | No | After | Persist font settings in CMS |

## Rendering saved articles

This SDK processes editor callbacks, persists media/fonts through your storage
adapter, and gives your backend the rewritten `vms_html` and `vms_json`. It does
not initialize the article in the browser by itself.

To display saved Verstka articles on your site, use the frontend viewer package
[`verstka-viewer`](https://www.npmjs.com/package/verstka-viewer), or implement
the same initialization/data-handling behavior in your own frontend using the
information from that package.

```bash
npm install verstka-viewer
```

Serve the saved `vms_html` together with the matching `vms_json` that you
persisted in `onFinalize`; media and font URLs should be the public URLs
returned by your `StorageAdapter`.

## Storage adapters

Implement `Verstka\Sdk\Storage\StorageAdapter` or use `LocalStorageAdapter`:

```php
$storage = new LocalStorageAdapter(
    root: '/var/www/storage',
    baseUrl: 'https://cdn.example.com',
);
```

## Symfony integration

1. Register the bundle (auto-discovered via `composer.json` extra).
2. Configure `config/packages/verstka.yaml`:

```yaml
verstka:
    api_key: '%env(VERSTKA_API_KEY)%'
    api_secret: '%env(VERSTKA_API_SECRET)%'
    callback_url: '%env(VERSTKA_CALLBACK_URL)%'
    callback_route_prefix: /verstka
```

3. Register your `StorageAdapter` and optional `VerstkaCallbacks` services.
4. Import routes from `vendor/verstka/sdk/src/Verstka/Sdk/Integration/Symfony/Resources/config/routes.yaml`.

`POST /verstka/callback` handles both material and `site_fonts_updated` events.

## Laravel integration

1. Publish config: `php artisan vendor:publish --tag=verstka-config`
2. Set env vars: `VERSTKA_API_KEY`, `VERSTKA_API_SECRET`, `VERSTKA_CALLBACK_URL`.
3. Bind `Verstka\Sdk\Storage\StorageAdapter` and optional `VerstkaCallbacks` in a service provider.

The service provider registers `POST /verstka/callback` automatically.

## Low-level helpers

```php
use Verstka\Sdk\Signature\SignatureService;
use Verstka\Sdk\Url\UrlBuilder;

SignatureService::signMaterial($materialId, $url, $secret);
SignatureService::verifySignature($materialId, $url, $signature, $secret);
UrlBuilder::buildAuthorizedContentUrl($contentUrl, $apiKey, $materialId);
```

## Python SDK parity

This package mirrors the sync subset of `verstka-sdk` (Python):

| Python | PHP |
|--------|-----|
| `VerstkaConfig` | `Verstka\Sdk\Config\VerstkaConfig` |
| `VerstkaClient` | `Verstka\Sdk\Client\VerstkaClient` |
| `CallbackProcessor` | `Verstka\Sdk\Callback\CallbackProcessor` |
| `sign_material` | `SignatureService::signMaterial` |
| `build_authorized_content_url` | `UrlBuilder::buildAuthorizedContentUrl` |
| `LocalStorageAdapter` | `Verstka\Sdk\Storage\LocalStorageAdapter` |
| FastAPI `build_callback_router` | Symfony `CallbackController` / Laravel route |

## Releasing

Publishing to [packagist.org](https://packagist.org/packages/verstka/sdk) is automated via [`.github/workflows/publish.yml`](.github/workflows/publish.yml) when a `v*` tag is pushed.

### One-time setup

1. Submit the repository at [packagist.org/packages/submit](https://packagist.org/packages/submit) (`https://github.com/verstka/verstka-sdk-php`).
2. Confirm repository ownership on Packagist.
3. Add GitHub Actions secrets in the repository settings:
   - `PACKAGIST_USERNAME` — your packagist.org username
   - `PACKAGIST_TOKEN` — API token from [packagist.org/profile](https://packagist.org/profile/)

Do **not** enable the Packagist GitHub webhook if you rely on the publish workflow — the API update runs only after tests pass.

### Release a version

```bash
git tag v0.1.0
git push origin v0.1.0
```

The workflow validates `composer.json`, runs PHPUnit, then calls the Packagist `update-package` API.

Verify installation:

```bash
composer require verstka/sdk:0.1.0
```

## License

MIT — see [LICENSE](LICENSE).
