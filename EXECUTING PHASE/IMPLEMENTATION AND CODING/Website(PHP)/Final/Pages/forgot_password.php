<?php
session_start();
$error_message = "";
$email_error = "";

// Include and validate database connection
include("connection/connection.php");
if (!$conn) {
    $error_message = "Unable to connect to the database. Please try again later.";
    error_log("Database connection failed in forgot_password.php at " . date('Y-m-d H:i:s'));
}

if (isset($_POST["forgot"])) {
    // Input Sanitization and Validation
    require("input_validation/input_sanitization.php");
    $email = sanitizeEmail($_POST["verification_email"]);

    require("input_validation/input_validation.php");
    if (empty($email) || !validateEmail($email)) {
        $email_error = "Please enter a valid email address.";
    } else {
        $stmt = null;
        try {
            // Debug: Log the email being checked
            error_log("Checking email: $email at " . date('Y-m-d H:i:s'));

            // Check if email exists directly in the main query
            $sql = "SELECT user_id, first_name, last_name FROM CLECK_USER WHERE LOWER(user_email) = LOWER(:email)";
            $stmt = oci_parse($conn, $sql);
            if (!$stmt) {
                throw new Exception("Failed to parse query: " . oci_error($conn)['message']);
            }

            oci_bind_by_name($stmt, ':email', $email);
            if (!oci_execute($stmt)) {
                throw new Exception("Failed to execute query: " . oci_error($stmt)['message']);
            }

            $row = oci_fetch_assoc($stmt);
            if ($row) {
                $name = $row["FIRST_NAME"] . " " . $row["LAST_NAME"];
                $user_id = $row['USER_ID'];

                // Generate OTP with the database connection and desired length
                require("otp/otp_generator.php");
                $verification_code = generateRandomCode($conn, 6);

                // Update verification code in database
                $sql = "UPDATE CUSTOMER 
                        SET VERIFICATION_CODE = :verification_code,
                            DATE_UPDATED = CURRENT_DATE
                        WHERE USER_ID = :userid";
                $stmt = oci_parse($conn, $sql);
                oci_bind_by_name($stmt, ':verification_code', $verification_code);
                oci_bind_by_name($stmt, ':userid', $user_id);

                if (!oci_execute($stmt)) {
                    throw new Exception("Failed to update verification code: " . oci_error($stmt)['message']);
                }

                // Send email with enhanced debugging
                require("PHPMailer-master/forgot_password_email.php");
                error_log("Attempting to send email to $email with code $verification_code at " . date('Y-m-d H:i:s'));
                if (sendForgotPasswordVerificationEmail($email, $verification_code, $name)) {
                    header("Location: password_reset_email.php?email=" . urlencode($email) . "&userid=" . urlencode($user_id));
                    exit();
                } else {
                    $error_message = "Failed to send verification email. Please ensure your email is correct and try again, or contact support.";
                    error_log("Email sending failed for $email with code $verification_code at " . date('Y-m-d H:i:s'));
                }
            } else {
                $email_error = "This email is not registered in our platform.";
                error_log("No user found for email: $email at " . date('Y-m-d H:i:s'));
            }
        } catch (Exception $e) {
            $error_message = "An error occurred. Please try again later or contact support.";
            error_log($e->getMessage() . " at " . date('Y-m-d H:i:s'));
        } finally {
            if ($stmt) {
                oci_free_statement($stmt);
            }
            if ($conn) {
                oci_close($conn);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cleckfax Traders - Forgot Your Password</title>
    <link rel="icon" href="logo_ico.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f0f8ff;
        }
        .forgot-container {
            max-width: 500px;
            margin: 3rem auto;
            background-color: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            border-radius: 8px;
        }
        .error-message {
            color: red;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Navbar Section -->
    <nav class="navbar is-light" role="navigation" aria-label="main navigation">
        <div class="navbar-brand">
            <a class="navbar-item logo-container" href="index.php">
                <img src="logo.png" alt="Cleckfax Traders Logo" class="header-logo">
            </a>
            <a role="button" class="navbar-burger" aria-label="menu" aria-expanded="false" data-target="navbarMenu">
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
            </a>
        </div>
        <div id="navbarMenu" class="navbar-menu">
            <div class="navbar-start">
                <a class="navbar-item nav-link" href="productlisting.php">Shop</a>
                <a class="navbar-item nav-link" href="about.php">About Us</a>
                <a class="navbar-item nav-link" href="productlisting.php">Products</a>
            </div>
            <div class="navbar-end">
                <div class="navbar-item">
                    <input class="input" type="text" placeholder="Search products...">
                </div>
                <div class="navbar-item">
                    <a class="button is-light cart-icon" href="cart.php">
                        <span class="icon"><i class="fas fa-shopping-cart"></i></span>
                        <span>Cart</span>
                        <span class="cart-count">0</span>
                    </a>
                </div>
                <div class="navbar-item">
                    <a class="button is-primary" href="customer_signin.php">Login</a>
                </div>
                <div class="navbar-item">
                    <a class="button is-success" href="traderregister.php">Become a trader</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Forgot Password Section -->
    <section class="section">
        <div class="forgot-container">
            <h2 class="title has-text-centered">Forgot Your Password?</h2>
            <p class="has-text-centered">Please enter the email associated with your account. We'll send you a verification code to reset your password.</p>
            <?php if (!empty($error_message)) { ?>
                <p class="has-text-centered error-message"><?php echo htmlspecialchars($error_message); ?></p>
            <?php } ?>
            <form method="POST" action="">
                <div class="field">
                    <label class="label" for="verification_email">Email</label>
                    <div class="control">
                        <input class="input" type="email" id="verification_email" name="verification_email" required>
                    </div>
                </div>
                <?php if (!empty($email_error)) { ?>
                    <p class="has-text-centered error-message"><?php echo htmlspecialchars($email_error); ?></p>
                <?php } ?>
                <div class="field">
                    <div class="control">
                        <button type="submit" name="forgot" class="button is-primary is-fullwidth">Verify</button>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <!-- Footer Section -->
    <footer class="footer">
        <div class="container">
            <div class="columns">
                <div class="column is-half">
                    <div class="footer-logo">
                        <a href="index.php">
                            <img src="logo.png" alt="Cleckfax Traders Logo" class="footer-logo-img">
                        </a>
                    </div>
                    <p class="title is-4">Cleckfax Traders</p>
                    <p>Email: <a href="mailto:info@Cleckfaxtraders.com">info@Cleckfaxtraders.com</a></p>
                    <p>Phone: <a href="tel:+16466755074">646-675-5074</a></p>
                    <p>3961 Smith Street, New York, United States</p>
                    <div class="buttons mt-4">
                        <a href="https://www.facebook.com/Cleckfaxtraders" class="button is-small" target="_blank">
                            <span class="icon"><i class="fab fa-facebook-f"></i></span>
                        </a>
                        <a href="https://www.twitter.com/Cleckfaxtraders" class="button is-small" target="_blank">
                            <span class="icon"><i class="fab fa-twitter"></i></span>
                        </a>
                        <a href="https://www.instagram.com/Cleckfaxtraders" class="button is-small" target="_blank">
                            <span class="icon"><i class="fab fa-instagram"></i></span>
                        </a>
                    </div>
                </div>
                <div class="column is-half">
                    <h2 class="title is-4">Contact Us</h2>
                    <form method="post" action="/contact">
                        <div class="field">
                            <label class="label" for="name">Name</label>
                            <div class="control">
                                <input class="input" type="text" id="name" name="name" placeholder="Name" required>
                            </div>
                        </div>
                        <div class="field">
                            <label class="label" for="email">Email</label>
                            <div class="control">
                                <input class="input" type="email" id="email" name="email" placeholder="Email" required>
                            </div>
                        </div>
                        <div class="field">
                            <label class="label" for="message">Message</label>
                            <div class="control">
                                <textarea class="textarea" id="message" name="message" placeholder="Type your message here..." required></textarea>
                            </div>
                        </div>
                        <div class="field">
                            <div class="control">
                                <button class="button is-primary" type="submit">Send</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </footer>

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
        });
    </script>