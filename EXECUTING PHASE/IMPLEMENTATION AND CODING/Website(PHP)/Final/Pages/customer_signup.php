<?php
// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Variable for Input_validation
$input_validation_passed = true;

include("connection/connection.php");

if (isset($_POST["submit_sign_up"]) && isset($_POST["terms"])) {
    // Input Sanitization
    require("input_validation/input_sanitization.php");
    $first_name = isset($_POST["first-name"]) ? sanitizeFirstName($_POST["first-name"]) : "";
    $last_name = isset($_POST["last-name"]) ? sanitizeLastName($_POST["last-name"]) : "";
    $email = isset($_POST["email"]) ? sanitizeEmail($_POST["email"]) : "";
    $password = isset($_POST["password"]) ? sanitizePassword($_POST["password"]) : "";
    $confirm_password = isset($_POST["confirm-password"]) ? sanitizePassword($_POST["confirm-password"]) : "";
    $gender = isset($_POST["gender"]) ? sanitizeGender($_POST["gender"]) : "";
    $contact_number = isset($_POST["contact"]) ? sanitizeContactNumber($_POST["contact"]) : "";

    // Input Validation
    require("input_validation/input_validation.php");
    $email_error = "";
    if (emailExists($email)) {
        $email_error = "Email Already Exists!!!";
        $input_validation_passed = false;
    }

    $first_name_error = "";
    if (validateFirstName($first_name) === "false") {
        $first_name_error = "Please Enter a Correct First Name";
        $input_validation_passed = false;
    }

    $last_name_error = "";
    if (validateLastName($last_name) === "false") {
        $last_name_error = "Please Enter a Correct Last Name";
        $input_validation_passed = false;
    }

    $contact_no_error = "";
    if (validateContactNumber($contact_number) === "false") {
        $contact_no_error = "Please Provide a Contact number (10-15 digits)";
        $input_validation_passed = false;
    }

    $password_error = "";
    if (validatePassword($_POST["password"]) === "false") {
        $password_error = "Password must contain at least six characters including one lowercase letter, one uppercase letter, and one digit.";
        $input_validation_passed = false;
    }

    $reenter_password_error = "";
    if (validateConfirmPassword($_POST["password"], $_POST["confirm-password"]) === "false") {
        $reenter_password_error = "Passwords Didn't Match";
        $input_validation_passed = false; // Fixed: Set validation flag to false
    }

    $gender_error = "";
    if (validateGender($gender) === "false") {
        $gender_error = "Please Select Your Gender.";
        $input_validation_passed = false;
    }

    $user_role = "customer";
    $todayDate = date('Y-m-d');
    $update_date = date('Y-m-d');
    try {
        require("otp/otp_generator.php");
        $verification_code = generateRandomCode($conn);
    } catch (Exception $e) {
        $general_error_message = "Failed to generate verification code: " . $e->getMessage();
        $input_validation_passed = false;
    }

    if ($input_validation_passed) {
        // Hash the password before storing
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Begin transaction by disabling autocommit
        $success = true;

        // Insert into CLECK_USER
        $sql_insert_user = "INSERT INTO CLECK_USER (
            first_name, last_name, user_email, user_gender, user_password, user_type, user_contact_no
        ) VALUES (
            :first_name, :last_name, :user_email, :user_gender, :user_password, 'customer', :user_contact_no
        ) RETURNING user_id INTO :user_id";
        $stmt_insert_user = oci_parse($conn, $sql_insert_user);

        // Bind input parameters
        oci_bind_by_name($stmt_insert_user, ':first_name', $first_name, -1, SQLT_CHR);
        oci_bind_by_name($stmt_insert_user, ':last_name', $last_name, -1, SQLT_CHR);
        oci_bind_by_name($stmt_insert_user, ':user_email', $email, -1, SQLT_CHR);
        oci_bind_by_name($stmt_insert_user, ':user_gender', $gender, -1, SQLT_CHR);
        oci_bind_by_name($stmt_insert_user, ':user_password', $hashed_password);
        oci_bind_by_name($stmt_insert_user, ':user_contact_no', $contact_number);

        // Bind output parameter for user_id
        $user_id = 0;
        oci_bind_by_name($stmt_insert_user, ':user_id', $user_id, -1, SQLT_INT);

        if (!oci_execute($stmt_insert_user, OCI_NO_AUTO_COMMIT)) {
            $error = oci_error($stmt_insert_user);
            error_log("Error inserting user: " . $error['message'], 3, 'error.log');
            $success = false;
        }

        oci_free_statement($stmt_insert_user);

        if ($success) {
            // Insert into CUSTOMER
            $sql = "INSERT INTO CUSTOMER 
                    (customer_date_joined, verification_code, date_updated, verified_customer, user_id) 
                    VALUES 
                    (TO_DATE(:customer_date_joined, 'YYYY-MM-DD'), :verification_code, TO_DATE(:date_updated, 'YYYY-MM-DD'), :verified_customer, :user_id)";
            $stmt = oci_parse($conn, $sql);

            $verified_customer = 0;

            oci_bind_by_name($stmt, ':customer_date_joined', $todayDate);
            oci_bind_by_name($stmt, ':verification_code', $verification_code);
            oci_bind_by_name($stmt, ':date_updated', $update_date);
            oci_bind_by_name($stmt, ':verified_customer', $verified_customer);
            oci_bind_by_name($stmt, ':user_id', $user_id);

            if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
                $error = oci_error($stmt);
                error_log("Error inserting customer: " . $error['message'], 3, 'error.log');
                $success = false;
            }

            oci_free_statement($stmt);
        }

        if ($success) {
            // Send verification email
            require("PHPMailer-master/email.php");
            $full_name = $first_name . " " . $last_name;
            sendVerificationEmail($email, $verification_code, $full_name);

            // Commit transaction
            oci_commit($conn);

            header("Location: email_verify.php?user_id=$user_id&email=$email");
            exit();
        } else {
            // Rollback transaction
            oci_rollback($conn);
            $general_error_message = "An error occurred during registration. Please try again.";
        }

        oci_close($conn);
    } else {
        $general_error_message = "Validation failed. Please check the form for errors.";
    }
} else {
    $checkbox_error = "Please Agree to Our Terms and Conditions";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CleckFax Traders - Sign Up</title>
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
            width: 100%;
            height: 110%;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .signup-image img {
            max-width: 100%;
            height: 100%;
        }
        .logo-container img {
            max-width: 150px;
            margin-bottom: 0rem;
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
    </style>
</head>
<body>
    <!-- Navbar Section -->
    <?php include('navbar.php'); ?>

    <!-- Signup Section -->
    <section class="section">
        <div class="signup-container">
            <div class="signup-form">
                <div class="logo-container has-text-centered">
                    <img src="CleckFax_Traders_Hub_Logo_group6-removebg-preview.png" alt="CleckFax Traders Logo">
                </div>
                <h1 class="title has-text-centered">Get started now</h1>
                <button class="button social google">
                    <span>Sign up with Google</span>
                </button>
                <button class="button social facebook">
                    <span>Sign up with Facebook</span>
                </button>
                <div class="divider">OR</div>
                <p class="has-text-centered" style="margin-bottom: 1rem; color: #7a7a7a;">
                    You have the option to register using either your email or phone number
                </p>
                <?php if (!empty($general_error_message)) { ?>
                    <p class="has-text-centered error-message"><?php echo $general_error_message; ?></p>
                <?php } ?>
                <form method="POST" id="customer_signup" name="customer_signup" action="">
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
                            <input class="input" type="tel" id="contact" name="contact" placeholder="Phone Number" pattern="[0-9]{10,15}" title="Please enter 10 to 15 numeric characters" required>
                        </div>
                        <?php if (!empty($contact_no_error)) { ?>
                            <p class="error-message"><?php echo $contact_no_error; ?></p>
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
                        <label class="checkbox">
                            <input type="checkbox" id="terms" name="terms" required> I agree to the Terms and Conditions
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
                    <p>Have an account? <a href="customer_signin.php">Sign In</a></p>
                    <p><a href="traderregister.php">Become a seller</a></p>
                </div>
            </div>
            <div class="signup-image">
                <img src="signup.jpeg" alt="Signup Image">
            </div>
        </div>
    </section>

    <!-- Footer Section -->
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