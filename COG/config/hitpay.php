<?php
// config/hitpay.php  –  HitPay sandbox credentials
// Sign up at https://dashboard.sandbox.hit-pay.com to get these values.

define('HITPAY_API_KEY',    'test_cce08acc43dfa38fdc87597757289b98a612160073d76c8301ffd1977e9a34c3');
define('HITPAY_SALT',       'Rg5jnz6Ajs5EGA1UAJH9mj0SbA7fdSh3oaaak7QHohlFcWaSPk9z48eaXvOYBBvN');
define('HITPAY_BASE_URL',   'https://api.sandbox.hit-pay.com/v1');
define('HITPAY_WEBHOOK_URL', 'https://bailey-dialogistic-mirtha.ngrok-free.dev/student/payment_callback.php');
define('HITPAY_SUCCESS_URL', 'https://bailey-dialogistic-mirtha.ngrok-free.dev/student/payment_success.php');
define('HITPAY_CANCEL_URL',  'https://bailey-dialogistic-mirtha.ngrok-free.dev/student/payment_cancel.php');
define('CURRENCY',           'PHP');

// Groq API key  (https://console.groq.com)
define('GROQ_API_KEY', 'your_groq_api_key_here');
define('GROQ_MODEL',   'llama3-8b-8192');