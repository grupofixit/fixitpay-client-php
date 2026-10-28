<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use GrupoFixIT\PaymentsClient\FixitPaymentsClient;

// Inicializar cliente
$client = new FixitPaymentsClient(
    apiKey: 'fx_sandbox_your_api_key_here',
    baseUrl: 'https://pagamento.grupofixit.co.mz/api/v1'
);

try {
    // Consultar previamente o nome do titular associado ao número Vodacom M-Pesa
    $customer = $client->lookupCustomerName('841234567');

    echo "=== Consulta de Titular M-Pesa ===\n";
    echo "Número Mascarado: " . $customer['msisdn'] . "\n";
    echo "Nome do Titular:  " . $customer['name'] . "\n";

} catch (\Throwable $e) {
    echo "Erro na consulta: " . $e->getMessage() . "\n";
}
