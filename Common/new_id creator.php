<?php
require 'connection.php';

// User details
$username = 'Sushant';
$email    = 'sushantadhikari70@gmail.com';
$plain_password = 'Sushant@123';
$branch   = 'dhapakhel';  // Added branch field

// Hash the password securely using PHP's built-in function
$hashed_password = password_hash($plain_password, PASSWORD_BCRYPT);

try {
    // Prepared statement to insert data (added branch field)
    $sql = "INSERT INTO login (username, email, password, branch) VALUES (:username, :email, :password, :branch)";
    $stmt = $pdo->prepare($sql);
    
    $stmt->execute([
        ':username' => $username,
        ':email'    => $email,
        ':password' => $hashed_password,
        ':branch'   => $branch
    ]);

    echo "User registered successfully!<br>";
    echo "Username: $username<br>";
    echo "Email: $email<br>";
    echo "Password (hashed): $hashed_password<br>";
    echo "Branch: $branch<br>";

} catch (PDOException $e) {
    if ($e->getCode() == 23000) { // Duplicate entry error
        echo "Error: Username or Email already exists!";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>