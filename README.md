# FixitPay PHP SDK (`grupofixit/fixitpay-client-php`)

[![PHP Version](https://img.shields.io/badge/PHP-8.2%20%7C%208.3%20%7C%208.4-777BB4?logo=php&logoColor=white)](https://php.net)
[![Laravel Support](https://img.shields.io/badge/Laravel-10.x%20%7C%2011.x%20%7C%2012.x-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![License](https://img.shields.io/badge/License-Proprietary-blue.svg)](LICENSE)
[![TLS Verified](https://img.shields.io/badge/TLS-Enforced-success.svg)](https://grupofixit.co.mz)

SDK oficial em **PHP / Laravel** para integração com a **Plataforma Central de Pagamentos Grupo FixIT** (`FixitPay`).

Permite que qualquer loja virtual, sistema de faturação, ERP, CRM ou aplicação web conecte de forma simples e segura ao ecossistema **Vodacom M-Pesa Moçambique**, utilizando unicamente a **API Key** gerada no painel administrativo, sem necessidade de lidar com certificados X.509 ou chaves criptográficas da operadora.

---

## Índice

- [Visão Geral e Arquitetura](#visão-geral-e-arquitetura)
- [Requisitos](#requisitos)
- [Instalação](#instalação)
- [Configuração](#configuração)
  - [Em PHP Puro](#em-php-puro)
  - [Em Laravel (Auto-Discovery)](#em-laravel-auto-discovery)
- [Casos de Uso e Exemplos Práticos](#casos-de-uso-e-exemplos-práticos)
  - [Caso 1: Cobrança Direta C2B (Push USSD no Telemóvel)](#caso-1-cobrança-direta-c2b-push-ussd-no-telemóvel)
  - [Caso 2: Sessão de Checkout Web (Redirecionamento)](#caso-2-sessão-de-checkout-web-redirecionamento)
  - [Caso 3: Recepção e Validação de Webhooks (HMAC-SHA256)](#caso-3-recepção-e-validação-de-webhooks-hmac-sha256)
  - [Caso 4: Desembolsos B2C (Pagamento a Clientes)](#caso-4-desembolsos-b2c-pagamento-a-clientes)
  - [Caso 5: Pagamento Comercial B2B (Transferência entre Empresas)](#caso-5-pagamento-comercial-b2b-transferência-entre-empresas)
  - [Caso 6: Reversão / Estorno de Pagamento](#caso-6-reversão--estorno-de-pagamento)
  - [Caso 7: Consulta de Nome do Titular M-Pesa](#caso-7-consulta-de-nome-do-titular-m-pesa)
  - [Caso 8: Consulta de Estado Síncrona (Status Query)](#caso-8-consulta-de-estado-síncrona-status-query)
- [Catálogo de Eventos de Webhook](#catálogo-de-eventos-de-webhook)
- [Tratamento de Erros](#tratamento-de-erros)
- [Princípios Inegociáveis de Segurança](#princípios-inegociáveis-de-segurança)
- [Licença e Suporte](#licença-e-suporte)

---

## Visão Geral e Arquitetura

```
┌─────────────────────────────────┐
│       Sua Aplicação PHP         │
│    (Loja / ERP / App Móvel)     │
└────────────────┬────────────────┘
                 │ 1. Chama SDK com API Key (fx_live_...)
                 ▼
┌─────────────────────────────────┐
│     FixitPay Platform           │ ◄─── Centraliza e protege credenciais
│  (pagamento.grupofixit.co.mz)   │      da Vodacom (certificados RSA / TLS)
└───────┬─────────────────▲───────┘
        │ 2. Dispara C2B  │ 3. Consulta Oficial de Estado
        ▼                 │    (Status Query Obrigatório)
┌─────────────────────────────────┐
│     Vodacom M-Pesa Moçambique   │
└─────────────────────────────────┘
        │ 4. Webhook Assinado (HMAC-SHA256)
        ▼
┌─────────────────────────────────┐
│   Sua URL de Webhook Registada  │ ───► Marca Pedido como PAGO
└─────────────────────────────────┘
```

---

## Requisitos

- **PHP:** `8.2`, `8.3` ou `8.4`
- **Extensões PHP:**
  - `ext-curl` (com suporte a TLS 1.2 / 1.3)
  - `ext-json`
  - `ext-hash` (para validação criptográfica HMAC)
- **Composer** 2.x

---

## Instalação

Instale o pacote na sua aplicação executando o comando Composer:

```bash
composer require grupofixit/fixitpay-client-php
```

> **Nota para repositório privado:** Caso o repositório esteja configurado como privado na sua organização, certifique-se de adicionar a referência VCS no seu `composer.json`:
> ```json
> "repositories": [
>     {
>         "type": "vcs",
>         "url": "https://github.com/grupofixit/fixitpay-client.git"
>     }
> ]
> ```

---

## Configuração

### Em PHP Puro

Basta instanciar a classe `FixitPaymentsClient` passando a sua **API Key** e opcionalmente o **Webhook Secret**:

```php
use GrupoFixIT\PaymentsClient\FixitPaymentsClient;

$client = new FixitPaymentsClient(
    apiKey: 'fx_live_01jb3d4e5f6g7h8j9k0l1m2n3p...',
    baseUrl: 'https://pagamento.grupofixit.co.mz/api/v1',
    webhookSecret: 'whsec_sua_chave_secreta_de_webhook...'
);
```

### Em Laravel (Auto-Discovery)

O pacote conta com **Package Auto-Discovery** nativo. Basta adicionar as variáveis de ambiente no ficheiro `.env` da sua aplicação Laravel:

```env
# Chave gerada no painel administrativo (fx_live_... para produção ou fx_sandbox_... para testes)
FIXIT_PAYMENTS_API_KEY=fx_sandbox_01jb3d4e5f6g7h8j9k0l1m2n3p

# Chave secreta para validação criptográfica dos webhooks
FIXIT_PAYMENTS_WEBHOOK_SECRET=whsec_sua_chave_secreta_aqui

# URL da plataforma (opcional, padrão: https://pagamento.grupofixit.co.mz/api/v1)
FIXIT_PAYMENTS_BASE_URL=https://pagamento.grupofixit.co.mz/api/v1
```

A Facade `FixitPayments` fica imediatamente disponível em qualquer Controller, Job ou Service!

---

## Casos de Uso e Exemplos Práticos

### Caso 1: Cobrança Direta C2B (Push USSD no Telemóvel)

Ideal para:
- Aplicações móveis (Flutter, React Native, Swift, Kotlin).
- Checkouts transparentes onde o cliente já preencheu o número no seu site.
- Sistemas de Ponto de Venda (POS / PDV), quiosques e cobranças balcão.

O telemóvel do cliente acenderá instantaneamente com o pop-up da Vodacom M-Pesa solicitando o PIN.

#### Em PHP Puro:
```php
use GrupoFixIT\PaymentsClient\FixitPaymentsClient;

$client = new FixitPaymentsClient(apiKey: 'fx_live_sua_chave_aqui');

try {
    $payment = $client->charge(
        msisdn: '841234567', // Aceita 84..., 85... ou 25884...
        amount: 350.00,
        orderId: 'PEDIDO-10023',
        description: 'Recarga de Saldo - FixIT Net',
        customerName: 'Manuel Matsinhe'
    );

    echo "Pagamento iniciado com ID: " . $payment['id'] . "\n";
    echo "Estado: " . $payment['state'] . "\n"; // 'pending' ou 'submitted'
    echo "Referência TX: " . $payment['transaction_reference'] . "\n";

} catch (\Throwable $e) {
    echo "Erro na cobrança: " . $e->getMessage();
}
```

#### Em Laravel (via Facade):
```php
namespace App\Http\Controllers;

use GrupoFixIT\PaymentsClient\Laravel\Facades\FixitPayments;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function directCharge(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|string',
            'amount' => 'required|numeric|min:1',
        ]);

        $payment = FixitPayments::charge(
            msisdn: $validated['phone'],
            amount: (float) $validated['amount'],
            orderId: 'ORD-' . auth()->id() . '-' . time(),
            description: 'Compra Direta no Site'
        );

        return response()->json([
            'success' => true,
            'payment_id' => $payment['id'],
            'message' => 'Por favor, introduza o PIN no seu telemóvel para autorizar o débito.',
        ]);
    }
}
```

---

### Caso 2: Sessão de Checkout Web (Redirecionamento)

Ideal para e-commerces tradicionais onde o utilizador é redirecionado para a página de pagamento oficial da FixIT com visual em modo escuro/glassmorphic.

```php
$session = $client->createCheckoutSession([
    'order_id' => 'ORD-99881',
    'amount' => 1250.00,
    'currency' => 'MZN',
    'description' => 'Compra na Loja Virtual FixIT Store',
    'return_url' => 'https://sua-loja.co.mz/pedidos/ORD-99881/sucesso',
    'cancel_url' => 'https://sua-loja.co.mz/carrinho',
    'customer' => [
        'name' => 'Armando Cossa',
        'email' => 'armando@exemplo.co.mz',
        'msisdn' => '258841234567',
    ],
    'metadata' => [
        'categoria' => 'hardware',
        'vendedor_id' => 15,
    ]
]);

// Redireciona o utilizador no navegador
header('Location: ' . $session['checkout_url']);
exit;
```

---

### Caso 3: Recepção e Validação de Webhooks (HMAC-SHA256)

Para proteger o seu sistema contra fraudes e requisições forjadas, valide sempre a assinatura enviada no cabeçalho `X-FixIT-Signature`.

#### Em PHP Puro:
```php
use GrupoFixIT\PaymentsClient\WebhookVerifier;

$verifier = new WebhookVerifier('whsec_seu_secret_configurado_no_painel');

$rawPayload = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_X_FIXIT_SIGNATURE'] ?? '';

try {
    $event = $verifier->verify($rawPayload, $signatureHeader);

    switch ($event['event']) {
        case 'payment.completed':
            $orderId = $event['data']['order_id'];
            $mpesaTxId = $event['data']['mpesa_transaction_id'];
            $amount = $event['data']['amount'];

            // ATUALIZE A SUA BASE DE DADOS: Marque o pedido como PAGO
            break;

        case 'payment.failed':
            $orderId = $event['data']['order_id'];
            $reason = $event['data']['failure_reason'];
            // Notificar o cliente sobre falha no pagamento
            break;

        case 'payment.expired':
            // O cliente não introduziu o PIN a tempo
            break;
    }

    http_response_code(200);
    echo json_encode(['received' => true]);

} catch (\InvalidArgumentException $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Assinatura inválida: ' . $e->getMessage()]);
}
```

#### Em Laravel (Controller Dedicado):
```php
namespace App\Http\Controllers;

use GrupoFixIT\PaymentsClient\Laravel\Facades\FixitPayments;
use Illuminate\Http\Request;

class FixitWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $signature = $request->header('X-FixIT-Signature');
        $rawContent = $request->getContent();

        try {
            $event = FixitPayments::verifyWebhook($rawContent, $signature);

            if ($event['event'] === 'payment.completed') {
                $orderId = $event['data']['order_id'];
                \App\Models\Order::where('id', $orderId)->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'transaction_id' => $event['data']['mpesa_transaction_id'],
                ]);
            }

            return response()->json(['received' => true], 200);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Webhook verification failed'], 401);
        }
    }
}
```

---

### Caso 4: Desembolsos B2C (Pagamento a Clientes)

Permite enviar valores da conta empresarial M-Pesa para a carteira de um cliente particular (ex: pagamento de prémios, salários, cashback ou comissões de afiliados):

```php
$result = $client->b2c(
    msisdn: '841234567',
    amount: 1500.00,
    reference: 'PREMIO-SORTEIO-01'
);

echo "Desembolso B2C iniciado: " . $result['id'];
```

---

### Caso 5: Pagamento Comercial B2B (Transferência entre Empresas)

Transferência de fundos corporativos para outra entidade comercial Vodacom M-Pesa (ex: pagar fornecedor via Shortcode):

```php
$result = $client->b2b(
    receiverPartyCode: '979797', // Shortcode Vodacom do fornecedor
    amount: 50000.00,
    reference: 'FATURA-FORN-2026-44'
);
```

---

### Caso 6: Reversão / Estorno de Pagamento

Permite anular uma transação concluída com devolução total ou parcial dos fundos ao cliente:

```php
$reversal = $client->reversal(
    paymentId: 'pay_01JB3D4E5F6G7H8J9K0L1M2N3P',
    amount: 350.00,
    reason: 'Cancelamento de pedido solicitado pelo cliente'
);

echo "Reversão solicitada: " . $reversal['id'];
```

---

### Caso 7: Consulta de Nome do Titular M-Pesa

Permite consultar previamente o nome associado à conta M-Pesa para exibir confirmação ao utilizador (ex: *"Deseja pagar para Manuel M...?"*):

```php
$lookup = $client->lookupCustomerName('841234567');

echo "Nome do titular: " . $lookup['name']; // Ex: "Manuel Matsinhe"
```

---

### Caso 8: Consulta de Estado Síncrona (Status Query)

Permite forçar uma verificação em tempo real diretamente na rede da Vodacom:

```php
// Consulta os detalhes locais do pagamento
$payment = $client->getPayment('pay_01JB3D4E5F6G7H8J9K0L1M2N3P');

// Ou força uma requisição de conciliação direta à Vodacom
$refreshed = $client->refreshPaymentStatus('pay_01JB3D4E5F6G7H8J9K0L1M2N3P');
```

---

## Catálogo de Eventos de Webhook

| Evento | Descrição | Ação Recomendada na sua Aplicação |
|---|---|---|
| `payment.completed` | Pagamento concluído e confirmado oficialmente via Status Query na Vodacom. | Liberar encomenda, emitir fatura, prestar o serviço. |
| `payment.failed` | Transação recusada (saldo insuficiente, PIN errado, cancelada pelo cliente). | Notificar cliente e permitir nova tentativa de pagamento. |
| `payment.expired` | Tempo limite esgotado sem introdução de PIN no telemóvel. | Devolver artigos ao stock e fechar o carrinho. |
| `payment.cancelled` | Cancelado voluntariamente pelo utilizador no ecrã de checkout. | Atualizar estado local para cancelado. |
| `payment.unknown` | Timeout na operadora sem confirmação conclusiva. | Manter em análise aguardando conciliação automática. |
| `reversal.completed` | Estorno financeiro concluído com sucesso. | Atualizar razão contabilística e saldo do cliente. |

---

## Tratamento de Erros

A SDK lança exceções nativas `RuntimeException` contendo o código de erro retornado pela Plataforma:

```php
try {
    $payment = $client->charge('841234567', 100.00);
} catch (\RuntimeException $e) {
    // Ex: "[INVALID_MSISDN] O número deve iniciar por 84 ou 85 (HTTP 422)"
    // Ex: "[UNAUTHORIZED] Missing or invalid API key (HTTP 401)"
    // Ex: "[TRANSACTION_LIMIT_EXCEEDED] O valor excede o limite configurado (HTTP 422)"
    error_log("Erro FixitPay: " . $e->getMessage());
}
```

---

## Princípios Inegociáveis de Segurança

1. **NUNCA peça ou armazene PIN:** A sua aplicação nunca deve ter campos de PIN. O PIN é digitado pelo utilizador exclusivamente no teclado do próprio telemóvel via USSD Push da Vodacom.
2. **NUNCA exponha a API Key no frontend:** A chave `fx_...` é estritamente confidencial e deve rodar apenas no backend (servidor para servidor). Nunca a insira em ficheiros JavaScript/React públicos ou apps móveis compiladas.
3. **Idempotência Automática:** Todas as chamadas de criação de pagamentos geram um UUID único (`Idempotency-Key`), impedindo cobranças em duplicado mesmo que haja oscilação de rede.
4. **Verificação TLS Rígida:** O cliente força `CURLOPT_SSL_VERIFYPEER = true` e validação estrita de certificados SSL/TLS.

---

## Licença e Suporte

- **Desenvolvido por:** [Grupo FixIT Limitada](https://grupofixit.co.mz)
- **Suporte Técnico / Integrações:** `dev@grupofixit.co.mz`
- **Painel Administrativo:** [pagamento.grupofixit.co.mz/admin](https://pagamento.grupofixit.co.mz/admin)
- **Licença:** Código Proprietário Grupo FixIT. Todos os direitos reservados.
