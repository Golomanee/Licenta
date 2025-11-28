<?php
session_start();
require_once 'config/database.php';

$message = '';
$success = false;

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Check if token exists and is not expired
    $stmt = $conn->prepare("SELECT id, email FROM User WHERE verification_token = ? AND token_expires > NOW() AND email_verified = 0");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Verify the email
        $stmt = $conn->prepare("UPDATE User SET email_verified = 1, verification_token = NULL, token_expires = NULL WHERE id = ?");
        $stmt->bind_param("i", $user['id']);
        
        if ($stmt->execute()) {
            $success = true;
            $message = 'Email verificat cu succes! Te poți autentifica acum.';
        } else {
            $message = 'A apărut o eroare. Te rugăm să încerci din nou.';
        }
    } else {
        $message = 'Token invalid sau expirat. Te rugăm să soliciți un nou email de verificare.';
    }
    $stmt->close();
} else {
    $message = 'Token lipsă.';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Verificare Email - Spital</title>
    <link rel="stylesheet" type="text/css" href="css/base.css">
    <link rel="stylesheet" type="text/css" href="css/components/navbar.css">
    <link rel="stylesheet" type="text/css" href="css/pages/login.css">
</head>
<body>
    <nav class="top-navbar">
        <div class="navbar-right">
            <a href="login.php">Intră în cont</a>
        </div>
        <div class="navbar-left">
            <a href="index.php">Acasa</a>
        </div>
    </nav>
    
    <div class="page-container">
        <h1 class="page-title"><?php echo $success ? '✓ Succes!' : '✗ Eroare'; ?></h1>
        
        <div class="login-box" style="text-align: center;">
            <div class="<?php echo $success ? 'success-message' : 'error-message'; ?>">
                <?php echo $message; ?>
            </div>
            <?php if ($success): ?>
                <a href="login.php" class="btn btn-red" style="display: inline-block; margin-top: 20px; text-decoration: none;">Intră în cont</a>
            <?php else: ?>
                <a href="register.php" class="btn btn-grey" style="display: inline-block; margin-top: 20px; text-decoration: none;">Înregistrează-te din nou</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
