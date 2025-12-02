<?php
// logout.php

session_start();

// Destroy ALL session data
$_SESSION = array();

// If you want to destroy the session cookie as well (recommended)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Finally, destroy the session
session_destroy();

// Redirect to login page (or home page)
header("Location: ../Common/login.php");
exit();
?>