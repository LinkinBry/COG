<?php
// config/hitpay.php  –  HitPay sandbox credentials
// Sign up at https://dashboard.sandbox.hit-pay.com to get these values.

define('HITPAY_API_KEY',    'your_hitpay_sandbox_api_key_here');
define('HITPAY_SALT',       'your_hitpay_sandbox_salt_here');
define('HITPAY_BASE_URL',   'https://api.sandbox.hit-pay.com/v1');
define('HITPAY_WEBHOOK_URL', 'https://yourdomain.com/student/payment_callback.php');
define('HITPAY_SUCCESS_URL', 'https://yourdomain.com/student/payment_success.php');
define('HITPAY_CANCEL_URL',  'https://yourdomain.com/student/payment_cancel.php');
define('CURRENCY',           'PHP');

// Groq API key  (https://console.groq.com)
define('GROQ_API_KEY', 'your_groq_api_key_here');
define('GROQ_MODEL',   'llama3-8b-8192');