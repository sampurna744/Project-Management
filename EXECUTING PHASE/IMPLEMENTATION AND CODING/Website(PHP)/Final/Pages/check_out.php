<?php
session_start();
error_log("Checkout: Session started at " . date('Y-m-d H:i:s') . ", Session ID: " . session_id() . ", USER_ID: " . ($_SESSION['USER_ID'] ?? 'unset'), 3, 'debug.log');

if (!isset($_SESSION['USER_ID']) || empty($_SESSION['USER_ID']) || !isset($_SESSION['USER_TYPE']) || $_SESSION['USER_TYPE'] !== 'customer') {
    error_log("Checkout: Invalid USER_ID or USER_TYPE, redirecting to signin at " . date('Y-m-d H:i:s'), 3, 'debug.log');
    header("Location: customer_signin.php?return_url=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

require("session/session.php");
include("connection/connection.php");

function executeQuery($conn, $sql, $params = []) {
    error_log("Executing Query: " . $sql . " with params: " . json_encode($params) . " at " . date('Y-m-d H:i:s'), 3, 'debug.log');
    $stmt = oci_parse($conn, $sql);
    if (!$stmt) {
        $e = oci_error($conn);
        error_log("Query Parse Error: " . $e['message'] . " for SQL: " . $sql . " at " . date('Y-m-d H:i:s'), 3, 'debug.log');
        throw new Exception("Query Parse Error: " . $e['message']);
    }
    foreach ($params as $key => &$val) {
        oci_bind_by_name($stmt, $key, $val);
    }
    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        error_log("Query Execute Error: " . $e['message'] . " for SQL: " . $sql . " at " . date('Y-m-d H:i:s'), 3, 'debug.log');
        throw new Exception("Query Execute Error: " . $e['message']);
    }
    return $stmt;
}

try {
    // Validate POST data
    $cart_id = isset($_POST['cartid']) ? (int)$_POST['cartid'] : 0;
    $customer_id = isset($_POST['customerid']) ? (int)$_POST['customerid'] : 0;
    $total_products = isset($_POST['number_product']) ? (int)$_POST['number_product'] : 0;
    $total_price = isset($_POST['total_price']) ? (float)$_POST['total_price'] : 0;
    $discount = isset($_POST['discount']) ? (float)$_POST['discount'] : 0;
    $selected_products = isset($_POST['selected_products']) ? array_map('intval', $_POST['selected_products']) : [];
    $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    error_log("Checkout: Received cart_id: $cart_id, customer_id: $customer_id, total_products: $total_products, total_price: $total_price, discount: $discount, selected_products: " . json_encode($selected_products) . " at " . date('Y-m-d H:i:s'), 3, 'debug.log');

    if ($cart_id <= 0 || $customer_id <= 0 || $total_products <= 0 || $total_price <= 0 || empty($selected_products) || empty($csrf_token)) {
        throw new Exception("Invalid input parameters at " . date('Y-m-d H:i:s'));
    }

    // Validate CSRF token
    if ($csrf_token !== $_SESSION['csrf_token']) {
        error_log("Checkout: Invalid CSRF token at " . date('Y-m-d H:i:s'), 3, 'debug.log');
        throw new Exception("Invalid CSRF token");
    }

    // Verify cart belongs to customer and is active
    $sql = "SELECT c.cart_id, c.order_product_id 
            FROM CART c 
            JOIN CUSTOMER cu ON c.customer_id = cu.customer_id 
            WHERE c.cart_id = :cart_id AND cu.customer_id = :customer_id 
            AND NOT EXISTS (SELECT 1 FROM ORDER_PRODUCT op WHERE op.order_product_id = c.order_product_id)";
    $stmt = executeQuery($conn, $sql, [':cart_id' => $cart_id, ':customer_id' => $customer_id]);
    $row = oci_fetch_assoc($stmt);
    if (!$row) {
        error_log("Checkout: Invalid or already processed cart $cart_id at " . date('Y-m-d H:i:s'), 3, 'debug.log');
        throw new Exception("Invalid or already processed cart");
    }
    $order_product_id = $row['ORDER_PRODUCT_ID'];
    oci_free_statement($stmt);

    // Validate selected products exist in cart
    $sql = "SELECT product_id, no_of_products, product_price FROM CART_ITEM 
            WHERE cart_id = :cart_id AND product_id IN (" . implode(',', array_map('intval', $selected_products)) . ")";
    $stmt = executeQuery($conn, $sql, [':cart_id' => $cart_id]);
    $cart_items = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $cart_items[$row['PRODUCT_ID']] = [
            'quantity' => $row['NO_OF_PRODUCTS'],
            'price' => $row['PRODUCT_PRICE']
        ];
    }
    oci_free_statement($stmt);

    if (count($cart_items) != count($selected_products)) {
        error_log("Checkout: Mismatch in selected products at " . date('Y-m-d H:i:s'), 3, 'debug.log');
        throw new Exception("Selected products mismatch");
    }

    // Validate totals
    $calculated_items = 0;
    $calculated_total = 0;
    foreach ($selected_products as $product_id) {
        if (!isset($cart_items[$product_id])) {
            throw new Exception("Product ID $product_id not found in cart");
        }
        $calculated_items += $cart_items[$product_id]['quantity'];
        $calculated_total += $cart_items[$product_id]['quantity'] * $cart_items[$product_id]['price'];
    }
    if ($calculated_items != $total_products || abs($calculated_total - $total_price) > 0.01) {
        error_log("Checkout: Total mismatch, calculated_items: $calculated_items, total_products: $total_products, calculated_total: $calculated_total, total_price: $total_price at " . date('Y-m-d H:i:s'), 3, 'debug.log');
        throw new Exception("Mismatch in number of items or total price");
    }

    // Begin transaction
    oci_execute(oci_parse($conn, "BEGIN NULL; END;"));

    // Insert into ORDER_PRODUCT
    $sql = "INSERT INTO ORDER_PRODUCT (order_product_id, no_of_product, order_status, total_price, slot_id, customer_id, order_date, order_time, discount_amount, cart_id) 
            VALUES (:order_product_id, :no_of_product, 0, :total_price, 0, :customer_id, SYSDATE, SYSTIMESTAMP, :discount_amount, :cart_id)";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':order_product_id', $order_product_id);
    oci_bind_by_name($stmt, ':no_of_product', $total_products);
    oci_bind_by_name($stmt, ':total_price', $total_price);
    oci_bind_by_name($stmt, ':customer_id', $customer_id);
    oci_bind_by_name($stmt, ':discount_amount', $discount);
    oci_bind_by_name($stmt, ':cart_id', $cart_id);
    if (!oci_execute($stmt)) {
        throw new Exception("Failed to insert into ORDER_PRODUCT at " . date('Y-m-d H:i:s'));
    }
    oci_free_statement($stmt);

    // Process selected products
    foreach ($selected_products as $product_id) {
        $product_qty = $cart_items[$product_id]['quantity'];
        $product_price = $cart_items[$product_id]['price'];

        // Fetch discount
        $sql = "SELECT DISCOUNT_PERCENT FROM DISCOUNT WHERE PRODUCT_ID = :product_id";
        $stmt = executeQuery($conn, $sql, [':product_id' => $product_id]);
        $row = oci_fetch_assoc($stmt);
        $discount_percent = $row ? (float)$row['DISCOUNT_PERCENT'] : 0;
        oci_free_statement($stmt);

        $discount_amount_per_product = ($product_price * $discount_percent) / 100;
        $discounted_price = $product_price - $discount_amount_per_product;

        // Insert into ORDER_DETAILS
        $sql = "INSERT INTO ORDER_DETAILS (order_product_id, product_id, product_qty, product_price, trader_user_id) 
                VALUES (:order_product_id, :product_id, :product_qty, :product_price, 
                        (SELECT user_id FROM PRODUCT WHERE product_id = :product_id))";
        $stmt = executeQuery($conn, $sql, [
            ':order_product_id' => $order_product_id,
            ':product_id' => $product_id,
            ':product_qty' => $product_qty,
            ':product_price' => $product_price
        ]);

        // Update PRODUCT_QUANTITY
        $sql = "UPDATE PRODUCT 
                SET product_quantity = product_quantity - :quantity 
                WHERE product_id = :product_id AND product_quantity >= :quantity";
        $stmt = executeQuery($conn, $sql, [
            ':quantity' => $product_qty,
            ':product_id' => $product_id
        ]);
        $rows_affected = oci_num_rows($stmt);
        if ($rows_affected == 0) {
            throw new Exception("Insufficient stock for product ID $product_id at " . date('Y-m-d H:i:s'));
        }
        oci_free_statement($stmt);

        // Delete from CART_ITEM
        $sql = "DELETE FROM CART_ITEM WHERE cart_id = :cart_id AND product_id = :product_id";
        executeQuery($conn, $sql, [':cart_id' => $cart_id, ':product_id' => $product_id]);
    }

    // Update PRODUCT stock status
    $sql = "UPDATE PRODUCT 
            SET stock_available = 'no', is_disabled = 0 
            WHERE product_quantity < 1";
    executeQuery($conn, $sql);

    // Commit transaction
    oci_execute(oci_parse($conn, "COMMIT"));

    // Clear session cart_id
    unset($_SESSION['cart_id']);
    error_log("Checkout: Transaction committed successfully for cart_id: $cart_id at " . date('Y-m-d H:i:s'), 3, 'debug.log');

    // Redirect to slot_time.php
    $url = "slot_time.php?customerid=$customer_id&order_id=$order_product_id&cartid=$cart_id&number_product=$total_products&total_price=$total_price&discount=$discount";
    header("Location: $url");
    exit;

} catch (Exception $e) {
    // Rollback transaction
    oci_execute(oci_parse($conn, "ROLLBACK"));
    error_log("Checkout Error: " . $e->getMessage(), 3, 'debug.log');
    header("Location: error_page.php?error=" . urlencode($e->getMessage()));
    exit;
} finally {
    if (is_resource($conn)) {
        oci_close($conn);
    }
}
?>