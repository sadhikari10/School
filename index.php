<?php
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect to dashboard if already logged in
    header("Location: Common/dashboard.php");
    exit();
} else {
    // Redirect to login page if not logged in
    header("Location: Common/login.php");
    exit();
}
?>