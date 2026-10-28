<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use GrupoFixIT\PaymentsClient\FixitPaymentsClient;

// 1. Inicializar cliente com a API Key da sua aplicação (obtida no painel administrativo)
$client = new FixitPaymentsClient(
    apiKey: 'fx_sandbox_your_api_key_here_1234567890',
    baseUrl: 'https://pagamento.grupofixit.co.mz/api/v1' // ou http://localhost:8000/api/v1
);

try {
    // 2. Criar uma sessão de checkout para um pedido
    $session = $client->createCheckoutSession([
        'order_id' => 'ORD-' . time(),
        'amount' => '1250.00',
        'currency' => 'MZN',
        'description' => 'Serviço de Consultoria TI - Grupo FixIT',
        'return_url' => 'https://sua-app.co.mz/pedidos/retorno',
        'cancel_url' => 'https://sua-app.co.mz/carrinho',
        'customer' => [
            'name' => 'Armando Cossa',
            'email' => 'armando@exemplo.co.mz',
            'msisdn' => '258841234567',
        ],
        'metadata' => [
            'sistema_origem' => 'ERP FixIT',
            'utilizador_id' => 42,
        ],
    ]);

    echo "Sessão de Checkout criada com sucesso!\n";
    echo "ID: " . $session['id'] . "\n";
    echo "URL de Checkout: " . $session['checkout_url'] . "\n";
    echo "Expira em: " . $session['expires_at'] . "\n";
    echo "Ambiente: " . $session['environment'] . "\n\n";

    echo "Redirecione o cliente no navegador para: " . $session['checkout_url'] . "\n";

} catch (\Throwable $e) {
    echo "Erro ao criar sessão: " . $e->getMessage() . "\n";
}
