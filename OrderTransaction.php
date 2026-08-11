<?php

class OrderTransaction {

    private function escapeValue($value)
    {
        $value = trim((string) $value);
        return str_replace("'", "\\'", $value);
    }

    public function getRecordQuery($tran_id)
    {
        $safeTranId = $this->escapeValue($tran_id);
        $sql = "SELECT * FROM orders WHERE transaction_id = '$safeTranId'";
        return $sql;
    }

    public function saveTransactionQuery($post_data)
    {
        $name = $this->escapeValue($post_data['cus_name'] ?? '');
        $email = $this->escapeValue($post_data['cus_email'] ?? '');
        $phone = $this->escapeValue($post_data['cus_phone'] ?? '');
        $transaction_amount = isset($post_data['total_amount']) ? (float) $post_data['total_amount'] : 0;
        $address = $this->escapeValue($post_data['cus_add1'] ?? '');
        $transaction_id = $this->escapeValue($post_data['tran_id'] ?? '');
        $currency = $this->escapeValue($post_data['currency'] ?? 'BDT');
        $userId = isset($post_data['user_id']) && is_numeric($post_data['user_id']) ? (int) $post_data['user_id'] : 'NULL';

        $userSql = $userId === 'NULL' ? 'NULL' : (int) $userId;

        $sql = "INSERT INTO orders (name, email, phone, amount, address, status, transaction_id, currency, user_id)
                VALUES ('$name', '$email', '$phone', $transaction_amount, '$address', 'Pending', '$transaction_id', '$currency', $userSql)";

        return $sql;
    }

    public function saveShippingQuery($shipping_data, $order_id)
    {
        $customer_name = $this->escapeValue($shipping_data['customer_name'] ?? '');
        $house_number = $this->escapeValue($shipping_data['house_number'] ?? '');
        $street_address = $this->escapeValue($shipping_data['street_address'] ?? '');
        $cus_city = $this->escapeValue($shipping_data['cus_city'] ?? '');
        $cus_postcode = $this->escapeValue($shipping_data['cus_postcode'] ?? '');
        $customer_mobile = $this->escapeValue($shipping_data['customer_mobile'] ?? '');
        $customer_email = $this->escapeValue($shipping_data['customer_email'] ?? '');

        $shipping_name = $this->escapeValue(!empty($shipping_data['shipping_name']) ? $shipping_data['shipping_name'] : $customer_name);
        $shipping_house_number = $this->escapeValue(!empty($shipping_data['shipping_house_number']) ? $shipping_data['shipping_house_number'] : $house_number);
        $shipping_street_address = $this->escapeValue(!empty($shipping_data['shipping_street_address']) ? $shipping_data['shipping_street_address'] : $street_address);
        $shipping_city = $this->escapeValue(!empty($shipping_data['shipping_city']) ? $shipping_data['shipping_city'] : $cus_city);
        $shipping_zip_code = $this->escapeValue(!empty($shipping_data['shipping_zip_code']) ? $shipping_data['shipping_zip_code'] : $cus_postcode);
        $shipping_mobile_number = $this->escapeValue(!empty($shipping_data['shipping_mobile_number']) ? $shipping_data['shipping_mobile_number'] : $customer_mobile);
        $safeOrderId = (int) $order_id;

        $sql = "INSERT INTO shipping (customer_name, house_number, street_address, cus_city, cus_postcode, customer_mobile, customer_email, shipping_name, shipping_house_number, shipping_street_address, shipping_city, shipping_zip_code, shipping_mobile_number, order_id)
                VALUES ('$customer_name', '$house_number', '$street_address', '$cus_city', '$cus_postcode', '$customer_mobile', '$customer_email', '$shipping_name', '$shipping_house_number', '$shipping_street_address', '$shipping_city', '$shipping_zip_code', '$shipping_mobile_number', $safeOrderId)";

        return $sql;
    }

    public function saveOrderItemQuery($item, $order_id)
    {
        $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
        $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 0;
        $itemTotal = isset($item['item_total']) ? (float) $item['item_total'] : 0;
        $safeOrderId = (int) $order_id;

        $sql = "INSERT INTO order_items (product_id, quantity, item_total, order_id)
                VALUES ($productId, $quantity, $itemTotal, $safeOrderId)";

        return $sql;
    }

    public function saveOrderWithDetails($conn, $order_data, $shipping_data, $items = [])
    {
        $conn->begin_transaction();
        try {
            // 1. Insert order
            $orderSql = $this->saveTransactionQuery($order_data);
            if (!$conn->query($orderSql)) {
                throw new Exception("Error saving order: " . $conn->error);
            }
            $orderId = $conn->insert_id;

            // 2. Insert shipping
            $shippingSql = $this->saveShippingQuery($shipping_data, $orderId);
            if (!$conn->query($shippingSql)) {
                throw new Exception("Error saving shipping details: " . $conn->error);
            }

            // 3. Insert order items
            if (!empty($items) && is_array($items)) {
                foreach ($items as $item) {
                    if (empty($item['product_id'])) {
                        continue;
                    }
                    $itemSql = $this->saveOrderItemQuery($item, $orderId);
                    if (!$conn->query($itemSql)) {
                        throw new Exception("Error saving order item: " . $conn->error);
                    }
                }
            }

            $conn->commit();
            return $orderId;
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }

    public function updateTransactionQuery($tran_id, $type = 'Processing')
    {
        $safeTranId = $this->escapeValue($tran_id);
        $safeType = $this->escapeValue($type);
        $sql = "UPDATE orders SET status = '$safeType' WHERE transaction_id = '$safeTranId'";

        return $sql;
    }
}


