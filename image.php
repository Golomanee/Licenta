<?php
session_start();
require_once 'config/database.php';

// Check if user parameter is provided
if (!isset($_GET['id'])) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

$userId = intval($_GET['id']);

// Fetch profile image from database
$stmt = $conn->prepare("SELECT profileimage FROM UserDetails WHERE userid = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($imageData);
$stmt->fetch();
$stmt->close();

if ($imageData) {
    // Output image
    header("Content-Type: image/jpeg");
    header("Content-Length: " . strlen($imageData));
    header("Cache-Control: max-age=3600"); // Cache for 1 hour
    echo $imageData;
} else {
    // Return 404 if no image found
    header('HTTP/1.0 404 Not Found');
}
?>
