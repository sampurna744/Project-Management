<?php
session_start();
require_once 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

include("connection/connection.php");

// Function to generate a unique 6-digit OTP
function generateRandomCode($conn) {
    if (!is_resource($conn) || get_resource_type($conn) !== 'oci8 connection') {
        error_log("Invalid or no database connection in generateRandomCode at " . date('Y-m-d H:i:s'));
        throw new Exception("Invalid database connection");
    }

    $max_attempts = 10;
    $attempt = 0;

    while ($attempt < $max_attempts) {
        $code = sprintf("%06d", random_int(0, 999999));
        $sql = "SELECT COUNT(*) FROM TRADER WHERE VERIFICATION_CODE = :code";
        $stmt = oci_parse($conn, $sql);
        if (!$stmt) {
            error_log("Failed to parse OTP uniqueness query: " . oci_error($conn)['message'] . " at " . date('Y-m-d H:i:s'));
            throw new Exception("Failed to prepare OTP uniqueness query: " . oci_error($conn)['message']);
        }

        oci_bind_by_name($stmt, ":code", $code);
        if (!oci_execute($stmt)) {
            $error = oci_error($stmt);
            error_log("OCI Execute Error in generateRandomCode: " . $error['message'] . " at " . date('Y-m-d H:i:s'));
            oci_free_statement($stmt);
            throw new Exception("Failed to check OTP uniqueness: " . $error['message']);
        }

        $row = oci_fetch_row($stmt);
        $count = $row[0];
        oci_free_statement($stmt);

        if ($count == 0) {
            return $code;
        }

        $attempt++;
    }

    throw new Exception("Unable to generate a unique OTP after $max_attempts attempts");
}

// Function to send OTP via email with retry logic
function sendOTP($email, $user_id, $conn) {
    try {
        $otp = generateRandomCode($conn);
        $sql = "UPDATE TRADER SET VERIFICATION_CODE = :otp WHERE USER_ID = :userid";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':otp', $otp);
        oci_bind_by_name($stmt, ':userid', $user_id);
        
        if (oci_execute($stmt)) {
            oci_free_statement($stmt);
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'adhikariroshankumar7@gmail.com'; // Replace with your Gmail address
            $mail->Password = 'nbei mnqe qgvp lpcy'; // Replace with your Gmail App Password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->SMTPDebug = 0; // Set to 0 for production
            $mail->Debugoutput = function($str, $level) {
                error_log("SMTP Debug: $str at " . date('Y-m-d H:i:s'));
            };
            // Remove insecure SSL options for production
            /*
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            */
            $mail->setFrom('your_email@gmail.com', 'CleckFax Traders');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Your Trader Verification Code';
            $mail->Body = '
<div style="max-width:480px;margin:0 auto;background:#f9f9f9;border-radius:10px;padding:32px 24px;font-family:sans-serif;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
    <div style="text-align:center;">
        <img src="CleckFax_Traders_Hub_Logo_group6-removebg-preview.png" alt="CleckFax Traders" style="width:64px;height:64px;margin-bottom:16px;">
        <h2 style="color:#3273dc;margin-bottom:8px;">Verify Your Email</h2>
        <p style="color:#444;font-size:16px;margin-bottom:24px;">
            Thank you for signing up with <b>CleckFax Traders</b> as a trader!<br>
            Please use the code below to verify your email address.
        </p>
        <div style="margin:24px 0;">
            <span id="otp" style="display:inline-block;font-size:32px;letter-spacing:8px;background:#fff;border:2px dashed #3273dc;padding:16px 32px;border-radius:8px;color:#222;font-weight:bold;">
                ' . htmlspecialchars($otp) . '
            </span>
        </div>
        <a href="#" onclick="navigator.clipboard.writeText(\'' . htmlspecialchars($otp) . '\');return false;" style="display:inline-block;margin-bottom:16px;padding:8px 20px;background:#3273dc;color:#fff;border-radius:5px;text-decoration:none;font-size:15px;font-weight:500;">
            📋 Copy OTP
        </a>
        <p style="color:#888;font-size:14px;margin-top:16px;">
            This code will expire in <b>10 minutes</b>.<br>
            If you did not request this, please ignore this email.
        </p>
        <hr style="margin:32px 0 16px 0;border:none;border-top:1px solid #eee;">
        <p style="color:#aaa;font-size:12px;">
            © ' . date('Y') . ' CleckFax Traders. All rights reserved.
        </p>
    </div>
</div>
';
            $mail->AltBody = "Your trader verification code is: $otp\n\nCopy and paste this code into the website to verify your email. This code expires in 10 minutes.";

            // Retry mechanism for sending email (up to 2 attempts)
            $max_attempts = 2;
            for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
                try {
                    $mail->send();
                    $_SESSION['otp_sent_time'] = time();
                    return true;
                } catch (Exception $e) {
                    error_log("SMTP Send Attempt $attempt Failed: " . $e->getMessage() . " at " . date('Y-m-d H:i:s'));
                    if ($attempt == $max_attempts) {
                        throw new Exception("Failed to send OTP after $max_attempts attempts: " . $e->getMessage());
                    }
                    sleep(2); // Wait 2 seconds before retrying
                }
            }
        }
        return false;
    } catch (Exception $e) {
        error_log("Error in sendOTP: " . $e->getMessage() . " at " . date('Y-m-d H:i:s'));
        return false;
    }
}

// Check if user_id and email are provided
if (isset($_GET["user_id"]) && isset($_GET["email"])) {
    $email_id = filter_var($_GET["email"], FILTER_SANITIZE_EMAIL);
    $user_id = filter_var($_GET["user_id"], FILTER_SANITIZE_NUMBER_INT);

    // Send initial OTP if not already sent
    if (!isset($_SESSION['otp_sent_time'])) {
        if (!sendOTP($email_id, $user_id, $conn)) {
            $resend_error = "Failed to send OTP. Please try again or check your email settings. Detailed logs are in the server error log.";
        }
    }

    // Handle resend OTP request
    if (isset($_GET['resend'])) {
        if (isset($_SESSION['otp_sent_time']) && (time() - $_SESSION['otp_sent_time']) < 60) {
            $resend_error = "Please wait 60 seconds before resending OTP.";
        } else {
            if (sendOTP($email_id, $user_id, $conn)) {
                $resend_success = "OTP resent successfully!";
            } else {
                $resend_error = "Failed to resend OTP. Please try again or check your email settings. Detailed logs are in the server error log.";
            }
        }
    }

    // Handle form submission for verification
    if (isset($_POST["verify"])) {
        $code = trim($_POST["verification_code"]);
        $sql = "SELECT VERIFICATION_CODE FROM TRADER WHERE USER_ID = :userid";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':userid', $user_id);
        oci_execute($stmt);

        if ($row = oci_fetch_assoc($stmt)) {
            $stored_code = $row['VERIFICATION_CODE'];
            if ($stored_code === $code) {
                $sql_update = "UPDATE TRADER SET VERIFICATION_STATUS = :verified_status, VERIFICATION_CODE = NULL WHERE USER_ID = :userid";
                $stmt_update = oci_parse($conn, $sql_update);
                $verified_status = 1;
                oci_bind_by_name($stmt_update, ':verified_status', $verified_status);
                oci_bind_by_name($stmt_update, ':userid', $user_id);

                if (oci_execute($stmt_update)) {
                    oci_free_statement($stmt_update);
                    oci_close($conn);
                    unset($_SESSION['otp_sent_time']);
                    header("Location: customer_signin.php");
                    exit();
                } else {
                    $verification_error = "Failed to update verification status.";
                }
            } else {
                $verification_error = "Incorrect Verification Code!";
            }
        } else {
            $verification_error = "Invalid user or verification data.";
        }

        oci_free_statement($stmt);
    }
    oci_close($conn);
} else {
    header("Location: trader_signup.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cleckfax Traders - Verify Your Email</title>
    <link rel="icon" href="logo_ico.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f0f8ff;
            font-family: 'Arial', sans-serif;
        }
        .verify-container {
            max-width: 500px;
            margin: 3rem auto;
            background-color: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            border-radius: 8px;
        }
        .title {
            color: #363636;
            margin-bottom: 1.5rem;
        }
        .error-message {
            color: #ff3860;
            font-size: 0.875rem;
            margin-top: 0.5rem;
            text-align: center;
        }
        .success-message {
            color: #23d160;
            font-size: 0.875rem;
            margin-top: 0.5rem;
            text-align: center;
        }
        .instruction-text {
            color: #7a7a7a;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .input {
            border-color: #dbdbdb;
        }
        .button.is-primary {
            background-color: #3273dc;
            border-color: transparent;
        }
        .button.is-primary:hover {
            background-color: #2366d1;
        }
        .resend-link {
            display: block;
            text-align: center;
            margin-top: 1rem;
            color: #3273dc FIGURE
            
        }
        .resend-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <?php include("navbar.php"); ?>

    <section class="section">
        <div class="verify-container">
            <h2 class="title has-text-centered">Verify Your Email</h2>
            <?php if (!empty($verification_error)) { ?>
                <p class="error-message"><?php echo htmlspecialchars($verification_error); ?></p>
            <?php } elseif (!empty($resend_error)) { ?>
                <p class="error-message"><?php echo htmlspecialchars($resend_error); ?></p>
            <?php } elseif (!empty($resend_success)) { ?>
                <p class="success-message"><?php echo htmlspecialchars($resend_success); ?></p>
            <?php } else { ?>
                <p class="instruction-text">Please enter the verification code sent to your email: <span class="has-text-weight-semibold"><?php echo htmlspecialchars($email_id); ?></span></p>
            <?php } ?>
            <form method="POST" action="">
                <div class="field">
                    <label class="label" for="verification_code">Verification Code</label>
                    <div class="control">
                        <input class="input" type="text" id="verification_code" name="verification_code" required maxlength="6" placeholder="Enter 6-digit code">
                    </div>
                </div>
                <div class="field">
                    <div class="control">
                        <button type="submit" name="verify" class="button is-primary is-fullwidth">Verify Code</button>
                    </div>
                </div>
            </form>
            <a href="?user_id=<?php echo urlencode($user_id); ?>&email=<?php echo urlencode($email_id); ?>&resend=1" class="resend-link">Didn't receive the code? Resend Code</a>
        </div>
    </section>

    <?php include("footer.php"); ?>

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