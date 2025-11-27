<?php
session_start();
require_once 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = $_POST['identifier'] ?? '';
    $password = $_POST['password_hash'] ?? '';
    
    if (!empty($identifier) && !empty($password)) {
        // Prepare statement to check user by name
        $stmt = $conn->prepare("SELECT * FROM user WHERE name = ?");
        $stmt->bind_param("s", $identifier);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Check if password matches (plain text comparison)
            if ($password === $user['password']) {
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'type' => $user['type']
                ];
                header('Location: index.php');
                exit;
            } else {
                $error = 'Nume sau parolă incorectă.';
            }
        } else {
            $error = 'Nume sau parolă incorectă.';
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
    <link rel="stylesheet" type="text/css" href="css/components/navbar.css">
    <link rel="stylesheet" type="text/css" href="css/pages/login.css">
</head>
<body>
    <nav class="top-navbar">
    <div class="navbar-right">
        <a href="login.php">Creeaza cont nou</a>
    </div>
    <div class="navbar-left">
        <a href="index.php">Acasa</a>
    </div>
</nav>
    
    <div class="page-container">
        <h1 class="page-title">Intră în cont</h1>
        
        <div class="login-box">
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
                Nu ai cont? <a href="#" class="signup-link">Înregistrează-te</a>
            </p>
        </div>
    </div>
</body>
</html>