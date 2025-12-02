<?php
session_start();
require '../Common/connection.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['selected_outlet_id'])) {
    header("Location: index.php");
    exit();
}

$outlet_id   = $_SESSION['selected_outlet_id'];
$outlet_name = $_SESSION['selected_outlet_name'];

// === ADD NEW ITEM + SIZES ===
if (isset($_POST['add_item'])) {
    $item_name = trim($_POST['item_name']);
    $sizes_input = trim($_POST['sizes']);
    $sizes = preg_split('/[\s,]+/', $sizes_input, -1, PREG_SPLIT_NO_EMPTY);

    if ($item_name && !empty($sizes)) {
        // Check if item already exists in this outlet
        $check = $pdo->prepare("SELECT item_id FROM items WHERE item_name = ? AND outlet_id = ?");
        $check->execute([$item_name, $outlet_id]);
        if ($check->fetch()) {
            $_SESSION['error'] = "Item '$item_name' already exists!";
        } else {
            // Add item
            $stmt = $pdo->prepare("INSERT INTO items (item_name, outlet_id) VALUES (?, ?)");
            $stmt->execute([$item_name, $outlet_id]);
            $item_id = $pdo->lastInsertId();

            // Add sizes
            $size_stmt = $pdo->prepare("INSERT INTO sizes (item_id, size_value, outlet_id) VALUES (?, ?, ?)");
            foreach ($sizes as $size) {
                $size = strtoupper(trim($size));
                if ($size) {
                    $size_stmt->execute([$item_id, $size, $outlet_id]);
                }
            }
            $_SESSION['success'] = "Item and sizes added successfully!";
        }
    } else {
        $_SESSION['error'] = "Please fill both fields!";
    }
    header("Location: school_items.php");
    exit();
}

// === FETCH ALL ITEMS WITH SIZES ===
$items = $pdo->prepare("
    SELECT i.item_id, i.item_name,
           GROUP_CONCAT(s.size_value ORDER BY s.size_value SEPARATOR ', ') as sizes
    FROM items i
    LEFT JOIN sizes s ON i.item_id = s.item_id
    WHERE i.outlet_id = ?
    GROUP BY i.item_id
    ORDER BY i.item_name
");
$items->execute([$outlet_id]);
$items = $items->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Items & Sizes - <?php echo htmlspecialchars($outlet_name); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Segoe UI',sans-serif;background:linear-gradient(135deg,#8e44ad,#9b59b6,#a569bd);min-height:100vh;padding:30px;color:#2c3e50;}
        .container{max-width:1100px;margin:0 auto;}
        .header{text-align:center;margin-bottom:40px;}
        .header h1{font-size:2.6rem;color:white;text-shadow:0 4px 15px rgba(0,0,0,0.3);}
        .outlet-badge{background:rgba(255,255,255,0.95);color:#8e44ad;padding:12px 40px;border-radius:50px;font-weight:700;font-size:1.3rem;display:inline-block;box-shadow:0 10px 30px rgba(0,0,0,0.2);}

        .card{background:white;border-radius:24px;padding:40px;box-shadow:0 20px 50px rgba(0,0,0,0.2);margin-bottom:30px;}
        .section-title{font-size:2rem;margin-bottom:25px;color:#2c3e50;display:flex;align-items:center;gap:12px;}

        .alert{padding:15px 20px;border-radius:12px;margin-bottom:25px;font-weight:600;}
        .success{background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
        .error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}

        table{width:100%;border-collapse:collapse;}
        th{background:#8e44ad;color:white;padding:16px;text-align:left;}
        td{padding:16px;border-bottom:1px solid #eee;}
        tr:hover{background:#f8f9ff;}
        .sizes-badge{background:#27ae60;color:white;padding:6px 12px;border-radius:20px;font-size:0.9rem;margin:3px;display:inline-block;}

        .add-form{display:flex;gap:15px;margin-top:30px;flex-wrap:wrap;}
        .add-form input{flex:1;min-width:280px;padding:16px;border:2px solid #e1e8ed;border-radius:14px;font-size:1.1rem;}
        .add-form button{padding:16px 40px;background:linear-gradient(135deg,#8e44ad,#9b59b6);color:white;border:none;border-radius:14px;font-weight:600;cursor:pointer;}

        .back-btn{display:inline-block;margin-top:40px;padding:12px 35px;background:#3498db;color:white;border-radius:50px;text-decoration:none;font-weight:600;}
        .back-btn:hover{background:#2980b9;}
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Manage Items & Sizes</h1>
            <div class="outlet-badge"><?php echo htmlspecialchars($outlet_name); ?></div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="card">
            <h2 class="section-title">Items List (<?php echo count($items); ?>)</h2>

            <?php if (empty($items)): ?>
                <p style="text-align:center;padding:50px;color:#7f8c8d;font-size:1.3rem;">No items added yet.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Available Sizes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $i => $item): ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td style="font-weight:600;"><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td>
                                    <?php if ($item['sizes']): ?>
                                        <?php foreach (explode(', ', $item['sizes']) as $size): ?>
                                            <span class="sizes-badge"><?php echo htmlspecialchars($size); ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <em style="color:#999;">No sizes</em>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <!-- Add New Item -->
            <div class="add-form">
                <form method="POST">
                    <input type="text" name="item_name" placeholder="Item name (e.g. Shirt, Pant)" required>
                    <input type="text" name="sizes" placeholder="Sizes: 28 30 32 34 or S M L XL" required>
                    <button type="submit" name="add_item">Add Item + Sizes</button>
                </form>
            </div>
        </div>

        <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
    </div>
</body>
</html>