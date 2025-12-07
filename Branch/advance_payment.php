<?php
session_start();
require '../Common/connection.php';
require '../Common/nepali_date.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Common/login.php');
    exit();
}

// Handle Return / Exchange / Cancel POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bill_number']) && isset($_POST['action_type'])) {
    $bill_number = $_POST['bill_number'];
    $action_type = $_POST['action_type'];
    $reason = trim($_POST['reason']);
    $amount_to_customer = (float)$_POST['amount_returned_to_customer'];
    $amount_by_customer = (float)$_POST['amount_returned_by_customer'];
    $user_id = $_SESSION['user_id'];
    $outlet_id = $_SESSION['selected_outlet_id'];

    if ($bill_number && $action_type && $reason) {
        // Update items in advance_payment (or sales) table with exchange_status
        $pdo->prepare("UPDATE advance_payment SET exchange_status = ? WHERE bill_number = ? AND (exchange_status IS NULL OR exchange_status = 'original')")
            ->execute([$action_type, $bill_number]);

        // Log in return_exchange_log table
        $stmt = $pdo->prepare("INSERT INTO return_exchange_log
            (bill_number, action_type, reason, amount_returned_by_customer, amount_returned_to_customer, user_id, outlet_id, logged_datetime)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$bill_number, $action_type, $reason, $amount_by_customer, $amount_to_customer, $user_id, $outlet_id]);

        header("Location: advance_payment.php");
        exit();
    } else {
        $error = "All fields are required!";
    }
}

// Secure POST → final_bill.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_bill'])) {
    $bill_number = (int)$_POST['bill_number'];
    if ($bill_number > 0) {
        $_SESSION['complete_bill_number'] = $bill_number;
        header("Location: final_bill.php");
        exit();
    }
}

// Search & Filter Logic
$search_bill = trim($_GET['bill'] ?? '');
$search_customer = trim($_GET['customer'] ?? '');
$search_date = trim($_GET['date'] ?? '');

$sql = "SELECT * FROM advance_payment WHERE status = 'unpaid'";
$params = [];

if ($search_bill !== '') {
    $sql .= " AND bill_number LIKE ?";
    $params[] = '%' . $search_bill . '%';
}
if ($search_customer !== '') {
    $sql .= " AND customer_name LIKE ?";
    $params[] = '%' . $search_customer . '%';
}
if ($search_date !== '') {
    $sql .= " AND bs_datetime LIKE ?";
    $params[] = $search_date . '%';
}

$sql .= " ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
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

.search-bar { padding: 20px; background: #f8f9fa; border-bottom: 1px solid #eee; display: flex; flex-wrap: wrap; gap: 15px; justify-content: center; align-items: center; }
.search-bar input, .search-bar button { padding: 12px 16px; font-size: 15px; border: 1px solid #ddd; border-radius: 8px; }
.search-bar input { width: 220px; }
.search-bar button { background: #667eea; color: white; cursor: pointer; font-weight: bold; border: none; }
.clear-btn { background: #95a5a6 !important; }
.clear-btn:hover { background: #7f8c8d !important; }

.stats { padding: 15px; background: #f8f9fa; text-align: center; font-size: 17px; color: #27ae60; font-weight: 600; }

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

.action-btn { background: #27ae60; color: white; border: none; padding: 10px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; margin-bottom:5px; display:block; }
.complete-btn { background:#764ba2; }
.no-data { text-align: center; padding: 80px 20px; color: #95a5a6; font-size: 20px; }
.back-btn { display: inline-block; margin: 25px; padding: 14px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 10px; font-weight: 600; }
.back-btn:hover { background: #5a6fd8; }

@media (max-width: 768px) {
    .search-bar { flex-direction: column; }
    .search-bar input { width: 100%; }
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

    <!-- Search Bar -->
    <div class="search-bar">
        <form method="GET">
            <input type="text" name="bill" placeholder="Bill Number" value="<?php echo htmlspecialchars($search_bill); ?>">
            <input type="text" name="customer" placeholder="Customer Name" value="<?php echo htmlspecialchars($search_customer); ?>">
            <input type="text" name="date" placeholder="Date (e.g. 2081-08-15)" value="<?php echo htmlspecialchars($search_date); ?>">
            <button type="submit">Search</button>
            <a href="advance_payment.php"><button type="button" class="clear-btn">Clear</button></a>
        </form>
    </div>

    <div class="stats">
        <?php if (!empty($search_bill) || !empty($search_customer) || !empty($search_date)): ?>
            Search Results: <strong><?php echo count($advances); ?></strong> unpaid advance bill(s) found
        <?php else: ?>
            Total Unpaid Advance Bills: <strong><?php echo count($advances); ?></strong>
        <?php endif; ?>
    </div>

    <?php if (empty($advances)): ?>
        <div class="no-data">
            <?php if (!empty($search_bill) || !empty($search_customer) || !empty($search_date)): ?>
                No advance bills found matching your search.
            <?php else: ?>
                No pending advance bills. All are completed!
            <?php endif; ?>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Bill No.</th>
                    <th>Date (BS)</th>
                    <th>Customer</th>
                    <th>Advance</th>
                    <th>Total Bill</th>
                    <th>Remaining</th>
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
                        <td data-label="Advance" class="amount">Rs. <?php echo number_format($row['advance_amount']); ?></td>
                        <td data-label="Total" class="total">Rs. <?php echo number_format($row['total']); ?></td>
                        <td data-label="Remaining" class="remaining <?php echo $remaining > 0 ? 'positive' : 'zero'; ?>">
                            Rs. <?php echo number_format($remaining); ?>
                            <?php echo $remaining == 0 ? ' (Paid)' : ''; ?>
                        </td>
                        <td data-label="Action">
                            <!-- Complete Bill -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="complete_bill" value="1">
                                <input type="hidden" name="bill_number" value="<?php echo $row['bill_number']; ?>">
                                <button type="submit" class="action-btn complete-btn">Complete Bill</button>
                            </form>

                            <!-- Return / Exchange -->
                            <button type="button" class="action-btn" 
                                    onclick="openReturnExchangeModal('<?php echo $row['bill_number']; ?>')">
                                Return / Exchange
                            </button>
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

<!-- Return / Exchange Modal -->
<div id="returnExchangeModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:1000; justify-content:center; align-items:center;">
    <div style="background:white; padding:30px; border-radius:12px; max-width:600px; width:95%; max-height:90%; overflow:auto;">
        <h2>Return / Exchange for Bill: <span id="modalBillNumber"></span></h2>
        <form id="returnExchangeForm" method="POST">
            <input type="hidden" name="bill_number" id="modal_bill_number">
            <label>Action:</label>
            <select name="action_type" required style="width:100%; padding:10px; margin-bottom:15px;">
                <option value="">Select Action</option>
                <option value="returned">Return</option>
                <option value="exchanged">Exchange</option>
                <option value="cancelled">Cancel</option>
            </select>

            <label>Amount Returned To Customer:</label>
            <input type="number" name="amount_returned_to_customer" required style="width:100%; padding:10px; margin-bottom:15px;" min="0" step="0.01">

            <label>Amount Returned By Customer:</label>
            <input type="number" name="amount_returned_by_customer" required style="width:100%; padding:10px; margin-bottom:15px;" min="0" step="0.01">

            <label>Reason:</label>
            <textarea name="reason" required style="width:100%; padding:10px; margin-bottom:20px;" placeholder="Enter reason for return/exchange/cancel"></textarea>

            <button type="submit" class="action-btn" style="margin-right:10px;">Submit</button>
            <button type="button" onclick="closeReturnExchangeModal()" style="background:#95a5a6;">Cancel</button>
        </form>
    </div>
</div>

<script>
function openReturnExchangeModal(billNumber) {
    document.getElementById('returnExchangeModal').style.display = 'flex';
    document.getElementById('modalBillNumber').innerText = billNumber;
    document.getElementById('modal_bill_number').value = billNumber;
}

function closeReturnExchangeModal() {
    document.getElementById('returnExchangeModal').style.display = 'none';
}
</script>
</body>
</html>
