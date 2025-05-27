<?php
session_start();
error_log("Cart: Session started at " . date('Y-m-d H:i:s') . ", Session ID: " . session_id() . ", USER_ID: " . ($_SESSION['USER_ID'] ?? 'unset') . ", USER_TYPE: " . ($_SESSION['USER_TYPE'] ?? 'unset'), 3, 'debug.log');

if (!isset($_SESSION['USER_ID']) || empty($_SESSION['USER_ID']) || !isset($_SESSION['USER_TYPE']) || $_SESSION['USER_TYPE'] !== 'customer') {
    error_log("Cart: Invalid USER_ID or USER_TYPE, redirecting to signin at " . date('Y-m-d H:i:s'), 3, 'debug.log');
    header("Location: customer_signin.php?return_url=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

require("session/session.php");
$user_id = $_SESSION["USER_ID"];
error_log("Cart: After session.php, USER_ID: " . $user_id . ", USER_TYPE: " . ($_SESSION['USER_TYPE'] ?? 'unset') . " at " . date('Y-m-d H:i:s'), 3, 'debug.log');

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

// Get customer_id
$sql = "SELECT customer_id FROM CUSTOMER WHERE user_id = :user_id";
$stmt = executeQuery($conn, $sql, [':user_id' => $user_id]);
$row = oci_fetch_assoc($stmt);
$customer_id = $row ? $row['CUSTOMER_ID'] : null;
oci_free_statement($stmt);

if (!$customer_id) {
    error_log("Cart: No customer_id for USER_ID: $user_id, redirecting to signin at " . date('Y-m-d H:i:s'), 3, 'debug.log');
    header("Location: customer_signin.php?return_url=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Initialize variables
$cart_id = null;
$results = [];
$total_products = 0;
$total_amount = 0;
$discount_amount = 0;
$actual_price = 0;

// Get cart_id from session or database
if (isset($_SESSION['cart_id'])) {
    $cart_id = (int)$_SESSION['cart_id'];
    $sql = "SELECT cart_id FROM CART WHERE cart_id = :cart_id AND customer_id = :customer_id 
            AND NOT EXISTS (SELECT 1 FROM ORDER_PRODUCT op WHERE op.order_product_id = CART.order_product_id)";
    $stmt = executeQuery($conn, $sql, [':cart_id' => $cart_id, ':customer_id' => $customer_id]);
    $row = oci_fetch_assoc($stmt);
    if (!$row) {
        error_log("Cart: Invalid session cart_id $cart_id, unsetting at " . date('Y-m-d H:i:s'), 3, 'debug.log');
        unset($_SESSION['cart_id']);
        $cart_id = null;
    }
    oci_free_statement($stmt);
}

if (!$cart_id) {
    $sql = "SELECT cart_id FROM CART WHERE customer_id = :customer_id 
            AND NOT EXISTS (SELECT 1 FROM ORDER_PRODUCT op WHERE op.order_product_id = CART.order_product_id)";
    $stmt = executeQuery($conn, $sql, [':customer_id' => $customer_id]);
    $row = oci_fetch_assoc($stmt);
    $cart_id = $row ? $row['CART_ID'] : null;
    error_log("Cart ID from DB: $cart_id, Session cart_id: " . ($_SESSION['cart_id'] ?? 'unset') . " at " . date('Y-m-d H:i:s'), 3, 'debug.log');
    $_SESSION['cart_id'] = $cart_id; // Ensure session is updated
    oci_free_statement($stmt);
}

if (!$cart_id) {
    $sql = "INSERT INTO CART (customer_id, order_product_id) VALUES (:customer_id, ORDER_PRODUCT_SEQ.NEXTVAL) RETURNING cart_id INTO :cart_id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':customer_id', $customer_id);
    oci_bind_by_name($stmt, ':cart_id', $cart_id, -1, OCI_B_INT);
    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        error_log("Cart Creation Error: " . $e['message'] . " for SQL: " . $sql . " at " . date('Y-m-d H:i:s'), 3, 'debug.log');
        die(htmlentities($e['message']));
    }
    oci_free_statement($stmt);
    $_SESSION['cart_id'] = $cart_id;
    oci_commit($conn);
    error_log("Cart: Created new cart with ID: $cart_id at " . date('Y-m-d H:i:s'), 3, 'debug.log');
}

// Fetch cart items
if ($cart_id) {
    error_log("Fetching cart items for cart_id: $cart_id at " . date('Y-m-d H:i:s'), 3, 'debug.log');
    $sql = "SELECT ci.cart_id, ci.product_id, ci.no_of_products, ci.product_price, 
                   p.product_name, p.product_picture, p.product_price AS original_price
            FROM CART_ITEM ci
            JOIN PRODUCT p ON ci.product_id = p.product_id
            WHERE ci.cart_id = :cart_id";
    $stmt = executeQuery($conn, $sql, [':cart_id' => $cart_id]);
    $row_count = 0;
    while ($row = oci_fetch_assoc($stmt)) {
        $results[] = $row;
        $total_products += $row['NO_OF_PRODUCTS'];
        $total_amount += $row['NO_OF_PRODUCTS'] * $row['PRODUCT_PRICE'];
        $actual_price += $row['NO_OF_PRODUCTS'] * $row['ORIGINAL_PRICE'];
        $discount_amount += $row['NO_OF_PRODUCTS'] * ($row['ORIGINAL_PRICE'] - $row['PRODUCT_PRICE']);
        $row_count++;
    }
    error_log("Fetched $row_count cart items for cart_id: $cart_id at " . date('Y-m-d H:i:s'), 3, 'debug.log');
    oci_free_statement($stmt);
} else {
    error_log("No cart_id found, creating new cart at " . date('Y-m-d H:i:s'), 3, 'debug.log');
    $sql = "INSERT INTO CART (customer_id, order_product_id) VALUES (:customer_id, ORDER_PRODUCT_SEQ.NEXTVAL) RETURNING cart_id INTO :cart_id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':customer_id', $customer_id);
    oci_bind_by_name($stmt, ':cart_id', $cart_id, -1, OCI_B_INT);
    oci_execute($stmt);
    oci_free_statement($stmt);
    $_SESSION['cart_id'] = $cart_id;
    oci_commit($conn);
    error_log("Cart: Re-created new cart with ID: $cart_id at " . date('Y-m-d H:i:s'), 3, 'debug.log');
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if (is_resource($conn)) {
    oci_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart</title>
    <link rel="icon" href="logo_ico.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="footer.css">
    <link rel="stylesheet" href="cart.css">
    <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css" />
    <style>
        body, html { margin: 0; padding: 0; height: 100%; }
        .container_cat { display: flex; flex-direction: column; min-height: 100vh; }
        .content { flex-grow: 1; display: flex; justify-content: center; align-items: center; }
        .empty-cart-message { font-size: 24px; color: #333; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 18px; 
                background-color: #f9f9f9; border-radius: 10px; overflow: hidden; 
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); }
        thead { background-color: #007bff; color: white; }
        th, td { padding: 12px 15px; text-align: center; }
        tbody tr { border-bottom: 1px solid #ddd; }
        .decrement, .increment { padding: 5px 10px; border: none; color: white; 
                                 cursor: pointer; border-radius: 5px; }
        .decrement { background-color: #dc3545; }
        .increment { background-color: #007bff; }
        .delete { padding: 8px 12px; border: 1px solid #007bff; background-color: transparent; 
                  color: black; border-radius: 5px; cursor: pointer; }
        .checkout { padding: 10px; background-color: #28a745; color: white; border: none; 
                    border-radius: 5px; cursor: pointer; }
        .checkout:disabled { background-color: #6c757d; cursor: not-allowed; }
        .summary-table {
            width: 100%;
            max-width: 400px;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 18px;
            background-color: #f9f9f9;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .summary-table thead {
            background-color: #007bff;
            color: white;
        }
        .summary-table th, .summary-table td {
            padding: 12px 15px;
            text-align: left;
        }
        .summary-table tbody tr {
            border-bottom: 1px solid #ddd;
        }
        .summary-table tbody tr:last-child {
            border-bottom: none;
        }
        .cart-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
            align-items: flex-start;
        }
        .cart-table-wrapper {
            flex: 1 1 auto;
            max-width: 800px;
        }
        .summary-section {
            order: 1;
            flex: 0 0 auto;
        }
    </style>
</head>
<body>
    <?php include("navbar.php"); ?>
    <div class="container_cat">
        <div class="content">
            <?php if (empty($results)) { ?>
                <div class="empty-cart-message">Your Cart is Empty!</div>
            <?php } else { ?>
                <section class="cart-section">
                    <h3>Cart</h3>
                    <div class="cart-container">
                        <div class="cart-table-wrapper">
                            <form method="POST" action="check_out.php" id="checkout-form">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Select</th>
                                            <th>Image</th>
                                            <th>Product Name</th>
                                            <th>Quantity</th>
                                            <th>Price</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($results as $row): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="selected_products[]" 
                                                           value="<?php echo $row['PRODUCT_ID']; ?>" 
                                                           class="product-checkbox"
                                                           data-quantity="<?php echo $row['NO_OF_PRODUCTS']; ?>"
                                                           data-price="<?php echo $row['PRODUCT_PRICE']; ?>"
                                                           data-original-price="<?php echo $row['ORIGINAL_PRICE']; ?>">
                                                </td>
                                                <td><img src="product_image/<?php echo htmlspecialchars($row['PRODUCT_PICTURE']); ?>" alt="<?php echo htmlspecialchars($row['PRODUCT_NAME']); ?>" style="max-width: 50px; border-radius: 5px;"></td>
                                                <td><?php echo htmlspecialchars($row['PRODUCT_NAME']); ?></td>
                                                <td>
                                                    <form method="POST" action="add_qty_to_cart.php">
                                                        <input type="hidden" name="product_id" value="<?php echo $row['PRODUCT_ID']; ?>">
                                                        <input type="hidden" name="cart_id" value="<?php echo $cart_id; ?>">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                        <button type="submit" name="action" value="decrease" class="decrement">-</button>
                                                        <span><?php echo $row['NO_OF_PRODUCTS']; ?></span>
                                                        <button type="submit" name="action" value="increase" class="increment" <?php echo $total_products >= 20 ? 'disabled' : ''; ?>>+</button>
                                                    </form>
                                                </td>
                                                <td>€<?php echo number_format($row['PRODUCT_PRICE'], 2); ?></td>
                                                <td>
                                                    <form method="POST" action="delete_cart_item.php">
                                                        <input type="hidden" name="cart_id" value="<?php echo $cart_id; ?>">
                                                        <input type="hidden" name="product_id" value="<?php echo $row['PRODUCT_ID']; ?>">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                        <button type="submit" class="delete">Remove</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </form>
                        </div>
                        <section class="summary-section">
                            <table class="summary-table">
                                <thead>
                                    <tr>
                                        <th colspan="2">Summary</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Number of items</td>
                                        <td><span id="selected-items">0</span></td>
                                    </tr>
                                    <tr>
                                        <td>Total price</td>
                                        <td>€<span id="selected-total-price">0.00</span></td>
                                    </tr>
                                    <tr>
                                        <td>Discount</td>
                                        <td>€<span id="selected-discount">0.00</span></td>
                                    </tr>
                                    <tr>
                                        <td>Final total</td>
                                        <td>€<span id="selected-final-total">0.00</span></td>
                                    </tr>
                                </tbody>
                            </table>
                            <form method="POST" action="slot_time.php" id="checkout-form">
                                <input type="hidden" name="customerid" value="<?php echo $customer_id; ?>">
                                <input type="hidden" name="cartid" value="<?php echo $cart_id; ?>">
                                <input type="hidden" name="number_product" id="number-product" value="0">
                                <input type="hidden" name="total_price" id="total-price" value="0">
                                <input type="hidden" name="discount" id="discount" value="0">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <button type="submit" name="checkout" class="checkout" id="checkout-button" disabled>Checkout</button>
                            </form>
                        </section>
                    </div>
                </section>
            <?php } ?>
        </div>
        <?php include("footer.php"); ?>
    </div>
    <script src="js/script.js"></script>
    <script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const checkoutForm = document.getElementById('checkout-form');
            const checkboxes = document.querySelectorAll('.product-checkbox');
            const checkoutButton = document.getElementById('checkout-button');
            const selectedItemsSpan = document.getElementById('selected-items');
            const totalPriceSpan = document.getElementById('selected-total-price');
            const discountSpan = document.getElementById('selected-discount');
            const finalTotalSpan = document.getElementById('selected-final-total');
            const numberProductInput = document.getElementById('number-product');
            const totalPriceInput = document.getElementById('total-price');
            const discountInput = document.getElementById('discount');

            function updateSummary() {
                let selectedItems = 0;
                let totalPrice = 0;
                let discount = 0;
                let finalTotal = 0;

                checkboxes.forEach(checkbox => {
                    if (checkbox.checked) {
                        const quantity = parseInt(checkbox.dataset.quantity);
                        const price = parseFloat(checkbox.dataset.price);
                        const originalPrice = parseFloat(checkbox.dataset.originalPrice);

                        selectedItems += quantity;
                        totalPrice += quantity * originalPrice;
                        finalTotal += quantity * price;
                        discount += quantity * (originalPrice - price);
                    }
                });

                selectedItemsSpan.textContent = selectedItems;
                totalPriceSpan.textContent = totalPrice.toFixed(2);
                discountSpan.textContent = discount.toFixed(2);
                finalTotalSpan.textContent = finalTotal.toFixed(2);

                numberProductInput.value = selectedItems;
                totalPriceInput.value = finalTotal;
                
                discountInput.value = discount;

                checkoutButton.disabled = selectedItems === 0;
            }

            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSummary);
            });

            checkoutForm.addEventListener('submit', function(e) {
                const checked = document.querySelectorAll('input[name="selected_products[]"]:checked');
                if (checked.length === 0) {
                    alert('Please select at least one product to checkout.');
                    e.preventDefault();
                }
            });

            updateSummary();
        });
    </script>
</body>
</html>