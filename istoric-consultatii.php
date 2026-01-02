<?php
// Redirect la profile.php cu tab-ul de istoric
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

header('Location: profile.php?tab=istoric');
exit;
