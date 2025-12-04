<?php
session_start();
require '../Common/connection.php';
require '../Common/nepali_date.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Common/login.php');
    exit();
}

// Handle POST redirect to bill (from clicking a record)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['view_bill'])) {
    $bill = (int)$_POST['view_bill'];
    ?>
    <form method="POST" action="measurement_bill.php" id="autoSubmit">
        <input type="hidden" name="view_bill" value="<?php echo $bill; ?>">
    </form>
    <script>document.getElementById('autoSubmit').submit();</script>
    <?php
    exit;
}

// Fetch all measurements
$stmt = $pdo->query("SELECT id, bill_number, fiscal_year, customer_name, phone, created_at 
                     FROM customer_measurements 
                     ORDER BY id DESC");
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Measurements</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f2f5; margin:0; padding:20px; color:#2c3e50; }
        .container { max-width: 1000px; margin: auto; background: white; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: linear-gradient(135deg, #8e44ad, #9b59b6); color: white; padding: 30px; text-align: center; }
        .header h1 { margin:0; font-size:28px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #9b59b6; color: white; }
        tr:hover { background: #f8f5ff; }
        tr { cursor: pointer; }
        .btn-view { background:#3498db; color:white; padding:10px 20px; border:none; border-radius:8px; cursor:pointer; font-weight:bold; }
        .btn-view:hover { background:#2980b9; }
        .back-btn { display:inline-block; margin:20px; padding:14px 30px; background:#667eea; color:white; text-decoration:none; border-radius:8px; }
        .back-btn:hover { background:#5a6fd8; }
        .no-data { text-align:center; padding:80px; color:#95a5a6; font-size:20px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>All Customer Measurements</h1>
        <p>Click any row to view/print bill</p>
    </div>

    <?php if (empty($records)): ?>
        <div class="no-data">No measurements saved yet.</div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Bill No.</th>
                    <th>Fiscal Year</th>
                    <th>Customer Name</th>
                    <th>Phone</th>
                    <th>Date & Time</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $row): ?>
                    <tr onclick="document.getElementById('form_<?php echo $row['id']; ?>').submit();">
                        <td><strong>#<?php echo $row['bill_number']; ?></strong></td>
                        <td><?php echo htmlspecialchars($row['fiscal_year']); ?></td>
                        <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['phone'] ?: '-'); ?></td>
                        <td><?php echo $row['created_at']; ?></td>
                        <td>
                            <form method="POST" id="form_<?php echo $row['id']; ?>">
                                <input type="hidden" name="view_bill" value="<?php echo $row['bill_number']; ?>">
                                <button type="submit" class="btn-view">View Bill</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div style="text-align:center;">
        <a href="measurement.php" class="back-btn">← New Measurement</a>
        <a href="dashboard.php" class="back-btn" style="background:#27ae60;">Dashboard</a>
    </div>
</div>
</body>
</html>