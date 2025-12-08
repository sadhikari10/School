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

$sql = "SELECT 
            bill_number,
            customer_name,
            school_name,
            total,
            payment_method,
            bs_datetime
        FROM sales 
        WHERE printed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          AND exchange_status = 'original'";

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
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
    body{
        font-family:'Segoe UI',sans-serif;
        background:linear-gradient(135deg,#8e44ad,#9b59b6);
        margin:0;padding:20px 20px 40px;
        color:#2c3e50;
        min-height:100vh;
    }
    .container{
        max-width:1200px;
        margin:30px auto;
        background:rgba(255,255,255,0.98);
        border-radius:20px;
        box-shadow:0 20px 60px rgba(0,0,0,0.25);
        overflow:hidden;
    }
    .header{
        background:linear-gradient(135deg,#8e44ad,#9b59b6);
        color:#fff;
        padding:40px 20px;
        text-align:center;
    }
    .header h1{
        margin:0;
        font-size:2.4rem;
        font-weight:700;
    }
    .header p{
        margin:10px 0 0;
        opacity:0.9;
        font-size:1.1rem;
    }
    .search-bar{
        padding:25px;
        background:#f8f1ff;
        display:flex;
        flex-wrap:wrap;
        gap:15px;
        justify-content:center;
        align-items:center;
    }
    .search-bar input{
        padding:14px 18px;
        border:2px solid #ddd;
        border-radius:12px;
        width:240px;
        font-size:1rem;
        transition:all 0.3s;
    }
    .search-bar input:focus{
        outline:none;
        border-color:#8e44ad;
        box-shadow:0 0 0 4px rgba(142,68,173,0.2);
    }
    .search-bar button{
        padding:14px 28px;
        background:#8e44ad;
        color:#fff;
        border:none;
        border-radius:12px;
        font-weight:600;
        cursor:pointer;
        transition:all 0.3s;
    }
    .search-bar button:hover{
        background:#732d91;
        transform:translateY(-2px);
    }
    .clear-btn{
        background:#95a5a6 !important;
    }
    .clear-btn:hover{
        background:#7f8c8d !important;
    }

    table{
        width:100%;
        border-collapse:collapse;
    }
    th{
        background:#8e44ad;
        color:#fff;
        padding:18px 15px;
        text-align:left;
        font-weight:600;
        position:sticky;
        top:0;
        z-index:10;
    }
    td{
        padding:16px 15px;
        border-bottom:1px solid #eee;
    }
    tr:hover{
        background:#f8f1ff;
    }
    .amount{
        font-weight:700;
        color:#27ae60;
    }
    .action-btn{
        background:#8e44ad;
        color:#fff;
        padding:11px 22px;
        border:none;
        border-radius:50px;
        cursor:pointer;
        font-weight:600;
        transition:all 0.3s;
        box-shadow:0 4px 15px rgba(142,68,173,0.3);
    }
    .action-btn:hover{
        background:#732d91;
        transform:scale(1.05);
        box-shadow:0 8px 25px rgba(142,68,173,0.4);
    }
    .no-data{
        padding:120px 20px;
        text-align:center;
        color:#95a5a6;
        font-size:1.5rem;
    }
    .back-btn{
        display:block;
        width:260px;
        margin:40px auto;
        padding:16px;
        background:#8e44ad;
        color:#fff;
        text-align:center;
        border-radius:50px;
        text-decoration:none;
        font-weight:600;
        font-size:1.1rem;
        transition:all 0.3s;
        box-shadow:0 8px 25px rgba(142,68,173,0.3);
    }
    .back-btn:hover{
        background:#732d91;
        transform:translateY(-3px);
    }

    /* Success Message */
    .success-msg{
        padding:18px;
        background:#d5f4e6;
        color:#27ae60;
        text-align:center;
        font-weight:600;
        border-radius:12px;
        margin:20px;
        border-left:6px solid #27ae60;
    }

    /* Modal */
    .modal{
        display:none;
        position:fixed;
        z-index:1000;
        left:0;top:0;
        width:100%;height:100%;
        background:rgba(0,0,0,0.7);
        backdrop-filter:blur(5px);
    }
    .modal-content{
        background:#fff;
        margin:6% auto;
        padding:40px;
        width:90%;
        max-width:480px;
        border-radius:20px;
        box-shadow:0 20px 80px rgba(142,68,173,0.4);
        position:relative;
        animation:modalFadeIn 0.4s;
    }
    @keyframes modalFadeIn{
        from{opacity:0;transform:translateY(-50px);}
        to{opacity:1;transform:translateY(0);}
    }
    .close{
        position:absolute;
        top:15px;right:25px;
        font-size:36px;
        cursor:pointer;
        color:#aaa;
        transition:all 0.3s;
    }
    .close:hover{color:#8e44ad;}
    .modal h3{
        margin-top:0;
        color:#8e44ad;
        font-size:1.6rem;
    }
    .modal label{
        display:block;
        margin:15px 0 8px;
        font-weight:600;
        color:#5f3f7e;
    }
    .modal input, .modal select, .modal textarea{
        width:100%;
        padding:14px;
        border-radius:12px;
        border:2px solid #ddd;
        font-size:1rem;
        transition:all 0.3s;
        box-sizing:border-box;
    }
    .modal input:focus, .modal select:focus, .modal textarea:focus{
        outline:none;
        border-color:#8e44ad;
        box-shadow:0 0 0 4px rgba(142,68,173,0.2);
    }
    .modal button[type=submit]{
        width:100%;
        padding:16px;
        background:#8e44ad;
        color:#fff;
        border:none;
        border-radius:12px;
        font-weight:bold;
        font-size:1.1rem;
        cursor:pointer;
        margin-top:20px;
        transition:all 0.3s;
    }
    .modal button[type=submit]:hover{
        background:#732d91;
        transform:translateY(-2px);
    }
</style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>Exchange / Return</h1>
        <p>Last 7 Days Sales Only</p>
    </div>

    <?php if(isset($_SESSION['message'])): ?>
        <div class="success-msg">
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
        <div class="no-data">
            <i class="fas fa-receipt fa-3x" style="color:#8e44ad;margin-bottom:20px;"></i><br>
            No sales found in the last 7 days.
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

    <a href="dashboard.php" class="back-btn">
        Back to Dashboard
    </a>
</div>

<!-- Modal -->
<div id="exchangeModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">×</span>
        <h3>Exchange / Return – Bill #<span id="billNo"></span></h3>
        <form method="POST">
            <input type="hidden" name="bill_number" id="modal_bill_number">
            
            <label>Action Type</label>
            <select name="action_type" required>
                <option value="returned">Returned</option>
                <option value="exchanged">Exchanged</option>
            </select>
            
            <label>Reason</label>
            <textarea name="reason" rows="4" required placeholder="Enter reason for return/exchange..."></textarea>
            
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