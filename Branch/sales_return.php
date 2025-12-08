<?php
session_start();
require '../Common/connection.php';
require '../Common/nepali_date.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Common/login.php');
    exit();
}

// Handle Exchange/Return
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exchange_return'])) {
    $bill_number     = (int)$_POST['bill_number'];
    $action          = $_POST['action_type'];
    $reason          = trim($_POST['reason']);
    $amount_paid     = (float)$_POST['amount_paid'];
    $amount_returned = (float)$_POST['amount_returned'];
    $user_id         = $_SESSION['user_id'];
    $outlet_id       = $_SESSION['outlet_id'] ?? 1;
    $bs_date         = nepali_date_time();

    $stmt = $pdo->prepare("INSERT INTO return_exchange_log 
        (bill_id, user_id, outlet_id, action, reason, amount_returned_by_customer, amount_returned_to_customer, logged_datetime)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$bill_number, $user_id, $outlet_id, $action, $reason, $amount_paid, $amount_returned, $bs_date]);

    $_SESSION['message'] = "Exchange/Return recorded for Bill #$bill_number";
    header("Location: sales_return.php");
    exit();
}

// Search filters
$search_bill     = trim($_GET['bill'] ?? '');
$search_customer = trim($_GET['customer'] ?? '');
$search_date     = trim($_GET['date'] ?? '');

// Correct query – now with proper quotes
$sql = "SELECT 
            bill_number,
            customer_name,
            school_name,
            total,
            payment_method,
            bs_datetime
        FROM sales 
        WHERE printed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          AND exchange_status = 'original'";   // ← fixed quote here

$params = [];

if ($search_bill !== '') {
    $sql .= " AND bill_number LIKE ?";
    $params[] = "%$search_bill%";
}
if ($search_customer !== '') {
    $sql .= " AND customer_name LIKE ?";
    $params[] = "%$search_customer%";
}
if ($search_date !== '') {
    $sql .= " AND bs_datetime LIKE ?";
    $params[] = "$search_date%";
}

$sql .= " ORDER BY printed_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Exchange / Return - Last 7 Days</title>
<link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap" rel="stylesheet">
<style>
    body{font-family:'Segoe UI',sans-serif;background:#f4f6f9;margin:0;padding:20px;color:#2c3e50;}
    .container{max-width:1150px;margin:auto;background:#fff;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,.1);overflow:hidden;}
    .header{background:linear-gradient(135deg,#e74c3c,#c0392b);color:#fff;padding:35px;text-align:center;}
    .header h1{margin:0;font-size:30px;}
    .search-bar{padding:20px;background:#f8f9fa;display:flex;flex-wrap:wrap;gap:15px;justify-content:center;align-items:center;}
    .search-bar input{padding:12px 16px;border:1px solid #ddd;border-radius:8px;width:220px;}
    .search-bar button{padding:12px 24px;background:#e74c3c;color:#fff;border:none;border-radius:8px;font-weight:bold;cursor:pointer;}
    .clear-btn{background:#95a5a6!important;}
    table{width:100%;border-collapse:collapse;}
    th{background:#e74c3c;color:#fff;padding:16px;text-align:left;position:sticky;top:0;}
    td{padding:14px 12px;border-bottom:1px solid #eee;}
    tr:hover{background:#fff5f5;}
    .amount{font-weight:600;color:#27ae60;}
    .action-btn{background:#e74c3c;color:#fff;padding:10px 18px;border:none;border-radius:8px;cursor:pointer;font-weight:600;}
    .action-btn:hover{background:#c0392b;}
    .no-data{padding:100px;text-align:center;color:#95a5a6;font-size:22px;}
    .back-btn{display:block;width:240px;margin:40px auto;padding:15px;background:#34495e;color:#fff;text-align:center;border-radius:12px;text-decoration:none;font-weight:600;}
    .modal{display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.6);}
    .modal-content{background:#fff;margin:8% auto;padding:35px;width:440px;border-radius:16px;box-shadow:0 20px60pxrgba(0,0,0,.4);position:relative;}
    .close{position:absolute;top:15px;right:25px;font-size:32px;cursor:pointer;color:#aaa;}
    input,select,textarea{width:100%;padding:13px;margin:10px0;border-radius:8px;border:1px solid #ccc;box-sizing:border-box;}
    button[type=submit]{width:100%;padding:15px;background:#e74c3c;color:#fff;border:none;border-radius:10px;font-weight:bold;cursor:pointer;}
</style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>Exchange / Return</h1>
        <p>Last 7 Days Sales Only</p>
    </div>

    <?php if(isset($_SESSION['message'])): ?>
        <div style="padding:18px;background:#d5f4e6;color:#27ae60;text-align:center;font-weight:600;border-radius:10px;margin:15px;">
            <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
        </div>
    <?php endif; ?>

    <div class="search-bar">
        <form method="GET">
            <input type="text" name="bill" placeholder="Bill Number" value="<?=htmlspecialchars($search_bill)?>">
            <input type="text" name="customer" placeholder="Customer Name" value="<?=htmlspecialchars($search_customer)?>">
            <input type="text" name="date" placeholder="BS Date (2082-08-21)" value="<?=htmlspecialchars($search_date)?>">
            <button type="submit">Search</button>
            <a href="sales_return.php"><button type="button" class="clear-btn">Clear</button></a>
        </form>
    </div>

    <?php if (empty($sales)): ?>
        <div class="no-data">No sales found in the last 7 days.</div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Bill No.</th>
                    <th>Date (BS)</th>
                    <th>Customer</th>
                    <th>School</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sales as $i => $s): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong>#<?= htmlspecialchars($s['bill_number']) ?></strong></td>
                    <td><?= htmlspecialchars($s['bs_datetime']) ?></td>
                    <td><?= htmlspecialchars($s['customer_name'] ?: 'Walk-in') ?></td>
                    <td><?= htmlspecialchars($s['school_name']) ?></td>
                    <td class="amount">Rs. <?= number_format($s['total']) ?></td>
                    <td><?= ucfirst($s['payment_method']) ?></td>
                    <td>
                        <button class="action-btn" onclick="openModal('<?= $s['bill_number'] ?>')">
                            Exchange/Return
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
</div>

<!-- Modal -->
<div id="exchangeModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">×</span>
        <h3 style="margin-top:0;color:#e74c3c;">Exchange / Return – Bill #<span id="billNo"></span></h3>
        <form method="POST">
            <input type="hidden" name="bill_number" id="modal_bill_number">
            <label>Action Type</label>
            <select name="action_type" required>
                <option value="returned">Returned</option>
                <option value="exchanged">Exchanged</option>
            </select>
            <label>Reason</label>
            <textarea name="reason" rows="4" required placeholder="Enter reason..."></textarea>
            <label>Amount Paid by Customer</label>
            <input type="number" step="0.01" name="amount_paid" value="0" required>
            <label>Amount Returned to Customer</label>
            <input type="number" step="0.01" name="amount_returned" value="0" required>
            <button type="submit" name="exchange_return">Submit Record</button>
        </form>
    </div>
</div>

<script>
function openModal(bill) {
    document.getElementById('modal_bill_number').value = bill;
    document.getElementById('billNo').textContent = bill;
    document.getElementById('exchangeModal').style.display = 'block';
}
function closeModal() {
    document.getElementById('exchangeModal').style.display = 'none';
}
window.onclick = e => {
    if (e.target === document.getElementById('exchangeModal')) closeModal();
};
</script>

</body>
</html>