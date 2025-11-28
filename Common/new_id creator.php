<?php
require 'connection.php';
// User details
$username = 'Sushant';
$email    = 'sushantadhikari70@gmail.com';
$plain_password = 'Sushant@123';

// Hash the password securely using PHP's built-in function
$hashed_password = password_hash($plain_password, PASSWORD_BCRYPT);

try {
    // Prepared statement to insert data
    $sql = "INSERT INTO login (username, email, password) VALUES (:username, :email, :password)";
    $stmt = $pdo->prepare($sql);
    
    $stmt->execute([
        ':username' => $username,
        ':email'    => $email,
        ':password' => $hashed_password
    ]);

    echo "User registered successfully!<br>";
    echo "Username: $username<br>";
    echo "Email: $email<br>";
    echo "Password (hashed): $hashed_password<br>";

} catch (PDOException $e) {
    if ($e->getCode() == 23000) { // Duplicate entry error
        echo "Error: Username or Email already exists!";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>