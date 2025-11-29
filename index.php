<?php
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect to dashboard if already logged in
    header("Location: Branch/dashboard.php");
    exit();
} else {
    // Redirect to login page if not logged in
    header("Location: Branch/login.php");
    exit();
}
?>