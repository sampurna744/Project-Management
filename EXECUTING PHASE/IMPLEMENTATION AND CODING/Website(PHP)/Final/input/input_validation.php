<?php
// Centralized database connection (assumes connection.php is included elsewhere)
global $conn;

// Function to validate Email for Uniqueness
function emailExists($email) {
    global $conn;

    if (!$conn) {
        error_log("No database connection in emailExists at " . date('Y-m-d H:i:s'));
        return false;
    }

    $email = trim(filter_var($email, FILTER_SANITIZE_EMAIL));
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $sql_query = "SELECT COUNT(*) FROM CLECK_USER WHERE LOWER(user_email) = LOWER(:email)";
    $stmt = oci_parse($conn, $sql_query);
    if (!$stmt) {
        error_log("Failed to parse emailExists query: " . oci_error($conn)['message'] . " at " . date('Y-m-d H:i:s'));
        return false;
    }

    oci_bind_by_name($stmt, ":email", $email);
    $result = oci_execute($stmt);

    if (!$result) {
        error_log("OCI Execute Error in emailExists: " . oci_error($stmt)['message'] . " at " . date('Y-m-d H:i:s'));
        oci_free_statement($stmt);
        return false;
    }

    $row = oci_fetch_array($stmt, OCI_NUM);
    $email_count = $row[0];
    oci_free_statement($stmt);

    return $email_count > 0;
}

// Function to validate First Name
function validateFirstName($first_name) {
    $first_name = trim($first_name);
    if (empty($first_name) || strlen($first_name) < 2 || !preg_match("/^[a-zA-Z'-]+$/", $first_name)) {
        error_log("Invalid first name: $first_name at " . date('Y-m-d H:i:s'));
        return false;
    }
    return true;
}

// Function to validate Last Name
function validateLastName($last_name) {
    $last_name = trim($last_name);
    if (empty($last_name) || strlen($last_name) < 2 || !preg_match("/^[a-zA-Z'-]+$/", $last_name)) {
        error_log("Invalid last name: $last_name at " . date('Y-m-d H:i:s'));
        return false;
    }
    return true;
}

// Function to validate email
function validateEmail($email) {
    $email = trim(filter_var($email, FILTER_SANITIZE_EMAIL));
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email: $email at " . date('Y-m-d H:i:s'));
        return false;
    }
    return true;
}

// Function to validate address
function validateAddress($address) {
    $address = trim($address);
    if (empty($address) || !preg_match("/^[A-Za-z0-9\s,.-]+$/", $address)) { // Added more flexibility
        error_log("Invalid address: $address at " . date('Y-m-d H:i:s'));
        return false;
    }
    return true;
}

// Function to validate contact number
function validateContactNumber($contact_number) {
    $contact_number = trim($contact_number);
    if (empty($contact_number) || !preg_match("/^[0-9]{10,15}$/", $contact_number)) {
        error_log("Invalid contact number: $contact_number at " . date('Y-m-d H:i:s'));
        return false;
    }
    return true;
}

// Function to validate password
function validatePassword($password) {
    $password = trim($password);
    if (empty($password) || strlen($password) < 6 || !preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}$/", $password)) {
        error_log("Invalid password: $password at " . date('Y-m-d H:i:s'));
        return false;
    }
    return true;
}

// Function to validate confirm password
function validateConfirmPassword($password, $confirm_password) {
    $password = trim($password);
    $confirm_password = trim($confirm_password);
    if ($password !== $confirm_password) {
        error_log("Password mismatch: $password vs $confirm_password at " . date('Y-m-d H:i:s'));
        return false;
    }
    return true;
}

// Function to validate date of birth
function validateDateOfBirth($dateOfBirth) {
    $dateOfBirth = trim($dateOfBirth);
    if (empty($dateOfBirth) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOfBirth)) {
        error_log("Invalid date format: $dateOfBirth at " . date('Y-m-d H:i:s'));
        return false;
    }
    $dob = DateTime::createFromFormat('Y-m-d', $dateOfBirth);
    if ($dob === false) {
        error_log("Invalid date value: $dateOfBirth at " . date('Y-m-d H:i:s'));
        return false;
    }
    $now = new DateTime();
    $age = $now->diff($dob)->y;
    if ($dob > $now || $age < 18) {
        error_log("Underage or future date: $dateOfBirth at " . date('Y-m-d H:i:s'));
        return false;
    }
    return true;
}

// Function to validate gender
function validateGender($gender) {
    $gender = trim(strtolower($gender));
    if (empty($gender) || !in_array($gender, ['male', 'female', 'other'])) {
        error_log("Invalid gender: $gender at " . date('Y-m-d H:i:s'));
        return false;
    }
    return true;
}

// Function to validate company registration number
function validateCompanyRegistrationNo($registrationNo) {
    $registrationNo = trim($registrationNo);
    if (empty($registrationNo) || !preg_match("/^[A-Za-z0-9-]+$/", $registrationNo)) { // Added hyphen for flexibility
        error_log("Invalid company registration number: $registrationNo at " . date('Y-m-d H:i:s'));
        return false;
    }
    return true;
}

// Function to validate shop name
function validateShopName($shopName) {
    $shopName = trim($shopName);
    if (empty($shopName) || !preg_match("/^[A-Za-z0-9\s,.-]+$/", $shopName)) { // Added more flexibility
        error_log("Invalid shop name: $shopName at " . date('Y-m-d H:i:s'));
        return false;
    }
    return true;
}

// Function to validate shop description
function validateShopDescription($description) {
    $description = trim($description);
    if (empty($description)) {
        error_log("Empty shop description at " . date('Y-m-d H:i:s'));
        return false;
    }
    return true;
}

// Function to validate category
function validateCategory($category) {
    $category = trim($category);
    if (empty($category) || !preg_match("/^[A-Za-z0-9\s]+$/", $category)) { // Added space for multi-word categories
        error_log("Invalid category: $category at " . date('Y-m-d H:i:s'));
        return false;
    }
    return true;
}

// Function to validate product name for uniqueness
function productNameExists($productName) {
    global $conn;

    if (!$conn) {
        error_log("No database connection in productNameExists at " . date('Y-m-d H:i:s'));
        return false;
    }

    $productName = trim($productName);
    $sql_query = "SELECT COUNT(*) FROM product WHERE LOWER(product_name) = LOWER(:productName)";
    $stmt = oci_parse($conn, $sql_query);
    if (!$stmt) {
        error_log("Failed to parse productNameExists query: " . oci_error($conn)['message'] . " at " . date('Y-m-d H:i:s'));
        return false;
    }

    oci_bind_by_name($stmt, ":productName", $productName);
    $result = oci_execute($stmt);

    if (!$result) {
        error_log("OCI Execute Error in productNameExists: " . oci_error($stmt)['message'] . " at " . date('Y-m-d H:i:s'));
        oci_free_statement($stmt);
        return false;
    }

    $row = oci_fetch_array($stmt, OCI_NUM);
    $productNameCount = $row[0];
    oci_free_statement($stmt);

    return $productNameCount > 0;
}
?>