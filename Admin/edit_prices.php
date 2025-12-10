<?php
session_start();
require '../Common/connection.php';

// Security check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin' ||
    !isset($_SESSION['selected_outlet_id']) || !isset($_SESSION['selected_school_id'])) {
    header("Location: schools.php");
    exit();
}

$outlet_id   = $_SESSION['selected_outlet_id'];
$outlet_name = $_SESSION['selected_outlet_name'] ?? 'Unknown Outlet';
$school_id   = $_SESSION['selected_school_id'];
$school_name = $_SESSION['selected_school_name'] ?? 'Unknown School';

// === FETCH ALL BRANDS FOR THIS OUTLET ===
$stmt = $pdo->prepare("SELECT brand_id, brand_name FROM brands WHERE outlet_id = ? ORDER BY brand_name");
$stmt->execute([$outlet_id]);
$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);

// === UPDATE OR INSERT PRICE ===
if (isset($_POST['update_price'])) {
    $price_id   = $_POST['price_id'] ?? null;
    $item_id    = $_POST['item_id'];
    $size_id    = $_POST['size_id'] ?? null;
    $new_price  = trim($_POST['price']);
    $brand_id   = $_POST['brand_id'] ?? null;

    if (is_numeric($new_price) && $new_price >= 0) {
        if ($price_id) {
            $stmt = $pdo->prepare("UPDATE school_item_prices SET price = ?, brand_id = ? WHERE price_id = ? AND school_id = ?");
            $stmt->execute([$new_price, $brand_id, $price_id, $school_id]);
            $_SESSION['success'] = "Price updated successfully!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO school_item_prices (school_id, item_id, size_id, price, brand_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$school_id, $item_id, $size_id, $new_price, $brand_id]);
            $_SESSION['success'] = "Price added successfully!";
        }
    } else {
        $_SESSION['error'] = "Please enter a valid price.";
    }

    header("Location: edit_prices.php");
    exit();
}

// === FILTER HANDLING ===
$filter_name  = trim($_GET['search_name'] ?? '');
$filter_brand = $_GET['filter_brand'] ?? '';

// === FETCH ITEMS WITH FILTERS ===
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
";

$params = [$outlet_id, $school_id, $outlet_id];

if ($filter_name) {
    $query .= " AND i.item_name LIKE ?";
    $params[] = "%$filter_name%";
}

if ($filter_brand) {
    $query .= " AND sip.brand_id = ?";
    $params[] = $filter_brand;
}

$query .= " ORDER BY i.item_name, s.size_value";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
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
body { font-family:'Segoe UI',sans-serif; background: linear-gradient(135deg,#8e44ad,#9b59b6,#a569bd); min-height:100vh; padding:20px; color:#2c3e50; }
.container { max-width:1200px; margin:0 auto; }
.header { text-align:center; margin-bottom:30px; }
.header h1 { font-size:2.2rem; color:white; text-shadow:0 4px 15px rgba(0,0,0,0.3); }
.outlet-badge { background:rgba(255,255,255,0.95); color:#8e44ad; padding:10px 30px; border-radius:50px; font-weight:700; font-size:1.2rem; display:inline-block; box-shadow:0 8px 25px rgba(0,0,0,0.2); margin-top:10px; }
.school-info { background:rgba(255,255,255,0.95); padding:10px 25px; border-radius:20px; display:inline-block; font-size:1.2rem; font-weight:600; color:#27ae60; margin-top:10px; }

.card { background:white; border-radius:20px; padding:25px; box-shadow:0 15px 40px rgba(0,0,0,0.2); margin-bottom:30px; }
.section-title { font-size:1.8rem; margin-bottom:20px; color:#2c3e50; display:flex; align-items:center; gap:12px; }

.alert { padding:12px 18px; border-radius:10px; margin-bottom:20px; font-weight:600; text-align:center; }
.success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
.error { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }

table { width:100%; border-collapse:collapse; margin-top:20px; font-size:0.95rem; }
th { background:#8e44ad; color:white; padding:12px; text-align:left; }
td { padding:10px; border-bottom:1px solid #eee; vertical-align:middle; }
tr:hover { background:#f8f9ff; }

.price-input, select, input[type="text"] { width:100%; padding:8px; border:2px solid #ddd; border-radius:8px; font-size:0.95rem; text-align:center; }
.price-input:focus, select:focus, input[type="text"]:focus { outline:none; border-color:#8e44ad; box-shadow:0 0 0 2px rgba(142,68,173,0.2); }

.btn-update { padding:8px 16px; background:#27ae60; color:white; border:none; border-radius:8px; cursor:pointer; font-weight:600; font-size:0.85rem; }
.btn-update:hover { background:#219653; }

.back-btn { display:inline-block; margin-top:20px; padding:10px 25px; background:#3498db; color:white; border-radius:50px; text-decoration:none; font-weight:600; }
.back-btn:hover { background:#2980b9; }

.no-price { color:#e74c3c; font-style:italic; font-size:0.85rem; }

.filter-form { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:15px; }
.filter-form input[type="text"], .filter-form select { flex:1; min-width:120px; }

@media (max-width:768px){
    table, thead, tbody, th, td, tr { display:block; }
    th, td { padding:8px; }
    tr { margin-bottom:15px; border:1px solid #ddd; border-radius:12px; padding:10px; }
    td { text-align:right; position:relative; padding-left:50%; }
    td::before { position:absolute; left:15px; width:45%; white-space:nowrap; text-align:left; font-weight:600; content:attr(data-label); }
    .btn-update { width:100%; margin-top:5px; }
    .price-input, select, input[type="text"] { text-align:left; }
}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Edit Prices</h1>
        <div class="outlet-badge"><?php echo htmlspecialchars($outlet_name); ?></div>
        <div class="school-info"><?php echo htmlspecialchars($school_name); ?></div>
    </div>

    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="card">
        <h2 class="section-title">Filters</h2>
        <form method="GET" class="filter-form">
            <input type="text" name="search_name" placeholder="Search Item Name..." value="<?php echo htmlspecialchars($filter_name); ?>">
            <select name="filter_brand">
                <option value="">-- All Brands --</option>
                <?php foreach($brands as $brand): ?>
                    <option value="<?php echo $brand['brand_id']; ?>" <?php echo ($brand['brand_id']==$filter_brand)?'selected':''; ?>>
                        <?php echo htmlspecialchars($brand['brand_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-update">Filter</button>
        </form>

        <h2 class="section-title">Item Prices</h2>

        <?php if(empty($items)): ?>
            <p style="text-align:center; padding:30px; color:#7f8c8d; font-size:1rem;">No items found.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item Name</th>
                        <th>Size</th>
                        <th>Current Price</th>
                        <th>New Price</th>
                        <th>Brand</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php 
                $count = 1;
                $current_item = '';
                foreach($items as $row):
                    $is_new_item = ($row['item_name'] !== $current_item);
                    $current_item = $row['item_name'];
                    $row_brand_id = $row['brand_id'] ?? '';
                ?>
                    <tr>
                        <?php if($is_new_item): ?>
                            <td rowspan="<?php echo count(array_filter($items, fn($x)=>$x['item_name']===$row['item_name'])); ?>"><?php echo $count++; ?></td>
                            <td rowspan="<?php echo count(array_filter($items, fn($x)=>$x['item_name']===$row['item_name'])); ?>"><?php echo htmlspecialchars($row['item_name']); ?></td>
                        <?php endif; ?>

                        <td data-label="Size"><?php echo $row['size_value'] ? htmlspecialchars($row['size_value']) : '<em>No size</em>'; ?></td>
                        <td data-label="Current Price"><?php echo $row['price']!==null ? 'Rs. '.number_format($row['price'],2) : '<span class="no-price">Not set</span>'; ?></td>
                        <td data-label="New Price">
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="price_id" value="<?php echo $row['price_id'] ?? ''; ?>">
                                <input type="hidden" name="item_id" value="<?php echo $row['item_id']; ?>">
                                <input type="hidden" name="size_id" value="<?php echo $row['size_id']; ?>">
                                <input type="number" name="price" class="price-input" step="0.01" min="0" value="<?php echo $row['price'] ?? ''; ?>">
                        </td>
                        <td data-label="Brand">
                            <select name="brand_id" required>
                                <option value="" disabled <?php echo empty($row_brand_id) ? 'selected' : ''; ?>>
                                    -- Select Brand --
                                </option>
                                <?php foreach($brands as $brand): ?>
                                    <option value="<?php echo $brand['brand_id']; ?>"
                                        <?php echo ($brand['brand_id'] == $row_brand_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($brand['brand_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td data-label="Action">
                                <button type="submit" name="update_price" class="btn-update">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div style="margin-top:20px; text-align:center;">
            <a href="schools.php" class="back-btn">Back to Schools</a>
        </div>
    </div>
</div>
</body>
</html>
