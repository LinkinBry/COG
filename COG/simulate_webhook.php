<?php
// Simulate a HitPay webhook POST to your callback

$webhookUrl = "https://bailey-dialogistic-mirtha.ngrok-free.dev/student/payment_callback.php";

// Example payload (adjust fields to match your DB schema)
$payload = [
    "id" => uniqid("txn_", true),
    "status" => "completed", // simulate a successful payment
    "payment_method" => "gcash",
    "amount" => 100.00,
    "currency" => "PHP",
    "reference_number" => uniqid("REF_", true),
    "created_at" => date("c"),
];

$ch = curl_init($webhookUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Webhook sent. HTTP status: $httpCode\n";
echo "Response: $response\n";
