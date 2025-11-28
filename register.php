<?php
session_start();
require_once 'config/database.php';
require_once 'config/mailer.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $name = $_POST['name'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (!empty($email) && !empty($name) && !empty($password) && !empty($confirmPassword)) {
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Adresa de email nu este validă.';
        } 
        // Check if passwords match
        else if ($password !== $confirmPassword) {
            $error = 'Parolele nu coincid.';
        }
        // Check if email already exists
        else {
            $stmt = $conn->prepare("SELECT id FROM User WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = 'Acest email este deja folosit.';
                $stmt->close();
            } else {
                $stmt->close();
                
                // Generate verification token
                $verification_token = bin2hex(random_bytes(32));
                $token_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                
                // Insert into User table
                $role = 'patient'; // Default role
                $stmt = $conn->prepare("INSERT INTO User (email, password, role, email_verified, verification_token, token_expires) VALUES (?, ?, ?, 0, ?, ?)");
                $stmt->bind_param("sssss", $email, $password, $role, $verification_token, $token_expires);
                
                if ($stmt->execute()) {
                    $userId = $conn->insert_id;
                    
                    // Insert into UserDetails table
                    $stmt2 = $conn->prepare("INSERT INTO UserDetails (userid, name) VALUES (?, ?)");
                    $stmt2->bind_param("is", $userId, $name);
                    $stmt2->execute();
                    $stmt2->close();
                    
                    // Send verification email using PHPMailer
                    if (sendVerificationEmail($email, $name, $verification_token)) {
                        $success = 'Cont creat cu succes! Verifică-ți emailul pentru a-ți activa contul.';
                    } else {
                        // If email fails, show verification link
                        $verification_link = "http://" . $_SERVER['HTTP_HOST'] . "/spital/verify-email.php?token=" . $verification_token;
                        $success = 'Cont creat cu succes! Email-ul nu a putut fi trimis. <a href="' . $verification_link . '" target="_blank">Verifică acum</a>';
                    }
                } else {
                    $error = 'Eroare la crearea contului. Încercați din nou.';
                }
                $stmt->close();
            }
        }
    } else {
        $error = 'Vă rugăm să completați toate câmpurile.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Înregistrare - Spital</title>
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
        <h1 class="page-title">Înregistrare</h1>
        
        <div class="login-box">
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input 
                        type="email" 
                        name="email" 
                        id="email"
                        placeholder="Introduceți adresa de email" 
                        required
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    />
                </div>
                
                <div class="form-group">
                    <label for="name">Nume</label>
                    <input 
                        type="text" 
                        name="name" 
                        id="name"
                        placeholder="Introduceți numele complet" 
                        required
                        value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                    />
                </div>
                
                <div class="form-group">
                    <label for="password">Parolă</label>
                    <input 
                        type="password" 
                        name="password" 
                        id="password"
                        placeholder="Introduceți parola" 
                        required
                    />
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirmă parola</label>
                    <input 
                        type="password" 
                        name="confirm_password" 
                        id="confirm_password"
                        placeholder="Confirmați parola" 
                        required
                    />
                </div>
                
                <button type="submit" class="btn btn-red">Creează cont</button>
            </form>
            
            <p class="signup-text">
                Ai deja cont? <a href="login.php" class="signup-link">Intră în cont</a>
            </p>
        </div>
    </div>
</body>
</html>
