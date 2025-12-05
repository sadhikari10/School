<?php
session_start();
require '../Common/connection.php';

// Security check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['selected_outlet_id'])) {
    header("Location: index.php");
    exit();
}

$outlet_id = $_SESSION['selected_outlet_id'];
$outlet_name = $_SESSION['selected_outlet_name'] ?? 'Unknown Outlet';

// Fetch sales for this outlet
$stmt = $pdo->prepare("SELECT * FROM sales WHERE outlet_id = ? ORDER BY bs_datetime DESC");
$stmt->execute([$outlet_id]);
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
    h1{color:#2c3e50;margin-bottom:25px;text-align:center;}
    table{width:100%;border-collapse:collapse;background:white;box-shadow:0 5px 20px rgba(0,0,0,0.1);}
    th,td{padding:12px;border:1px solid #ddd;text-align:left;}
    th{background:#8e44ad;color:white;}
    tr:hover{background:#f1f1f1;}
    .items-list{padding-left:15px;}
    .back-btn{display:inline-block;margin-top:20px;padding:10px 25px;background:#3498db;color:white;border-radius:50px;text-decoration:none;}
    .back-btn:hover{background:#2980b9;}
</style>
</head>
<body>
<div class="container">
    <h1>Sales Report - <?php echo htmlspecialchars($outlet_name); ?></h1>

    <?php if(empty($sales)): ?>
        <p style="text-align:center;color:#7f8c8d;font-size:1.2rem;">No sales recorded yet.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Bill Number</th>
                    <th>Fiscal Year</th>
                    <th>School Name</th>
                    <th>Customer Name</th>
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
                        <td><?php echo $i + 1; ?></td>
                        <td><?php echo htmlspecialchars($sale['bill_number']); ?></td>
                        <td><?php echo htmlspecialchars($sale['fiscal_year']); ?></td>
                        <td><?php echo htmlspecialchars($sale['school_name']); ?></td>
                        <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                        <td><?php echo number_format($sale['total'], 2); ?></td>
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
                            else:
                                echo '-';
                            endif;
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
