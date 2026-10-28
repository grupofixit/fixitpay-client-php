<?php

declare(strict_types=1);

namespace GrupoFixIT\PaymentsClient\Laravel;

use GrupoFixIT\PaymentsClient\FixitPaymentsClient;
use Illuminate\Support\ServiceProvider;

class FixitPaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FixitPaymentsClient::class, function ($app) {
            $config = $app['config']->get('services.fixit_payments', []);

            return new FixitPaymentsClient(
                apiKey: (string) ($config['api_key'] ?? env('FIXIT_PAYMENTS_API_KEY', '')),
                baseUrl: (string) ($config['base_url'] ?? env('FIXIT_PAYMENTS_BASE_URL', 'https://pagamento.grupofixit.co.mz/api/v1')),
                webhookSecret: $config['webhook_secret'] ?? env('FIXIT_PAYMENTS_WEBHOOK_SECRET'),
                timeout: (int) ($config['timeout'] ?? 30)
            );
        });

        $this->app->alias(FixitPaymentsClient::class, 'fixit-payments');
    }
}
