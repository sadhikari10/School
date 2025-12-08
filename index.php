<?php
session_start();


    // Redirect to login page if not logged in
    header("Location: Common/login.php");
    exit();

?>