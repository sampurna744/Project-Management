<?php
session_start();
include("connection/connection.php");

try {
    // User session
    $user_id = isset($_SESSION["USER_ID"]) ? (int)$_SESSION["USER_ID"] : 0;
    $searchText = "";

    // Get trader_id from URL
    $trader_id = isset($_GET["trader_id"]) ? filter_var($_GET["trader_id"], FILTER_SANITIZE_NUMBER_INT) : null;
    if (!$trader_id || !is_numeric($trader_id)) {
        throw new Exception("Invalid or no trader ID provided.");
    }
    $trader_id = (int)$trader_id;
    error_log("trader_id: $trader_id");

    // Fetch shop details
    $shop_details = null;
    $sql = "SELECT s.shop_name AS SHOP_NAME, s.shop_profile AS SHOP_PROFILE
            FROM SHOP s
            JOIN Cleck_User u ON s.user_id = u.user_id
            WHERE s.user_id = :trader_id AND u.user_type = 'trader'";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':trader_id', $trader_id, -1, OCI_B_INT);
    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        error_log("Shop query error: " . $e['message']);
        throw new Exception("Failed to execute shop query: " . $e['message']);
    }
    if ($row = oci_fetch_assoc($stmt)) {
        $shop_details = $row; // Fixed: Correct assignment of fetched row
    }
    error_log("Shop details query returned: " . ($shop_details ? print_r($shop_details, true) : 'null'));
    oci_free_statement($stmt);

    // Validate trader_id existence
    if (!$shop_details) {
        // Check if trader_id exists in Cleck_User
        $sql = "SELECT COUNT(*) AS user_count FROM Cleck_User WHERE user_id = :trader_id AND user_type = 'trader'";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':trader_id', $trader_id);
        oci_execute($stmt);
        $user_count = oci_fetch_assoc($stmt)['USER_COUNT'];
        oci_free_statement($stmt);

        // Check if trader_id exists in SHOP
        $sql = "SELECT COUNT(*) AS shop_count FROM SHOP WHERE user_id = :trader_id";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':trader_id', $trader_id);
        oci_execute($stmt);
        $shop_count = oci_fetch_assoc($stmt)['SHOP_COUNT'];
        oci_free_statement($stmt);

        error_log("Validation: user_count=$user_count, shop_count=$shop_count for trader_id=$trader_id");
        throw new Exception("Shop not found. User exists: " . ($user_count ? 'Yes' : 'No') . ", Shop exists: " . ($shop_count ? 'Yes' : 'No'));
    }

    // Fetch trader shops for navbar dropdown
    $trader_shop = [];
    $sql = "SELECT u.user_id AS USER_ID, s.shop_name AS SHOP_NAME
            FROM Cleck_User u 
            JOIN SHOP s ON u.user_id = s.user_id 
            WHERE u.user_type = 'trader'";
    $stmt = oci_parse($conn, $sql);
    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        error_log("Trader shops query error: " . $e['message']);
        throw new Exception("Failed to execute trader shops query: " . $e['message']);
    }
    while ($row = oci_fetch_assoc($stmt)) {
        $trader_shop[] = $row;
    }
    oci_free_statement($stmt);

    // Sanitization function
    function sanitizeString($input) {
        return htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8');
    }

    // Sort variable
    $sort_by = isset($_POST["sort-by"]) ? sanitizeString($_POST["sort-by"]) : null;

    // Fetch products for the shop (up to 10)
    $products = [];
    $sql = "SELECT 
                p.product_id AS PRODUCT_ID, 
                p.product_name AS PRODUCT_NAME, 
                p.product_price AS PRODUCT_PRICE, 
                p.product_picture AS PRODUCT_PICTURE,
                COALESCE(TO_NUMBER(d.discount_percent), 0) AS DISCOUNT_PERCENT
            FROM 
                PRODUCT p
            LEFT JOIN 
                DISCOUNT d ON p.product_id = d.product_id
            WHERE 
                p.user_id = :trader_id  AND ROWNUM <= 10";
    
    // Add sorting to query
    switch ($sort_by) {
        case "alphabetically_asc":
            $sql .= " ORDER BY p.PRODUCT_NAME ASC";
            break;
        case "alphabetically_desc":
            $sql .= " ORDER BY p.PRODUCT_NAME DESC";
            break;
        case "price-low-to-high":
            $sql .= " ORDER BY p.PRODUCT_PRICE ASC";
            break;
        case "price-high-to-low":
            $sql .= " ORDER BY p.PRODUCT_PRICE DESC";
            break;
        default:
            $sql .= " ORDER BY p.PRODUCT_ID DESC";
            break;
    }

    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':trader_id', $trader_id, -1, OCI_B_INT);
    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        error_log("Products query error: " . $e['message']);
        throw new Exception("Failed to execute products query: " . $e['message']);
    }
    while ($row = oci_fetch_assoc($stmt)) {
        $products[] = $row;
    }
    error_log("Products query returned: " . count($products) . " products");
    oci_free_statement($stmt);

    oci_close($conn);

} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    $error_message = "An error occurred: " . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cleckfax Traders Hub - <?php echo !empty($shop_details) && isset($shop_details['SHOP_NAME']) ? htmlspecialchars($shop_details['SHOP_NAME']) : 'Shop'; ?></title>
    <link rel="icon" href="logo_ico.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f0f8ff;
            font-family: 'Arial', sans-serif;
        }
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
        }
        .main {
            padding: 30px;
        }
        .shop {
            display: flex;
            align-items: center;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .shop-profile {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .shop-profile img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #3273dc;
        }
        .shop-info .name {
            font-weight: bold;
            font-size: 20px;
            color: #333;
        }
        .products {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        .product-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.2s;
            position: relative;
        }
        .product-card:hover {
            transform: translateY(-5px);
        }
        .product-card img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-bottom: 1px solid #e0e0e0;
        }
        .product-card .content {
            padding: 15px;
            text-align: center;
        }
        .product-card .name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
            height: 40px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .product-card .price {
            font-size: 18px;
            font-weight: bold;
            color: #3273dc;
            margin-bottom: 10px;
        }
        .product-card .price .original {
            font-size: 14px;
            color: #aaa;
            text-decoration: line-through;
            margin-left: 10px;
        }
        .product-card .buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 10px;
        }
        .product-card .button {
            padding: 10px;
            border-radius: 5px;
            font-size: 14px;
            transition: background-color 0.2s;
        }
        .product-card .button.is-success:hover {
            background-color: #218838;
        }
        .product-card .favorite {
            position: absolute;
            top: 10px;
            right: 10px;
            color: #666;
            font-size: 20px;
            cursor: pointer;
            transition: color 0.2s;
        }
        .product-card .favorite:hover,
        .product-card .favorite.active {
            color: #ff3860;
        }
        .product-card .see-more {
            color: #3273dc;
            font-size: 14px;
            text-decoration: none;
            display: block;
        }
        .product-card .see-more:hover {
            text-decoration: underline;
        }
        .top-section {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-bottom: 1rem;
        }
        @media (max-width: 768px) {
            .products {
                grid-template-columns: repeat(2, 1fr);
            }
            .shop {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }
        @media (max-width: 480px) {
            .products {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include('navbar.php'); ?>

    <!-- Main Content -->
    <div class="main container">
        <?php if (isset($error_message)): ?>
            <p class="has-text-centered has-text-danger"><?php echo $error_message; ?></p>
        <?php endif; ?>
        <?php if (!empty($shop_details) && isset($shop_details['SHOP_NAME'])): ?>
            <div class="shop">
                <div class="shop-profile">
                    <img src="shop_profile_image/<?php echo htmlspecialchars($shop_details['SHOP_PROFILE'] ?? 'default.png'); ?>" alt="<?php echo htmlspecialchars($shop_details['SHOP_NAME']); ?>">
                    <div class="shop-info">
                        <div class="name"><?php echo htmlspecialchars($shop_details['SHOP_NAME']); ?></div>
                    </div>
                </div>
            </div>
            <h2 class="title is-4">Products</h2>
            <div class="top-section">
                <form class="sort-form" name="sort_form" id="sort_form" method="POST" action="">
                    <div class="field has-addons">
                        <label class="label" for="sort-by">Sort By:</label>
                        <div class="control">
                            <div class="select">
                                <select name="sort-by" id="sort-by">
                                    <option value="alphabetically_asc" <?php echo ($sort_by === 'alphabetically_asc') ? 'selected' : ''; ?>>Name: A to Z</option>
                                    <option value="alphabetically_desc" <?php echo ($sort_by === 'alphabetically_desc') ? 'selected' : ''; ?>>Name: Z to A</option>
                                    <option value="price-low-to-high" <?php echo ($sort_by === 'price-low-to-high') ? 'selected' : ''; ?>>Price: Low to High</option>
                                    <option value="price-high-to-low" <?php echo ($sort_by === 'price-high-to-low') ? 'selected' : ''; ?>>Price: High to Low</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="products">
                <?php if (empty($products)): ?>
                    <p class="has-text-centered">No products available for this shop.</p>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <img src="product_image/<?php echo htmlspecialchars($product['PRODUCT_PICTURE'] ?? 'default.png'); ?>" alt="<?php echo htmlspecialchars($product['PRODUCT_NAME']); ?>">
                            <a href="#" class="favorite" data-product="<?php echo htmlspecialchars($product['PRODUCT_ID']); ?>" data-user="<?php echo htmlspecialchars($user_id); ?>" data-search="<?php echo addslashes($searchText); ?>"><i class="fas fa-heart"></i></a>
                            <div class="content">
                                <div class="name"><?php echo htmlspecialchars($product['PRODUCT_NAME']); ?></div>
                                <div class="price">
                                    <?php
                                    $original_price = $product['PRODUCT_PRICE'];
                                    $discount_percent = (float)$product['DISCOUNT_PERCENT'];
                                    $discount_price = $discount_percent ? $original_price * (1 - $discount_percent / 100) : $original_price;
                                    ?>
                                    €<?php echo number_format($discount_price, 2); ?>
                                    <?php if ($discount_percent): ?>
                                        <span class="original">€<?php echo number_format($original_price, 2); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="buttons">
                                    <button class="button is-success" onclick="addToCart(<?php echo htmlspecialchars($product['PRODUCT_ID']); ?>, <?php echo htmlspecialchars($user_id); ?>, '<?php echo addslashes($searchText); ?>')">
                                        <span class="icon"><i class="fas fa-shopping-cart"></i></span>
                                        <span>Add to Cart</span>
                                    </button>
                                    <a href="product_detail.php?productId=<?php echo htmlspecialchars($product['PRODUCT_ID']); ?>" class="see-more">See More...</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="has-text-centered">Shop not found.</p>
        <?php endif; ?>
    </div>

    <?php include('footer.php'); ?>

    <!-- JavaScript -->
    <script>
        // Sort Form Submission
        document.getElementById('sort-by').addEventListener('change', function() {
            document.getElementById('sort_form').submit();
        });

        // Add to Cart Function
        function addToCart(productId, userId, searchText) {
            event.preventDefault();
            if (!userId || userId === '0') {
                alert('Please log in to add items to your cart.');
                window.location.href = 'customer_signin.php?return_url=' + encodeURIComponent(window.location.href);
                return;
            }
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

        // Wishlist Toggle Function
        function toggleWishlist(productId, userId, searchText) {
            event.preventDefault();
            if (!userId || userId === '0') {
                alert('Please log in to add items to your wishlist.');
                window.location.href = 'customer_signin.php?return_url=' + encodeURIComponent(window.location.href);
                return;
            }
            const heartIcon = event.currentTarget;
            const isActive = heartIcon.classList.contains('active');
            const url = isActive
                ? `remove_from_wishlist.php?product_id=${productId}&user_id=${userId}&searchtext=${encodeURIComponent(searchText)}`
                : `add_to_wishlist.php?product_id=${productId}&user_id=${userId}&searchtext=${encodeURIComponent(searchText)}`;
            fetch(url)
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success') {
                        heartIcon.classList.toggle('active');
                        alert(isActive ? 'Product removed from wishlist!' : 'Product added to wishlist!');
                    } else {
                        alert('Failed to update wishlist: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Wishlist Error:', error);
                    alert('An error occurred while updating wishlist: ' + error.message);
                });
        }

        // Attach Wishlist Event Listeners
        document.querySelectorAll('.favorite').forEach(icon => {
            icon.addEventListener('click', function(e) {
                e.preventDefault();
                const productId = this.getAttribute('data-product');
                const userId = this.getAttribute('data-user');
                const searchText = this.getAttribute('data-search');
                toggleWishlist(productId, userId, searchText);
            });
        });
    </script>
</body>
</html>