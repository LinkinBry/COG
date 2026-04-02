<?php
// includes/HitPay.php
require_once __DIR__ . '/../config/env.php';

class HitPay {

    private static function apiKey(): string   { return env('HITPAY_API_KEY', ''); }
    private static function salt(): string     { return env('HITPAY_SALT', ''); }
    private static function baseUrl(): string  { return rtrim(env('HITPAY_BASE_URL', 'https://api.sandbox.hit-pay.com/v1'), '/'); }
    private static function currency(): string { return env('HITPAY_CURRENCY', 'PHP'); }

    /**
     * Create a HitPay payment request and return ['url'=>..., 'payment_id'=>...]
     * or ['error' => 'message'].
     */
    public static function createPayment(
        float  $amount,
        string $referenceNumber,
        string $email,
        string $name,
        string $phone   = '',
        string $purpose = 'Certificate of Grades'
    ): array {
        $apiKey = self::apiKey();
        if (empty($apiKey) || $apiKey === 'your_hitpay_sandbox_api_key_here') {
            return ['error' => 'HitPay API key is not configured. Please update your .env file.'];
        }

        $successUrl = env('HITPAY_SUCCESS_URL', '') . '?ref=' . urlencode($referenceNumber);
        $webhookUrl = env('HITPAY_WEBHOOK_URL', '');
        $cancelUrl  = env('HITPAY_CANCEL_URL', '');

        $payload = [
            'amount'                  => number_format($amount, 2, '.', ''),
            'currency'                => self::currency(),
            'email'                   => $email,
            'name'                    => $name,
            'phone'                   => $phone,
            'purpose'                 => $purpose,
            'reference_number'        => $referenceNumber,
            'redirect_url'            => $successUrl,
            'webhook'                 => $webhookUrl,
            'cancel_url'              => $cancelUrl,
            'allow_repeated_payments' => false,
            'send_email'              => true,
            'payment_methods'         => ['gcash', 'card'], 
        ];

        $ch = curl_init(self::baseUrl() . '/payment-requests');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_HTTPHEADER     => [
                'X-BUSINESS-API-KEY: ' . $apiKey,
                'X-Requested-With: XMLHttpRequest',
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("HitPay cURL error: $curlError");
            return ['error' => 'Payment gateway connection failed.'];
        }

        $data = json_decode($response, true);
        if ($httpCode === 200 && !empty($data['url'])) {
            return ['url' => $data['url'], 'payment_id' => $data['id'] ?? ''];
        }

        error_log("HitPay API error ({$httpCode}): {$response}");
        return ['error' => $data['message'] ?? 'Payment request failed. Please try again.'];
    }

    /**
     * Verify the HMAC signature sent by HitPay webhooks.
     */
    public static function verifyWebhook(array $payload, string $hmac): bool {
        $salt = self::salt();
        if (empty($salt)) {
            error_log("HitPay SALT is not configured in .env");
            return false;
        }
        // HitPay signature algorithm: sort keys, concat key+value, HMAC-SHA256
        ksort($payload);
        $message  = '';
        foreach ($payload as $k => $v) {
            $message .= $k . $v;
        }
        $expected = hash_hmac('sha256', $message, $salt);
        return hash_equals($expected, strtolower($hmac));
    }

    /**
     * Retrieve a payment-request from HitPay by its ID.
     */
    public static function getPayment(string $paymentRequestId): array {
        $ch = curl_init(self::baseUrl() . '/payment-requests/' . $paymentRequestId);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'X-BUSINESS-API-KEY: ' . self::apiKey(),
                'X-Requested-With: XMLHttpRequest',
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true) ?? [];
    }
}