<?php

if (!defined('PROJECT_PATH')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        ? 'https://'
        : 'http://';
    $host = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] ? $_SERVER['HTTP_HOST'] : 'localhost';
    
    // Auto-detect project base path
    $scriptDir = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])) : '';
    if (strpos($scriptDir, 'SSLCommerz-PHP') !== false) {
        $basePath = preg_replace('#/SSLCommerz-PHP.*$#i', '', $scriptDir);
    } else {
        $basePath = rtrim($scriptDir, '/');
    }
    $basePath = rtrim($basePath, '/');
    define('PROJECT_PATH', $protocol . $host . $basePath);
}

if (!defined('IS_SANDBOX')) {
    define('IS_SANDBOX', true); // 'true' for sandbox, 'false' for live
}

if (!defined('STORE_ID')) {
    define('STORE_ID', 'codem6a775b6028e98'); // your store id. For sandbox, register at https://developer.sslcommerz.com/registration/
}

if (!defined('STORE_PASSWORD')) {
    define('STORE_PASSWORD', 'codem6a775b6028e98@ssl'); // your store password.
}

return [
    'success_url' => 'checkout.php?paid=true', // your success url
    'failed_url' => 'checkout.php?paid=false', // your fail url
    'cancel_url' => 'checkout.php?paid=cancel', //your cancel url
    'ipn_url' => 'SSLCommerz-PHP/pg_redirection/ipn.php', // your ipn url


    'projectPath' => PROJECT_PATH,
    'apiDomain' => IS_SANDBOX ? 'https://sandbox.sslcommerz.com' : 'https://securepay.sslcommerz.com',
    'apiCredentials' => [
        'store_id' => STORE_ID,
        'store_password' => STORE_PASSWORD,
    ],
    'apiUrl' => [
        'make_payment' => "/gwprocess/v4/api.php",
        'order_validate' => "/validator/api/validationserverAPI.php",
    ],
    'connect_from_localhost' => false,
    'verify_hash' => false,
];

