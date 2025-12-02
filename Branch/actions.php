<?php
session_start();
require '../Common/connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';
$branch = $_SESSION['branch'] ?? '';

// Get user shop info
$stmt_user = $pdo->prepare("SELECT shop_name FROM login WHERE id = :id");
$stmt_user->execute([':id' => $user_id]);
$user = $stmt_user->fetch(PDO::FETCH_ASSOC);
$shop_name = $user['shop_name'] ?? 'Clothes Store';

// Handle redirects
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['go_to_sales'])) {
        header('Location: sales.php');
        exit;
    } elseif (isset($_POST['go_to_dashboard'])) {
        header('Location: dashboard.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actions - <?php echo htmlspecialchars($shop_name); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .actions-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            padding: 50px 40px;
            border-radius: 25px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 600px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .actions-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #667eea, #764ba2, #f093fb, #f5576c);
            animation: gradientShift 3s ease-in-out infinite;
        }

        @keyframes gradientShift {
            0%, 100% { transform: translateX(-100%); }
            50% { transform: translateX(100%); }
        }

        .welcome-header {
            margin-bottom: 40px;
        }

        .welcome-header i {
            font-size: 4rem;
            color: #667eea;
            margin-bottom: 20px;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }

        .welcome-header h1 {
            color: #2c3e50;
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .welcome-header p {
            color: #7f8c8d;
            font-size: 1.1rem;
            margin-bottom: 15px;
        }

        .shop-info {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 40px;
            border-left: 5px solid #667eea;
        }

        .shop-info h3 {
            color: #2c3e50;
            font-size: 1.3rem;
            margin-bottom: 5px;
        }

        .shop-info p {
            color: #6c757d;
            font-size: 1rem;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .action-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 25px;
            border-radius: 20px;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .action-card:hover::before {
            left: 100%;
        }

        .action-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.4);
        }

        .action-card i {
            font-size: 3rem;
            margin-bottom: 15px;
        }

        .action-card h3 {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .action-card p {
            font-size: 0.95rem;
            opacity: 0.9;
            line-height: 1.4;
        }

        .logout-section {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 2px dashed #e9ecef;
        }

        .logout-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .logout-btn:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(231, 76, 60, 0.3);
        }

        @media (max-width: 768px) {
            .actions-container {
                padding: 40px 25px;
                margin: 10px;
            }
            
            .welcome-header h1 {
                font-size: 2rem;
            }
            
            .actions-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="actions-container">
        <div class="welcome-header">
            <i class="fas fa-tachometer-alt"></i>
            <h1>Welcome Back, <?php echo htmlspecialchars($username); ?>!</h1>
            <p>Choose an action to continue</p>
        </div>

        <div class="shop-info">
            <h3><i class="fas fa-store"></i> <?php echo htmlspecialchars($shop_name); ?></h3>
            <p><i class="fas fa-map-marker-alt"></i> Branch: <?php echo htmlspecialchars($branch ?: 'Main'); ?></p>
        </div>

        <form method="POST" style="display: contents;">
            <div class="actions-grid">
                <a href="sales.php" class="action-card" onclick="this.closest('form').querySelector('input[name=go_to_sales]').click(); return false;">
                    <i class="fas fa-chart-bar"></i>
                    <h3>Sales Reports</h3>
                    <p>View detailed sales analytics, daily reports, and billing history</p>
                </a>

                <a href="dashboard.php" class="action-card" onclick="this.closest('form').querySelector('input[name=go_to_dashboard]').click(); return false;">
                    <i class="fas fa-school"></i>
                    <h3>School Dashboard</h3>
                    <p>Manage school orders, track uniforms, and create new bills</p>
                </a>
            </div>
        </form>

        <div class="logout-section">
            <a href="logout.php" class="logout-btn" onclick="return confirm('Are you sure you want to logout?')">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </div>

    <script>
        // Add click animation to action cards
        document.querySelectorAll('.action-card').forEach(card => {
            card.addEventListener('click', function() {
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 150);
            });
        });

        // Add loading state to form submissions
        document.querySelectorAll('input[type="hidden"]').forEach(input => {
            input.addEventListener('click', function() {
                const card = this.closest('.action-card');
                if (card) {
                    const icon = card.querySelector('i');
                    const originalIcon = icon.innerHTML;
                    icon.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                }
            });
        });
    </script>
</body>
</html>