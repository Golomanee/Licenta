<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$userId = $user['id'];

// Fetch full user details from database
$stmt = $conn->prepare("SELECT * FROM user WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$userDetails = $result->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Profilul meu - Spital</title>
    <link rel="stylesheet" type="text/css" href="css/base.css">
    <link rel="stylesheet" type="text/css" href="css/components/navbar.css">
    <link rel="stylesheet" type="text/css" href="css/pages/profile.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="page-container">
        <h1 class="page-title">Profilul meu</h1>
        
        <div class="profile-box">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($userDetails['name'], 0, 1)); ?>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($userDetails['name']); ?></h2>
                    <p class="user-type"><?php echo htmlspecialchars(ucfirst($userDetails['type'])); ?></p>
                </div>
            </div>
            
            <div class="profile-details">
                <div class="detail-row">
                    <span class="detail-label">Nume:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($userDetails['name']); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Tip utilizator:</span>
                    <span class="detail-value"><?php echo htmlspecialchars(ucfirst($userDetails['type'])); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">ID:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($userDetails['id']); ?></span>
                </div>
            </div>
            
            <div class="profile-actions">
                <a href="edit-profile.php" class="btn btn-red">Editează profilul</a>
                <a href="change-password.php" class="btn btn-grey">Schimbă parola</a>
            </div>
        </div>
    </div>
</body>
</html>
