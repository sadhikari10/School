<?php
require '../Common/connection.php';   // adjust path if needed

// === New Admin User Details ===
$username       = 'Test Test';
$email          = 'test@gmail.com';
$phone_number   = '9840000000';
$shop_name      = 'Everest Clothe Store Pvt Ltd';   // ← Correct column name
$plain_password = 'Test@123';
$role           = 'staff';
$outlet_id      = 2;//null

// Secure password hash
$hashed_password = password_hash($plain_password, PASSWORD_BCRYPT);

try {
    $sql = "INSERT INTO login 
            (username, email, phone_number, shop_name, password, role, outlet_id) 
            VALUES 
            (:username, :email, :phone_number, :shop_name, :password, :role, :outlet_id)";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':username'     => $username,
        ':email'        => $email,
        ':phone_number' => $phone_number,
        ':shop_name'    => $shop_name,        // ← Fixed: shop_name
        ':password'     => $hashed_password,
        ':role'         => $role,
        ':outlet_id'    => $outlet_id
    ]);

    echo "<h2 style='color:green; text-align:center; font-family:Arial;'>Admin Account Created Successfully!</h2>";
    echo "<div style='font-family:Arial; background:#f0f8ff; padding:30px; border-radius:15px; max-width:700px; margin:30px auto; line-height:2.2; border:3px solid #27ae60;'>";
    echo "<strong>Full Name:</strong> $username<br>";
    echo "<strong>Email:</strong> $email<br>";
    echo "<strong>Phone Number:</strong> $phone_number<br>";
    echo "<strong>Shop Name:</strong> $shop_name<br>";
    echo "<strong>Role:</strong> <span style='color:#8e44ad; font-weight:bold; font-size:1.2em;'>$role</span><br>";
    echo "<strong>Outlet ID:</strong> $outlet_id<br>";
    echo "<strong>Password (plain):</strong> $plain_password<br>";
    echo "<hr>";
    echo "<small><strong>Hashed Password (saved in DB):</strong><br>";
    echo "<code style='font-size:10px; background:#eee; padding:10px; display:block; word-break:break-all;'>$hashed_password</code></small>";
    echo "</div>";

    echo "<div style='text-align:center; margin-top:40px;'>";
    echo "<a href='login.php' style='padding:16px 50px; background:#8e44ad; color:white; text-decoration:none; border-radius:50px; font-weight:bold; font-size:1.2rem; box-shadow:0 8px 20px rgba(0,0,0,0.2);'>
            Go to Login Page
          </a>";
    echo "</div>";

} catch (PDOException $e) {
    echo "<h2 style='color:red; text-align:center;'>Error Creating Account</h2>";
    echo "<div style='background:#ffebee; padding:25px; border-radius:12px; max-width:700px; margin:30px auto; font-family:Arial; border:2px solid #e74c3c;'>";

    if ($e->getCode() == 23000) {
        echo "<strong>Account Already Exists!</strong><br><br>";
        echo "A user with this <strong>email</strong>, <strong>username</strong>, or <strong>phone number</strong> is already registered.";
    } else {
        echo "<strong>Database Error:</strong><br><br>";
        echo $e->getMessage();
    }
    echo "</div>";
}
?>