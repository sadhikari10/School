<?php
session_start();
require '../Common/connection.php';
require '../Common/nepali_date.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Common/login.php');
    exit();
}

// Secure POST → final_bill.php (no URL data)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_bill'])) {
    $bill_number = (int)$_POST['bill_number'];
    if ($bill_number > 0) {
        $_SESSION['complete_bill_number'] = $bill_number;
        header("Location: final_bill.php");
        exit();
    }
}

// Only show UNPAID advance bills
$sql = "SELECT * FROM advance_payment WHERE status = 'unpaid' ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$advances = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advance Payments Record</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; margin: 0; padding: 20px; color: #2c3e50; }
        .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 28px; font-weight: 600; }
        .header p { margin: 10px 0 0; opacity: 0.95; }
        .stats { padding: 20px; background: #f8f9fa; border-bottom: 1px solid #eee; font-size: 18px; font-weight: 600; color: #27ae60; text-align: center; }

        table { width: 100%; border-collapse: collapse; font-size: 14.5px; }
        th { background: #667eea; color: white; padding: 16px 12px; text-align: left; font-weight: 600; position: sticky; top: 0; z-index: 10; }
        td { padding: 14px 12px; border-bottom: 1px solid #eee; }
        tr:hover { background: #f8fffe; }
        tr:nth-child(even) { background: #fdfdff; }

        .amount { font-weight: bold; color: #27ae60; }
        .total { font-weight: bold; color: #8e44ad; }
        .remaining { font-weight: bold; }
        .remaining.positive { color: #e74c3c; }
        .remaining.zero { color: #27ae60; }

        .badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .cash { background: #d5f4e6; color: #27ae60; }
        .online { background: #fef5e7; color: #e67e22; }

        .date { font-family: 'Courier New', monospace; color: #7f8c8d; }

        .action-btn {
            background: #27ae60;
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: 0.3s;
        }
        .action-btn:hover { background: #219653; }

        .no-data { text-align: center; padding: 80px 20px; color: #95a5a6; font-size: 20px; }
        .back-btn { display: inline-block; margin: 25px; padding: 14px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 10px; font-weight: 600; }
        .back-btn:hover { background: #5a6fd8; }

        @media (max-width: 768px) {
            table, thead, tbody, th, td, tr { display: block; }
            thead tr { display: none; }
            tr { margin-bottom: 20px; border: 1px solid #ddd; border-radius: 12px; padding: 15px; }
            td { text-align: right; position: relative; padding-left: 50%; }
            td:before { content: attr(data-label); position: absolute; left: 15px; width: 45%; font-weight: bold; text-align: left; color: #667eea; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Advance Payments Record</h1>
            <p>All advance collections from customers</p>
        </div>

        <div class="stats">
            Total Unpaid Advance Bills: <strong><?php echo count($advances); ?></strong>
        </div>

        <?php if (empty($advances)): ?>
            <div class="no-data">
                No pending advance bills. All are completed!
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Bill No.</th>
                        <th>Date (BS)</th>
                        <th>Customer</th>
                        <th>School</th>
                        <th>Advance</th>
                        <th>Total Bill</th>
                        <th>Remaining</th>
                        <th>Method</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($advances as $i => $row): 
                        $remaining = $row['total'] - $row['advance_amount'];
                    ?>
                        <tr>
                            <td data-label="#"><?php echo $i + 1; ?></td>
                            <td data-label="Bill No."><strong>#<?php echo $row['bill_number']; ?></strong></td>
                            <td data-label="Date" class="date"><?php echo htmlspecialchars($row['bs_datetime']); ?></td>
                            <td data-label="Customer"><?php echo htmlspecialchars($row['customer_name'] ?: 'Walk-in'); ?></td>
                            <td data-label="School"><?php echo htmlspecialchars($row['school_name'] ?: '-'); ?></td>
                            <td data-label="Advance" class="amount">Rs. <?php echo number_format($row['advance_amount']); ?></td>
                            <td data-label="Total" class="total">Rs. <?php echo number_format($row['total']); ?></td>
                            <td data-label="Remaining" class="remaining <?php echo $remaining > 0 ? 'positive' : 'zero'; ?>">
                                Rs. <?php echo number_format($remaining); ?>
                                <?php echo $remaining == 0 ? ' (Paid)' : ''; ?>
                            </td>
                            <td data-label="Method">
                                <span class="badge <?php echo $row['payment_method']; ?>">
                                    <?php echo ucfirst($row['payment_method']); ?>
                                </span>
                            </td>
                            <td data-label="Action">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="complete_bill" value="1">
                                    <input type="hidden" name="bill_number" value="<?php echo $row['bill_number']; ?>">
                                    <button type="submit" class="action-btn">Complete Bill</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div style="text-align:center;">
            <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>