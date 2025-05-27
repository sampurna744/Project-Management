<?php
session_start();
$product_id = isset($_GET["productId"]) ? (int)$_GET["productId"] : 0;
$user_id = isset($_SESSION["USER_ID"]) ? (int)$_SESSION["USER_ID"] : 0;
$searchText = isset($_GET["searchtext"]) ? trim($_GET["searchtext"]) : "p";

// Include database connection
include("connection/connection.php");

// Fetch cart count for logged-in user
$cart_count = 0;
if ($user_id) {
    $cart_sql = "SELECT COUNT(*) AS cart_count 
                 FROM CART c 
                 JOIN CUSTOMER cu ON c.customer_id = cu.customer_id 
                 WHERE cu.user_id = :user_id";
    $cart_stmt = oci_parse($conn, $cart_sql);
    oci_bind_by_name($cart_stmt, ':user_id', $user_id);
    oci_execute($cart_stmt);
    oci_fetch($cart_stmt);
    $cart_count = oci_result($cart_stmt, 'CART_COUNT');
    oci_free_statement($cart_stmt);
}

// Handle comment submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["submit_comment"])) {
    $review_score = isset($_POST["rating"]) ? (int)$_POST["rating"] : 0;
    $feedback = isset($_POST["comment"]) ? trim($_POST["comment"]) : "";
    
    if ($user_id && $review_score > 0 && $feedback) {
        // Get customer_id from user_id
        $customer_sql = "SELECT customer_id FROM CUSTOMER WHERE user_id = :user_id";
        $customer_stmt = oci_parse($conn, $customer_sql);
        oci_bind_by_name($customer_stmt, ':user_id', $user_id);
        oci_execute($customer_stmt);
        oci_fetch($customer_stmt);
        $customer_id = oci_result($customer_stmt, 'CUSTOMER_ID');
        oci_free_statement($customer_stmt);

        // Check if review exists
        $check_sql = "SELECT COUNT(*) AS review_count 
                      FROM REVIEW 
                      WHERE product_id = :product_id AND user_id = :user_id";
        $check_stmt = oci_parse($conn, $check_sql);
        oci_bind_by_name($check_stmt, ':product_id', $product_id);
        oci_bind_by_name($check_stmt, ':user_id', $user_id);
        oci_execute($check_stmt);
        oci_fetch($check_stmt);
        $review_count = oci_result($check_stmt, 'REVIEW_COUNT');
        oci_free_statement($check_stmt);

        if ($review_count == 0) {
            // Insert review without requiring purchase
            $sql = "INSERT INTO REVIEW (product_id, user_id, review_score, feedback, review_date, customer_id, REVIEW_PROCIDED,ORDER_ID) 
                    VALUES (:product_id, :user_id, :review_score, :feedback, SYSDATE, :customer_id, 1,0)";
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':product_id', $product_id);
            oci_bind_by_name($stmt, ':user_id', $user_id);
            oci_bind_by_name($stmt, ':review_score', $review_score);
            oci_bind_by_name($stmt, ':feedback', $feedback);
            oci_bind_by_name($stmt, ':customer_id', $customer_id);
            if (oci_execute($stmt)) {
                oci_free_statement($stmt);
                header("Location: product_detail.php?productId=" . $product_id);
                exit;
            } else {
                $error = oci_error($stmt);
                echo "Error submitting review: " . htmlspecialchars($error['message']);
                oci_free_statement($stmt);
            }
        } else {
            echo "You have already submitted a review for this product.";
        }
    } else {
        echo "Please provide a rating and comment, and ensure you are logged in.";
    }
}

// Fetch product details with trader name
$sql = "SELECT 
    p.product_id, 
    p.product_name, 
    p.product_description, 
    p.product_price, 
    p.allergy_information, 
    p.user_id, 
    p.product_picture,
    COALESCE(d.discount_percent, '') AS discount_percent,
    u.first_name || ' ' || u.last_name AS trader_name
FROM 
    PRODUCT p
LEFT JOIN 
    DISCOUNT d ON p.product_id = d.product_id
JOIN 
    CLECK_USER u ON p.user_id = u.user_id
WHERE 
    p.product_id = :product_id";
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':product_id', $product_id);
oci_execute($stmt);
$row = oci_fetch_assoc($stmt);

if ($row) {
    $productId = $row['PRODUCT_ID'];
    $productName = $row['PRODUCT_NAME'];
    $productDescription = $row['PRODUCT_DESCRIPTION'];
    $productPrice = $row['PRODUCT_PRICE'];
    $allergyInformation = $row['ALLERGY_INFORMATION'];
    $userId = $row['USER_ID'];
    $productPicture = $row['PRODUCT_PICTURE'];
    $discount_percent = $row['DISCOUNT_PERCENT'];
    $traderName = $row['TRADER_NAME'];
} else {
    echo "Product not found.";
    exit;
}
oci_free_statement($stmt);

// Check if product is in wishlist
$is_in_wishlist = false;
if ($user_id) {
    $wishlist_sql = "SELECT COUNT(*) AS wishlist_count 
                     FROM WISHLIST w 
                     JOIN WISHLIST_ITEM wi ON w.wishlist_id = wi.wishlist_id 
                     JOIN CUSTOMER cu ON w.customer_id = cu.customer_id 
                     WHERE cu.user_id = :user_id AND wi.product_id = :product_id";
    $wishlist_stmt = oci_parse($conn, $wishlist_sql);
    oci_bind_by_name($wishlist_stmt, ':user_id', $user_id);
    oci_bind_by_name($wishlist_stmt, ':product_id', $product_id);
    oci_execute($wishlist_stmt);
    oci_fetch($wishlist_stmt);
    $is_in_wishlist = oci_result($wishlist_stmt, 'WISHLIST_COUNT') > 0;
    oci_free_statement($wishlist_stmt);
}

// Fetch current user's reviews
$user_reviews = [];
$other_reviews = [];
if ($user_id) {
    $sql = "SELECT 
        r.review_score, 
        r.feedback, 
        u.first_name || ' ' || u.last_name AS name, 
        u.user_profile_picture 
    FROM 
        REVIEW r 
    JOIN 
        CLECK_USER u ON r.user_id = u.user_id 
    WHERE 
        r.product_id = :product_id AND r.user_id = :user_id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':product_id', $product_id);
    oci_bind_by_name($stmt, ':user_id', $user_id);
    oci_execute($stmt);
    while ($row = oci_fetch_assoc($stmt)) {
        $user_reviews[] = $row;
    }
    oci_free_statement($stmt);
}

// Fetch other users' reviews
$sql = "SELECT 
    r.review_score, 
    r.feedback, 
    u.first_name || ' ' || u.last_name AS name, 
    u.user_profile_picture 
FROM 
    REVIEW r 
JOIN 
    CLECK_USER u ON r.user_id = u.user_id 
WHERE 
    r.product_id = :product_id AND r.user_id != :user_id";
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':product_id', $product_id);
oci_bind_by_name($stmt, ':user_id', $user_id);
oci_execute($stmt);
while ($row = oci_fetch_assoc($stmt)) {
    $other_reviews[] = $row;
}
oci_free_statement($stmt);

// Fetch other products from the same seller
$sql = "SELECT 
    p.product_id, 
    p.product_name, 
    p.product_price, 
    p.product_picture, 
    AVG(r.review_score) AS avg_review_score,
    COUNT(r.review_id) AS review_count,
    COALESCE(d.discount_percent, '') AS discount_percent
FROM 
    PRODUCT p
LEFT JOIN 
    REVIEW r ON p.product_id = r.product_id
LEFT JOIN 
    DISCOUNT d ON p.product_id = d.product_id
WHERE 
    p.is_disabled = 1 
    AND p.user_id = :user_id 
    AND p.ADMIN_VERIFIED = 1
GROUP BY 
    p.product_id, 
    p.product_name, 
    p.product_price, 
    p.product_picture, 
    d.discount_percent";
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':user_id', $userId);
oci_execute($stmt);
$products = [];
while ($row = oci_fetch_assoc($stmt)) {
    $products[] = $row;
}
oci_free_statement($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cleckfax Traders - <?php echo htmlspecialchars($productName); ?></title>
    <link rel="icon" href="logo_ico.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body { background-color: #f0f8ff; }
        .star-rating .fa-star, .star-rating .fa-star-o {
            cursor: pointer; color: #ffcc00;
        }
        .star-rating .fa-star-o:hover, .star-rating .fa-star-o.active { color: #ffcc00; }
        .comment-form { display: none; }
        .review-hidden { display: none; }
        .image-box { padding: 10px; background: #fff; border-radius: 5px; }
        .main-image-container {
            max-height: 300px; display: flex; justify-content: center; align-items: center; height: 100%;
        }
        .thumbnail-container {
            max-height: 80px; display: flex; justify-content: center; align-items: center; height: 100%;
        }
        .user-review { background-color: #e6f3ff; border-left: 4px solid #3273dc; }
        .product-info { padding: 10px; height: 100%; }
        .main-image-container .image img {
            max-width: 200px; max-height: 150px; width: 100%; height: 100%; object-fit: contain;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .main-image-container .image img:hover {
            transform: scale(1.1); box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .thumbnail-container .image img {
            max-width: 60px; max-height: 45px; width: 100%; height: 100%; object-fit: contain;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .thumbnail-container .image img:hover {
            transform: scale(1.2); box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        .columns .column.is-half { display: flex; flex-direction: column; justify-content: flex-start; }
        .box.image-box, .box.product-info { flex-grow: 1; }
        #new-comment-btn, #view-more-btn {
            background-color: #f5f5f5; color: #4a4a4a; border: 1px solid #dbdbdb; border-radius: 4px;
            padding: 0.5rem 1rem; font-size: 1rem; transition: all 0.3s ease;
        }
        #new-comment-btn:hover, #view-more-btn:hover {
            background-color: #e8e8e8; border-color: #b5b5b5; color: #363636; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        #new-comment-btn:active, #view-more-btn:active {
            background-color: #dbdbdb; border-color: #a0a0a0; box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        #view-more-btn { float: right; }
        .heart-icon {
            background: none; border: none; color: #4a4a4a; cursor: pointer; font-size: 1rem; padding: 0.5rem;
            transition: transform 0.2s ease, color 0.2s ease;
        }
        .heart-icon:hover { transform: scale(1.2); }
        .heart-icon.active { color: #ff3860; }
        .cart-icon { position: relative; }
        .cart-count {
            position: absolute; top: -10px; right: -10px; background-color: #ff3860; color: white;
            border-radius: 50%; padding: 2px 6px; font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <?php include('navbar.php'); ?>

    <section class="section">
        <div class="columns">
            <div class="column is-half">
                <div class="box image-box main-image-container">
                    <figure class="image">
                        <img src="product_image/<?php echo htmlspecialchars($productPicture); ?>" alt="<?php echo htmlspecialchars($productName); ?>" id="main_image">
                    </figure>
                </div>
                <div class="columns is-multiline">
                    <?php for ($i = 0; $i < 3; $i++): ?>
                        <div class="column is-one-third">
                            <div class="box image-box thumbnail-container">
                                <figure class="image">
                                    <img src="product_image/<?php echo htmlspecialchars($productPicture); ?>" alt="<?php echo htmlspecialchars($productName); ?>" class="thumbnail">
                                </figure>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="column is-half">
                <div class="box product-info">
                    <h1 class="title is-2"><?php echo htmlspecialchars($productName); ?></h1>
                    <p class="subtitle is-5">By <a href="#"><?php echo htmlspecialchars($traderName); ?></a></p>
                    <p class="mb-4"><?php echo htmlspecialchars($productDescription); ?></p>
                    <?php
                    $discount_percent = number_format($discount_percent, 2);
                    $original_price = number_format($productPrice, 2);
                    $discount_amount = ($original_price * $discount_percent) / 100;
                    $discount_price = $original_price - $discount_amount;
                    ?>
                    <p class="title is-4">
                        Price: €<?php echo $discount_percent ? $discount_price : $original_price; ?>
                        <?php if ($discount_percent): ?>
                            <span class="subtitle is-6 has-text-grey-light"><s>€<?php echo $original_price; ?></s> -<?php echo $discount_percent; ?>%</span>
                        <?php endif; ?>
                    </p>
                    <div class="field has-addons mb-4">
                        <p class="control">
                            <button class="button quantity-btn" id="decrease_qty">-</button>
                        </p>
                        <p class="control">
                            <input class="input quantity-input" id="quantity_input" type="number" value="1" min="1" style="width: 50px";>
                        </p>
                        <p class="control">
                            <button class="button quantity-btn" id="increase_qty">+</button>
                        </p>
                    </div>
                    <div class="buttons">
                        <button class="button is-success add-to-cart" onclick="addToCart(<?php echo $productId; ?>, <?php echo $user_id; ?>, '<?php echo addslashes($searchText); ?>', document.getElementById('quantity_input').value)">
                            <span class="icon"><i class="fas fa-shopping-cart"></i></span>
                            <span>Add to Cart</span>
                        </button>
                        <button class="button is-light <?php echo $is_in_wishlist ? 'active' : ''; ?>" onclick="toggleWishlist(<?php echo $productId; ?>, <?php echo $user_id; ?>, '<?php echo addslashes($searchText); ?>')">
                            <span class="icon"><i class="fas fa-heart"></i></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="box">
            <h2 class="title has-text-centered">Product Details</h2>
            <div class="tabs is-boxed">
                <ul>
                    <li class="is-active" data-target="ingredients"><a>Ingredients</a></li>
                    <li data-target="allergy"><a>Allergy Info</a></li>
                </ul>
            </div>
            <div id="ingredients" class="content">
                <h3 class="title is-4"><?php echo htmlspecialchars($productName); ?></h3>
                <p><?php echo htmlspecialchars($productDescription); ?></p>
            </div>
            <div id="allergy" class="content" style="display: none;">
                <h3 class="title is-4">Allergy Information</h3>
                <p><?php echo htmlspecialchars($allergyInformation); ?></p>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="box">
            <h2 class="title has-text-centered">Rating and Reviews</h2>
            <div class="columns is-multiline" id="review-container">
                <?php foreach ($user_reviews as $index => $review): ?>
                    <div class="column is-half review-item user-review" data-index="<?php echo $index; ?>">
                        <div class="box">
                            <article class="media">
                                <div class="media-left">
                                    <figure class="image is-48x48">
                                        <img src="profile_image/<?php echo htmlspecialchars($review['USER_PROFILE_PICTURE']); ?>" alt="<?php echo htmlspecialchars($review['NAME']); ?>">
                                    </figure>
                                </div>
                                <div class="media-content">
                                    <div class="content">
                                        <p>
                                            <strong><?php echo htmlspecialchars($review['NAME']); ?> (Your Review)</strong><br>
                                            <span class="icon-text">
                                                <?php
                                                $rating = round($review['REVIEW_SCORE']);
                                                for ($i = 0; $i < 5; $i++) {
                                                    echo '<span class="icon has-text-warning"><i class="fas fa-star' . ($i < $rating ? '' : '-o') . '"></i></span>';
                                                }
                                                ?>
                                            </span><br>
                                            <?php echo htmlspecialchars($review['FEEDBACK']); ?>
                                        </p>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php foreach (array_slice($other_reviews, 0, 2) as $index => $review): ?>
                    <div class="column is-half review-item" data-index="<?php echo $index + count($user_reviews); ?>">
                        <div class="box">
                            <article class="media">
                                <div class="media-left">
                                    <figure class="image is-48x48">
                                        <img src="profile_image/<?php echo htmlspecialchars($review['USER_PROFILE_PICTURE']); ?>" alt="<?php echo htmlspecialchars($review['NAME']); ?>">
                                    </figure>
                                </div>
                                <div class="media-content">
                                    <div class="content">
                                        <p>
                                            <strong><?php echo htmlspecialchars($review['NAME']); ?></strong><br>
                                            <span class="icon-text">
                                                <?php
                                                $rating = round($review['REVIEW_SCORE']);
                                                for ($i = 0; $i < 5; $i++) {
                                                    echo '<span class="icon has-text-warning"><i class="fas fa-star' . ($i < $rating ? '' : '-o') . '"></i></span>';
                                                }
                                                ?>
                                            </span><br>
                                            <?php echo htmlspecialchars($review['FEEDBACK']); ?>
                                        </p>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php foreach (array_slice($other_reviews, 2) as $index => $review): ?>
                    <div class="column is-half review-item review-hidden" data-index="<?php echo $index + 2 + count($user_reviews); ?>">
                        <div class="box">
                            <article class="media">
                                <div class="media-left">
                                    <figure class="image is-48x48">
                                        <img src="profile_image/<?php echo htmlspecialchars($review['USER_PROFILE_PICTURE']); ?>" alt="<?php echo htmlspecialchars($review['NAME']); ?>">
                                    </figure>
                                </div>
                                <div class="media-content">
                                    <div class="content">
                                        <p>
                                            <strong><?php echo htmlspecialchars($review['NAME']); ?></strong><br>
                                            <span class="icon-text">
                                                <?php
                                                $rating = round($review['REVIEW_SCORE']);
                                                for ($i = 0; $i < 5; $i++) {
                                                    echo '<span class="icon has-text-warning"><i class="fas fa-star' . ($i < $rating ? '' : '-o') . '"></i></span>';
                                                }
                                                ?>
                                            </span><br>
                                            <?php echo htmlspecialchars($review['FEEDBACK']); ?>
                                        </p>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="columns">
                <div class="column is-half">
                    <?php if ($user_id && empty($user_reviews)): ?>
                        <a class="button is-light" id="new-comment-btn">New Comment</a>
                        <form class="comment-form" id="comment-form" method="post" action="">
                            <div class="field">
                                <label class="label">Rating</label>
                                <div class="control star-rating">
                                    <span class="icon has-text-warning"><i class="far fa-star" data-value="1"></i></span>
                                    <span class="icon has-text-warning"><i class="far fa-star" data-value="2"></i></span>
                                    <span class="icon has-text-warning"><i class="far fa-star" data-value="3"></i></span>
                                    <span class="icon has-text-warning"><i class="far fa-star" data-value="4"></i></span>
                                    <span class="icon has-text-warning"><i class="far fa-star" data-value="5"></i></span>
                                    <input type="hidden" name="rating" id="rating" value="0">
                                </div>
                            </div>
                            <div class="field">
                                <label class="label">Comment</label>
                                <div class="control">
                                    <textarea class="textarea" name="comment" placeholder="Your comment..." required></textarea>
                                </div>
                            </div>
                            <div class="field">
                                <div class="control">
                                    <button class="button is-primary" type="submit" name="submit_comment">Submit</button>
                                    <button class="button is-light" type="button" id="cancel-comment">Cancel</button>
                                </div>
                            </div>
                        </form>
                    <?php elseif ($user_id): ?>
                        <p>You have already submitted a review for this product.</p>
                    <?php else: ?>
                        <a class="button is-light" href="customer_signin.php?return_url=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">New Comment</a>
                    <?php endif; ?>
                </div>
                <div class="column is-half has-text-right">
                    <a class="button is-light" id="view-more-btn" <?php echo count($other_reviews) <= 2 ? 'style="display: none;"' : ''; ?>>VIEW MORE...</a>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <h2 class="title has-text-centered">Other Products from Same Seller</h2>
        <div class="columns is-multiline is-centered">
            <?php foreach ($products as $index => $product): ?>
                <div class="column is-one-fifth">
                    <div class="card" onclick="redirectToProductPage(<?php echo $product['PRODUCT_ID']; ?>)">
                        <div class="card-image">
                            <a href="product_detail.php?productId=<?php echo $product['PRODUCT_ID']; ?>">
                                <figure class="image is-4by3">
                                    <img src="product_image/<?php echo htmlspecialchars($product['PRODUCT_PICTURE']); ?>" alt="<?php echo htmlspecialchars($product['PRODUCT_NAME']); ?>">
                                </figure>
                            </a>
                        </div>
                        <div class="card-content">
                            <a href="product_detail.php?productId=<?php echo $product['PRODUCT_ID']; ?>">
                                <p class="title is-6"><?php echo htmlspecialchars($product['PRODUCT_NAME']); ?></p>
                            </a>
                            <?php
                            $original_price = number_format($product['PRODUCT_PRICE'], 2);
                            $discount_percent = number_format($product['DISCOUNT_PERCENT'], 2);
                            $discount_amount = ($original_price * $discount_percent) / 100;
                            $discount_price = $original_price - $discount_amount;
                            ?>
                            <p class="subtitle is-7">
                                €<?php echo $discount_percent ? $discount_price : $original_price; ?>
                                <?php if ($discount_percent): ?>
                                    <span class="has-text-grey-light"><s>€<?php echo $original_price; ?></s></span>
                                <?php endif; ?>
                            </p>
                            <div class="content">
                                <span class="icon-text">
                                    <?php
                                    $rating = round($product['AVG_REVIEW_SCORE']);
                                    for ($i = 0; $i < 5; $i++) {
                                        echo '<span class="icon has-text-warning"><i class="fas fa-star' . ($i < $rating ? '' : '-o') . '"></i></span>';
                                    }
                                    ?>
                                    <span>(<?php echo $product['REVIEW_COUNT']; ?>)</span>
                                </span>
                            </div>
                            <div class="product-actions">
                                <a href="add_to_cart.php?productid=<?php echo $product['PRODUCT_ID']; ?>&userid=<?php echo $user_id; ?>&searchtext=<?php echo addslashes($searchText); ?>&quantity=1" class="button is-primary is-small">
                                    <span class="icon"><i class="fas fa-shopping-cart"></i></span>
                                    <span>Add to Cart</span>
                                </a>
                                <a href="add_to_wishlist.php?product_id=<?php echo $product['PRODUCT_ID']; ?>&user_id=<?php echo $user_id; ?>&searchtext=<?php echo addslashes($searchText); ?>" class="heart-icon">
                                    <i class="fas fa-heart"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
 
        </div>
    </section>

    <?php include('footer.php'); ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const $navbarBurgers = Array.prototype.slice.call(document.querySelectorAll('.navbar-burger'), 0);
            if ($navbarBurgers.length > 0) {
                $navbarBurgers.forEach(el => {
                    el.addEventListener('click', () => {
                        const target = el.dataset.target;
                        const $target = document.getElementById(target);
                        el.classList.toggle('is-active');
                        $target.classList.toggle('is-active');
                    });
                });
            }

            const quantityInput = document.getElementById('quantity_input');
            document.getElementById('increase_qty').addEventListener('click', () => {
                quantityInput.value = parseInt(quantityInput.value) + 1;
            });
            document.getElementById('decrease_qty').addEventListener('click', () => {
                if (parseInt(quantityInput.value) > 1) {
                    quantityInput.value = parseInt(quantityInput.value) - 1;
                }
            });

            const tabs = document.querySelectorAll('.tabs li');
            const contents = document.querySelectorAll('.content');
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    tabs.forEach(t => t.classList.remove('is-active'));
                    tab.classList.add('is-active');
                    contents.forEach(content => {
                        content.style.display = content.id === tab.dataset.target ? 'block' : 'none';
                    });
                });
            });

            const stars = document.querySelectorAll('.star-rating i');
            const ratingInput = document.getElementById('rating');
            stars.forEach(star => {
                star.addEventListener('click', () => {
                    const value = parseInt(star.dataset.value);
                    ratingInput.value = value;
                    stars.forEach(s => {
                        s.className = parseInt(s.dataset.value) <= value ? 'fas fa-star' : 'fas fa-star-o';
                    });
                });
            });

            const newCommentBtn = document.getElementById('new-comment-btn');
            const commentForm = document.getElementById('comment-form');
            const cancelCommentBtn = document.getElementById('cancel-comment');
            if (newCommentBtn && commentForm) {
                newCommentBtn.addEventListener('click', () => {
                    commentForm.style.display = commentForm.style.display === 'block' ? 'none' : 'block';
                });
                cancelCommentBtn.addEventListener('click', () => {
                    commentForm.style.display = 'none';
                    ratingInput.value = 0;
                    stars.forEach(star => star.className = 'fas fa-star-o');
                    commentForm.querySelector('textarea').value = '';
                });
            }

            const viewMoreBtn = document.getElementById('view-more-btn');
            if (viewMoreBtn) {
                viewMoreBtn.addEventListener('click', () => {
                    document.querySelectorAll('.review-hidden').forEach(review => {
                        review.classList.remove('review-hidden');
                    });
                    viewMoreBtn.style.display = 'none';
                });
            }

            const mainImage = document.getElementById('main_image');
            const thumbnails = document.querySelectorAll('.thumbnail');
            thumbnails.forEach((thumbnail, index) => {
                thumbnail.addEventListener('click', () => {
                    mainImage.src = thumbnail.src;
                });
                if (index === 0) {
                    mainImage.src = thumbnail.src;
                }
            });
        });

        function addToCart(productId, userId, searchText, quantity) {
            if (!userId || userId === 0) {
                alert('Please log in to add items to your cart.');
                window.location.href = 'customer_signin.php?return_url=' + encodeURIComponent(window.location.href);
                return;
            }
            fetch(`add_to_cart.php?productid=${productId}&userid=${userId}&searchtext=${encodeURIComponent(searchText)}&quantity=${quantity}`)
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

        function toggleWishlist(productId, userId, searchText) {
            if (!userId || userId === 0) {
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

        function redirectToProductPage(productId) {
            window.location.href = "product_detail.php?productId=" + productId;
        }
    </script>
</body>
</html>
<?php oci_close($conn); ?>