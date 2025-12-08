<?php
session_start();
require '../Common/connection.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['selected_outlet_id'])) {
    header("Location: index.php");
    exit();
}

$outlet_id   = $_SESSION['selected_outlet_id'];
$outlet_name = $_SESSION['selected_outlet_name'];

// Get exchanges + real username from 'login' table
$stmt = $pdo->prepare("
    SELECT rel.*,
           COALESCE(l.username, 'Unknown User') AS logged_by_name
    FROM return_exchange_log rel
    LEFT JOIN login l ON rel.user_id = l.id
    WHERE rel.outlet_id = ? AND rel.action = 'exchanged'
    ORDER BY rel.logged_datetime DESC
");
$stmt->execute([$outlet_id]);
$exchanges = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exchanged Orders - <?php echo htmlspecialchars($outlet_name); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body{font-family:'Segoe UI',sans-serif;background:linear-gradient(135deg,#8e44ad,#9b59b6);min-height:100vh;padding:40px 20px;color:#2c3e50;}
        .container{max-width:1300px;margin:0 auto;background:rgba(255,255,255,0.98);border-radius:20px;padding:30px;box-shadow:0 20px 50px rgba(0,0,0,0.2);}
        h1{text-align:center;color:#8e44ad;margin-bottom:30px;font-size:2rem;}
        table{width:100%;border-collapse:collapse;margin-top:20px;}
        th,td{padding:15px 12px;text-align:left;border-bottom:1px solid #ddd;}
        th{background:#8e44ad;color:white;font-weight:600;}
        tr:hover{background:#f8f1ff;}
        .no-data{text-align:center;padding:40px;font-size:1.3rem;color:#7f8c8d;}
        .back-btn{display:inline-block;margin-top:30px;padding:12px 35px;background:#8e44ad;color:white;border-radius:50px;text-decoration:none;font-weight:600;}
        .back-btn:hover{background:#732d91;transform:scale(1.05);}
        .amount{text-align:right;font-weight:600;}
    </style>
</head>
<body>
<div class="container">
    <h1>Exchanged Orders – <?php echo htmlspecialchars($outlet_name); ?></h1>

    <?php if (empty($exchanges)): ?>
        <p class="no-data">No exchanged orders found.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Bill ID</th>
                    <th>Reason</th>
                    <th class="amount">Amt Paid<br><small>(by Customer)</small></th>
                    <th class="amount">Amt Received<br><small>(from Customer)</small></th>
                    <th>Logged By</th>
                    <th>Date & Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($exchanges as $row): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($row['bill_id']); ?></strong></td>
                    <td><?php echo htmlspecialchars($row['reason'] ?: '-'); ?></td>
                    <td class="amount"><?php echo number_format($row['amount_returned_by_customer'], 2); ?></td>
                    <td class="amount"><?php echo number_format($row['amount_returned_to_customer'], 2); ?></td>
                    <td><strong><?php echo htmlspecialchars($row['logged_by_name']); ?></strong></td>
                    <td><?php echo date('d-m-Y H:i', strtotime($row['logged_datetime'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div style="text-align:center;margin-top:20px;">
        <a href="export_excel.php?report=exchange<?php echo !empty($_SERVER['QUERY_STRING']) ? '&'.htmlspecialchars($_SERVER['QUERY_STRING']) : ''; ?>" 
        style="background:#f39c12;color:white;padding:12px 30px;border-radius:8px;text-decoration:none;font-weight:bold;">
        Export Exchanged Orders to Excel
        </a>
        <br><br>
        <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
    </div>
</div>
</body>
</html>