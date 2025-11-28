<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<nav class="top-navbar">
    <div class="navbar-right">
        <a href="index.php" class="navbar-logo">
            <img src="images/logo.png" alt="Spital Logo">
        </a>
        <?php if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'doctor'): ?>
            <a href="#">Programează-te</a>
        <?php endif; ?>
        <?php if (isset($_SESSION['user'])): ?>
            <div class="dropdown">
                <button class="dropdown-btn"><?php echo htmlspecialchars($_SESSION['user']['name']); ?> ▼</button>
                <div class="dropdown-content">
                    <a href="profile.php">Profilul meu</a>
                    <a href="#">Setări</a>
                    <a href="logout.php">Ieșire</a>
                </div>
            </div>
        <?php else: ?>
            <a href="login.php">Intră în cont</a>
        <?php endif; ?>
    </div>
    
    <div class="navbar-left">
        <a href="#">Medici</a>
        <a href="#">Specialitati</a>
        <a href="#">EDU</a>
    </div>
</nav>
