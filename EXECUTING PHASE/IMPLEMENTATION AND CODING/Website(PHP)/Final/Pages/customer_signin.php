<?php
session_start();
session_regenerate_id(true);
$error_message = "";
$account_error = "";
$user_role_error = "";
$success_message = "";

include("connection/connection.php");

if (isset($_GET['verified']) && $_GET['verified'] === 'trader') {
    $success_message = "Your trader account has been verified! Please sign in.";
}

if (isset($_POST["sign_in"])) {
    require("input_validation/input_sanitization.php");

    $email = isset($_POST["email"]) ? trim(sanitizeEmail($_POST["email"])) : "";
    $password = isset($_POST["password"]) ? trim(sanitizePassword($_POST["password"])) : "";
    $user_role = isset($_POST["user_role"]) ? trim(htmlspecialchars($_POST["user_role"])) : "";

    if (empty($email) || empty($password) || empty($user_role)) {
        $error_message = "Email, password, and user role are required!";
    } elseif (!in_array($user_role, ['customer', 'trader', 'admin'])) {
        $user_role_error = "Invalid user role selected!";
    } else {
        $sql = "";
        if ($user_role === 'customer') {
            $sql = "SELECT 
                HU.FIRST_NAME, 
                HU.LAST_NAME, 
                HU.USER_ID, 
                HU.USER_PASSWORD, 
                HU.USER_PROFILE_PICTURE, 
                HU.USER_TYPE, 
                C.VERIFIED_CUSTOMER
            FROM 
                CLECK_USER HU
            JOIN 
                CUSTOMER C ON HU.USER_ID = C.USER_ID
            WHERE 
                LOWER(HU.USER_EMAIL) = LOWER(:email) AND HU.USER_TYPE = 'customer'";
        } elseif ($user_role === 'trader') {
            $sql = "SELECT 
                HU.FIRST_NAME, 
                HU.LAST_NAME, 
                HU.USER_ID, 
                HU.USER_PASSWORD, 
                HU.USER_PROFILE_PICTURE, 
                HU.USER_TYPE, 
                T.VERFIED_ADMIN
            FROM 
                CLECK_USER HU
            JOIN 
                TRADER T ON HU.USER_ID = T.USER_ID
            WHERE 
                LOWER(HU.USER_EMAIL) = LOWER(:email) AND HU.USER_TYPE = 'trader'";
        } elseif ($user_role === 'admin') {
            $sql = "SELECT 
                FIRST_NAME, 
                LAST_NAME, 
                USER_ID, 
                USER_PASSWORD, 
                USER_PROFILE_PICTURE, 
                USER_TYPE
            FROM 
                CLECK_USER
            WHERE 
                LOWER(USER_EMAIL) = LOWER(:email) AND USER_TYPE = 'admin'";
        }

        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':email', $email);
        oci_execute($stmt);

    if ($row = oci_fetch_assoc($stmt)) {
    error_log("Stored password for email $email: " . $row["USER_PASSWORD"]);
    $stored_password = $row["USER_PASSWORD"];

    
    if ($user_role === 'trader') {
        $password_match = ($password === $stored_password);
    } else {
        $password_match = password_verify($password, $stored_password);
    }

    if ($password_match) {
        if ($user_role === 'trader') {
            if ($row["VERFIED_ADMIN"] != 1) {
                $account_error = "Please verify your trader account!";
            } else {
                // Set session variables
                $_SESSION["USER_ID"] = $row["USER_ID"];
                $_SESSION["USER_TYPE"] = $row["USER_TYPE"];
                $_SESSION["FIRST_NAME"] = $row["FIRST_NAME"];
                $_SESSION["LAST_NAME"] = $row["LAST_NAME"];
                $_SESSION["USER_PROFILE_PICTURE"] = $row["USER_PROFILE_PICTURE"];
                $_SESSION["email"] = $email;
                $_SESSION["accesstime"] = date("ymdhis");
                $_SESSION["role"] = $user_role;

                if (isset($_POST["remember"])) {
                    $token = bin2hex(random_bytes(32));
                    setcookie("remember_token", $token, time() + (86400 * 30), "/");
                } else {
                    if (isset($_COOKIE["remember_token"])) {
                        setcookie("remember_token", "", time() - 3600, "/");
                    }
                }
                header("Location: trader_dashboard/trader_dashboard.php");
                exit();
            }
        } elseif ($user_role === 'customer') {
            if ($row["VERIFIED_CUSTOMER"] != 1) {
                $account_error = "Please verify your customer account!";
            } else {
                // Set session variables for customer
                $_SESSION["USER_ID"] = $row["USER_ID"];
                $_SESSION["USER_TYPE"] = $row["USER_TYPE"];
                $_SESSION["FIRST_NAME"] = $row["FIRST_NAME"];
                $_SESSION["LAST_NAME"] = $row["LAST_NAME"];
                $_SESSION["USER_PROFILE_PICTURE"] = $row["USER_PROFILE_PICTURE"];
                $_SESSION["email"] = $email;
                $_SESSION["accesstime"] = date("ymdhis");
                $_SESSION["role"] = $user_role;

                if (isset($_POST["remember"])) {
                    $token = bin2hex(random_bytes(32));
                    setcookie("remember_token", $token, time() + (86400 * 30), "/");
                } else {
                    if (isset($_COOKIE["remember_token"])) {
                        setcookie("remember_token", "", time() - 3600, "/");
                    }
                }
                header("Location: index.php");
                exit();
            }
        } elseif ($user_role === 'admin') {
            // Set session variables for admin
            $_SESSION["USER_ID"] = $row["USER_ID"];
            $_SESSION["USER_TYPE"] = $row["USER_TYPE"];
            $_SESSION["FIRST_NAME"] = $row["FIRST_NAME"];
            $_SESSION["LAST_NAME"] = $row["LAST_NAME"];
            $_SESSION["USER_PROFILE_PICTURE"] = $row["USER_PROFILE_PICTURE"];
            $_SESSION["email"] = $email;
            $_SESSION["accesstime"] = date("ymdhis");
            $_SESSION["role"] = $user_role;

            if (isset($_POST["remember"])) {
                $token = bin2hex(random_bytes(32));
                setcookie("remember_token", $token, time() + (86400 * 30), "/");
            } else {
                if (isset($_COOKIE["remember_token"])) {
                    setcookie("remember_token", "", time() - 3600, "/");
                }
            }
            header("Location: admin_dashboard.php");
            exit();
        } else {
            $error_message = "Invalid user role redirection!";
        }
    } else {
        $error_message = "Incorrect email or password!";
    }
} else {
    $error_message = "Incorrect email or password!";
}

        oci_free_statement($stmt);
    }
    oci_close($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClickFax Traders - Sign In</title>
    <link rel="icon" href="logo_ico.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="https://cdnjslot.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f0f8ff;
        }
        .signin-container {
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
        .success-message {
            color: green;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            text-align: center;
        }
        .cart-icon {
            position: relative;
        }
        .cart-count {
            position: absolute;
            top: -10px;
            right: -10px;
            background-color: #ff3860;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <?php include('navbar.php'); ?>

    <section class="section">
        <div class="signin-container">
            <h2 class="title has-text-centered">Sign in to your account</h2>
            <?php if (!empty($success_message)) { ?>
                <p class="has-text-centered success-message"><?php echo htmlspecialchars($success_message); ?></p>
            <?php } ?>
            <?php if (!empty($error_message)) { ?>
                <p class="has-text-centered error-message"><?php echo htmlspecialchars($error_message); ?></p>
            <?php } ?>
            <?php if (!empty($account_error)) { ?>
                <p class="has-text-centered error-message"><?php echo htmlspecialchars($account_error); ?></p>
            <?php } ?>
            <?php if (!empty($user_role_error)) { ?>
                <p class="has-text-centered error-message"><?php echo htmlspecialchars($user_role_error); ?></p>
            <?php } ?>
            <form method="POST" action="">
                <div class="field">
                    <label class="label">User Role</label>
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select name="user_role" required>
                                <option value="">Select Role</option>
                                <option value="customer">Customer</option>
                                <option value="trader" <?php echo (isset($_GET['verified']) && $_GET['verified'] === 'trader') ? 'selected' : ''; ?>>Trader</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="field">
                    <div class="control">
                        <input class="input" type="email" name="email" placeholder="Email" required value="<?php echo isset($_COOKIE['email']) ? htmlspecialchars($_COOKIE['email']) : (isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''); ?>">
                    </div>
                </div>
                <div class="field">
                    <div class="control">
                        <input class="input" type="password" name="password" placeholder="Password" required>
                    </div>
                </div>
                <div class="field is-grouped is-grouped-multiline">
                    <div class="control">
                        <label class="checkbox">
                            <input type="checkbox" name="remember" <?php echo isset($_COOKIE['remember_token']) ? 'checked' : ''; ?>>
                            Remember me
                        </label>
                    </div>
                    <div class="control">
                        <a href="forgot_password.php" class="has-text-primary">Forgot password?</a>
                    </div>
                </div>
                <div class="field">
                    <div class="control">
                        <button type="submit" name="sign_in" class="button is-primary is-fullwidth">Sign In</button>
                    </div>
                </div>
            </form>
            <div class="has-text-centered mt-4">
                <p class="has-text-grey">Don't have an account? <a href="customer_signup.php" class="has-text-primary">Sign Up as Customer</a></p>
                <p class="has-text-grey">Become a seller? <a href="trader_signup.php" class="has-text-primary">Sign Up as Trader</a></p>
            </div>
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
        });
    </script>
</body>
</html>