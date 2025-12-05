<?php
session_start();
require '../Common/connection.php';
require '../Common/nepali_date.php'; // your Nepali date helper

// Security check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['selected_outlet_id'])) {
    header("Location: index.php");
    exit();
}

$outlet_id = $_SESSION['selected_outlet_id'];
$outlet_name = $_SESSION['selected_outlet_name'] ?? 'Unknown Outlet';

// Initialize filters
$params = [$outlet_id];
$filters = [];

// === FILTERS ===
if (!empty($_GET['fiscal_year'])) {
    $filters[] = "fiscal_year = ?";
    $params[] = $_GET['fiscal_year'];
}

if (!empty($_GET['school_name'])) {
    $filters[] = "school_name LIKE ?";
    $params[] = "%" . $_GET['school_name'] . "%";
}

if (!empty($_GET['customer_name'])) {
    $filters[] = "customer_name LIKE ?";
    $params[] = "%" . $_GET['customer_name'] . "%";
}

if (!empty($_GET['payment_method'])) {
    $filters[] = "payment_method = ?";
    $params[] = $_GET['payment_method'];
}

if (!empty($_GET['printed_by'])) {
    $filters[] = "printed_by LIKE ?";
    $params[] = "%" . $_GET['printed_by'] . "%";
}

if (!empty($_GET['bill_number'])) {
    $filters[] = "bill_number LIKE ?";
    $params[] = "%" . $_GET['bill_number'] . "%";
}

// Start & End date filter
$start_date = $_GET['start_date'] ?? null;
$end_date   = $_GET['end_date'] ?? null;

if ($start_date && $end_date) {
    $filters[] = "bs_datetime BETWEEN ? AND ?";
    $params[] = $start_date . ' 00:00:00';
    $params[] = $end_date . ' 23:59:59';
} elseif (!$start_date && !$end_date) {
    // Default last 7 Nepali days
    $today = explode(' ', nepali_date_time())[0];
    [$y, $m, $d] = explode('-', $today);
    $last7 = [];
    for ($i = 6; $i >= 0; $i--) {
        $day = (int)$d - $i;
        if ($day <= 0) $day = 1;
        $last7[] = sprintf('%04d-%02d-%02d', $y, $m, $day);
    }
    $placeholders = implode(',', array_fill(0, count($last7), '?'));
    $filters[] = "DATE(bs_datetime) IN ($placeholders)";
    foreach ($last7 as $date) $params[] = $date;
}

// Build SQL
$sql = "SELECT * FROM sales WHERE outlet_id = ?";
if ($filters) $sql .= " AND " . implode(' AND ', $filters);
$sql .= " ORDER BY bs_datetime DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sales Report - <?php echo htmlspecialchars($outlet_name); ?></title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
    body{font-family:'Segoe UI',sans-serif;padding:20px;background:#f4f6f9;}
    .container{max-width:1200px;margin:0 auto;}
    h1{color:#2c3e50;margin-bottom:20px;text-align:center;}
    table{width:100%;border-collapse:collapse;background:white;box-shadow:0 5px 20px rgba(0,0,0,0.1);}
    th,td{padding:10px;border:1px solid #ddd;text-align:left;font-size:0.9rem;}
    th{background:#8e44ad;color:white;}
    tr:hover{background:#f1f1f1;}
    .items-list{padding-left:15px;}
    .back-btn{display:inline-block;margin-top:20px;padding:8px 20px;background:#3498db;color:white;border-radius:50px;text-decoration:none;font-size:0.9rem;}
    .back-btn:hover{background:#2980b9;}
    .filter-form input{padding:6px 10px;margin:0 5px 5px 0;font-size:0.85rem;}
    .filter-form button{padding:6px 15px;background:#27ae60;color:white;border:none;border-radius:6px;font-size:0.85rem;cursor:pointer;}
    .filter-form button:hover{background:#219150;}
</style>
</head>
<body>
<div class="container">
    <h1>Sales Report - <?php echo htmlspecialchars($outlet_name); ?></h1>

    <form method="GET" class="filter-form">
        <input type="text" name="fiscal_year" placeholder="Fiscal Year" value="<?php echo htmlspecialchars($_GET['fiscal_year'] ?? ''); ?>">
        <input type="text" name="school_name" placeholder="School Name" value="<?php echo htmlspecialchars($_GET['school_name'] ?? ''); ?>">
        <input type="text" name="customer_name" placeholder="Customer Name" value="<?php echo htmlspecialchars($_GET['customer_name'] ?? ''); ?>">
        <input type="text" name="payment_method" placeholder="Payment Method" value="<?php echo htmlspecialchars($_GET['payment_method'] ?? ''); ?>">
        <input type="text" name="printed_by" placeholder="Printed By" value="<?php echo htmlspecialchars($_GET['printed_by'] ?? ''); ?>">
        <input type="text" name="bill_number" placeholder="Bill Number" value="<?php echo htmlspecialchars($_GET['bill_number'] ?? ''); ?>">
        <input type="text" name="start_date" placeholder="Start Date YYYY-MM-DD" value="<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>">
        <input type="text" name="end_date" placeholder="End Date YYYY-MM-DD" value="<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>">
        <button type="submit">Search</button>
    </form>

    <?php if(empty($sales)): ?>
        <p style="text-align:center;color:#7f8c8d;font-size:1rem;">No records found.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Bill Number</th>
                    <th>Fiscal Year</th>
                    <th>School</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Payment Method</th>
                    <th>Printed By</th>
                    <th>Date & Time</th>
                    <th>Items</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($sales as $i => $sale): ?>
                <tr>
                    <td><?php echo $i+1; ?></td>
                    <td><?php echo htmlspecialchars($sale['bill_number']); ?></td>
                    <td><?php echo htmlspecialchars($sale['fiscal_year']); ?></td>
                    <td><?php echo htmlspecialchars($sale['school_name']); ?></td>
                    <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                    <td><?php echo number_format($sale['total'],2); ?></td>
                    <td><?php echo htmlspecialchars($sale['payment_method']); ?></td>
                    <td><?php echo htmlspecialchars($sale['printed_by']); ?></td>
                    <td><?php echo htmlspecialchars($sale['bs_datetime']); ?></td>
                    <td>
                        <?php 
                        $items = json_decode($sale['items_json'], true);
                        if($items && is_array($items)):
                            echo '<ul class="items-list">';
                            foreach($items as $item){
                                echo '<li>'.htmlspecialchars($item['name']).' (Size: '.htmlspecialchars($item['size']).') × '.htmlspecialchars($item['quantity']).' → '.number_format($item['price'],2).'</li>';
                            }
                            echo '</ul>';
                        else: echo '-'; endif;
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
</div>
</body>
</html>
