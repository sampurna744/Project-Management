<?php
session_start();
error_log("delete_cart_item: Session started at " . date('Y-m-d H:i:s') . ", Session ID: " . session_id() . ", USER_ID: " . ($_SESSION['USER_ID'] ?? 'unset'), 3, 'debug.log');

if (!isset($_SESSION['USER_ID']) || empty($_SESSION['USER_ID'])) {
    error_log("delete_cart_item: Invalid USER_ID, redirecting to signin at " . date('Y-m-d H:i:s'), 3, 'debug.log');
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
    error_log("delete_cart_item: Invalid CSRF token at " . date('Y-m-d H:i:s'), 3, 'debug.log');
    header("Location: cart.php");
    exit;
}

$cart_id = isset($_POST['cart_id']) ? (int)$_POST['cart_id'] : 0;
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

if (!$cart_id || !$product_id) {
    error_log("delete_cart_item: Invalid input, cart_id: $cart_id, product_id: $product_id at " . date('Y-m-d H:i:s'), 3, 'debug.log');
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
    error_log("delete_cart_item: Cart $cart_id does not belong to user at " . date('Y-m-d H:i:s'), 3, 'debug.log');
    oci_free_statement($stmt);
    oci_close($conn);
    header("Location: cart.php");
    exit;
}
oci_free_statement($stmt);

// Delete the item from CART_ITEM
$sql = "DELETE FROM CART_ITEM WHERE cart_id = :cart_id AND product_id = :product_id";
$stmt = executeQuery($conn, $sql, [':cart_id' => $cart_id, ':product_id' => $product_id]);
oci_commit($conn);
oci_free_statement($stmt);

oci_close($conn);
header("Location: cart.php");
exit;
?>