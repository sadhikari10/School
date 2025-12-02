<?php
session_start();
require '../Common/connection.php';  // Make sure this path is correct

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? '';

    if (empty($email) || empty($password) || empty($role)) {
        $error_message = 'Please fill all fields including role!';
    } else {
        try {
            $sql = "SELECT id, username, email, password, role, outlet_id 
                    FROM login 
                    WHERE email = :email LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verify password + selected role matches actual role in DB
            if ($user && password_verify($password, $user['password']) && $user['role'] === $role) {

                // Common session data
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['email']     = $user['email'];
                $_SESSION['role']      = $user['role'];

                if ($user['role'] === 'admin') {
                    // Admin: no outlet needed
                    $_SESSION['outlet_id'] = null;
                    header("Location: ../admin/index.php");
                    exit();
                } else {
                    // Staff: outlet_id must exist
                    if (!$user['outlet_id']) {
                        $error_message = 'This staff account has no outlet assigned. Contact admin.';
                    } else {
                        $_SESSION['outlet_id'] = (int)$user['outlet_id'];
                        header("Location: actions.php");  // Change to your staff dashboard if needed
                        exit();
                    }
                }
            } else {
                $error_message = 'Invalid email, password, or role selected!';
            }
        } catch (Exception $e) {
            $error_message = 'Login failed. Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - School Uniform System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 50%, #a569bd 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); padding: 40px;
            border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); width: 100%; max-width: 400px;
            text-align: center; border: 1px solid rgba(255,255,255,0.2);
        }
        .login-header i { font-size: 3rem; color: #8e44ad; margin-bottom: 15px; }
        .login-header h1 { color: #2c3e50; font-size: 2rem; font-weight: 700; margin-bottom: 5px; }
        .login-header p { color: #7f8c8d; }
        .form-group { position: relative; margin-bottom: 25px; text-align: left; }
        .form-group label { display: block; margin-bottom: 8px; color: #2c3e50; font-weight: 600; }
        .form-group input, .form-group select {
            width: 100%; padding: 15px 20px; border: 2px solid #e1e8ed; border-radius: 12px;
            font-size: 1rem; background: #f8f9fa; transition: all 0.3s ease;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none; border-color: #8e44ad; background: white;
            box-shadow: 0 0 0 3px rgba(142,68,173,0.1); transform: translateY(-2px);
        }
        .login-btn {
            width: 100%; padding: 15px; background: linear-gradient(135deg, #8e44ad, #9b59b6);
            color: white; border: none; border-radius: 12px; font-size: 1.1rem; font-weight: 600;
            cursor: pointer; transition: all 0.3s ease; margin-top: 10px;
        }
        .login-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(142,68,173,0.3); }
        .message {
            padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 500;
            display: flex; align-items: center; gap: 10px;
        }
        .error { background: #fee; color: #c53030; border: 1px solid #fed7d7; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-user-lock"></i>
            <h1>Welcome Back</h1>
            <p>Please sign in to continue</p>
        </div>

        <?php if ($error_message): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" name="email" id="email" required placeholder="Enter your email"
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" required placeholder="Enter your password">
            </div>

            <div class="form-group">
                <label for="role">Login As</label>
                <select name="role" id="role" required>
                    <option value="" disabled selected>-- Select Role --</option>
                    <option value="staff" <?php echo (isset($_POST['role']) && $_POST['role']==='staff') ? 'selected' : ''; ?>>
                        Staff (Outlet)
                    </option>
                    <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role']==='admin') ? 'selected' : ''; ?>>
                        Admin (Manager)
                    </option>
                </select>
            </div>

            <button type="submit" class="login-btn">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
        </form>
    </div>

    <script>
        document.getElementById('email').focus();

        // Password eye toggle
        const passField = document.getElementById('password');
        const container = passField.parentElement;
        container.style.position = 'relative';
        const eye = document.createElement('i');
        eye.className = 'fas fa-eye';
        eye.style.cssText = 'position:absolute; right:15px; top:50%; transform:translateY(-50%); cursor:pointer; color:#8e44ad; font-size:1.1rem;';
        container.appendChild(eye);

        eye.addEventListener('click', () => {
            const type = passField.type === 'password' ? 'text' : 'password';
            passField.type = type;
            eye.className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
        });
    </script>
</body>
</html>