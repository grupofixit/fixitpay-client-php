<?php

declare(strict_types=1);

namespace GrupoFixIT\PaymentsClient;

/**
 * Cliente oficial para comunicação com a API REST da Plataforma de Pagamentos Grupo FixIT.
 */
class FixitPaymentsClient
{
    private WebhookVerifier $webhookVerifier;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://pagamento.grupofixit.co.mz/api/v1',
        private readonly ?string $webhookSecret = null,
        private readonly int $timeout = 30
    ) {
        if ($this->webhookSecret !== null) {
            $this->webhookVerifier = new WebhookVerifier($this->webhookSecret);
        }
    }

    /**
     * Obter perfil e ambiente da aplicação autenticada.
     *
     * @return array<string, mixed>
     */
    public function me(): array
    {
        return $this->request('GET', '/me');
    }

    /**
     * Criar uma nova sessão de checkout.
     *
     * @param array{
     *     order_id: string,
     *     amount: string|float,
     *     description: string,
     *     return_url: string,
     *     cancel_url?: string|null,
     *     customer?: array{name?: string, email?: string, msisdn?: string}|null,
     *     metadata?: array<string, mixed>|null,
     *     currency?: string
     * } $params
     * @param string|null $idempotencyKey Chave única opcional (se omitida, é gerada automaticamente via UUID)
     * @return array{id: string, checkout_url: string, expires_at: string, environment: string}
     */
    public function createCheckoutSession(array $params, ?string $idempotencyKey = null): array
    {
        $idempotencyKey = $idempotencyKey ?? $this->generateUuid();

        // Formatação garantida de valor decimal com 2 casas
        if (isset($params['amount'])) {
            $params['amount'] = number_format((float) $params['amount'], 2, '.', '');
        }

        /** @var array{id: string, checkout_url: string, expires_at: string, environment: string} */
        return $this->request('POST', '/checkout-sessions', $params, $idempotencyKey);
    }

    /**
     * Consultar estado da sessão de checkout.
     *
     * @param string $sessionId
     * @return array<string, mixed>
     */
    public function getCheckoutSession(string $sessionId): array
    {
        return $this->request('GET', "/checkout-sessions/{$sessionId}");
    }

    /**
     * Consultar detalhes completos de um pagamento pelo seu ID público (ex: pay_01J...).
     *
     * @param string $paymentId
     * @return array<string, mixed>
     */
    public function getPayment(string $paymentId): array
    {
        return $this->request('GET', "/payments/{$paymentId}");
    }

    /**
     * Listar pagamentos da aplicação filtrados opcionalmente por order_id ou estado.
     *
     * @param string|null $orderId
     * @param string|null $state
     * @return array<array<string, mixed>>
     */
    public function listPayments(?string $orderId = null, ?string $state = null): array
    {
        $query = [];
        if ($orderId !== null) {
            $query['order_id'] = $orderId;
        }
        if ($state !== null) {
            $query['state'] = $state;
        }

        $path = '/payments' . (!empty($query) ? '?' . http_build_query($query) : '');

        /** @var array<array<string, mixed>> */
        return $this->request('GET', $path);
    }

    /**
     * Forçar consulta síncrona de estado (Transaction Status Query) à Vodacom M-Pesa.
     *
     * @param string $paymentId
     * @return array<string, mixed>
     */
    public function refreshPaymentStatus(string $paymentId): array
    {
        return $this->request('POST', "/payments/{$paymentId}/refresh");
    }

    /**
     * Iniciar cobrança C2B direta (Customer-to-Business via Push USSD no telemóvel do cliente).
     *
     * O cliente recebe imediatamente o prompt de inserção de PIN no telemóvel Vodacom.
     *
     * @param string $msisdn Número Vodacom (ex: 841234567, 851234567 ou 258841234567)
     * @param float $amount Valor em Meticais (min: 1.00)
     * @param string|null $orderId Identificador único do pedido na sua aplicação
     * @param string|null $description Descrição do pagamento
     * @param string|null $customerName Nome do titular do pedido
     * @param string|null $idempotencyKey Chave única opcional
     * @return array<string, mixed>
     */
    public function c2b(
        string $msisdn,
        float $amount,
        ?string $orderId = null,
        ?string $description = null,
        ?string $customerName = null,
        ?string $idempotencyKey = null
    ): array {
        $idempotencyKey = $idempotencyKey ?? $this->generateUuid();

        $payload = [
            'msisdn' => $msisdn,
            'amount' => number_format($amount, 2, '.', ''),
        ];

        if ($orderId !== null) {
            $payload['order_id'] = $orderId;
        }

        if ($description !== null) {
            $payload['description'] = $description;
        }

        if ($customerName !== null) {
            $payload['customer_name'] = $customerName;
        }

        return $this->request('POST', '/payments/c2b', $payload, $idempotencyKey);
    }

    /**
     * Alias amigável para cobrança direta C2B via Push USSD.
     */
    public function charge(
        string $msisdn,
        float $amount,
        ?string $orderId = null,
        ?string $description = null,
        ?string $customerName = null,
        ?string $idempotencyKey = null
    ): array {
        return $this->c2b($msisdn, $amount, $orderId, $description, $customerName, $idempotencyKey);
    }

    /**
     * Iniciar transferência B2C (Business to Customer).
     *
     * @param string $msisdn Número Vodacom no formato 25884xxxxxxx ou 25885xxxxxxx
     * @param float $amount Valor em Meticais
     * @param string|null $reference Referência de auditoria
     * @param string|null $idempotencyKey
     * @return array<string, mixed>
     */
    public function b2c(string $msisdn, float $amount, ?string $reference = null, ?string $idempotencyKey = null): array
    {
        $idempotencyKey = $idempotencyKey ?? $this->generateUuid();

        return $this->request('POST', '/payments/b2c', [
            'msisdn' => $msisdn,
            'amount' => number_format($amount, 2, '.', ''),
            'reference' => $reference,
        ], $idempotencyKey);
    }

    /**
     * Iniciar pagamento comercial B2B (Business to Business).
     *
     * @param string $receiverPartyCode Shortcode de destino (ex: 979797)
     * @param float $amount
     * @param string|null $reference
     * @param string|null $idempotencyKey
     * @return array<string, mixed>
     */
    public function b2b(string $receiverPartyCode, float $amount, ?string $reference = null, ?string $idempotencyKey = null): array
    {
        $idempotencyKey = $idempotencyKey ?? $this->generateUuid();

        return $this->request('POST', '/payments/b2b', [
            'receiver_party_code' => $receiverPartyCode,
            'amount' => number_format($amount, 2, '.', ''),
            'reference' => $reference,
        ], $idempotencyKey);
    }

    /**
     * Iniciar reversão total ou parcial de um pagamento.
     *
     * @param string $paymentId ID do pagamento original concluído
     * @param float $amount Valor a reverter
     * @param string $reason Justificação obrigatória do estorno
     * @param string|null $idempotencyKey
     * @return array<string, mixed>
     */
    public function reversal(string $paymentId, float $amount, string $reason, ?string $idempotencyKey = null): array
    {
        $idempotencyKey = $idempotencyKey ?? $this->generateUuid();

        return $this->request('POST', "/payments/{$paymentId}/reversals", [
            'amount' => number_format($amount, 2, '.', ''),
            'reason' => $reason,
        ], $idempotencyKey);
    }

    /**
     * Consultar nome do titular Vodacom M-Pesa (Customer Name Query).
     *
     * @param string $msisdn
     * @return array{msisdn: string, first_name: string, second_name: string, display_name: string}
     */
    public function lookupCustomerName(string $msisdn): array
    {
        /** @var array{msisdn: string, first_name: string, second_name: string, display_name: string} */
        return $this->request('GET', '/customers/name?msisdn=' . urlencode($msisdn));
    }

    /**
     * Validar e decodificar um webhook recebido da plataforma.
     *
     * @param string $rawPayload
     * @param string $signatureHeader
     * @return array<string, mixed>
     */
    public function verifyWebhook(string $rawPayload, string $signatureHeader): array
    {
        if (!isset($this->webhookVerifier)) {
            throw new \LogicException('Webhook secret não configurado no cliente. Forneça o secret no construtor para validar webhooks.');
        }

        return $this->webhookVerifier->verify($rawPayload, $signatureHeader);
    }

    /**
     * Executa a requisição HTTP via cURL nativo com TLS estritamente verificado.
     *
     * @param string $method
     * @param string $endpoint
     * @param array<string, mixed>|null $body
     * @param string|null $idempotencyKey
     * @return array<string, mixed>
     */
    private function request(string $method, string $endpoint, ?array $body = null, ?string $idempotencyKey = null): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json',
        ];

        if ($idempotencyKey !== null) {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        // Regra inegociável: TLS sempre verificado
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            $headers[] = 'Content-Type: application/json';
            $jsonBody = json_encode($body ?? [], JSON_THROW_ON_ERROR);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException("Erro de rede ao comunicar com a plataforma de pagamentos: {$curlError}");
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException("Resposta não-JSON da plataforma (HTTP {$httpCode}): " . substr((string) $response, 0, 200));
        }

        if ($httpCode >= 400) {
            $errorMsg = $decoded['error']['message'] ?? $decoded['message'] ?? 'Erro desconhecido na plataforma de pagamentos';
            $errorCode = $decoded['error']['code'] ?? 'HTTP_' . $httpCode;
            throw new \RuntimeException("[{$errorCode}] {$errorMsg} (HTTP {$httpCode})");
        }

        return $decoded;
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // v4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
