<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

# This is a sample page to understand how to connect payment gateway

require_once(__DIR__ . "/lib/SslCommerzNotification.php");

require_once(__DIR__ . "/db_connection.php");
require_once(__DIR__ . "/OrderTransaction.php");

global $conn_integration;

use SslCommerz\SslCommerzNotification;

# Organize the submitted/inputted data
$post_data = array();

$post_data['total_amount'] = isset($_POST['amount']) ? (float) $_POST['amount'] : 0;
$post_data['currency'] = "BDT";
$post_data['tran_id'] = "SSLCZ_TEST_" . uniqid();

$billingHouseNumber = isset($_POST['house_number']) ? trim($_POST['house_number']) : '';
$billingStreetAddress = isset($_POST['street_address']) ? trim($_POST['street_address']) : '';
$billingAddress = trim($billingHouseNumber . ' ' . $billingStreetAddress);

$shippingHouseNumber = isset($_POST['shipping_house_number']) ? trim($_POST['shipping_house_number']) : '';
$shippingStreetAddress = isset($_POST['shipping_street_address']) ? trim($_POST['shipping_street_address']) : '';
$shippingAddress = trim($shippingHouseNumber . ' ' . $shippingStreetAddress);

$userId = null;
if (isset($_POST['user_id']) && is_numeric($_POST['user_id'])) {
    $userId = (int) $_POST['user_id'];
} elseif (isset($_SESSION['user']) && !empty($_SESSION['user']->id)) {
    $userId = (int) $_SESSION['user']->id;
}

# CUSTOMER INFORMATION
$customerName = isset($_POST['customer_name']) && trim($_POST['customer_name']) !== '' ? trim($_POST['customer_name']) : "John Doe";
$customerEmail = isset($_POST['customer_email']) && trim($_POST['customer_email']) !== '' ? trim($_POST['customer_email']) : "john.doe@email.com";
$customerCity = isset($_POST['cus_city']) && trim($_POST['cus_city']) !== '' ? trim($_POST['cus_city']) : "Dhaka";
$customerPostcode = isset($_POST['cus_postcode']) && trim($_POST['cus_postcode']) !== '' ? trim($_POST['cus_postcode']) : "1000";
$customerMobile = isset($_POST['customer_mobile']) && trim($_POST['customer_mobile']) !== '' ? trim($_POST['customer_mobile']) : "01711111111";

$post_data['cus_name'] = $customerName;
$post_data['cus_email'] = $customerEmail;
$post_data['cus_add1'] = $billingAddress !== '' ? $billingAddress : "Dhaka";
$post_data['cus_add2'] = "Dhaka";
$post_data['cus_city'] = $customerCity;
$post_data['cus_state'] = "Dhaka";
$post_data['cus_postcode'] = $customerPostcode;
$post_data['cus_country'] = "Bangladesh";
$post_data['cus_phone'] = $customerMobile;
$post_data['cus_fax'] = "01711111111";

# SHIPMENT INFORMATION
$shippingName = isset($_POST['shipping_name']) && trim($_POST['shipping_name']) !== '' ? trim($_POST['shipping_name']) : $customerName;
$shippingCity = isset($_POST['shipping_city']) && trim($_POST['shipping_city']) !== '' ? trim($_POST['shipping_city']) : $customerCity;
$shippingZipCode = isset($_POST['shipping_zip_code']) && trim($_POST['shipping_zip_code']) !== '' ? trim($_POST['shipping_zip_code']) : $customerPostcode;
$shippingMobileNumber = isset($_POST['shipping_mobile_number']) && trim($_POST['shipping_mobile_number']) !== '' ? trim($_POST['shipping_mobile_number']) : $customerMobile;

$post_data["shipping_method"] = "YES";
$post_data['ship_name'] = $shippingName;
$post_data['ship_add1'] = $shippingAddress !== '' ? $shippingAddress : $post_data['cus_add1'];
$post_data['ship_add2'] = "";
$post_data['ship_city'] = $shippingCity;
$post_data['ship_state'] = "Dhaka";
$post_data['ship_postcode'] = $shippingZipCode;
$post_data['ship_phone'] = $shippingMobileNumber;
$post_data['ship_country'] = "Bangladesh";

$post_data['user_id'] = $userId;

$post_data['emi_option'] = "1";
$post_data["product_category"] = "Electronic";
$post_data["product_profile"] = "general";
$post_data["product_name"] = "Computer";
$post_data["num_of_item"] = "1";

# SHIPPING TABLE DATA
$shipping_data = [
    'customer_name' => $customerName,
    'house_number' => $billingHouseNumber,
    'street_address' => $billingStreetAddress,
    'cus_city' => $customerCity,
    'cus_postcode' => $customerPostcode,
    'customer_mobile' => $customerMobile,
    'customer_email' => $customerEmail,
    'shipping_name' => $shippingName,
    'shipping_house_number' => $shippingHouseNumber !== '' ? $shippingHouseNumber : $billingHouseNumber,
    'shipping_street_address' => $shippingStreetAddress !== '' ? $shippingStreetAddress : $billingStreetAddress,
    'shipping_city' => $shippingCity,
    'shipping_zip_code' => $shippingZipCode,
    'shipping_mobile_number' => $shippingMobileNumber,
];

# ORDER ITEMS DATA
$items = [];
if (isset($_POST['product_id']) && is_array($_POST['product_id'])) {
    foreach ($_POST['product_id'] as $idx => $pid) {
        $qty = isset($_POST['quantity'][$idx]) ? (int)$_POST['quantity'][$idx] : 1;
        $itemTotal = isset($_POST['item_total'][$idx]) ? (float)$_POST['item_total'][$idx] : 0;
        $items[] = [
            'product_id' => (int)$pid,
            'quantity' => $qty,
            'item_total' => $itemTotal
        ];
    }
} elseif (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $productId => $quantity) {
        $pRes = $conn_integration->query("SELECT discount_price, regular_price FROM products WHERE id = " . (int)$productId);
        $price = 0;
        if ($pRes && $pRow = $pRes->fetch_assoc()) {
            $price = !empty($pRow['discount_price']) ? $pRow['discount_price'] : ($pRow['regular_price'] ?? 0);
        }
        $items[] = [
            'product_id' => (int)$productId,
            'quantity' => (int)$quantity,
            'item_total' => ((float)$price * (int)$quantity)
        ];
    }
}

# Save into `orders`, `shipping`, and `order_items`
$query = new OrderTransaction();
try {
    $orderId = $query->saveOrderWithDetails($conn_integration, $post_data, $shipping_data, $items);
    $_SESSION['last_order_id'] = $orderId;
    $_SESSION['last_tran_id'] = $post_data['tran_id'];

    # Call the Payment Gateway Library
    $sslcz = new SslCommerzNotification();
    $msg = $sslcz->makePayment($post_data, 'hosted');
    if (!is_array($msg)) {
        echo $msg;
    }
} catch (Exception $e) {
    echo "Error saving order: " . $e->getMessage();
}


