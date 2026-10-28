<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use GrupoFixIT\PaymentsClient\FixitPaymentsClient;

// 1. Inicializar cliente da SDK com a API Key da sua aplicação
// A chave é gerada no painel administrativo da Plataforma (ex: fx_live_... ou fx_test_...)
$client = new FixitPaymentsClient(
    apiKey: 'fx_sandbox_your_api_key_here_1234567890',
    baseUrl: 'https://pagamento.grupofixit.co.mz/api/v1',
    webhookSecret: 'whsec_your_app_webhook_secret_here'
);

try {
    // 2. Disparar cobrança C2B direta (Transparent Checkout / Prompt USSD imediato)
    // O telemóvel do cliente acenderá com o popup do M-Pesa para introduzir o PIN!
    $payment = $client->charge(
        msisdn: '841234567', // Aceita 84..., 85... ou 25884...
        amount: 250.00,
        orderId: 'ORD-' . time(),
        description: 'Assinatura Mensal - Plano Pro Grupo FixIT',
        customerName: 'Manuel Matsinhe'
    );

    echo "=== Cobrança Disparada com Sucesso! ===\n";
    echo "ID do Pagamento: " . $payment['id'] . "\n";
    echo "Estado Atual:    " . $payment['state'] . "\n";
    echo "Valor:           " . $payment['amount'] . " " . $payment['currency'] . "\n";
    echo "Destinatário:    " . $payment['msisdn'] . "\n";
    echo "Referência TX:   " . $payment['transaction_reference'] . "\n";
    echo "\nO cliente recebeu o prompt na tela do telefone para digitar o PIN M-Pesa.\n";
    echo "Quando o cliente confirmar, a plataforma enviará um webhook assinado para o seu sistema.\n";

} catch (\Throwable $e) {
    echo "Erro ao processar cobrança: " . $e->getMessage() . "\n";
}

