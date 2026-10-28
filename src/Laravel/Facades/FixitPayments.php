<?php

declare(strict_types=1);

namespace GrupoFixIT\PaymentsClient\Laravel\Facades;

use GrupoFixIT\PaymentsClient\FixitPaymentsClient;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array me()
 * @method static array createCheckoutSession(array $params, ?string $idempotencyKey = null)
 * @method static array c2b(string $msisdn, float $amount, ?string $orderId = null, ?string $description = null, ?string $customerName = null, ?string $idempotencyKey = null)
 * @method static array charge(string $msisdn, float $amount, ?string $orderId = null, ?string $description = null, ?string $customerName = null, ?string $idempotencyKey = null)
 * @method static array getCheckoutSession(string $sessionId)
 * @method static array getPayment(string $paymentId)
 * @method static array listPayments(?string $orderId = null, ?string $state = null)
 * @method static array refreshPaymentStatus(string $paymentId)
 * @method static array b2c(string $msisdn, float $amount, ?string $reference = null, ?string $idempotencyKey = null)
 * @method static array b2b(string $receiverPartyCode, float $amount, ?string $reference = null, ?string $idempotencyKey = null)
 * @method static array reversal(string $paymentId, float $amount, string $reason, ?string $idempotencyKey = null)
 * @method static array lookupCustomerName(string $msisdn)
 * @method static array verifyWebhook(string $rawPayload, string $signatureHeader)
 *
 * @see FixitPaymentsClient
 */
class FixitPayments extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'fixit-payments';
    }
}
