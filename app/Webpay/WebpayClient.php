<?php

namespace App\Webpay;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Webpay Plus by Transbank, through its REST API (v1.2), with Laravel's
 * HTTP client and no SDK. Every call has a short timeout and throws a
 * WebpayException when Transbank does not answer or answers an error.
 */
class WebpayClient
{
    private const string PATH = '/rswebpaytransaction/api/webpay/v1.2/transactions';

    /**
     * Start a transaction for the amount. She pays it on the returned URL,
     * which the browser must reach by POST with the token as token_ws.
     *
     * @return array{token: string, url: string}
     *
     * @throws WebpayException
     */
    public function create(string $buyOrder, string $sessionId, int $amount, string $returnUrl): array
    {
        $body = $this->send(fn (PendingRequest $request): Response => $request->post(self::PATH, [
            'buy_order' => $buyOrder,
            'session_id' => $sessionId,
            'amount' => $amount,
            'return_url' => $returnUrl,
        ]));

        if (! is_string($body['token'] ?? null) || ! is_string($body['url'] ?? null)) {
            throw new WebpayException('Webpay did not return a token.');
        }

        return ['token' => $body['token'], 'url' => $body['url']];
    }

    /**
     * Confirm the transaction once she comes back. Transbank answers an
     * error when it was already confirmed, or when she never paid.
     *
     * @throws WebpayException
     */
    public function commit(string $token): WebpayResult
    {
        return WebpayResult::fromResponse($this->send(fn (PendingRequest $request): Response => $request->put(self::PATH.'/'.$token)));
    }

    /**
     * How the transaction stands, for up to 7 days after it was created.
     *
     * @throws WebpayException
     */
    public function status(string $token): WebpayResult
    {
        return WebpayResult::fromResponse($this->send(fn (PendingRequest $request): Response => $request->get(self::PATH.'/'.$token)));
    }

    /**
     * Give back the amount to her card: a reversal on the same day, or a
     * nullification later.
     *
     * @throws WebpayException when Transbank refuses it.
     */
    public function refund(string $token, int $amount): void
    {
        $body = $this->send(fn (PendingRequest $request): Response => $request->post(self::PATH.'/'.$token.'/refunds', ['amount' => $amount]));

        if (($body['type'] ?? null) !== 'REVERSED' && ($body['response_code'] ?? null) !== 0) {
            throw new WebpayException('Transbank refused the refund.');
        }
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     * @return array<string, mixed>
     *
     * @throws WebpayException
     */
    private function send(callable $call): array
    {
        $request = Http::baseUrl(config()->string('services.webpay.base_url'))
            ->withHeaders([
                'Tbk-Api-Key-Id' => config()->string('services.webpay.commerce_code'),
                'Tbk-Api-Key-Secret' => config()->string('services.webpay.api_key'),
            ])
            ->acceptJson()
            ->asJson()
            ->timeout(10);

        try {
            $response = $call($request);
        } catch (ConnectionException $exception) {
            throw new WebpayException('Webpay did not answer.', previous: $exception);
        }

        if ($response->failed()) {
            $message = $response->json('error_message');

            throw new WebpayException(is_string($message) ? $message : "Webpay answered {$response->status()}.");
        }

        $body = $response->json();

        return is_array($body) ? $body : [];
    }
}
