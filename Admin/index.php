<?php
session_start();
require '../Common/connection.php';

// Security: Only allow admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Common/login.php");
    exit();
}

// Handle outlet selection
$selected_outlet = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['outlet_id'])) {
    $outlet_id = (int)$_POST['outlet_id'];
    
    // Verify outlet exists
    $stmt = $pdo->prepare("SELECT outlet_id, outlet_name FROM outlets WHERE outlet_id = ?");
    $stmt->execute([$outlet_id]);
    $outlet = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($outlet) {
        $_SESSION['selected_outlet_id']   = $outlet['outlet_id'];
        $_SESSION['selected_outlet_name'] = $outlet['outlet_name'];
        header("Location: dashboard.php");  // Change to your main admin page
        exit();
    }
}

// Fetch all outlets — using correct column name: outlet_id
$stmt = $pdo->query("SELECT outlet_id, outlet_name FROM outlets ORDER BY outlet_name");
$outlets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Select Outlet</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 50%, #a569bd 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;
        }
        .card {
            background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(12px); padding: 50px 40px;
            border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.2); width: 100%; max-width: 480px;
            text-align: center; border: 1px solid rgba(255,255,255,0.3);
        }
        .welcome-icon { font-size: 4rem; color: #8e44ad; margin-bottom: 20px; }
        h1 { color: #2c3e50; font-size: 2.2rem; margin-bottom: 10px; }
        p.greeting { color: #7f8c8d; font-size: 1.1rem; margin-bottom: 30px; }
        .outlet-select { margin: 30px 0; }
        select {
            width: 100%; padding: 18px 20px; border: 2px solid #e1e8ed; border-radius: 14px;
            font-size: 1.1rem; background: #f8f9fa; transition: all 0.3s ease; cursor: pointer;
        }
        select:focus {
            outline: none; border-color: #8e44ad; background: white;
            box-shadow: 0 0 0 4px rgba(142,68,173,0.15);
        }
        .btn {
            width: 100%; padding: 18px; background: linear-gradient(135deg, #8e44ad, #9b59b6);
            color: white; border: none; border-radius: 14px; font-size: 1.2rem; font-weight: 600;
            cursor: pointer; transition: all 0.3s ease; margin-top: 20px;
        }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(142,68,173,0.4); }
        .logout { margin-top: 30px; font-size: 0.95rem; }
        .logout a { color: #8e44ad; text-decoration: none; font-weight: 600; }
        .logout a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="card">
        <i class="fas fa-crown welcome-icon"></i>
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
        <p class="greeting">You are logged in as <strong>Administrator</strong></p>
        
        <form method="POST">
            <div class="outlet-select">
                <label for="outlet_id" style="display:block; margin-bottom:12px; color:#2c3e50; font-weight:600; font-size:1.1rem;">
                    Select Outlet to Manage
                </label>
                <select name="outlet_id" id="outlet_id" required>
                    <option value="" disabled selected>-- Choose an Outlet --</option>
                    <?php foreach ($outlets as $outlet): ?>
                        <option value="<?php echo $outlet['outlet_id']; ?>">
                            <?php echo htmlspecialchars($outlet['outlet_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn">
                Enter Dashboard
            </button>
        </form>

        <div class="logout">
            <a href="logout.php">Logout</a>
        </div>
    </div>
</body>
</html>