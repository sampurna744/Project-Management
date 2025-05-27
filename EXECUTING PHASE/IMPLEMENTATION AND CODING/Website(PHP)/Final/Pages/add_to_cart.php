<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable display of errors to prevent breaking JSON
header('Content-Type: application/json'); // Set JSON response header

// Log file for debugging
$logFile = 'add_to_cart.log';

// Helper function to log messages
function logMessage($message, $file) {
    error_log(date('Y-m-d H:i:s') . " - $message\n", 3, $file);
}

try {
    // Start session and include session validation
    require("session/session.php");
    logMessage("Session started. Session ID: " . session_id() . ", USER_ID: " . ($_SESSION["USER_ID"] ?? 'unset') . ", USER_TYPE: " . ($_SESSION["USER_TYPE"] ?? 'unset'), $logFile);

    // Get parameters
    $user_id = isset($_GET["userid"]) ? (int)$_GET["userid"] : 0;
    $product_id = isset($_GET["productid"]) ? (int)$_GET["productid"] : 0;
    $quantity = isset($_GET["quantity"]) ? (int)$_GET["quantity"] : 1;
    $search_text = isset($_GET["searchtext"]) ? trim($_GET["searchtext"]) : "";
    logMessage("Parameters - user_id: $user_id, product_id: $product_id, quantity: $quantity, search_text: $search_text", $logFile);

    // Validate user
    if (!$user_id || $user_id !== (int)$_SESSION["USER_ID"]) {
        logMessage("Error: User not logged in or invalid user ID.", $logFile);
        echo json_encode(['status' => 'error', 'message' => 'User not logged in or invalid user ID.']);
        exit;
    }

    // Include database connection
    include("connection/connection.php");
    if (!$conn) {
        logMessage("Error: Database connection failed.", $logFile);
        throw new Exception("Database connection failed.");
    }
    logMessage("Database connection established.", $logFile);

    // Get customer_id
    $sql = "SELECT customer_id FROM CUSTOMER WHERE user_id = :user_id";
    $stmt = oci_parse($conn, $sql);
    if (!$stmt) {
        $e = oci_error($conn);
        logMessage("Error preparing customer query: " . $e['message'], $logFile);
        throw new Exception("Failed to prepare customer query: " . $e['message']);
    }
    oci_bind_by_name($stmt, ':user_id', $user_id);
    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        logMessage("Error executing customer query: " . $e['message'], $logFile);
        throw new Exception("Failed to execute customer query: " . $e['message']);
    }
    $row = oci_fetch_assoc($stmt);
    $customer_id = $row ? $row['CUSTOMER_ID'] : null;
    oci_free_statement($stmt);
    logMessage("Customer ID retrieved: " . ($customer_id ?? 'null'), $logFile);

    if (!$customer_id) {
        logMessage("Error: Customer not found for user_id: $user_id", $logFile);
        echo json_encode(['status' => 'error', 'message' => 'Customer not found.']);
        exit;
    }

    // Check for existing cart in session or database
    $cart_id = isset($_SESSION['cart_id']) ? (int)$_SESSION['cart_id'] : null;
    if ($cart_id) {
        $cart_sql = "SELECT cart_id FROM CART WHERE cart_id = :cart_id AND customer_id = :customer_id 
                     AND NOT EXISTS (SELECT 1 FROM ORDER_PRODUCT op WHERE op.order_product_id = CART.order_product_id)";
        $cart_stmt = oci_parse($conn, $cart_sql);
        if (!$cart_stmt) {
            $e = oci_error($conn);
            logMessage("Error preparing cart query: " . $e['message'], $logFile);
            throw new Exception("Failed to prepare cart query: " . $e['message']);
        }
        oci_bind_by_name($cart_stmt, ':cart_id', $cart_id);
        oci_bind_by_name($cart_stmt, ':customer_id', $customer_id);
        if (!oci_execute($cart_stmt)) {
            $e = oci_error($cart_stmt);
            logMessage("Error executing cart query: " . $e['message'], $logFile);
            throw new Exception("Failed to execute cart query: " . $e['message']);
        }
        $cart_row = oci_fetch_assoc($cart_stmt);
        if (!$cart_row) {
            $cart_id = null;
            unset($_SESSION['cart_id']);
        }
        oci_free_statement($cart_stmt);
        logMessage("Session after cart check - cart_id: " . ($_SESSION['cart_id'] ?? 'unset'), $logFile);
        oci_commit($conn);
    }

    if (!$cart_id) {
        $cart_sql = "SELECT cart_id FROM CART WHERE customer_id = :customer_id 
                     AND NOT EXISTS (SELECT 1 FROM ORDER_PRODUCT op WHERE op.order_product_id = CART.order_product_id)";
        $cart_stmt = oci_parse($conn, $cart_sql);
        if (!$cart_stmt) {
            $e = oci_error($conn);
            logMessage("Error preparing cart query: " . $e['message'], $logFile);
            throw new Exception("Failed to prepare cart query: " . $e['message']);
        }
        oci_bind_by_name($cart_stmt, ':customer_id', $customer_id);
        if (!oci_execute($cart_stmt)) {
            $e = oci_error($cart_stmt);
            logMessage("Error executing cart query: " . $e['message'], $logFile);
            throw new Exception("Failed to execute cart query: " . $e['message']);
        }
        $cart_row = oci_fetch_assoc($cart_stmt);
        $cart_id = $cart_row ? $cart_row['CART_ID'] : null;
        oci_free_statement($cart_stmt);
        logMessage("Cart ID from DB: " . ($cart_id ?? 'null'), $logFile);
    }

    if (!$cart_id) {
        $cart_sql = "INSERT INTO CART (customer_id, order_product_id) 
                     VALUES (:customer_id, ORDER_PRODUCT_SEQ.NEXTVAL) RETURNING cart_id INTO :cart_id";
        $cart_stmt = oci_parse($conn, $cart_sql);
        if (!$cart_stmt) {
            $e = oci_error($conn);
            logMessage("Error preparing insert cart query: " . $e['message'], $logFile);
            throw new Exception("Failed to prepare insert cart query: " . $e['message']);
        }
        oci_bind_by_name($cart_stmt, ':customer_id', $customer_id);
        oci_bind_by_name($cart_stmt, ':cart_id', $cart_id, -1, OCI_B_INT);
        if (!oci_execute($cart_stmt)) {
            $e = oci_error($cart_stmt);
            logMessage("Error executing insert cart query: " . $e['message'], $logFile);
            throw new Exception("Failed to execute insert cart query: " . $e['message']);
        }
        oci_free_statement($cart_stmt);
        $_SESSION['cart_id'] = $cart_id;
        oci_commit($conn);
        logMessage("New cart created with ID: $cart_id, Session cart_id: " . ($_SESSION['cart_id'] ?? 'unset'), $logFile);
    }

    if ($product_id) {
        $product_sql = "SELECT p.product_price, 
                               COALESCE(TO_NUMBER(NULLIF(d.discount_percent, '')), 0) AS discount_percent 
                        FROM PRODUCT p 
                        LEFT JOIN DISCOUNT d ON p.product_id = d.product_id 
                        WHERE p.product_id = :product_id";
        $product_stmt = oci_parse($conn, $product_sql);
        if (!$product_stmt) {
            $e = oci_error($conn);
            logMessage("Error preparing product query: " . $e['message'], $logFile);
            throw new Exception("Failed to prepare product query: " . $e['message']);
        }
        oci_bind_by_name($product_stmt, ':product_id', $product_id);
        if (!oci_execute($product_stmt)) {
            $e = oci_error($product_stmt);
            logMessage("Error executing product query: " . $e['message'], $logFile);
            throw new Exception("Failed to execute product query: " . $e['message']);
        }
        $product_row = oci_fetch_assoc($product_stmt);
        $product_price = $product_row ? $product_row['PRODUCT_PRICE'] : 0;
        $discount_percent = $product_row ? $product_row['DISCOUNT_PERCENT'] : 0;
        $discounted_price = $product_price * (1 - $discount_percent / 100);
        oci_free_statement($product_stmt);
        logMessage("Product price: $product_price, Discount: $discount_percent%, Discounted price: $discounted_price", $logFile);

        if ($product_price) {
            $count_sql = "SELECT SUM(no_of_products) AS total_products 
                          FROM CART_ITEM WHERE cart_id = :cart_id";
            $count_stmt = oci_parse($conn, $count_sql);
            if (!$count_stmt) {
                $e = oci_error($conn);
                logMessage("Error preparing count query: " . $e['message'], $logFile);
                throw new Exception("Failed to prepare count query: " . $e['message']);
            }
            oci_bind_by_name($count_stmt, ':cart_id', $cart_id);
            if (!oci_execute($count_stmt)) {
                $e = oci_error($count_stmt);
                logMessage("Error executing count query: " . $e['message'], $logFile);
                throw new Exception("Failed to execute count query: " . $e['message']);
            }
            $count_row = oci_fetch_assoc($count_stmt);
            $total_products = $count_row ? (int)$count_row['TOTAL_PRODUCTS'] : 0;
            oci_free_statement($count_stmt);
            logMessage("Total products in cart: $total_products", $logFile);

            if ($total_products + $quantity <= 20) {
                $item_sql = "SELECT no_of_products FROM CART_ITEM 
                             WHERE cart_id = :cart_id AND product_id = :product_id";
                $item_stmt = oci_parse($conn, $item_sql);
                if (!$item_stmt) {
                    $e = oci_error($conn);
                    logMessage("Error preparing item query: " . $e['message'], $logFile);
                    throw new Exception("Failed to prepare item query: " . $e['message']);
                }
                oci_bind_by_name($item_stmt, ':cart_id', $cart_id);
                oci_bind_by_name($item_stmt, ':product_id', $product_id);
                if (!oci_execute($item_stmt)) {
                    $e = oci_error($item_stmt);
                    logMessage("Error executing item query: " . $e['message'], $logFile);
                    throw new Exception("Failed to execute item query: " . $e['message']);
                }
                $item_row = oci_fetch_assoc($item_stmt);
                $existing_quantity = $item_row ? (int)$item_row['NO_OF_PRODUCTS'] : 0;
                oci_free_statement($item_stmt);
                logMessage("Existing quantity of product $product_id in cart: $existing_quantity", $logFile);

                if ($existing_quantity) {
                    $new_quantity = $existing_quantity + $quantity;
                    $update_sql = "UPDATE CART_ITEM 
                                   SET no_of_products = :no_of_products, product_price = :product_price 
                                   WHERE cart_id = :cart_id AND product_id = :product_id";
                    $update_stmt = oci_parse($conn, $update_sql);
                    if (!$update_stmt) {
                        $e = oci_error($conn);
                        logMessage("Error preparing update query: " . $e['message'], $logFile);
                        throw new Exception("Failed to prepare update query: " . $e['message']);
                    }
                    oci_bind_by_name($update_stmt, ':no_of_products', $new_quantity);
                    oci_bind_by_name($update_stmt, ':product_price', $discounted_price);
                    oci_bind_by_name($update_stmt, ':cart_id', $cart_id);
                    oci_bind_by_name($update_stmt, ':product_id', $product_id);
                    if (!oci_execute($update_stmt)) {
                        $e = oci_error($update_stmt);
                        logMessage("Error executing update query: " . $e['message'], $logFile);
                        throw new Exception("Failed to execute update query: " . $e['message']);
                    }
                    oci_commit($conn);
                    logMessage("Updated quantity of product $product_id to $new_quantity", $logFile);
                    echo json_encode(['status' => 'success', 'message' => 'Product quantity updated in cart.', 'cart_id' => $cart_id]);
                } else {
                    $insert_sql = "INSERT INTO CART_ITEM (cart_id, product_id, no_of_products, product_price) 
                                   VALUES (:cart_id, :product_id, :no_of_products, :product_price)";
                    $insert_stmt = oci_parse($conn, $insert_sql);
                    if (!$insert_stmt) {
                        $e = oci_error($conn);
                        logMessage("Error preparing insert item query: " . $e['message'], $logFile);
                        throw new Exception("Failed to prepare insert item query: " . $e['message']);
                    }
                    oci_bind_by_name($insert_stmt, ':cart_id', $cart_id);
                    oci_bind_by_name($insert_stmt, ':product_id', $product_id);
                    oci_bind_by_name($insert_stmt, ':no_of_products', $quantity);
                    oci_bind_by_name($insert_stmt, ':product_price', $discounted_price);
                    if (!oci_execute($insert_stmt)) {
                        $e = oci_error($insert_stmt);
                        logMessage("Error executing insert item query: " . $e['message'], $logFile);
                        throw new Exception("Failed to execute insert item query: " . $e['message']);
                    }
                    oci_commit($conn);
                    logMessage("Inserted product $product_id into cart with quantity $quantity", $logFile);
                    echo json_encode(['status' => 'success', 'message' => 'Product added to cart.', 'cart_id' => $cart_id]);
                }
            } else {
                logMessage("Error: Cart is full.", $logFile);
                echo json_encode(['status' => 'error', 'message' => 'Cart is full.']);
            }
        } else {
            logMessage("Error: Product not found.", $logFile);
            echo json_encode(['status' => 'error', 'message' => 'Product not found.']);
        }
    } else {
        logMessage("Error: Invalid product ID.", $logFile);
        echo json_encode(['status' => 'error', 'message' => 'Invalid product ID.']);
    }
} catch (Exception $e) {
    logMessage("Exception: " . $e->getMessage(), $logFile);
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
} finally {
    if (isset($conn) && is_resource($conn)) {
        oci_close($conn);
        logMessage("Database connection closed.", $logFile);
    }
}
exit;
?>