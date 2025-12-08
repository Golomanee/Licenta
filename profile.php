<?php
session_start();

// Prevent caching - force revalidation
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$userId = $user['id'];

// Fetch full user details from database (join User and UserDetails tables)
$stmt = $conn->prepare("SELECT u.*, ud.name, ud.birthday, ud.phone, ud.country, ud.city, ud.height, ud.weight, ud.profileimage FROM User u LEFT JOIN UserDetails ud ON u.id = ud.userid WHERE u.id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$userDetails = $result->fetch_assoc();
$stmt->close();

// Calculate age from birthday
$age = null;
if ($userDetails['birthday']) {
    $birthDate = new DateTime($userDetails['birthday']);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;
}
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
                    <?php if (!empty($userDetails['profileimage'])): ?>
                        <img src="image.php?id=<?php echo $userId; ?>" alt="Profile Picture" class="profile-image">
                    <?php else: ?>
                        <?php echo strtoupper(substr($userDetails['name'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($userDetails['name']); ?></h2>
                    <p class="user-type"><?php echo htmlspecialchars(ucfirst($userDetails['role'])); ?></p>
                </div>
            </div>
            
            <div class="profile-details">
                <div class="details-section">
                    <h3 class="section-title">Informații personale</h3>
                    <div class="detail-row">
                        <span class="detail-label">Email:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($userDetails['email']); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Data nașterii:</span>
                        <span class="detail-value"><?php echo $userDetails['birthday'] ? htmlspecialchars(date('d.m.Y', strtotime($userDetails['birthday']))) : 'Nu este setată'; ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Vârstă:</span>
                        <span class="detail-value"><?php echo $age ? $age . ' ani' : 'Nu este setată'; ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Telefon:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($userDetails['phone'] ?? 'Nu este setat'); ?></span>
                    </div>
                </div>
                
                <div class="details-section">
                    <h3 class="section-title">Locație</h3>
                    <div class="detail-row">
                        <span class="detail-label">Țară:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($userDetails['country'] ?? 'Nu este setată'); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Oraș:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($userDetails['city'] ?? 'Nu este setat'); ?></span>
                    </div>
                </div>
                
                <div class="details-section">
                    <h3 class="section-title">Informații fizice</h3>
                    <div class="detail-row">
                        <span class="detail-label">Înălțime:</span>
                        <span class="detail-value"><?php echo $userDetails['height'] ? htmlspecialchars($userDetails['height']) . ' cm' : 'Nu este setată'; ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Greutate:</span>
                        <span class="detail-value"><?php echo $userDetails['weight'] ? htmlspecialchars($userDetails['weight']) . ' kg' : 'Nu este setată'; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="profile-actions">
                <a href="settings.php" class="btn btn-red">Editează profilul</a>
                <a href="change-password.php" class="btn btn-grey">Schimbă parola</a>
            </div>
        </div>
    </div>
</body>
</html>
