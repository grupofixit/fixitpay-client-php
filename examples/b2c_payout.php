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
    // Transferir fundos da conta corporativa M-Pesa para a carteira de um cliente particular
    $payout = $client->b2c(
        msisdn: '841234567',
        amount: 1500.00,
        reference: 'COMISSAO-AFILIADO-42'
    );

    echo "=== Transferência B2C Iniciada com Sucesso ===\n";
    echo "ID: " . $payout['id'] . "\n";
    echo "Estado: " . $payout['state'] . "\n";
    echo "Destinatário: " . $payout['msisdn'] . "\n";
    echo "Valor: " . $payout['amount'] . " " . $payout['currency'] . "\n";

} catch (\Throwable $e) {
    echo "Erro ao processar B2C: " . $e->getMessage() . "\n";
}
