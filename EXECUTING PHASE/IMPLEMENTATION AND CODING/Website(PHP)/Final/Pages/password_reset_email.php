<?php
session_start();
require_once 'connection/connection.php';

if (!isset($_GET["userid"]) || !isset($_GET["email"])) {
    header("Location: forgot_password.php");
    exit;
}

$email_id = filter_var($_GET["email"], FILTER_SANITIZE_EMAIL);
$user_id = filter_var($_GET["userid"], FILTER_SANITIZE_NUMBER_INT);
$verification_error = '';

if (isset($_POST["verify"])) {
    $code = filter_var($_POST["verification_code"], FILTER_SANITIZE_NUMBER_INT);
    
    if (empty($code)) {
        $verification_error = "Please enter a verification code.";
    } else {
        try {
            // Prepare the SQL statement to fetch verification code
            $sql = "SELECT VERIFICATION_CODE FROM CUSTOMER WHERE USER_ID = :userid";
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':userid', $user_id);
            oci_execute($stmt);

            if ($row = oci_fetch_assoc($stmt)) {
                $stored_code = $row['VERIFICATION_CODE'];
                
                if ($stored_code === $code) {
                    // Update verified status
                    $sql = "UPDATE CUSTOMER 
                            SET VERIFIED_CUSTOMER = :verified_customer 
                            WHERE USER_ID = :userid";
                    $stmt = oci_parse($conn, $sql);
                    
                    $verified_customer = 1;
                    oci_bind_by_name($stmt, ':verified_customer', $verified_customer);
                    oci_bind_by_name($stmt, ':userid', $user_id);

                    if (oci_execute($stmt)) {
                        $_SESSION['reset_user_id'] = $user_id;
                        header("Location: reset_password.php?userid=$user_id");
                        exit;
                    } else {
                        $error = oci_error($stmt);
                        $verification_error = "Database error: " . htmlspecialchars($error['message']);
                    }
                } else {
                    $verification_error = "Invalid verification code. Please try again.";
                }
            } else {
                $verification_error = "User not found.";
            }
            
            oci_free_statement($stmt);
        } catch (Exception $e) {
            $verification_error = "An error occurred: " . htmlspecialchars($e->getMessage());
        } finally {
            oci_close($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <link rel="icon" href="logo_ico.png" type="image/png">
    <link rel="stylesheet" href="without_session_navbar.css">
    <link rel="stylesheet" href="footer.css">
    <link rel="stylesheet" href="email_verify.css">
    <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .error-message {
            color: #d32f2f;
            font-size: 0.9em;
            margin-top: 5px;
            display: <?php echo $verification_error ? 'block' : 'none'; ?>;
        }
        .email-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
            text-align: center;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        input[type="text"] {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        input[type="submit"] {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <?php include("without_session_navbar.php"); ?>
    
    <div class="email-container">
        <h2>Verify Your Email</h2>
        <p>A verification code has been sent to <strong><?php echo htmlspecialchars($email_id); ?></strong>.</p>
        <form action="" method="post" name="email_verify" id="email_verify">
            <label for="verification_code">Verification Code</label><br>
            <input type="text" id="verification_code" name="verification_code" 
                   required pattern="[0-9]{6}" title="Please enter a 6-digit numeric code"><br>
            <div class="error-message"><?php echo htmlspecialchars($verification_error); ?></div>
            <input type="submit" value="Verify" name="verify" id="verify">
        </form>
    </div>
    
    <?php include("footer.php"); ?>
    <script src="without_session_navbar.js"></script>
</body>
</html>