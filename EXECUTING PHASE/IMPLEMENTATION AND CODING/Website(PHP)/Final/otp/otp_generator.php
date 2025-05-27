<?php
/**
 * Generates a unique numeric OTP for email verification.
 * Ensures the OTP is not already in use in the CUSTOMER table.
 * 
 * @param resource $conn The Oracle database connection resource
 * @param int $length The length of the OTP (default: 6)
 * @return string A numeric OTP of specified length (e.g., "123456")
 * @throws Exception If unable to generate a unique OTP after max attempts or if connection fails
 */
function generateRandomCode($conn, $length = 6) {
    // Validate the database connection
    if (!is_resource($conn) || get_resource_type($conn) !== 'oci8 connection') {
        error_log("Invalid or no database connection in generateRandomCode at " . date('Y-m-d H:i:s'));
        throw new Exception("Invalid database connection");
    }

    // Validate the length parameter
    if ($length < 1 || $length > 20) { // Reasonable bounds for OTP length
        error_log("Invalid OTP length: $length in generateRandomCode at " . date('Y-m-d H:i:s'));
        throw new Exception("OTP length must be between 1 and 20");
    }

    $max_attempts = 10; // Maximum attempts to generate a unique OTP
    $attempt = 0;

    while ($attempt < $max_attempts) {
        // Generate a numeric OTP of specified length using random_int for cryptographic security
        $max_value = pow(10, $length) - 1; // e.g., for 6 digits: 999999
        $min_value = pow(10, $length - 1); // e.g., for 6 digits: 100000
        $code = sprintf("%0{$length}d", random_int($min_value, $max_value)); // Ensures exact length with leading zeros

        // Check if this OTP already exists in the CUSTOMER table
        $sql = "SELECT COUNT(*) FROM CUSTOMER WHERE VERIFICATION_CODE = :code";
        $stmt = oci_parse($conn, $sql);
        if (!$stmt) {
            error_log("Failed to parse OTP uniqueness query: " . oci_error($conn)['message'] . " at " . date('Y-m-d H:i:s'));
            throw new Exception("Failed to prepare OTP uniqueness query: " . oci_error($conn)['message']);
        }

        oci_bind_by_name($stmt, ":code", $code);
        $execute_success = oci_execute($stmt);
        if (!$execute_success) {
            $error = oci_error($stmt);
            error_log("OCI Execute Error in generateRandomCode: " . $error['message'] . " at " . date('Y-m-d H:i:s'));
            oci_free_statement($stmt);
            throw new Exception("Failed to check OTP uniqueness: " . $error['message']);
        }

        $row = oci_fetch_row($stmt);
        $count = $row[0];
        oci_free_statement($stmt);

        // If no matching OTP exists, return the code
        if ($count == 0) {
            return $code;
        }

        $attempt++;
    }

    // If we can't generate a unique OTP after max attempts, throw an exception
    throw new Exception("Unable to generate a unique OTP after $max_attempts attempts");
}
?>