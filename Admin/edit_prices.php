<?php
session_start();
require '../Common/connection.php';

// Security: Must be admin + have selected outlet & school
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin' ||
    !isset($_SESSION['selected_outlet_id']) || !isset($_SESSION['selected_school_id'])) {
    header("Location: schools.php");
    exit();
}

$outlet_id   = $_SESSION['selected_outlet_id'];
$outlet_name = $_SESSION['selected_outlet_name'] ?? 'Unknown Outlet';
$school_id   = $_SESSION['selected_school_id'];
$school_name = $_SESSION['selected_school_name'] ?? 'Unknown School';

// === UPDATE PRICE ===
if (isset($_POST['update_price'])) {
    $price_id = (int)$_POST['price_id'];
    $new_price = trim($_POST['price']);

    if (is_numeric($new_price) && $new_price >= 0) {
        $stmt = $pdo->prepare("UPDATE school_item_prices SET price = ? WHERE price_id = ? AND school_id = ?");
        $stmt->execute([$new_price, $price_id, $school_id]);
        $_SESSION['success'] = "Price updated successfully!";
    } else {
        $_SESSION['error'] = "Please enter a valid price.";
    }
    header("Location: edit_prices.php");
    exit();
}

// === FETCH ALL ITEMS WITH SIZES AND CURRENT PRICES FOR THIS SCHOOL ===
$query = "
    SELECT 
        i.item_id,
        i.item_name,
        s.size_id,
        s.size_value,
        sip.price_id,
        sip.price,
        sip.brand_id
    FROM items i
    LEFT JOIN sizes s ON s.item_id = i.item_id AND s.outlet_id = ?
    LEFT JOIN school_item_prices sip 
        ON sip.item_id = i.item_id 
        AND sip.size_id = s.size_id 
        AND sip.school_id = ?
    WHERE i.outlet_id = ?
    ORDER BY i.item_name, s.size_value
";

$stmt = $pdo->prepare($query);
$stmt->execute([$outlet_id, $school_id, $outlet_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Prices - <?php echo htmlspecialchars($school_name); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #8e44ad, #9b59b6, #a569bd);
            min-height: 100vh; padding: 30px; color: #2c3e50;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header {
            text-align: center; margin-bottom: 40px;
        }
        .header h1 { font-size: 2.6rem; color: white; text-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        .outlet-badge {
            background: rgba(255,255,255,0.95); color: #8e44ad; padding: 12px 40px;
            border-radius: 50px; font-weight: 700; font-size: 1.3rem; display: inline-block;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2); margin-top: 10px;
        }
        .school-info {
            background: rgba(255,255,255,0.95); padding: 15px 30px; border-radius: 20px;
            display: inline-block; font-size: 1.4rem; font-weight: 600; color: #27ae60; margin-top: 15px;
        }

        .card {
            background: white; border-radius: 24px; padding: 40px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2); margin-bottom: 30px;
        }
        .section-title {
            font-size: 2rem; margin-bottom: 25px; color: #2c3e50;
            display: flex; align-items: center; gap: 12px;
        }

        .alert {
            padding: 15px 20px; border-radius: 12px; margin-bottom: 25px;
            font-weight: 600; text-align: center;
        }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        table {
            width: 100%; border-collapse: collapse; margin-top: 20px;
        }
        th {
            background: #8e44ad; color: white; padding: 18px; text-align: left;
        }
        td {
            padding: 16px; border-bottom: 1px solid #eee; vertical-align: middle;
        }
        tr:hover { background: #f8f9ff; }

        .price-input {
            width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 10px;
            font-size: 1rem; text-align: center;
        }
        .price-input:focus {
            outline: none; border-color: #8e44ad; box-shadow: 0 0 0 3px rgba(142,68,173,0.2);
        }

        .btn-update {
            padding: 10px 20px; background: #27ae60; color: white; border: none;
            border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 0.9rem;
        }
        .btn-update:hover { background: #219653; }

        .back-btn {
            display: inline-block; margin-top: 30px; padding: 12px 35px;
            background: #3498db; color: white; border-radius: 50px; text-decoration: none;
            font-weight: 600;
        }
        .back-btn:hover { background: #2980b9; }

        .no-price {
            color: #e74c3c; font-style: italic; font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Edit Prices</h1>
            <div class="outlet-badge"><?php echo htmlspecialchars($outlet_name); ?></div>
            <div class="school-info">
                <?php echo htmlspecialchars($school_name); ?>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="card">
            <h2 class="section-title">Item Prices</h2>

            <?php if (empty($items)): ?>
                <p style="text-align:center; padding:50px; color:#7f8c8d; font-size:1.3rem;">
                    No items found. Please add items first.
                </p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th>Item Name</th>
                            <th width="15%">Size</th>
                            <th width="20%">Current Price</th>
                            <th width="20%">New Price</th>
                            <th width="15%">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $count = 1;
                        $current_item = '';
                        foreach ($items as $row): 
                            $is_new_item = ($row['item_name'] !== $current_item);
                            $current_item = $row['item_name'];
                        ?>
                            <tr <?php echo $is_new_item ? 'style="border-top: 3px solid #8e44ad;"' : ''; ?>>
                                <?php if ($is_new_item): ?>
                                    <td rowspan="<?php echo count(array_filter($items, fn($x) => $x['item_name'] === $row['item_name'])); ?>">
                                        <?php echo $count++; ?>
                                    </td>
                                    <td rowspan="<?php echo count(array_filter($items, fn($x) => $x['item_name'] === $row['item_name'])); ?>">
                                        <strong><?php echo htmlspecialchars($row['item_name']); ?></strong>
                                    </td>
                                <?php endif; ?>

                                <td><?php echo $row['size_value'] ? htmlspecialchars($row['size_value']) : '<em>No size</em>'; ?></td>
                                <td>
                                    <?php echo $row['price'] !== null ? 'Rs. ' . number_format($row['price'], 2) : '<span class="no-price">Not set</span>'; ?>
                                </td>
                                <td>
                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="price_id" value="<?php echo $row['price_id'] ?? ''; ?>">
                                        <input type="number" 
                                               name="price" 
                                               class="price-input" 
                                               step="0.01" 
                                               min="0" 
                                               placeholder="0.00"
                                               value="<?php echo $row['price'] ?? ''; ?>">
                                    </form>
                                </td>
                                <td>
                                    <button type="submit" formaction="" name="update_price" class="btn-update">
                                        Update
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <div style="margin-top: 40px; text-align: center;">
                <a href="schools.php" class="back-btn">Back to Schools</a>
            </div>
        </div>
    </div>
</body>
</html>