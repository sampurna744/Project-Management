<?php
// rfid.php

header('Content-Type: application/json');

// Get JSON POST body
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['uid'])) {
    http_response_code(400);
    echo json_encode(['error' => 'UID missing']);
    exit;
}

$uid = strtoupper(trim($input['uid']));
$timestamp = date('Y-m-d H:i:s');

// Oracle DB connection details


$conn = oci_connect('test', 'test', '//localhost/xe');
if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Prepare SQL insert
$sql = "INSERT INTO RFID_READ ( rfid, times) VALUES ( :rfid, TO_TIMESTAMP(:times, 'YYYY-MM-DD HH24:MI:SS'))";

$stid = oci_parse($conn, $sql);

oci_bind_by_name($stid, ':rfid', $uid); // Assuming 'rfid' is same as 'rfid_id' for now
oci_bind_by_name($stid, ':times', $timestamp);

$exec = @oci_execute($stid);

if (!$exec) {
    $e = oci_error($stid);
    http_response_code(500);
    echo json_encode(['error' => 'Insert failed', 'details' => $e['message']]);
} else {
    echo json_encode(['success' => true, 'uid' => $uid]);
}

oci_free_statement($stid);
oci_close($conn);
?>
