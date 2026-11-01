<?php

namespace App\Webpay;

/**
 * A Webpay Plus transaction as Transbank reports it, after confirming it
 * (commit) or reading it (status). It is approved only when its status is
 * AUTHORIZED and its response code is 0.
 */
final readonly class WebpayResult
{
    public function __construct(
        public string $status,
        public ?int $responseCode,
        public int $amount,
        public string $buyOrder,
        public ?string $authorizationCode,
        public ?string $cardLastDigits,
    ) {}

    /**
     * @param  array<string, mixed>  $body
     */
    public static function fromResponse(array $body): self
    {
        $card = is_array($body['card_detail'] ?? null) ? $body['card_detail'] : [];

        return new self(
            status: (string) ($body['status'] ?? ''),
            responseCode: isset($body['response_code']) && is_numeric($body['response_code']) ? (int) $body['response_code'] : null,
            amount: is_numeric($body['amount'] ?? null) ? (int) $body['amount'] : 0,
            buyOrder: (string) ($body['buy_order'] ?? ''),
            authorizationCode: isset($body['authorization_code']) && is_scalar($body['authorization_code']) ? (string) $body['authorization_code'] : null,
            cardLastDigits: isset($card['card_number']) && is_scalar($card['card_number']) ? substr((string) $card['card_number'], -4) : null,
        );
    }

    public function isAuthorized(): bool
    {
        return $this->status === 'AUTHORIZED' && $this->responseCode === 0;
    }

    /**
     * Still waiting for her to pay, or abandoned before paying.
     */
    public function isUnpaid(): bool
    {
        return $this->status === 'INITIALIZED';
    }

    /**
     * Its money already went back to the card.
     */
    public function isRefunded(): bool
    {
        return in_array($this->status, ['NULLIFIED', 'REVERSED'], true);
    }
}
