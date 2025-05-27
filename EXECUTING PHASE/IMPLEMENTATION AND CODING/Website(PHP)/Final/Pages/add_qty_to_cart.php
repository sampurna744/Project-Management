<?php
session_start();
error_log("add_qty_to_cart: Session started at " . date('Y-m-d H:i:s') . ", Session ID: " . session_id() . ", USER_ID: " . ($_SESSION['USER_ID'] ?? 'unset'), 3, 'debug.log');

if (!isset($_SESSION['USER_ID']) || empty($_SESSION['USER_ID'])) {
    error_log("add_qty_to_cart: Invalid USER_ID, redirecting to signin at " . date('Y-m-d H:i:s'), 3, 'debug.log');
    header("Location: customer_signin.php");
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
        die(htmlentities($e['message']));
    }
    foreach ($params as $key => &$val) {
        oci_bind_by_name($stmt, $key, $val);
    }
    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        error_log("Query Execute Error: " . $e['message'] . " for SQL: " . $sql . " at " . date('Y-m-d H:i:s'), 3, 'debug.log');
        die(htmlentities($e['message']));
    }
    return $stmt;
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("add_qty_to_cart: Invalid CSRF token at " . date('Y-m-d H:i:s'), 3, 'debug.log');
    header("Location: cart.php");
    exit;
}

$cart_id = isset($_POST['cart_id']) ? (int)$_POST['cart_id'] : 0;
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if (!$cart_id || !$product_id || !in_array($action, ['increase', 'decrease'])) {
    error_log("add_qty_to_cart: Invalid input, cart_id: $cart_id, product_id: $product_id, action: $action at " . date('Y-m-d H:i:s'), 3, 'debug.log');
    header("Location: cart.php");
    exit;
}

// Verify cart belongs to user
$sql = "SELECT c.cart_id 
        FROM CART c 
        JOIN CUSTOMER cu ON c.customer_id = cu.customer_id 
        WHERE c.cart_id = :cart_id AND cu.user_id = :user_id";
$stmt = executeQuery($conn, $sql, [':cart_id' => $cart_id, ':user_id' => $_SESSION['USER_ID']]);
if (!oci_fetch($stmt)) {
    error_log("add_qty_to_cart: Cart $cart_id does not belong to user at " . date('Y-m-d H:i:s'), 3, 'debug.log');
    oci_free_statement($stmt);
    oci_close($conn);
    header("Location: cart.php");
    exit;
}
oci_free_statement($stmt);

// Check total products in cart
$sql = "SELECT SUM(no_of_products) AS total_products 
        FROM CART_ITEM 
        WHERE cart_id = :cart_id";
$stmt = executeQuery($conn, $sql, [':cart_id' => $cart_id]);
$row = oci_fetch_assoc($stmt);
$total_products = $row ? (int)$row['TOTAL_PRODUCTS'] : 0;
oci_free_statement($stmt);

if ($action == 'increase' && $total_products >= 20) {
    error_log("add_qty_to_cart: Maximum product limit reached ($total_products) at " . date('Y-m-d H:i:s'), 3, 'debug.log');
    header("Location: cart.php");
    exit;
}

// Update or insert quantity
$sql = "BEGIN
        MERGE INTO CART_ITEM ci
        USING (SELECT :cart_id AS cart_id, :product_id AS product_id FROM DUAL) d
        ON (ci.cart_id = d.cart_id AND ci.product_id = d.product_id)
        WHEN MATCHED THEN
            UPDATE SET no_of_products = CASE 
                WHEN :action = 'increase' THEN no_of_products + 1
                WHEN :action = 'decrease' AND no_of_products > 1 THEN no_of_products - 1
                ELSE no_of_products
            END
        WHEN NOT MATCHED THEN
            INSERT (cart_id, product_id, no_of_products, product_price)
            VALUES (:cart_id, :product_id, 1, (SELECT product_price FROM PRODUCT WHERE product_id = :product_id));
        END;";
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':cart_id', $cart_id);
oci_bind_by_name($stmt, ':product_id', $product_id);
oci_bind_by_name($stmt, ':action', $action);
if (!oci_execute($stmt)) {
    $e = oci_error($stmt);
    error_log("add_qty_to_cart: Merge Error: " . $e['message'] . " at " . date('Y-m-d H:i:s'), 3, 'debug.log');
    oci_free_statement($stmt);
    oci_close($conn);
    header("Location: cart.php");
    exit;
}
oci_free_statement($stmt);
oci_commit($conn);

// Check if quantity became 0 and delete if necessary
if ($action == 'decrease') {
    $sql = "SELECT no_of_products FROM CART_ITEM WHERE cart_id = :cart_id AND product_id = :product_id";
    $stmt = executeQuery($conn, $sql, [':cart_id' => $cart_id, ':product_id' => $product_id]);
    $row = oci_fetch_assoc($stmt);
    $current_qty = $row ? (int)$row['NO_OF_PRODUCTS'] : 0;
    oci_free_statement($stmt);

    if ($current_qty == 0) {
        $sql = "DELETE FROM CART_ITEM WHERE cart_id = :cart_id AND product_id = :product_id";
        executeQuery($conn, $sql, [':cart_id' => $cart_id, ':product_id' => $product_id]);
        oci_commit($conn);
    }
}

oci_close($conn);
header("Location: cart.php");
exit;
?>