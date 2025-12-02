<?php
session_start();
require '../Common/connection.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin' || 
    !isset($_SESSION['selected_outlet_id']) || !isset($_SESSION['selected_school_id'])) {
    header("Location: index.php");
    exit();
}

$outlet_id      = $_SESSION['selected_outlet_id'];
$outlet_name    = $_SESSION['selected_outlet_name'];
$school_id      = $_SESSION['selected_school_id'];
$school_name    = $_SESSION['selected_school_name'];

// === ADD NEW ITEM ===
if (isset($_POST['add_item'])) {
    $item_name = trim($_POST['item_name']);
    
    if (empty($item_name)) {
        $_SESSION['error'] = "Item name cannot be empty.";
    } else {
        // Prevent duplicate item name for this school
        $check = $pdo->prepare("SELECT item_id FROM items WHERE item_name = ? AND school_id = ?");
        $check->execute([$item_name, $school_id]);
        
        if ($check->fetch()) {
            $_SESSION['error'] = "Item '$item_name' already exists for this school!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO items (item_name, school_id) VALUES (?, ?)");
            $stmt->execute([$item_name, $school_id]);
            $_SESSION['success'] = "Item added successfully!";
        }
    }
    header("Location: school_items.php");
    exit();
}

// Fetch all items for this school
$stmt = $pdo->prepare("SELECT item_id, item_name FROM items WHERE school_id = ? ORDER BY item_name");
$stmt->execute([$school_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Items - <?php echo htmlspecialchars($school_name); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 50%, #a569bd 100%);
            min-height: 100vh; padding: 20px;
        }
        .container { max-width: 1000px; margin: 0 auto; }
        .header {
            background: rgba(255,255,255,0.95); padding: 25px 30px; border-radius: 18px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.15); text-align: center; margin-bottom: 30px;
            backdrop-filter: blur(10px);
        }
        .header h1 { color: #2c3e50; font-size: 2.2rem; margin-bottom: 8px; }
        .badge { 
            display: inline-block; background: #27ae60; color: white; padding: 8px 20px; 
            border-radius: 50px; font-weight: 600; margin: 8px; 
        }
        .card {
            background: rgba(255,255,255,0.95); border-radius: 18px; padding: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.15); backdrop-filter: blur(10px);
        }
        .section-title { font-size: 1.6rem; color: #2c3e50; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }

        .alert { padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background: #8e44ad; color: white; padding: 15px; text-align: left; }
        td { padding: 15px; border-bottom: 1px solid #eee; }
        tr:hover { background: #f9f9ff; }
        .item-name { font-weight: 600; color: #2c3e50; }

        .add-form {
            display: flex; gap: 15px; margin-top: 25px; flex-wrap: wrap;
        }
        .add-form input {
            flex: 1; min-width: 280px; padding: 14px; border: 2px solid #e1e8ed;
            border-radius: 12px; font-size: 1rem;
        }
        .add-form button {
            padding: 14px 32px; background: linear-gradient(135deg, #8e44ad, #9b59b6);
            color: white; border: none; border-radius: 12px; font-weight: 600; cursor: pointer;
        }

        .links { margin-top: 30px; }
        .links a { color: #8e44ad; text-decoration: none; font-weight: 600; margin: 0 15px; }
        .links a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>School Items</h1>
            <p>Items list for</p>
            <div class="badge"><?php echo htmlspecialchars($school_name); ?></div>
            <div class="badge"><?php echo htmlspecialchars($outlet_name); ?></div>
        </div>

        <div class="card">
            <h2 class="section-title">Items List (<?php echo count($items); ?>)</h2>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <?php if (empty($items)): ?>
                <p style="text-align:center; color:#7f8c8d; font-size:1.1rem; padding:30px 0;">
                    No items added yet for this school.
                </p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $i => $item): ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <div class="add-form">
                <form method="POST">
                    <input type="text" name="item_name" placeholder="Enter new item name" required>
                    <button type="submit" name="add_item">Add Item</button>
                </form>
            </div>

            <div class="links">
                <a href="dashboard.php">Back to Schools</a> |
                <a href="index.php">Change Outlet</a> |
                <a href="../logout.php">Logout</a>
            </div>
        </div>
    </div>
</body>
</html>