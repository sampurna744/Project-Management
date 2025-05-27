<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Only POST method is allowed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['uid']) || !isset($data['name']) || !isset($data['description'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields"]);
    exit;
}

$uuid = $data['uid']; // Map 'uid' to 'UUID'
$tag_name = $data['name']; // Map 'name' to 'TAG_NAME'
$description = $data['description']; // Map 'description' to 'DESCRIPTION'

// Validate UUID (string, e.g., hexadecimal RFID tag ID)
if (empty($uuid) || !is_string($uuid)) {
    http_response_code(400);
    echo json_encode(["error" => "UID must be a non-empty string"]);
    exit;
}

include("connection/connection.php");

// 1. Check for existing UUID
$checkSql = "SELECT ID FROM RFID_TAGS WHERE UUID = :bind_uuid";
$checkStmt = oci_parse($conn, $checkSql);
oci_bind_by_name($checkStmt, ":bind_uuid", $uuid);

if (!oci_execute($checkStmt)) {
    $e = oci_error($checkStmt);
    http_response_code(500);
    echo json_encode(["error" => "Failed to check UUID: " . $e['message']]);
    oci_free_statement($checkStmt);
    oci_close($conn);
    exit;
}

$row = oci_fetch_assoc($checkStmt);

if ($row) {
    http_response_code(422);
    echo json_encode(["error" => "Tag already exists"]);
    oci_free_statement($checkStmt);
    oci_close($conn);
    exit;
}
oci_free_statement($checkStmt);

// 2. Generate new ID manually (from MAX)
$getIdSql = "SELECT NVL(MAX(ID), 0) + 1 AS NEW_ID FROM RFID_TAGS";
$getIdStmt = oci_parse($conn, $getIdSql);
if (!oci_execute($getIdStmt)) {
    $e = oci_error($getIdStmt);
    http_response_code(500);
    echo json_encode(["error" => "Failed to generate ID: " . $e['message']]);
    oci_free_statement($getIdStmt);
    oci_close($conn);
    exit;
}
$row = oci_fetch_assoc($getIdStmt);
$newId = $row['NEW_ID'];
oci_free_statement($getIdStmt);

// 3. Insert new tag
$insertSql = 'INSERT INTO RFID_TAGS (ID, UUID, TAG_NAME, DESCRIPTION) VALUES (:bind_id, :bind_uuid, :bind_name, :bind_desc)';
$insertStmt = oci_parse($conn, $insertSql);
oci_bind_by_name($insertStmt, ":bind_id", $newId);
oci_bind_by_name($insertStmt, ":bind_uuid", $uuid);
oci_bind_by_name($insertStmt, ":bind_name", $tag_name);
oci_bind_by_name($insertStmt, ":bind_desc", $description);

if (oci_execute($insertStmt)) {
    http_response_code(201);
    echo json_encode(["message" => "Tag saved successfully"]);
} else {
    $e = oci_error($insertStmt);
    http_response_code(500);
    echo json_encode(["error" => "Failed to save tag: " . $e['message']]);
}

oci_free_statement($insertStmt);
oci_close($conn);
?>