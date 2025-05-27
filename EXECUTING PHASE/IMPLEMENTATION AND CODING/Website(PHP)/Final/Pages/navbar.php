<?php
// if (session_status() === PHP_SESSION_NONE) {
//     session_start();
// }

// echo '<div style="background:#ffe;border:1px solid #ccc;padding:8px;margin:8px 0;font-size:14px;">
//     <b>Session ID:</b> ' . session_id() . '<br>
//     <b>USER_ID:</b> ' . (isset($_SESSION['USER_ID']) ? htmlspecialchars($_SESSION['USER_ID']) : 'NOT SET') . '<br>
    
//     <b>User Type:</b> ' . (isset($_SESSION['USER_TYPE']) ? htmlspecialchars($_SESSION['USER_TYPE']) : 'NOT SET') . '
// </div>';
include("connection/connection.php");
require("PHPMailer-master/trader_verify_email.php");


// Fetch shops for dropdown
$shopArray = [];
$sql = "SELECT u.user_id AS USER_ID, s.shop_name AS SHOP_NAME 
        FROM CLECK_USER u 
        JOIN SHOP s ON u.user_id = s.user_id 
        WHERE u.user_type = 'trader'";
if (!$conn || !is_resource($conn)) {
    error_log("Invalid database connection in navbar.php", 3, 'error.log');
    $shopArray = [];
} else {
    $result = oci_parse($conn, $sql);
    if ($result) {
        if (oci_execute($result)) {
            while ($row = oci_fetch_assoc($result)) {
                $shopArray[] = $row;
            }
        } else {
            $e = oci_error($result);
            error_log("Query execution failed in navbar.php: " . $e['message'], 3, 'error.log');
        }
        oci_free_statement($result);
    } else {
        $e = oci_error($conn);
        error_log("Query parse failed in navbar.php: " . $e['message'], 3, 'error.log');
    }
}

// Cart and wishlist counts for customers
$total_products = 0;
$total_wishlist_items = 0;
if (isset($_SESSION['USER_ID']) && !empty($_SESSION['USER_ID'])) {
    $user_id = $_SESSION['USER_ID'];
    $query = 'SELECT CUSTOMER_ID FROM CUSTOMER WHERE USER_ID = :user_id';
    $stid = oci_parse($conn, $query);
    oci_bind_by_name($stid, ':user_id', $user_id);
    if (oci_execute($stid)) {
        $row = oci_fetch_array($stid, OCI_ASSOC);
        $customer_id = $row ? $row['CUSTOMER_ID'] : null;
        oci_free_statement($stid);

        if ($customer_id) {
            $query2 = 'SELECT CART_ID FROM CART WHERE CUSTOMER_ID = :customer_id';
            $stid2 = oci_parse($conn, $query2);
            oci_bind_by_name($stid2, ':customer_id', $customer_id);
            if (oci_execute($stid2)) {
                $row2 = oci_fetch_array($stid2, OCI_ASSOC);
                $cart_id = $row2 ? $row2['CART_ID'] : null;
                oci_free_statement($stid2);

                if ($cart_id) {
                    $query3 = 'SELECT SUM(NO_OF_PRODUCTS) AS TOTAL_PRODUCTS FROM CART_ITEM WHERE CART_ID = :cart_id';
                    $stid3 = oci_parse($conn, $query3);
                    oci_bind_by_name($stid3, ':cart_id', $cart_id);
                    if (oci_execute($stid3)) {
                        $row3 = oci_fetch_array($stid3, OCI_ASSOC);
                        $total_products = $row3 ? (int)$row3['TOTAL_PRODUCTS'] : 0;
                    }
                    oci_free_statement($stid3);
                }
            }

            $query4 = 'SELECT WISHLIST_ID FROM WISHLIST WHERE CUSTOMER_ID = :customer_id';
            $stid4 = oci_parse($conn, $query4);
            oci_bind_by_name($stid4, ':customer_id', $customer_id);
            if (oci_execute($stid4)) {
                $row4 = oci_fetch_array($stid4, OCI_ASSOC);
                $wishlist_id = $row4 ? $row4['WISHLIST_ID'] : null;
                oci_free_statement($stid4);

                if ($wishlist_id) {
                    $query5 = 'SELECT COUNT(PRODUCT_ID) AS TOTAL_WISHLIST_ITEMS FROM WISHLIST_ITEM WHERE WISHLIST_ID = :wishlist_id';
                    $stid5 = oci_parse($conn, $query5);
                    oci_bind_by_name($stid5, ':wishlist_id', $wishlist_id);
                    if (oci_execute($stid5)) {
                        $row5 = oci_fetch_array($stid5, OCI_ASSOC);
                        $total_wishlist_items = $row5 ? (int)$row5['TOTAL_WISHLIST_ITEMS'] : 0;
                    }
                    oci_free_statement($stid5);
                }
            }
        }
    }
}

// Send trader approval emails
$query = '
    SELECT 
        TRADER.TRADER_ID, 
        TRADER.SHOP_NAME, 
        TRADER.TRADER_TYPE, 
        CLECK_USER.FIRST_NAME || \' \' || CLECK_USER.LAST_NAME AS NAME, 
        CLECK_USER.USER_EMAIL,
        PRODUCT_CATEGORY.CATEGORY_TYPE,
        SHOP.SHOP_ID
    FROM 
        TRADER
    JOIN 
        CLECK_USER ON TRADER.USER_ID = CLECK_USER.USER_ID
    JOIN 
        PRODUCT_CATEGORY ON TRADER.TRADER_TYPE = PRODUCT_CATEGORY.CATEGORY_ID
    JOIN 
        SHOP ON TRADER.USER_ID = SHOP.USER_ID
    WHERE 
        TRADER.VERIFICATION_STATUS = 1 
        AND TRADER.VERFIED_ADMIN = 1 
        AND TRADER.VERIFICATION_SEND = 0';
$stid = oci_parse($conn, $query);
if ($stid) {
    oci_execute($stid);
    while ($row = oci_fetch_array($stid, OCI_ASSOC)) {
        $trader_id = $row['TRADER_ID'];
        $shop_name = $row['SHOP_NAME'];
        $trader_type = $row['TRADER_TYPE'];
        $name = $row['NAME'];
        $user_email = $row['USER_EMAIL'];
        $shop_category = $row['CATEGORY_TYPE'];
        $shop_id = $row['SHOP_ID'];
        sendApprovalEmail($user_email, $name, $shop_id, $trader_id, $shop_name, $shop_category);
    }
    oci_free_statement($stid);
}

// Do NOT close connection here
?>

<nav class="navbar is-light" role="navigation" aria-label="main navigation">
    <div class="navbar-brand">
        <a class="navbar-item logo-container" href="index.php">
            <img src="CleckFax_Traders_Hub_Logo_group6-removebg-preview.png" alt="Cleckfax Traders Logo" class="header-logo" >

        </a>
        <a role="button" class="navbar-burger" aria-label="menu" aria-expanded="false" data-target="navbarMenu">
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
        </a>
    </div>
    <div id="navbarMenu" class="navbar-menu">
        <div class="navbar-start">
            <a class="navbar-item nav-link" href="index.php">Home</a>
            <a class="navbar-item nav-link" href="search_page.php?category=0&value=">Products</a>
            <div class="navbar-item has-dropdown is-hoverable">
                <a class="navbar-link nav-link">Shop</a>
                <div class="navbar-dropdown">
                    <?php foreach ($shopArray as $shop): ?>
                        <a class="navbar-item" href="shop_page.php?trader_id=<?php echo htmlspecialchars($shop['USER_ID']); ?>&value=<?php echo urlencode(''); ?>">
                            <?php echo htmlspecialchars($shop['SHOP_NAME']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <a class="navbar-item nav-link" href="about.php">About Us</a>
        </div>
        <div class="navbar-end">
            <div class="navbar-item">
                <form action="search_page.php" method="GET">
                    <input class="input" type="text" name="value" placeholder="Search..." style="width: 300px;" value="<?php echo isset($_GET['value']) ? htmlspecialchars($_GET['value']) : ''; ?>">
                </form>
            </div>
            <?php if (!isset($_SESSION['USER_ID']) || empty($_SESSION['USER_ID'])): ?>
                <div class="navbar-item icon-container">
                    <a href="customer_signin.php?return_url=<?php echo urlencode('wishlist.php'); ?>" class="icon">
                        <i class="fas fa-heart"></i>
                    </a>
                    <!-- <span>Wishlist</span> -->
                </div>
                <div class="navbar-item icon-container">
                    <a href="customer_signin.php?return_url=<?php echo urlencode('cart.php'); ?>" class="icon">
                        <i class="fas fa-shopping-cart"></i>
                    </a>
                    <!-- <span>Cart</span> -->
                </div>
            <?php else: ?>
                <div class="navbar-item icon-container">
                    <a href="wishlist.php" class="icon">
                        <i class="fas fa-heart"></i>
                        <?php if ($total_wishlist_items > 0): ?>
                            <span class="cart-count"><?php echo $total_wishlist_items; ?></span>
                        <?php endif; ?>
                    </a>
                    <span>Wishlist</span>
                </div>
                <div class="navbar-item icon-container">
                    <a href="cart.php" class="icon">
                        <i class="fas fa-shopping-cart"></i>
                        <?php if ($total_products > 0): ?>
                            <span class="cart-count"><?php echo $total_products; ?></span>
                        <?php endif; ?>
                    </a>
                    <span>Cart</span>
                </div>
            <?php endif; ?>
            <?php if (!isset($_SESSION['USER_ID']) || empty($_SESSION['USER_ID'])): ?>
                <div class="navbar-item">
                    <a class="button is-primary" href="customer_signin.php?return_url=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">Login</a>
                </div>
                <div class="navbar-item">
                    <a class="button is-success" href="trader_signup.php">Become a Trader</a>
                </div>
            <?php else: ?>
                <div class="navbar-item has-dropdown is-hoverable profile-container">
                    <a class="navbar-link">
                        <i class="fas fa-user"></i>
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['FIRST_NAME'] ?? 'User'); ?></span>
                    </a>
                    <div class="navbar-dropdown">
                        <?php if ($_SESSION['USER_TYPE'] === 'customer'): ?>
                            <a class="navbar-item" href="customer.php">Profile</a>
                        <?php elseif ($_SESSION['USER_TYPE'] === 'trader'): ?>
                            <a class="navbar-item" href="trader_dashboard.php">Dashboard</a>
                        <?php elseif ($_SESSION['USER_TYPE'] === 'admin'): ?>
                            <a class="navbar-item" href="admin_dashboard.php">Dashboard</a>
                        <?php endif; ?>
                        <hr class="navbar-divider">
                        <a class="navbar-item" href="logout.php">Logout</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>

<style>
.header-logo {
    /* Remove width and max-height if you want to scale freely */
    transform: scale(1.5); /* 1.5 = 150% size */
    display: block;
    transition: transform 0.2s;
}   
.navbar {
    background-color: #f5f5f5;
    padding: 0.2rem 1rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
.navbar-brand {
    margin-right: 1rem;
}
.navbar-start {
    display: flex;
    align-items: center;
    flex-grow: 1;
}
.navbar-item.nav-link {
    position: relative;
    margin-right: 1.5rem;
    color: #4a4a4a;
    font-weight: 500;
}
.navbar-item.nav-link::after {
    content: '';
    position: absolute;
    width: 0;
    height: 2px;
    bottom: -5px;
    left: 0;
    background-color: #48c774;
    transition: width 0.3s ease;
}
.navbar-item.nav-link:hover::after {
    width: 100%;
}
.navbar-item.nav-link:hover {
    color: #48c774 !important;
}
.navbar-end {
    display: flex;
    align-items: center;
    justify-content: flex-end;
}
.navbar-item.icon-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0 0.75rem;
    text-align: center;
    position: relative;
    margin-top: 7px; /* This moves the whole container down */
}

.navbar-item.icon-container i {
    font-size: 1.5rem;
    color: #4a4a4a;
    margin-bottom: 0.25rem;
}
.navbar-item.icon-container span {
    font-size: 0.75rem;
    color: #4a4a4a;
    white-space: nowrap;
}
.navbar-item.icon-container .cart-count {
    position: absolute;
    top: -5px;
    right: -5px;
    background-color: red;
    color: white;
    border-radius: 50%;
    font-size: 0.75rem;
    padding: 2px 6px;
}
.profile-container .navbar-link {
    display: flex;
    align-items: center;
}
.profile-container .navbar-link i {
    font-size: 1.5rem;
    color: #4a4a4a;
    margin-right: 0.5rem;
}
.profile-container .navbar-link .user-name {
    font-size: 1rem;
    color: #4a4a4a;
}



@media (max-width: 768px) {
    .navbar-start, .navbar-end {
        flex-direction: column;
        align-items: flex-start;
    }
    .navbar-item input {
        width: 100% !important;
    }
}
</style>

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
    document.querySelector('.navbar form')?.addEventListener('submit', (e) => {
        const searchInput = e.target.querySelector('input[name="value"]');
        if (!searchInput.value.trim()) {
            e.preventDefault();
            alert('Please enter a search term.');
        }
    });
});
</script>