<?php
// Ensure no whitespace before opening tag
try {
    // Attempt to connect to Oracle database
    $conn = oci_connect('test', 'test', '//localhost/xe');
    
    if (!$conn) {
        $error = oci_error();
        throw new Exception('Database connection failed: ' . $error['message']);
    }
} catch (Exception $e) {
    // Log error and display user-friendly message
    error_log($e->getMessage(), 3, 'error.log');
    die('Unable to connect to the database. Please try again later.');
}
// No closing PHP tag to prevent whitespace issues