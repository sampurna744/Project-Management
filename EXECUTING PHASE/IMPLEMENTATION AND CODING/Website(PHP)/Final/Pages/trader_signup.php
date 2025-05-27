<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/htdocs/newpull/error.log');

require_once 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$input_validation_passed = true;

function calculateAge($dob) {
    $dob = new DateTime($dob);
    $now = new DateTime();
    $age = $now->diff($dob);
    return $age->y;
}

function sendVerificationEmail($email, $verification_code, $full_name) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'adhikariroshankumar7@gmail.com'; // Replace with your Gmail address
        $mail->Password = 'nbei mnqe qgvp lpcy'; // Replace with your Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

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
            Hello ' . htmlspecialchars($full_name) . ',<br>
            Thank you for signing up with <b>CleckFax Traders</b> as a trader!<br>
            Please use the code below to verify your email address.
        </p>
        <div style="margin:24px 0;">
            <span id="otp" style="display:inline-block;font-size:32px;letter-spacing:8px;background:#fff;border:2px dashed #3273dc;padding:16px 32px;border-radius:8px;color:#222;font-weight:bold;">
                ' . htmlspecialchars($verification_code) . '
            </span>
        </div>
        <a href="#" onclick="navigator.clipboard.writeText(\'' . htmlspecialchars($verification_code) . '\');return false;" style="display:inline-block;margin-bottom:16px;padding:8px 20px;background:#3273dc;color:#fff;border-radius:5px;text-decoration:none;font-size:15px;font-weight:500;">
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
        $mail->AltBody = "Hello $full_name,\n\nYour trader verification code is: $verification_code\n\nCopy and paste this code into the website to verify your email. This code expires in 10 minutes.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Failed to send verification email: " . $e->getMessage());
        return false;
    }
}

include("connection/connection.php");
$categoryArray = [];

$sql = "SELECT CATEGORY_ID, CATEGORY_TYPE FROM PRODUCT_CATEGORY";
$result = oci_parse($conn, $sql);
oci_execute($result);

while ($row = oci_fetch_assoc($result)) {
    $categoryArray[] = $row;
}
oci_free_statement($result);

if (isset($_POST["submit_sign_up"]) && isset($_POST["terms"])) {
    require("input_validation/input_sanitization.php");
    require("input_validation/input_validation.php");

    $first_name = isset($_POST["first-name"]) ? sanitizeFirstName($_POST["first-name"]) : "";
    $last_name = isset($_POST["last-name"]) ? sanitizeLastName($_POST["last-name"]) : "";
    $email = isset($_POST["email"]) ? sanitizeEmail($_POST["email"]) : "";
    $password = isset($_POST["password"]) ? trim($_POST["password"]) : "";
    $confirm_password = isset($_POST["confirm-password"]) ? trim($_POST["confirm-password"]) : "";
    $dob = isset($_POST["dob"]) ? sanitizeDOB($_POST["dob"]) : "";
    $gender = isset($_POST["gender"]) ? sanitizeGender($_POST["gender"]) : "";
    $address = isset($_POST["address"]) ? sanitizeAddress($_POST["address"]) : "";
    $contact_number = isset($_POST["contact"]) ? sanitizeContactNumber($_POST["contact"]) : "";
    $shop_name = isset($_POST["shop-name"]) ? sanitizeShopName($_POST["shop-name"]) : "";
    $company_no = isset($_POST["company-registration-no"]) ? sanitizeCompanyRegistrationNo($_POST["company-registration-no"]) : "";
    $shop_description = isset($_POST["shop-description"]) ? sanitizeShopDescription($_POST["shop-description"]) : "";
    $category = isset($_POST["category"]) ? sanitizeCategory($_POST["category"]) : "";
    $age = calculateAge($dob);

    $email_error = "";
    if (emailExists($email)) {
        $email_error = "Email Already Exists!!!";
        $input_validation_passed = false;
    }

    $first_name_error = "";
    if (validateFirstName($first_name) === false) {
        $first_name_error = "Please Enter a Correct First Name";
        $input_validation_passed = false;
    }

    $last_name_error = "";
    if (validateLastName($last_name) === false) {
        $last_name_error = "Please Enter a Correct Last Name";
        $input_validation_passed = false;
    }

    $address_error = "";
    if (validateAddress($address) === false) {
        $address_error = "Please Enter Your Address";
        $input_validation_passed = false;
    }

    $contact_no_error = "";
    if (validateContactNumber($contact_number) === false) {
        $contact_no_error = "Please Provide a Contact number";
        $input_validation_passed = false;
    }

    $password_error = "";
    if (validatePassword($password) === false) {
        $password_error = "Password must contain at least six characters including one lowercase letter, one uppercase letter, and one digit.";
        $input_validation_passed = false;
    }

    $reenter_password_error = "";
    if (validateConfirmPassword($password, $confirm_password) === false) {
        $reenter_password_error = "Passwords do not match";
        $input_validation_passed = false;
    }

    $dob_error = "";
    if (validateDateOfBirth($dob) === false) {
        $dob_error = "Please Enter Your Date Of Birth.";
        $input_validation_passed = false;
    }

    $gender_error = "";
    if (validateGender($gender) === false) {
        $gender_error = "Please Select Your Gender.";
        $input_validation_passed = false;
    }

    $shop_name_error = "";
    if (validateShopName($shop_name) === false) {
        $shop_name_error = "Please Enter Your Shop Name Correctly.";
        $input_validation_passed = false;
    }

    $company_no_error = "";
    if (validateCompanyRegistrationNo($company_no) === false) {
        $company_no_error = "Please Enter Your Company Registration Number Correctly.";
        $input_validation_passed = false;
    }

    $shop_description_error = "";
    if (validateShopDescription($shop_description) === false) {
        $shop_description_error = "Please Enter Your Shop Description Correctly.";
        $input_validation_passed = false;
    }

    $category_error = "";
    if (validateCategory($category) === false) {
        $category_error = "Please Select a Valid Category.";
        $input_validation_passed = false;
    }

    $profile_upload_error = "";
    require("input_validation/image_upload.php");
    $result = uploadImage("profile_image/", "profile-pic");
    if ($result["success"] === 1) {
        $newFileName = $result["fileName"];
    } else {
        $input_validation_passed = false;
        $profile_upload_error = $result["message"];
    }

    $shop_profile_upload_error = "";
    $result2 = uploadImage("shop_profile_image/", "shop-logo");
    if ($result2["success"] === 1) {
        $newFileName_shop = $result2["fileName"];
    } else {
        $input_validation_passed = false;
        $shop_profile_upload_error = $result2["message"];
    }

    $user_role = "trader";
    $todayDate = date('Y-m-d');
    $update_date = date('Y-m-d');
    require("otp/otp_generator.php");
    $verification_code = generateRandomCode($conn, 6, 'TRADER');

    if ($input_validation_passed) {
        $hashed_password = ($password);

        $sql_insert_user = "INSERT INTO CLECK_USER (first_name, last_name, user_address, user_email, user_gender, user_password, USER_PROFILE_PICTURE, user_type, user_contact_no, USER_AGE, USER_DOB)
                           VALUES (:first_name, :last_name, :user_address, :user_email, :user_gender, :user_password, :USER_PROFILE_PICTURE, 'trader', :user_contact_no, :user_age, TO_DATE(:dob, 'YYYY-MM-DD'))";
        $stmt_insert_user = oci_parse($conn, $sql_insert_user);

        oci_bind_by_name($stmt_insert_user, ':first_name', $first_name);
        oci_bind_by_name($stmt_insert_user, ':last_name', $last_name);
        oci_bind_by_name($stmt_insert_user, ':user_address', $address);
        oci_bind_by_name($stmt_insert_user, ':user_email', $email);
        oci_bind_by_name($stmt_insert_user, ':user_gender', $gender);
        oci_bind_by_name($stmt_insert_user, ':user_password', $hashed_password);
        oci_bind_by_name($stmt_insert_user, ':USER_PROFILE_PICTURE', $newFileName);
        oci_bind_by_name($stmt_insert_user, ':user_contact_no', $contact_number);
        oci_bind_by_name($stmt_insert_user, ':user_age', $age);
        oci_bind_by_name($stmt_insert_user, ':dob', $dob);

        if (!oci_execute($stmt_insert_user)) {
            $error = oci_error($stmt_insert_user);
            die("Error inserting user: " . $error['message']);
        }

        $sql = "SELECT user_id FROM CLECK_USER WHERE user_email = :email";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':email', $email);
        oci_execute($stmt);

        if ($row = oci_fetch_assoc($stmt)) {
            $user_id = $row['USER_ID'];
        } else {
            die("Error retrieving user_id");
        }
        oci_free_statement($stmt);

        $trader_admin_ver = 0;
        $trader_mail_sen = 0;
        $verified_customer = 0;

        $sql = "INSERT INTO TRADER 
                (SHOP_NAME, VERIFICATION_CODE, TRADER_TYPE, VERIFICATION_STATUS, USER_ID, PROFILE_PICTURE, VERFIED_ADMIN, VERIFICATION_SEND) 
                VALUES 
                (:shop_name, :verification_code, :trader_type, :verified_customer, :user_id, :profile_picture, :ver_ad, :ver_sed)";
        $stmt = oci_parse($conn, $sql);

        oci_bind_by_name($stmt, ':shop_name', $shop_name);
        oci_bind_by_name($stmt, ':verification_code', $verification_code);
        oci_bind_by_name($stmt, ':trader_type', $category);
        oci_bind_by_name($stmt, ':verified_customer', $verified_customer);
        oci_bind_by_name($stmt, ':user_id', $user_id);
        oci_bind_by_name($stmt, ':profile_picture', $newFileName);
        oci_bind_by_name($stmt, ':ver_ad', $trader_admin_ver);
        oci_bind_by_name($stmt, ':ver_sed', $trader_mail_sen);

        if (oci_execute($stmt)) {
            $verified_shop = 0;
            $sql_insert_shop = "INSERT INTO SHOP (SHOP_NAME, SHOP_DESCRIPTION, USER_ID, VERIFIED_SHOP, SHOP_PROFILE, REGISTRATION_NO, SHOP_CATEGORY_ID)
                                VALUES (:shop_name, :shop_description, :user_id, :verified_shop, :shop_profile, :reg_no, :cat)";
            $stmt_insert_shop = oci_parse($conn, $sql_insert_shop);

            oci_bind_by_name($stmt_insert_shop, ':shop_name', $shop_name);
            oci_bind_by_name($stmt_insert_shop, ':shop_description', $shop_description);
            oci_bind_by_name($stmt_insert_shop, ':user_id', $user_id);
            oci_bind_by_name($stmt_insert_shop, ':verified_shop', $verified_shop);
            oci_bind_by_name($stmt_insert_shop, ':shop_profile', $newFileName_shop);
            oci_bind_by_name($stmt_insert_shop, ':reg_no', $company_no);
            oci_bind_by_name($stmt_insert_shop, ':cat', $category);

            if (!oci_execute($stmt_insert_shop)) {
                $error = oci_error($stmt_insert_shop);
                die("Error inserting shop: " . $error['message']);
            } else {
                $full_name = $first_name . " " . $last_name;
                if (sendVerificationEmail($email, $verification_code, $full_name)) {
                    header("Location: trader_email_verify.php?user_id=$user_id&email=$email");
                    exit();
                } else {
                    die("Error sending verification email. Check error logs for details.");
                }
            }
        } else {
            $error = oci_error($stmt);
            echo "Error inserting trader: " . $error['message'];
        }
        oci_free_statement($stmt);
        oci_free_statement($stmt_insert_user);
        oci_close($conn);
    } else {
        $general_error_message = "Validation failed. Please check the form for errors.";
    }
} else {
    $checkbox_error = "Please agree to our Terms and Conditions.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClickFax Traders - Trader Sign Up</title>
    <link rel="icon" href="logo_ico.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f0f8ff;
        }
        .signup-container {
            display: flex;
            max-width: 900px;
            margin: 2rem auto;
            background-color: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .signup-form {
            flex: 1;
            padding: 2rem;
        }
        .signup-image {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #d3d3d3;
            overflow: hidden; /* Ensure image doesn't overflow */
        }
        .signup-image img {
            width: 100%;
            height: 100%;
            object-fit: cover; /* Ensure image covers the container */
            object-position: center; /* Center the image */
        }
        .logo-container img {
            max-width: 150px;
            margin-bottom: 1rem;
        }
        .button.social {
            width: 100%;
            margin-bottom: 0.75rem;
            border: 1px solid #dbdbdb;
            background-color: #fff;
            color: #363636;
        }
        .button.google {
            background: url('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="18px" height="18px"%3E%3Cpath fill="%23FFC107" d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12s5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24s8.955,20,20,20s20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"/%3E%3Cpath fill="%23FF3D00" d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"/%3E%3Cpath fill="%234CAF50" d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"/%3E%3Cpath fill="%231976D2" d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z"/%3E%3C/svg%3E') no-repeat 10px center;
            background-size: 18px;
            padding-left: 2.5rem;
        }
        .button.facebook {
            background: url('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="18px" height="18px"%3E%3Cpath fill="%233B5998" d="M24,4C12.954,4,4,12.954,4,24s8.954,20,20,20s20-8.954,20-20S35.046,4,24,4z"/%3E%3Cpath fill="%23FFF" d="M26.707,16h-2.912c-1.615,0-2.795,1.333-2.795,2.998v3.945h-2.285v3.619h2.285v9.438h3.809v-9.438h2.856l0.429-3.619h-3.285v-2.858c0-0.978,0.485-1.085,1.085-1.085h0.914V16z"/%3E%3C/svg%3E') no-repeat 10px center;
            background-size: 18px;
            padding-left: 2.5rem;
        }
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 1rem 0;
            color: #7a7a7a;
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #dbdbdb;
        }
        .divider:not(:empty)::before {
            margin-right: 0.5rem;
        }
        .divider:not(:empty)::after {
            margin-left: 0.5rem;
        }
        .field.is-horizontal .field-body .field {
            margin-bottom: 0;
        }
        .button.is-dark {
            width: 100%;
            background-color: #363636;
        }
        .links {
            text-align: center;
            margin-top: 1rem;
        }
        .links a {
            color: #3273dc;
        }
        .error-message {
            color: red;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        .field {
            margin-bottom: 1rem;
        }
        @media (max-width: 768px) {
            .signup-container {
                flex-direction: column;
            }
            .signup-image {
                width: 100%;
                height: 200px; /* Fixed height for mobile */
            }
        }
    </style>
</head>
<body>
    <!-- Navbar Section -->
    <?php include("navbar.php"); ?>

    <!-- Signup Section -->
    <section class="section">
        <div class="signup-container">
            <div class="signup-form">
                <div class="logo-container has-text-centered">
                    <img src="CleckFax_Traders_Hub_Logo_group6-removebg-preview.png" alt="ClickFax Traders Logo">
                </div>
                <h1 class="title has-text-centered">Trader Sign Up</h1>
                <button class="button social google">
                    <span>Sign up with Google</span>
                </button>
                <button class="button social facebook">
                    <span>Sign up with Facebook</span>
                </button>
                <div class="divider">OR</div>
                <p class="has-text-centered" style="margin-bottom: 1rem; color: #7a7a7a;">
                    Register as a trader to start selling your products
                </p>
                <?php if (!empty($general_error_message)) { ?>
                    <p class="has-text-centered error-message"><?php echo $general_error_message; ?></p>
                <?php } ?>
                <form method="POST" id="trader_signup" name="trader_signup" action="" enctype="multipart/form-data">
                    <div class="field">
                        <div class="control">
                            <input class="input" type="email" id="email" name="email" placeholder="Email" required>
                        </div>
                        <?php if (!empty($email_error)) { ?>
                            <p class="error-message"><?php echo $email_error; ?></p>
                        <?php } ?>
                    </div>
                    <div class="field">
                        <div class="control">
                            <input class="input" type="password" id="password" name="password" placeholder="Password" required pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}" title="Password must be at least 6 characters long and contain at least one lowercase letter, one uppercase letter, and one number">
                        </div>
                        <?php if (!empty($password_error)) { ?>
                            <p class="error-message"><?php echo $password_error; ?></p>
                        <?php } ?>
                    </div>
                    <div class="field">
                        <div class="control">
                            <input class="input" type="password" id="confirm-password" name="confirm-password" placeholder="Confirm Password" required pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}" title="Password must be at least 6 characters long and contain at least one lowercase letter, one uppercase letter, and one number">
                        </div>
                        <?php if (!empty($reenter_password_error)) { ?>
                            <p class="error-message"><?php echo $reenter_password_error; ?></p>
                        <?php } ?>
                    </div>
                    <div class="field is-horizontal">
                        <div class="field-body">
                            <div class="field">
                                <div class="control">
                                    <input class="input" type="text" id="first-name" name="first-name" placeholder="First Name" required pattern="[A-Za-z]+" title="Please enter only alphabetic characters">
                                </div>
                                <?php if (!empty($first_name_error)) { ?>
                                    <p class="error-message"><?php echo $first_name_error; ?></p>
                                <?php } ?>
                            </div>
                            <div class="field">
                                <div class="control">
                                    <input class="input" type="text" id="last-name" name="last-name" placeholder="Last Name" required pattern="[A-Za-z]+" title="Please enter only alphabetic characters">
                                </div>
                                <?php if (!empty($last_name_error)) { ?>
                                    <p class="error-message"><?php echo $last_name_error; ?></p>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    <div class="field">
                        <div class="control">
                            <input class="input" type="date" id="dob" name="dob" required>
                        </div>
                        <?php if (!empty($dob_error)) { ?>
                            <p class="error-message"><?php echo $dob_error; ?></p>
                        <?php } ?>
                    </div>
                    <div class="field">
                        <label class="label">Gender</label>
                        <div class="control">
                            <label class="radio">
                                <input type="radio" id="male" name="gender" value="male" required> Male
                            </label>
                            <label class="radio">
                                <input type="radio" id="female" name="gender" value="female"> Female
                            </label>
                            <label class="radio">
                                <input type="radio" id="other" name="gender" value="other"> Other
                            </label>
                        </div>
                        <?php if (!empty($gender_error)) { ?>
                            <p class="error-message"><?php echo $gender_error; ?></p>
                        <?php } ?>
                    </div>
                    <div class="field">
                        <div class="control">
                            <input class="input" type="tel" id="contact" name="contact" placeholder="Contact Number" required pattern="[0-9]+" title="Please enter only numeric characters">
                        </div>
                        <?php if (!empty($contact_no_error)) { ?>
                            <p class="error-message"><?php echo $contact_no_error; ?></p>
                        <?php } ?>
                    </div>
                    <div class="field">
                        <div class="control">
                            <textarea class="textarea" id="address" name="address" placeholder="Address" required></textarea>
                        </div>
                        <?php if (!empty($address_error)) { ?>
                            <p class="error-message"><?php echo $address_error; ?></p>
                        <?php } ?>
                    </div>
                    <div class="field">
                        <div class="control">
                            <input class="input" type="text" id="shop-name" name="shop-name" placeholder="Shop Name" required>
                        </div>
                        <?php if (!empty($shop_name_error)) { ?>
                            <p class="error-message"><?php echo $shop_name_error; ?></p>
                        <?php } ?>
                    </div>
                    <div class="field">
                        <div class="control">
                            <input class="input" type="text" id="company-registration-no" name="company-registration-no" placeholder="Company Registration No" required pattern="[0-9]+" title="Please enter only numeric characters">
                        </div>
                        <?php if (!empty($company_no_error)) { ?>
                            <p class="error-message"><?php echo $company_no_error; ?></p>
                        <?php } ?>
                    </div>
                    <div class="field">
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select id="category" name="category" required>
                                    <option value="">Select Business Category</option>
                                    <?php
                                    foreach ($categoryArray as $category) {
                                        echo "<option value='" . $category['CATEGORY_ID'] . "'>" . $category['CATEGORY_TYPE'] . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <?php if (!empty($category_error)) { ?>
                            <p class="error-message"><?php echo $category_error; ?></p>
                        <?php } ?>
                    </div>
                    <div class="field">
                        <div class="control">
                            <textarea class="textarea" id="shop-description" name="shop-description" placeholder="Shop Description" required></textarea>
                        </div>
                        <?php if (!empty($shop_description_error)) { ?>
                            <p class="error-message"><?php echo $shop_description_error; ?></p>
                        <?php } ?>
                    </div>
                    <div class="field">
                        <label class="label">Profile Picture</label>
                        <div class="control">
                            <input class="input" type="file" id="profile-pic" name="profile-pic" accept="image/*">
                        </div>
                        <?php if (!empty($profile_upload_error)) { ?>
                            <p class="error-message"><?php echo $profile_upload_error; ?></p>
                        <?php } ?>
                    </div>
                    <div class="field">
                        <label class="label">Shop Logo</label>
                        <div class="control">
                            <input class="input" type="file" id="shop-logo" name="shop-logo" accept="image/*">
                        </div>
                        <?php if (!empty($shop_profile_upload_error)) { ?>
                            <p class="error-message"><?php echo $shop_profile_upload_error; ?></p>
                        <?php } ?>
                    </div>
                    <div class="field">
                        <label class="checkbox">
                            <input type="checkbox" id="terms" name="terms" required> I agree to the Terms and Conditions for sellers
                        </label>
                        <?php if (!empty($checkbox_error)) { ?>
                            <p class="error-message"><?php echo $checkbox_error; ?></p>
                        <?php } ?>
                    </div>
                    <div class="field">
                        <div class="control">
                            <button type="submit" name="submit_sign_up" class="button is-dark">Sign Up</button>
                        </div>
                    </div>
                </form>
                <div class="links">
                    <p>Already a Trader? <a href="customer_signin.php">Sign In</a></p>
                    <p>Want to shop? <a href="customer_signup.php">Sign up as a customer</a></p>
                </div>
            </div>
            <div class="signup-image">
                <img src="tradersignup.jpg" alt="Signup Image">
            </div>
        </div>
    </section>

    <!-- Footer Section -->
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