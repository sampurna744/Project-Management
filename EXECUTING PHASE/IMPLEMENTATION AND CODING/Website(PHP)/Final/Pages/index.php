<?php
session_start();
include("connection/connection.php");

try {
    // Update product stock
    $sql = "UPDATE PRODUCT SET STOCK_AVAILABLE = 'no', IS_DISABLED = 0 WHERE PRODUCT_QUANTITY < 1";
    $stmt = oci_parse($conn, $sql);
    if (!$stmt) {
        $e = oci_error($conn);
        throw new Exception("Failed to prepare statement: " . $e['message']);
    }
    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        throw new Exception("Failed to execute statement: " . $e['message']);
    }
    oci_free_statement($stmt);

    // Fetch categories for Top Categories section
    $categoryArray = [];
    $sql = "SELECT CATEGORY_ID, CATEGORY_TYPE, CATEGORY_IMAGE FROM PRODUCT_CATEGORY";
    $result = oci_parse($conn, $sql);
    oci_execute($result);
    while ($row = oci_fetch_assoc($result)) {
        $categoryArray[] = $row;
    }
    oci_free_statement($result);

    // User session
    $user_id = isset($_SESSION["USER_ID"]) ? $_SESSION["USER_ID"] : 0;
    $searchText = "";

    // Fetch reviews for logged-in users
    $reviews = [];
    if ($user_id > 0) {
        $selectReviewSql = "SELECT REVIEW_ID, PRODUCT_ID FROM REVIEW WHERE REVIEW_PROCIDED = 0 AND USER_ID = :customerId";
        $selectReviewStmt = oci_parse($conn, $selectReviewSql);
        oci_bind_by_name($selectReviewStmt, ':customerId', $user_id);
        if (oci_execute($selectReviewStmt)) {
            while ($row = oci_fetch_assoc($selectReviewStmt)) {
                $productId = $row['PRODUCT_ID'];
                $selectProductSql = "SELECT PRODUCT_ID, PRODUCT_NAME, PRODUCT_PICTURE FROM PRODUCT WHERE PRODUCT_ID = :productId AND IS_DISABLED=1 AND ADMIN_VERIFIED=1";
                $selectProductStmt = oci_parse($conn, $selectProductSql);
                oci_bind_by_name($selectProductStmt, ':productId', $productId);
                if (oci_execute($selectProductStmt)) {
                    $productDetails = oci_fetch_assoc($selectProductStmt);
                    $reviews[] = [
                        'REVIEW_ID' => $row['REVIEW_ID'],
                        'PRODUCT_ID' => $productId,
                        'PRODUCT_NAME' => $productDetails['PRODUCT_NAME'],
                        'PRODUCT_PICTURE' => $productDetails['PRODUCT_PICTURE']
                    ];
                }
                oci_free_statement($selectProductStmt);
            }
        }
        oci_free_statement($selectReviewStmt);
    }

    // Handle review submission
    if (isset($_POST["review_submit"])) {
        function sanitizeInput($data) {
            $data = trim($data);
            $data = stripslashes($data);
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
            $data = preg_replace("/[^a-zA-Z0-9\-_.,?!()'\"\s]/", "", $data);
            return $data;
        }
        $submittedRating = sanitizeInput($_POST["rating"]);
        $submittedReview = sanitizeInput($_POST["review"]);
        $reviewId = sanitizeInput($_POST["review_id"]);
        $updateReviewSql = "UPDATE REVIEW SET REVIEW_SCORE = :rating, FEEDBACK = :feedback, REVIEW_PROCIDED = 1, REVIEW_DATE = CURRENT_DATE WHERE REVIEW_ID = :reviewId";
        $updateReviewStmt = oci_parse($conn, $updateReviewSql);
        oci_bind_by_name($updateReviewStmt, ':rating', $submittedRating);
        oci_bind_by_name($updateReviewStmt, ':feedback', $submittedReview);
        oci_bind_by_name($updateReviewStmt, ':reviewId', $reviewId);
        if (oci_execute($updateReviewStmt)) {
            header("Location: {$_SERVER['PHP_SELF']}");
            exit();
        }
        oci_free_statement($updateReviewStmt);
    }

    // Fetch products with reviews and discounts
    $products_review = [];
    $sql = "SELECT 
                p.PRODUCT_ID, 
                p.PRODUCT_NAME, 
                p.PRODUCT_PRICE, 
                p.PRODUCT_PICTURE, 
                AVG(r.REVIEW_SCORE) AS AVG_REVIEW_SCORE,
                COUNT(r.REVIEW_SCORE) AS TOTAL_REVIEWS,
                COALESCE(d.DISCOUNT_PERCENT, '') AS DISCOUNT_PERCENT
            FROM 
                product p
            LEFT JOIN 
                review r ON p.PRODUCT_ID = r.PRODUCT_ID
            LEFT JOIN 
                discount d ON p.PRODUCT_ID = d.PRODUCT_ID
            WHERE 
                p.IS_DISABLED = 1 AND ADMIN_VERIFIED = 1
            GROUP BY 
                p.PRODUCT_ID, p.PRODUCT_NAME, p.PRODUCT_PRICE, p.PRODUCT_PICTURE, d.DISCOUNT_PERCENT";
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    while ($row = oci_fetch_assoc($stmt)) {
        $products_review[] = $row;
    }
    oci_free_statement($stmt);

    // Sort products by AVG_REVIEW_SCORE and select top 5 for Bestsellers
    usort($products_review, function($a, $b) {
        return $b['AVG_REVIEW_SCORE'] <=> $a['AVG_REVIEW_SCORE'];
    });
    $selected_indices = array_slice(array_keys($products_review), 0, min(5, count($products_review)));

    // Select top 4 for Build Your Basket (different from Bestsellers)
    $build_basket_indices = array_slice(array_keys($products_review), 0, min(4, count($products_review)));

    // Fetch trader information
    $trader_shop = [];
    $sql = "SELECT 
                u.USER_ID,
                s.SHOP_NAME, 
                u.FIRST_NAME || ' ' || u.LAST_NAME AS TRADER_NAME,
                u.USER_PROFILE_PICTURE,
                s.SHOP_DESCRIPTION
            FROM 
                CLECK_USER u 
            JOIN 
                SHOP s ON u.USER_ID = s.USER_ID 
            WHERE 
                u.USER_TYPE = 'trader'";
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    while ($row = oci_fetch_assoc($stmt)) {
        $description = $row['SHOP_DESCRIPTION'];
        $words = explode(' ', trim($description));
        $row['SHOP_DESCRIPTION'] = implode(' ', array_slice($words, 0, 10));
        $trader_shop[] = $row;
    }
    oci_free_statement($stmt);

} catch (Exception $e) {
    error_log($e->getMessage(), 3, 'error.log');
    $error_message = "An error occurred while processing data.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cleckfax Traders</title>
    <link rel="icon" href="logo_ico.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f0f8ff;
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
        }

        /* Hero Section */
        .slider-hero {
            background: url('1111.jpg') center center no-repeat;
            background-size: cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .slider-hero .hero-body {
            padding: 2rem;
        }
        .slider-hero .title, .slider-hero .subtitle {
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        .slider-hero .button.is-light {
            border: 2px solid #fff;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .slider-hero .button.is-light:hover {
            background-color: #fff;
            color: #3273dc;
        }

        /* Top Categories Section */
        .section {
            padding: 3rem 1.5rem;
        }
        .title.has-text-centered {
            font-size: 2rem;
            margin-bottom: 2rem;
            color: #363636;
        }
        .circle-img {
            width: 128px;
            height: 128px;
            object-fit: cover;
            border-radius: 50%;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .circle-img:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
        }
        .columns.is-multiline.is-centered .column.is-narrow {
            padding: 1rem;
        }
        .columns.is-multiline.is-centered .column.is-narrow p.mt-2 a {
            color: #4a4a4a;
            font-weight: 500;
            text-decoration: none;
        }
        .columns.is-multiline.is-centered .column.is-narrow p.mt-2 a:hover {
            color: #3273dc;
        }

        /* Pricing Styling */
        .price-container {
            display: inline-flex;
            align-items: baseline;
            gap: 0.5rem;
        }
        .price-original {
            font-size: 0.85rem;
            color: #b5b5b5;
            text-decoration: line-through;
        }
        .discount-percent {
            color: #ff0000;
            font-size: 0.9rem;
        }

        /* Bestsellers and Build Your Basket Sections */
        .card {
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .card-image .image.is-4by3 img {
            object-fit: cover;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }
        .card-content {
            padding: 1.5rem;
        }
        .card-content .title.is-6 {
            font-size: 1.1rem;
            color: #363636;
            margin-bottom: 0.25rem;
            text-align: left;
        }
        .card-content .subtitle.is-7 {
            font-size: 0.9rem;
            color: #4a4a4a;
            margin-bottom: 0.5rem;
            text-align: left;
        }
        .content .icon-text .icon {
            margin-right: 0.25rem;
        }
        .product-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        .product-actions .button {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        .product-actions .icon-btn {
            background: none;
            border: none;
            color: #4a4a4a;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0.5rem;
            transition: transform 0.2s ease, color 0.2s ease;
        }
        .product-actions .icon-btn:hover {
            transform: scale(1.2);
            color: #3273dc;
        }
        .product-actions .heart-icon.active {
            color: #ff3860;
        }

        /* Unlock Local Community Offer Section */
        .has-background-light {
            background-color: #f5f5f5;
        }
        .columns.is-vcentered {
            align-items: center;
        }
        .columns.is-vcentered .column.is-half {
            padding: 2rem;
        }
        .columns.is-vcentered .column.is-half .title {
            font-size: 1.8rem;
            color: #363636;
        }
        .columns.is-vcentered .column.is-half .button.is-primary.is-large {
            padding: 1rem 2rem;
            font-size: 1.2rem;
        }
        .columns.is-vcentered .column.is-half .image img {
            border-radius: 8px;
            max-height: 300px;
            object-fit: cover;
        }

        /* Meet Our Traders Section */
        .our-traders {
            background-color: #fff;
        }
        .traders-grid {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
        }
        .trader-card {
            background-color: #f3f4f6;
            text-align: center;
            padding: 20px;
            border-radius: 8px;
            width: 18%;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .trader-card:hover {
            transform: translateY(-5px);
        }
        .trader-card .image-placeholder {
            background-color: #e5e7eb;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            overflow: hidden;
            border-radius: 8px;
        }
        .trader-card .image-placeholder img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }
        .trader-card h3 {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #363636;
        }
        .trader-card p {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .trader-card .social-icons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }
        .trader-card .social-icons a {
            color: #6b7280;
            font-size: 1.2rem;
            transition: color 0.3s ease;
        }
        .trader-card .social-icons a:hover {
            color: #3273dc;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .traders-grid {
                flex-wrap: wrap;
            }
            .trader-card {
                width: 30%;
            }
            .columns.is-multiline.is-centered .column.is-one-fifth {
                width: 33.33%;
            }
        }
        @media (max-width: 768px) {
            .traders-grid {
                flex-direction: column;
                align-items: center;
            }
            .trader-card {
                width: 100%;
                max-width: 400px;
            }
            .columns.is-multiline.is-centered .column.is-one-fifth {
                width: 50%;
            }
            .slider-hero .title.is-1 {
                font-size: 2rem;
            }
            .slider-hero .subtitle {
                font-size: 1rem;
            }
            .columns.is-vcentered .column.is-half {
                text-align: center;
            }
            .columns.is-vcentered .column.is-half .image img {
                max-height: 200px;
            }
        }
        @media (max-width: 480px) {
            .columns.is-multiline.is-centered .column.is-one-fifth {
                width: 100%;
            }
            .columns.is-multiline.is-centered .column.is-narrow {
                width: 50%;
            }
            .circle-img {
                width: 100px;
                height: 100px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar Section -->
    <?php include('navbar.php'); ?>

    <!-- Close the connection AFTER including navbar.php -->
    <?php if (is_resource($conn)) oci_close($conn); ?>

    <!-- Hero Section -->
    <section class="hero is-fullheight is-primary slider-hero">
        <div class="hero-body">
            <div class="container has-text-left" style="margin-left: -280px";>
                <h1 class="title is-1 has-text-white">
                    FRESH - SUPPORT YOUR LOCAL TRADERS
                </h1>
                <p class="subtitle has-text-white">
                    Order by Tuesday midnight for pickup Wed-Fri
                </p>
                <a class="button is-light is-large" href="search_page.php?shop=0&value=">Shop Now</a>
            </div>
        </div>
    </section>

    <!-- Top Categories Section -->
    <section class="section">
        <h2 class="title has-text-centered">TOP CATEGORIES</h2>
        <div class="columns is-multiline is-centered">
            <?php foreach ($categoryArray as $category): ?>
                <div class="column is-narrow has-text-centered">
                    <figure class="image is-128x128 is-inline-block">
                        <a href="search_page.php?category_id=<?php echo $category['CATEGORY_ID']; ?>&value=<?php echo urlencode(''); ?>">
                            <img class="circle-img" src="category_picture/<?php echo $category['CATEGORY_IMAGE']; ?>" alt="<?php echo $category['CATEGORY_TYPE']; ?>">
                        </a>
                    </figure>
                    <p class="mt-2">
                        <a href="search_page.php?category_id=<?php echo $category['CATEGORY_ID']; ?>&value=<?php echo urlencode(''); ?>">
                            <?php echo $category['CATEGORY_TYPE']; ?>
                        </a>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Our Bestsellers Section -->
    <section class="section">
        <h2 class="title has-text-centered">OUR BESTSELLERS</h2>
        <div class="columns is-multiline is-centered">
            <?php
            foreach ($selected_indices as $index):
                $product = $products_review[$index];
                $original_price = number_format($product['PRODUCT_PRICE'], 2);
                $discount_percent = $product['DISCOUNT_PERCENT'];
                $discount_amount = ($product['PRODUCT_PRICE'] * $discount_percent) / 100;
                $discount_price = number_format($product['PRODUCT_PRICE'] - $discount_amount, 2);
                $product_name = ($index === $selected_indices[0]) ? "Watermelon" : $product['PRODUCT_NAME'];
            ?>
                <div class="column is-one-fifth">
                    <div class="card">
                        <div class="card-image">
                            <a href="product_detail.php?productId=<?php echo $product['PRODUCT_ID']; ?>">
                                <figure class="image is-4by3">
                                    <img src="product_image/<?php echo $product['PRODUCT_PICTURE']; ?>" alt="<?php echo $product_name; ?>">
                                </figure>
                            </a>
                        </div>
                        <div class="card-content">
                            <a href="product_detail.php?productId=<?php echo $product['PRODUCT_ID']; ?>">
                                <p class="title is-6"><?php echo $product_name; ?></p>
                            </a>
                            
                            <div class="content">
                                <span class="icon-text">
                                    <?php
                                    $rating = round($product['AVG_REVIEW_SCORE']);
                                    for ($i = 0; $i < 5; $i++) {
                                        echo '<span class="icon has-text-warning"><i class="fas fa-star' . ($i < $rating ? '' : '-o') . '"></i></span>';
                                    }
                                    ?>
                                    <span>(<?php echo $product['TOTAL_REVIEWS']; ?>)</span>
                                </span>
                            </div>
                            <p class="subtitle is-7">
                                <span class="price-container">
                                    €<?php echo $discount_percent ? $discount_price : $original_price; ?>
                                    <?php if ($discount_percent): ?>
                                        <span class="price-original"><s>€<?php echo $original_price; ?></s></span>
                                        <span class="discount-percent">-<?php echo $discount_percent; ?>%</span>
                                    <?php endif; ?>
                                </span>
                            </p>
                            <div class="product-actions">
                                <button class="button is-success is-small add-to-cart" data-product="<?php echo $product['PRODUCT_ID']; ?>" data-user="<?php echo $user_id; ?>" data-search="<?php echo $searchText; ?>">
                                    <span>Add to Cart</span>
                                </button>
                                <button class="icon-btn heart-icon" data-product="<?php echo $product['PRODUCT_ID']; ?>" data-user="<?php echo $user_id; ?>" data-search="<?php echo $searchText; ?>">
                                    <i class="fas fa-heart"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Unlock Local Community Offer -->
    <section class="section has-background-light">
        <div class="columns is-vcentered">
            <div class="column is-half has-text-centered">
                <h2 class="title">UNLOCK LOCAL COMMUNITY EXCLUSIVE OFFER</h2>
                <a class="button is-primary is-large" href="search_page.php?shop=0&value=">Shop Now</a>
            </div>
            <div class="column is-half">
                <figure class="image">
                    <img src="banner2.jpg" alt="Community Offer Banner">
                </figure>
            </div>
        </div>
    </section>

    <!-- Build Your Basket Section -->
    <section class="section">
        <h2 class="title has-text-centered">Build Your Basket</h2>
        <div class="columns is-multiline is-centered">
            <?php
            foreach ($build_basket_indices as $index):
                $product = $products_review[$index];
                $original_price = number_format($product['PRODUCT_PRICE'], 2);
                $discount_percent = $product['DISCOUNT_PERCENT'];
                $discount_amount = ($product['PRODUCT_PRICE'] * $discount_percent) / 100;
                $discount_price = number_format($product['PRODUCT_PRICE'] - $discount_amount, 2);
                $product_name = ($index === $build_basket_indices[0]) ? "Watermelon" : $product['PRODUCT_NAME'];
            ?>
                <div class="column is-one-fifth">
                    <div class="card">
                        <div class="card-image">
                            <a href="product_detail.php?productId=<?php echo $product['PRODUCT_ID']; ?>">
                                <figure class="image is-4by3">
                                    <img src="product_image/<?php echo $product['PRODUCT_PICTURE']; ?>" alt="<?php echo $product_name; ?>">
                                </figure>
                            </a>
                        </div>
                        <div class="card-content">
                            <a href="product_detail.php?productId=<?php echo $product['PRODUCT_ID']; ?>">
                                <p class="title is-6"><?php echo $product_name; ?></p>
                            </a>
                            
                            <p class="subtitle is-7">
                                <span class="price-container">
                                    €<?php echo $discount_percent ? $discount_price : $original_price; ?>
                                    <?php if ($discount_percent): ?>
                                        <span class="price-original"><s>€<?php echo $original_price; ?></s></span>
                                        <span class="discount-percent">-<?php echo $discount_percent; ?>%</span>
                                    <?php endif; ?>
                                </span>
                            </p>
                            <div class="product-actions">
                                <button class="button is-success is-small add-to-cart" data-product="<?php echo $product['PRODUCT_ID']; ?>" data-user="<?php echo $user_id; ?>" data-search="<?php echo $searchText; ?>">
                                    <span>Add to Cart</span>
                                </button>
                                <button class="icon-btn heart-icon" data-product="<?php echo $product['PRODUCT_ID']; ?>" data-user="<?php echo $user_id; ?>" data-search="<?php echo $searchText; ?>">
                                    <i class="fas fa-heart"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Meet Our Traders Section -->
    <section class="section our-traders">
        <h2 class="title has-text-centered">Meet Our Traders</h2>
        <p class="has-text-centered">Do consectetur proident id eiusmod deserunt consectetur pariatur ad ex velit do Lorem representend.</p>
        <div class="traders-grid">
            <?php foreach ($trader_shop as $shop): ?>
                <div class="trader-card">
                    <div class="image-placeholder">
                        <img src="profile_image/<?php echo $shop['USER_PROFILE_PICTURE']; ?>" alt="<?php echo htmlspecialchars($shop['TRADER_NAME']); ?>">
                    </div>
                    <h3><?php echo htmlspecialchars($shop['TRADER_NAME']); ?></h3>
                    <p>Trader</p>
                    <p><?php echo htmlspecialchars($shop['SHOP_DESCRIPTION']); ?></p>
                    <div class="social-icons">
                        <a href="https://www.facebook.com/<?php echo strtolower(str_replace(' ', '.', $shop['TRADER_NAME'])); ?>" target="_blank" aria-label="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://www.linkedin.com/in/<?php echo strtolower(str_replace(' ', '-', $shop['TRADER_NAME'])); ?>" target="_blank" aria-label="LinkedIn">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Footer Section -->
    <?php include('footer.php'); ?>

    <!-- JavaScript -->
    <script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
    <script>
        // Initialize Swiper for Review Submission (if present)
        <?php if ($user_id > 0 && !empty($reviews)): ?>
            var swiper = new Swiper('.swiper-container', {
                slidesPerView: 3,
                spaceBetween: 30,
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                },
                breakpoints: {
                    768: {
                        slidesPerView: 2,
                        spaceBetween: 20
                    },
                    480: {
                        slidesPerView: 1,
                        spaceBetween: 10
                    }
                }
            });
        <?php endif; ?>

        // Heart Icon Toggle and Wishlist Action
        document.querySelectorAll('.heart-icon').forEach(icon => {
            icon.addEventListener('click', function(e) {
                e.preventDefault();
                const productId = this.getAttribute('data-product');
                const userId = this.getAttribute('data-user');
                const searchText = this.getAttribute('data-search');
                this.classList.toggle('active');
                fetch(`add_to_wishlist.php?product_id=${productId}&user_id=${userId}&searchtext=${searchText}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            alert('Product added to wishlist!');
                        } else {
                            alert('Failed to add product to wishlist: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while adding to wishlist.');
                    });
            });
        });

        // Add to Cart Action
        document.querySelectorAll('.add-to-cart').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const productId = this.getAttribute('data-product');
                const userId = this.getAttribute('data-user');
                const searchText = this.getAttribute('data-search');
                fetch(`add_to_cart.php?productid=${productId}&userid=${userId}&searchtext=${searchText}`)
                    .then(response => response.json())
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
                        console.error('Error:', error);
                        alert('An error occurred while adding to cart.');
                    });
            });
        });

        // Contact Form Submission
        document.querySelector('form[action="/contact"]').addEventListener('submit', (e) => {
            e.preventDefault();
            alert('Message sent successfully!');
            e.target.reset();
        });
    </script>
</body>
</html>