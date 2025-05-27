<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION["USER_ID"]) || empty($_SESSION["USER_ID"])) {
    header("Location: customer_signin.php?return_url=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$user_id = $_SESSION["USER_ID"];

// Include the database connection
include("connection/connection.php");

// Prepare the SQL statement to get CUSTOMER_ID from CUSTOMER table
$sql = "SELECT CUSTOMER_ID FROM CUSTOMER WHERE USER_ID = :user_id";
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':user_id', $user_id);
oci_execute($stmt);
$row = oci_fetch_assoc($stmt);

if ($row) {
    $customer_id = $row['CUSTOMER_ID'];
    $wishlist_id = null;
    $results = [];

    // Check if the customer has an existing wishlist
    $sqlWishlistCheck = "SELECT WISHLIST_ID FROM WISHLIST WHERE CUSTOMER_ID = :customer_id";
    $stmtWishlistCheck = oci_parse($conn, $sqlWishlistCheck);
    oci_bind_by_name($stmtWishlistCheck, ':customer_id', $customer_id);
    oci_execute($stmtWishlistCheck);
    $rowWishlistCheck = oci_fetch_assoc($stmtWishlistCheck);

    if ($rowWishlistCheck) {
        $wishlist_id = $rowWishlistCheck['WISHLIST_ID'];
        // Prepare the SQL statement to fetch wishlist items with product details
        $sql = "SELECT p.PRODUCT_ID, p.PRODUCT_NAME, p.PRODUCT_PRICE, p.STOCK_AVAILABLE, p.PRODUCT_PICTURE
                FROM PRODUCT p
                INNER JOIN WISHLIST_ITEM wi ON p.PRODUCT_ID = wi.PRODUCT_ID
                WHERE wi.WISHLIST_ID = :wishlist_id";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':wishlist_id', $wishlist_id);
        oci_execute($stmt);

        while ($row = oci_fetch_assoc($stmt)) {
            $results[] = $row;
        }
        oci_free_statement($stmt);
    }
    oci_free_statement($stmtWishlistCheck);
}
oci_close($conn);

// Set searchtext to empty string if not provided
$searchtext = "";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wishlist</title>
    <link rel="icon" href="logo_ico.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        html, body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        .container_cat {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .empty-container {
            flex-grow: 1;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .content {
            flex-grow: 1;
            padding: 2rem;
        }
        .empty-wishlist-message {
            font-size: 1.5rem;
            color: #363636;
            text-align: center;
        }
        .product-list {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            justify-content: center;
        }
        .product {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            width: 250px;
            transition: transform 0.3s ease;
            cursor: pointer;
        }
        .product:hover {
            transform: translateY(-5px);
        }
        .product img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }
        .product-details {
            padding: 1rem;
        }
        .product-details h2 {
            font-size: 1.1rem;
            color: #363636;
            margin-bottom: 0.5rem;
        }
        .availability .in-stock {
            color: #28a745;
        }
        .availability .out-of-stock {
            color: #dc3545;
        }
        .product-details p {
            margin: 0.25rem 0;
            color: #4a4a4a;
        }
        .product-actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            padding: 0 1rem 1rem 1rem;
        }
        .add-to-cart-button, .remove-button {
            display: block;
            text-align: center;
            padding: 0.5rem;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }
        .add-to-cart-button {
            background-color: #28a745;
            color: white;
        }
        .add-to-cart-button:hover {
            background-color: #218838;
        }
        .remove-button {
            background-color: #dc3545;
            color: white;
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
        }
        .remove-button:hover {
            background-color: #c82333;
        }
    </style>
</head>
<body>
    <?php include("navbar.php"); ?>

    <div class="container_cat">
        <div class="<?php echo empty($results) ? 'empty-container' : 'content'; ?>">
            <?php if (empty($results)) { ?>
                <div class="empty-wishlist-message">Your Wishlist is Empty!</div>
            <?php } else { ?>
                <section id="wishlist" class="section">
                    <h1 class="title has-text-centered">My Wishlist</h1>
                    <div class="product-list">
                        <?php foreach ($results as $row): ?>
                            <div class="product" onclick="redirectToProductPage('<?php echo htmlspecialchars($row['PRODUCT_ID'], ENT_QUOTES, 'UTF-8'); ?>')">
                                <img src="product_image/<?php echo htmlspecialchars($row['PRODUCT_PICTURE'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($row['PRODUCT_NAME'], ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="product-details">
                                    <h2><?php echo htmlspecialchars($row['PRODUCT_NAME'], ENT_QUOTES, 'UTF-8'); ?></h2>
                                    <p class="availability">
                                        Availability: 
                                        <span class="<?php echo $row['STOCK_AVAILABLE'] == 'no' ? 'out-of-stock' : 'in-stock'; ?>">
                                            <?php echo $row['STOCK_AVAILABLE'] == 'no' ? 'Out of stock' : 'In stock'; ?>
                                        </span>
                                    </p>
                                    <?php
                                    $product_price = $row['PRODUCT_PRICE'];
                                    $discount_percent = isset($row['DISCOUNT_PERCENT']) ? $row['DISCOUNT_PERCENT'] : 0;
                                    $discounted_price = $product_price - ($product_price * ($discount_percent / 100));
                                    ?>
                                    <p>Price: €<?php echo number_format($discounted_price, 2); ?></p>
                                </div>
                                <div class="product-actions">
                                    <button class="add-to-cart-button" onclick="addToCart(<?php echo htmlspecialchars($row['PRODUCT_ID'], ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars($user_id, ENT_QUOTES, 'UTF-8'); ?>, '<?php echo htmlspecialchars($searchtext, ENT_QUOTES, 'UTF-8'); ?>'); event.stopPropagation();">
                                        <span class="icon"><i class="fas fa-shopping-cart"></i></span>
                                        <span>Add to Cart</span>
                                    </button>
                                    <a href="delete_wishlist_item.php?wishlist_id=<?php echo htmlspecialchars($wishlist_id, ENT_QUOTES, 'UTF-8'); ?>&product_id=<?php echo htmlspecialchars($row['PRODUCT_ID'], ENT_QUOTES, 'UTF-8'); ?>" class="remove-button">Remove</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php } ?>
        </div>
        <?php include("footer.php"); ?>
    </div>

    <script>
        function redirectToProductPage(productId) {
            window.location.href = "product_detail.php?productId=" + encodeURIComponent(productId);
        }

        function addToCart(productId, userId, searchText) {
            fetch(`add_to_cart.php?productid=${productId}&userid=${userId}&searchtext=${encodeURIComponent(searchText)}`)
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success') {
                        alert('Product added to cart!');
                        if (confirm('Do you want to view your cart?')) {
                            window.location.href = 'cart.php';
                        }
                    } else {
                        alert('Failed to add product to cart: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Cart Error:', error);
                    alert('An error occurred while adding to cart: ' + error.message);
                });
        }
    </script>
</body>
</html>