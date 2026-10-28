<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use GrupoFixIT\PaymentsClient\WebhookVerifier;

// 1. Obter segredo de webhook configurado no painel da aplicação (ex: whsec_...)
$webhookSecret = 'whsec_your_app_webhook_secret_here';
$verifier = new WebhookVerifier($webhookSecret);

// 2. Ler corpo bruto e cabeçalho de assinatura HTTP
$rawPayload = file_get_contents('php://input') ?: '';
$signatureHeader = $_SERVER['HTTP_X_FIXIT_SIGNATURE'] ?? '';

try {
    // 3. Validar assinatura HMAC-SHA256 e tolerância temporal
    $event = $verifier->verify($rawPayload, $signatureHeader);

    $eventType = $event['event'] ?? '';
    $payment = $event['data'] ?? [];

    // 4. Tratar o evento com base no tipo
    switch ($eventType) {
        case 'payment.completed':
            $orderId = $payment['order_id'] ?? null;
            $amount = $payment['amount'] ?? null;
            $mpesaTxId = $payment['mpesa_transaction_id'] ?? null;

            // IMPORTANTE: Atualize o seu banco de dados local marcando o pedido como PAGO
            // ex: Order::where('id', $orderId)->update(['status' => 'paid', 'transaction_id' => $mpesaTxId]);
            error_log("Pagamento concluído para encomenda {$orderId}. M-Pesa ID: {$mpesaTxId}, Valor: {$amount}");
            break;

        case 'payment.failed':
            $orderId = $payment['order_id'] ?? null;
            $reason = $payment['failure_reason'] ?? 'Desconhecido';
            error_log("Pagamento falhou para encomenda {$orderId}. Motivo: {$reason}");
            break;

        case 'payment.expired':
            $orderId = $payment['order_id'] ?? null;
            error_log("Sessão expirou sem pagamento para encomenda {$orderId}.");
            break;

        case 'reversal.completed':
            $orderId = $payment['order_id'] ?? null;
            $reversedAmount = $payment['reversed_amount'] ?? null;
            error_log("Reversão concluída para pagamento {$payment['id']}. Valor revertido: {$reversedAmount}");
            break;

        case 'payment.unknown':
            error_log("Pagamento {$payment['id']} em estado desconhecido. Aguardando reconciliação.");
            break;

        default:
            error_log("Evento desconhecido recebido: {$eventType}");
    }

    // 5. Responder com HTTP 200 OK para confirmar recepção
    http_response_code(200);
    echo json_encode(['received' => true]);

} catch (\InvalidArgumentException $e) {
    // Assinatura inválida, adulterada ou expirada
    http_response_code(401);
    echo json_encode(['error' => 'Assinatura inválida: ' . $e->getMessage()]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno ao processar webhook']);
}
