<?php
session_start();
require_once 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = $_POST['identifier'] ?? '';
    $password = $_POST['password_hash'] ?? '';
    
    if (!empty($identifier) && !empty($password)) {
        // Check if identifier contains @ to determine if it's email or name
        if (strpos($identifier, '@') !== false) {
            // Search by email in User table
            $stmt = $conn->prepare("SELECT u.*, ud.name FROM User u LEFT JOIN UserDetails ud ON u.id = ud.userid WHERE u.email = ?");
        } else {
            // Search by name in UserDetails table
            $stmt = $conn->prepare("SELECT u.*, ud.name FROM User u INNER JOIN UserDetails ud ON u.id = ud.userid WHERE ud.name = ?");
        }
        
        $stmt->bind_param("s", $identifier);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Check if email is verified
            if ($user['email_verified'] == 0) {
                $error = 'Te rugăm să verifici adresa de email înainte de a te autentifica.';
            }
            // Check if password matches (plain text comparison)
            else if ($password === $user['password']) {
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'role' => $user['role']
                ];
                $_SESSION['user_id'] = $user['id'];
                
                if ($user['role'] === 'admin') {
                    header('Location: admindashboard.php');
                    exit;
                }
                elseif ($user['role'] === 'doctor') {
                    header('Location: doctordashboard.php');
                    exit;
                }
                else {
                    header('Location: profile.php');
                    exit;
                }
            } else {
                $error = 'Email/nume sau parolă incorectă.';
            }
        } else {
            $error = 'Email/nume sau parolă incorectă.';
        }
        
        $stmt->close();
    } else {
        $error = 'Vă rugăm să completați toate câmpurile.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Intră în cont - Spital</title>
    <link rel="stylesheet" type="text/css" href="css/base.css">
    <link rel="stylesheet" type="text/css" href="css/pages/login.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-logo">
            <a href="index.php">
                <img src="images/logo.png" alt="Spital Logo">
            </a>
        </div>
        
        <div class="login-box">
            <h1 class="auth-title">Intră în cont</h1>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="identifier">Email sau nume</label>
                    <input 
                        type="text" 
                        name="identifier" 
                        id="identifier"
                        placeholder="Introduceți email sau nume" 
                        required
                        value="<?php echo htmlspecialchars($_POST['identifier'] ?? ''); ?>"
                    />
                </div>
                
                <div class="form-group">
                    <label for="password">Parolă</label>
                    <input 
                        type="password" 
                        name="password_hash" 
                        id="password"
                        placeholder="Introduceți parola" 
                        required
                    />
                </div>
                
                <div class="form-footer">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" />
                        Ține-mă minte
                    </label>
                    <a href="#" class="forgot-password">Ai uitat parola?</a>
                </div>
                
                <button type="submit" class="btn btn-red">Intră în cont</button>
            </form>
            
            <p class="signup-text">
                Nu ai cont? <a href="register.php" class="signup-link">Înregistrează-te</a>
            </p>
        </div>
        
        <div class="auth-footer">
            <a href="index.php">Acasă</a>
        </div>
    </div>
</body>
</html>