<?php

declare(strict_types=1);

namespace GrupoFixIT\PaymentsClient;

/**
 * Utilitário de validação criptográfica de Webhooks recebidos da plataforma de pagamentos.
 *
 * Utiliza HMAC-SHA256 e proteção contra ataques de repetição (replay attack)
 * através da verificação de timestamp com tolerância configurável (por defeito: 300 segundos / 5 minutos).
 */
class WebhookVerifier
{
    /**
     * @param string $secret Segredo de webhook partilhado configurado no painel da aplicação (ex: whsec_...)
     * @param int $toleranceSeconds Tolerância máxima em segundos (defeito: 300)
     */
    public function __construct(
        private readonly string $secret,
        private readonly int $toleranceSeconds = 300
    ) {
    }

    /**
     * Valida a assinatura do webhook e retorna o payload decodificado se for válido.
     *
     * Formato esperado do cabeçalho `X-FixIT-Signature`:
     * `t=1700000000,v1=abcdef0123456789...`
     *
     * @param string $rawPayload Corpo bruto da requisição HTTP (sem alterações)
     * @param string $signatureHeader Valor do cabeçalho X-FixIT-Signature
     * @return array<string, mixed>
     * @throws \InvalidArgumentException Se a assinatura for inválida ou tiver expirado
     */
    public function verify(string $rawPayload, string $signatureHeader): array
    {
        $parts = [];
        foreach (explode(',', $signatureHeader) as $pair) {
            $kv = explode('=', trim($pair), 2);
            if (count($kv) === 2) {
                $parts[$kv[0]] = $kv[1];
            }
        }

        if (empty($parts['t']) || empty($parts['v1'])) {
            throw new \InvalidArgumentException('Cabeçalho de assinatura malformatado. Esperado t=...,v1=...');
        }

        $timestamp = (int) $parts['t'];
        $signature = $parts['v1'];

        // Proteção contra replay attacks
        $currentTime = time();
        if (abs($currentTime - $timestamp) > $this->toleranceSeconds) {
            throw new \InvalidArgumentException("Assinatura do webhook expirou. Diferença temporal: " . abs($currentTime - $timestamp) . "s");
        }

        // Assinatura esperada: hash_hmac('sha256', "{$timestamp}.{$rawPayload}", $secret)
        $expected = hash_hmac('sha256', "{$timestamp}.{$rawPayload}", $this->secret);

        if (!hash_equals($expected, $signature)) {
            throw new \InvalidArgumentException('Assinatura do webhook inválida. A requisição foi adulterada ou o secret está incorreto.');
        }

        $decoded = json_decode($rawPayload, true);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('Corpo do webhook não é um JSON válido.');
        }

        return $decoded;
    }
}
