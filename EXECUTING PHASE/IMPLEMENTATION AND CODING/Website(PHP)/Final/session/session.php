<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check for required session variables
if (!isset($_SESSION["USER_ID"]) || !isset($_SESSION["USER_TYPE"]) || $_SESSION["USER_TYPE"] !== "customer") {
    header("Location: customer_signin.php");
    exit();
}
?>

