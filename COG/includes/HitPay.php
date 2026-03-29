<?php
// includes/HitPay.php
require_once __DIR__ . '/../config/hitpay.php';

class HitPay {

    /**
     * Create a payment request and return the checkout URL.
     *
     * @param float  $amount
     * @param string $referenceNumber  – your internal order / request number
     * @param string $email
     * @param string $name
     * @param string $phone           – optional
     * @param string $purpose         – line-item description shown to payer
     * @return array  ['url' => string, 'payment_id' => string]  or  ['error' => string]
     */
    public static function createPayment(
        float  $amount,
        string $referenceNumber,
        string $email,
        string $name,
        string $phone  = '',
        string $purpose = 'Certificate of Grades'
    ): array {

        $payload = [
            'amount'           => number_format($amount, 2, '.', ''),
            'currency'         => CURRENCY,
            'email'            => $email,
            'name'             => $name,
            'phone'            => $phone,
            'purpose'          => $purpose,
            'reference_number' => $referenceNumber,
            'redirect_url'     => HITPAY_SUCCESS_URL . '?ref=' . urlencode($referenceNumber),
            'webhook'          => HITPAY_WEBHOOK_URL,
            'allow_repeated_payments' => false,
            'send_email'       => true,
            'payment_methods'  => ['paymaya', 'card', 'grab_pay', 'dob', 'dob_bpi', 'dob_rcbc',
                                   'dob_chinabank', 'wechat', 'alipay', 'qr_ph'],
        ];

        $ch = curl_init(HITPAY_BASE_URL . '/payment-requests');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_HTTPHEADER     => [
                'X-BUSINESS-API-KEY: ' . HITPAY_API_KEY,
                'X-Requested-With: XMLHttpRequest',
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("HitPay cURL error: $curlError");
            return ['error' => 'Payment gateway connection failed.'];
        }

        $data = json_decode($response, true);

        if ($httpCode === 200 && isset($data['url'])) {
            return [
                'url'        => $data['url'],
                'payment_id' => $data['id'] ?? '',
            ];
        }

        error_log("HitPay API error ($httpCode): $response");
        return ['error' => $data['message'] ?? 'Payment request failed. Please try again.'];
    }

    /**
     * Verify HitPay webhook HMAC signature.
     *
     * @param array  $payload  – $_POST from the webhook
     * @param string $hmac     – value of X-HITPAY-SIGNATURE header
     * @return bool
     */
    public static function verifyWebhook(array $payload, string $hmac): bool {
        ksort($payload);
        $message = implode('', array_map(
            fn($k, $v) => "$k$v",
            array_keys($payload),
            array_values($payload)
        ));
        $expected = hash_hmac('sha256', $message, HITPAY_SALT);
        return hash_equals($expected, $hmac);
    }

    /**
     * Get a payment-request by its HitPay ID.
     */
    public static function getPayment(string $paymentRequestId): array {
        $ch = curl_init(HITPAY_BASE_URL . '/payment-requests/' . $paymentRequestId);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'X-BUSINESS-API-KEY: ' . HITPAY_API_KEY,
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