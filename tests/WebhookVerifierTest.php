<?php

declare(strict_types=1);

namespace GrupoFixIT\PaymentsClient\Tests;

use GrupoFixIT\PaymentsClient\WebhookVerifier;
use PHPUnit\Framework\TestCase;

class WebhookVerifierTest extends TestCase
{
    private string $secret = 'whsec_test_secret_key_12345';
    private WebhookVerifier $verifier;

    protected function setUp(): void
    {
        $this->verifier = new WebhookVerifier($this->secret);
    }

    public function testValidWebhookSignaturePasses(): void
    {
        $payload = json_encode([
            'id' => 'evt_123456',
            'event' => 'payment.completed',
            'data' => [
                'order_id' => 'ORD-100',
                'amount' => '250.00',
            ],
        ], JSON_THROW_ON_ERROR);

        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $this->secret);
        $header = "t={$timestamp},v1={$signature}";

        $result = $this->verifier->verify($payload, $header);

        $this->assertSame('evt_123456', $result['id']);
        $this->assertSame('payment.completed', $result['event']);
        $this->assertSame('ORD-100', $result['data']['order_id']);
    }

    public function testTamperedPayloadThrowsException(): void
    {
        $payload = json_encode(['event' => 'payment.completed']);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $this->secret);
        $header = "t={$timestamp},v1={$signature}";

        $tamperedPayload = json_encode(['event' => 'payment.completed', 'tampered' => true]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Assinatura do webhook inválida');

        $this->verifier->verify($tamperedPayload, $header);
    }

    public function testExpiredTimestampThrowsException(): void
    {
        $payload = json_encode(['event' => 'payment.completed']);
        $expiredTimestamp = time() - 301; // Mais de 5 minutos
        $signature = hash_hmac('sha256', "{$expiredTimestamp}.{$payload}", $this->secret);
        $header = "t={$expiredTimestamp},v1={$signature}";

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Timestamp do webhook expirado');

        $this->verifier->verify($payload, $header);
    }
}
