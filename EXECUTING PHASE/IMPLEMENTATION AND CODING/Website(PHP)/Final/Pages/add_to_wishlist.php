<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require("session/session.php");

$user_id = isset($_GET["user_id"]) ? (int)$_GET["user_id"] : 0;
$product_id = isset($_GET["product_id"]) ? (int)$_GET["product_id"] : 0;
$search_text = isset($_GET["searchtext"]) ? trim($_GET["searchtext"]) : "";

$response = ['status' => 'error', 'message' => ''];

if (!$user_id) {
    $response['message'] = "User not logged in.";
    echo json_encode($response);
    exit;
}

if (!$product_id) {
    $response['message'] = "Invalid product ID.";
    echo json_encode($response);
    exit;
}

include("connection/connection.php");

// Validate product
$sql = "SELECT PRODUCT_ID FROM PRODUCT WHERE PRODUCT_ID = :product_id AND IS_DISABLED = 1 AND ADMIN_VERIFIED = 1";
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':product_id', $product_id);
oci_execute($stmt);
if (!oci_fetch_assoc($stmt)) {
    $response['message'] = "Product not found or unavailable.";
    echo json_encode($response);
    oci_free_statement($stmt);
    oci_close($conn);
    exit;
}
oci_free_statement($stmt);

// Get customer_id
$sql = "SELECT customer_id FROM CUSTOMER WHERE user_id = :user_id";
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':user_id', $user_id);
oci_execute($stmt);
$row = oci_fetch_assoc($stmt);
$customer_id = $row ? $row['CUSTOMER_ID'] : null;
oci_free_statement($stmt);

if (!$customer_id) {
    $response['message'] = "Invalid customer.";
    echo json_encode($response);
    oci_close($conn);
    exit;
}

// Check for existing wishlist
$wishlist_sql = "SELECT wishlist_id FROM WISHLIST WHERE customer_id = :customer_id";
$wishlist_stmt = oci_parse($conn, $wishlist_sql);
oci_bind_by_name($wishlist_stmt, ':customer_id', $customer_id);
oci_execute($wishlist_stmt);
$wishlist_row = oci_fetch_assoc($wishlist_stmt);
$wishlist_id = $wishlist_row ? $wishlist_row['WISHLIST_ID'] : null;
oci_free_statement($wishlist_stmt);

if (!$wishlist_id) {
    // Create new wishlist
    $wishlist_sql = "INSERT INTO WISHLIST (customer_id, wishlist_created_date, wishlist_updated_date) 
                     VALUES (:customer_id, SYSDATE, SYSDATE) RETURNING wishlist_id INTO :wishlist_id";
    $wishlist_stmt = oci_parse($conn, $wishlist_sql);
    oci_bind_by_name($wishlist_stmt, ':customer_id', $customer_id);
    oci_bind_by_name($wishlist_stmt, ':wishlist_id', $wishlist_id, -1, OCI_B_INT);
    if (!oci_execute($wishlist_stmt)) {
        $e = oci_error($wishlist_stmt);
        $response['message'] = "Failed to create wishlist: " . $e['message'];
        echo json_encode($response);
        oci_free_statement($wishlist_stmt);
        oci_close($conn);
        exit;
    }
    oci_free_statement($wishlist_stmt);
}

// Check wishlist item count
$count_sql = "SELECT COUNT(*) AS total_items FROM WISHLIST_ITEM WHERE wishlist_id = :wishlist_id";
$count_stmt = oci_parse($conn, $count_sql);
oci_bind_by_name($count_stmt, ':wishlist_id', $wishlist_id);
oci_execute($count_stmt);
$count_row = oci_fetch_assoc($count_stmt);
$total_items = $count_row ? (int)$count_row['TOTAL_ITEMS'] : 0;
oci_free_statement($count_stmt);

if ($total_items >= 10) {
    $response['message'] = "Wishlist is full.";
    echo json_encode($response);
    oci_close($conn);
    exit;
}

// Check if item exists
$item_sql = "SELECT 1 FROM WISHLIST_ITEM WHERE wishlist_id = :wishlist_id AND product_id = :product_id";
$item_stmt = oci_parse($conn, $item_sql);
oci_bind_by_name($item_stmt, ':wishlist_id', $wishlist_id);
oci_bind_by_name($item_stmt, ':product_id', $product_id);
oci_execute($item_stmt);
$exists = oci_fetch($item_stmt);
oci_free_statement($item_stmt);

if ($exists) {
    $response['status'] = 'success';
    $response['message'] = "Product already in wishlist.";
    echo json_encode($response);
    oci_close($conn);
    exit;
}

// Insert new item
$insert_sql = "INSERT INTO WISHLIST_ITEM (wishlist_id, product_id) 
               VALUES (:wishlist_id, :product_id)";
$insert_stmt = oci_parse($conn, $insert_sql);
oci_bind_by_name($insert_stmt, ':wishlist_id', $wishlist_id);
oci_bind_by_name($insert_stmt, ':product_id', $product_id);
if (oci_execute($insert_stmt)) {
    $response['status'] = 'success';
    $response['message'] = "Product added to wishlist.";
} else {
    $e = oci_error($insert_stmt);
    $response['message'] = "Failed to add product to wishlist: " . $e['message'];
}
oci_free_statement($insert_stmt);

oci_close($conn);
echo json_encode($response);
exit;
?>