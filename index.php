<?php
session_start();

// Prevent caching - force revalidation
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>
<head>
    <title>My First PHP Page</title>
    <link rel="stylesheet" type="text/css" href="css/components/navbar.css">
    <link rel="stylesheet" type="text/css" href="css/components/appointment.css">
    <link rel="stylesheet" type="text/css" href="css/base.css">
</head>
<body>

<?php include 'includes/navbar.php'; ?>
<div class="page-container">
    <h1 class="page-title">Cere o programare</h1>

    <div class="appointment-box">
        <h2 class="appointment-title">Programeaza-te din contul tau!</h2>
        <p class="appointment-text">
            Daca ai cont, te poti programa direct din contul tau. Daca nu ai, îți poți face rapid un cont.
        </p>

        <div class="appointment-buttons">
            <a href="login.php" class="btn btn-red">Intră în cont</a>
            <a href="register.php" class="btn btn-grey">Cont nou</a>
        </div>
    </div>
</div>


<?php
?>

</body>
</html>