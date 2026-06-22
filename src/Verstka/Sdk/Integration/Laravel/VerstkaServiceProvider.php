<?php

declare(strict_types=1);

namespace Verstka\Sdk\Integration\Laravel;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Verstka\Sdk\Client\VerstkaClient;
use Verstka\Sdk\Config\VerstkaConfig;
use Verstka\Sdk\Integration\Laravel\Http\Controllers\CallbackController;

final class VerstkaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/verstka.php', 'verstka');

        $this->app->singleton(VerstkaConfig::class, function (): VerstkaConfig {
            $config = config('verstka');

            return new VerstkaConfig(
                apiKey: (string) $config['api_key'],
                apiSecret: (string) $config['api_secret'],
                callbackUrl: (string) $config['callback_url'],
                apiUrl: (string) ($config['api_url'] ?? VerstkaConfig::DEFAULT_API_URL),
                basicAuthUser: $config['basic_auth_user'] ?? null,
                basicAuthPassword: $config['basic_auth_password'] ?? null,
                maxContentSize: (int) ($config['max_content_size'] ?? VerstkaConfig::DEFAULT_MAX_CONTENT_SIZE),
                requestTimeout: (float) ($config['request_timeout'] ?? 60.0),
                downloadTimeout: (float) ($config['download_timeout'] ?? 120.0),
                debug: (bool) ($config['debug'] ?? false),
            );
        });

        $this->app->singleton(VerstkaClient::class, function ($app): VerstkaClient {
            return new VerstkaClient($app->make(VerstkaConfig::class));
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/verstka.php' => config_path('verstka.php'),
            ], 'verstka-config');
        }

        $prefix = (string) config('verstka.callback_route_prefix', '/verstka');
        Route::prefix($prefix)->group(function (): void {
            Route::post('/callback', [CallbackController::class, '__invoke']);
        });

        $handler = $this->app->make(ExceptionHandler::class);
        if (method_exists($handler, 'renderable')) {
            $handler->renderable(static function (\Throwable $exception, $request) {
                return VerstkaExceptionRenderer::render($exception, $request);
            });
        }
    }
}
