<?php
require_once '../db_connect.php';

$conn = getDatabaseConnection();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$data = json_decode(file_get_contents("php://input"), true);
$roomId = $data['roomId'] ?? '';

if (empty($roomId)) {
    http_response_code(400);
    echo json_encode(['valid' => false, 'error' => 'Room ID is required']);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM rooms WHERE room_id = ?");
$stmt->bind_param("s", $roomId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['valid' => true]);
} else {
    echo json_encode(['valid' => false, 'error' => 'Room not found']);
}

$conn->close();
