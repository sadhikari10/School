<?php
session_start();
require '../Common/connection.php';
require '../Common/nepali_date.php';
require '../vendor/autoload.php'; // PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Security
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

// ============= EXCEL EXPORT LOGIC =============
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $search_bill            = trim($_GET['bill'] ?? '');
    $search_customer        = trim($_GET['customer'] ?? '');
    $search_date            = trim($_GET['date'] ?? '');
    $search_payment         = trim($_GET['payment_method'] ?? '');
    $search_advance_payment = trim($_GET['advance_payment_method'] ?? '');
    $outlet_id              = $_SESSION['outlet_id'] ?? 0;

    $sql = "SELECT 
                s.bill_number,
                DATE(s.bs_datetime) as bs_date,
                s.customer_name,
                s.school_name,
                s.total,
                s.advance_amount,
                s.final_amount,
                s.payment_method,
                s.advance_payment_method,
                s.items_json
            FROM sales s
            WHERE s.printed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
              AND s.exchange_status = 'original'
              AND s.outlet_id = ?";

    $params = [$outlet_id];

    if ($search_bill !== '') {
        $sql .= " AND s.bill_number LIKE ?";
        $params[] = "%$search_bill%";
    }
    if ($search_customer !== '') {
        $sql .= " AND s.customer_name LIKE ?";
        $params[] = "%$search_customer%";
    }
    if ($search_date !== '') {
        $sql .= " AND DATE(s.bs_datetime) = ?";
        $params[] = $search_date;
    }
    if ($search_payment !== '') {
        $sql .= " AND s.payment_method = ?";
        $params[] = $search_payment;
    }
    if ($search_advance_payment !== '') {
        $sql .= " AND s.advance_payment_method = ?";
        $params[] = $search_advance_payment;
    }

    $sql .= " ORDER BY s.printed_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare items text for Excel
    foreach ($sales as &$s) {
        $items = json_decode($s['items_json'] ?? '', true) ?? [];
        $itemsText = '';
        foreach ($items as $it) {
            $itemsText .= ($it['name'] ?? '') . ' (' . ($it['size'] ?? 'N/A') . ') ×' .
                          ($it['quantity'] ?? 0) . ' @ ' . number_format($it['price'] ?? 0, 2) . "\n";
        }
        $s['items_text'] = $itemsText ?: '';
    }
    unset($s);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Exchange Return Sales');

    // Headers (Items column added)
    $headers = ['S.N', 'Bill No.', 'Date (BS)', 'Customer', 'School', 'Total', 'Advance Amt', 'Final Amt', 'Final Pay', 'Adv. Pay Method', 'Items'];
    $col = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue($col++ . '1', $h);
    }

    // Header styling
    $sheet->getStyle('A1:K1')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A1:K1')->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FF8e44ad');

    // Data + Grand Totals
    $row = 2;
    $sn = 1;
    $grand_total = $grand_advance = $grand_final = 0;

    foreach ($sales as $s) {
        $advance_amt = $s['advance_amount'] > 0 ? $s['advance_amount'] : 0;

        $sheet->setCellValue('A' . $row, $sn++);
        $sheet->setCellValue('B' . $row, $s['bill_number']);
        $sheet->setCellValue('C' . $row, $s['bs_date']);
        $sheet->setCellValue('D' . $row, $s['customer_name'] ?: 'Walk-in');
        $sheet->setCellValue('E' . $row, $s['school_name']);
        $sheet->setCellValue('F' . $row, $s['total']);
        $sheet->setCellValue('G' . $row, $advance_amt);
        $sheet->setCellValue('H' . $row, $s['final_amount']);
        $sheet->setCellValue('I' . $row, ucfirst($s['payment_method'] ?? ''));
        $sheet->setCellValue('J' . $row, $s['advance_payment_method'] ? ucfirst($s['advance_payment_method']) : '-');
        $sheet->setCellValue('K' . $row, $s['items_text']);
        $sheet->getStyle('K' . $row)->getAlignment()->setWrapText(true);

        $grand_total   += $s['total'];
        $grand_advance += $advance_amt;
        $grand_final   += $s['final_amount'];
        $row++;
    }

    // Grand Total Row
    $sheet->setCellValue('E' . $row, 'GRAND TOTAL');
    $sheet->setCellValue('F' . $row, $grand_total);
    $sheet->setCellValue('G' . $row, $grand_advance);
    $sheet->setCellValue('H' . $row, $grand_final);
    $sheet->getStyle('E' . $row . ':H' . $row)->getFont()->setBold(true)->setSize(13);
    $sheet->getStyle('F2:H' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle('F2:H' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    // Auto-size columns
    foreach (range('A', 'K') as $c) {
        $sheet->getColumnDimension($c)->setAutoSize(true);
    }
    $sheet->getColumnDimension('K')->setWidth(60);

    $sheet->freezePane('A2');

    // Download
    ob_end_clean();
    $filename = "Sales_" . date('Y-m-d') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}

// ============= NORMAL DISPLAY LOGIC =============
$search_bill            = trim($_GET['bill'] ?? '');
$search_customer        = trim($_GET['customer'] ?? '');
$search_date            = trim($_GET['date'] ?? '');
$search_payment         = trim($_GET['payment_method'] ?? '');
$search_advance_payment = trim($_GET['advance_payment_method'] ?? '');
$outlet_id              = $_SESSION['outlet_id'] ?? 0;

$sql = "SELECT 
            s.bill_number,
            DATE(s.bs_datetime) as bs_date,
            s.customer_name,
            s.school_name,
            s.total,
            s.advance_amount,
            s.final_amount,
            s.payment_method,
            s.advance_payment_method,
            s.items_json
        FROM sales s
        WHERE s.printed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          AND s.exchange_status = 'original'
          AND s.outlet_id = ?";

$params = [$outlet_id];

if ($search_bill !== '') {
    $sql .= " AND s.bill_number LIKE ?";
    $params[] = "%$search_bill%";
}
if ($search_customer !== '') {
    $sql .= " AND s.customer_name LIKE ?";
    $params[] = "%$search_customer%";
}
if ($search_date !== '') {
    $sql .= " AND DATE(s.bs_datetime) = ?";
    $params[] = $search_date;
}
if ($search_payment !== '') {
    $sql .= " AND s.payment_method = ?";
    $params[] = $search_payment;
}
if ($search_advance_payment !== '') {
    $sql .= " AND s.advance_payment_method = ?";
    $params[] = $search_advance_payment;
}

$sql .= " ORDER BY s.printed_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare items display for HTML
foreach ($sales as &$s) {
    $items = json_decode($s['items_json'] ?? '', true) ?? [];
    $itemsText = '';
    foreach ($items as $it) {
        $itemsText .= htmlspecialchars($it['name'] ?? '') .
                      ' (' . htmlspecialchars($it['size'] ?? 'N/A') . ') × ' .
                      ($it['quantity'] ?? 0) . ' @ ' .
                      number_format($it['price'] ?? 0, 2) . "<br>";
    }
    $s['items_display'] = $itemsText ?: '-';
}
unset($s);
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
        max-width:1300px;
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
    }
    .search-bar form{
        display:flex;
        flex-wrap:wrap;
        gap:12px;
        align-items:end;
        justify-content:center;
    }
    .search-bar input,
    .search-bar select{
        padding:12px 14px;
        border-radius:8px;
        border:1px solid #ddd;
        font-size:1rem;
        min-width:160px;
    }
    .search-bar button{
        padding:12px 24px;
        background:#8e44ad;
        color:white;
        border:none;
        border-radius:8px;
        cursor:pointer;
        font-weight:bold;
    }
    .clear-btn{
        background:#e74c3c !important;
        margin-left:8px;
    }
    table{
        width:100%;
        border-collapse:collapse;
        margin-top:20px;
        background:white;
    }
    th, td{
        padding:10px 8px;
        border:1px solid #ddd;
        text-align:left;
        font-size:0.95rem;
        vertical-align:middle;
    }
    th{
        background:#8e44ad;
        color:white;
    }
    .numeric, .amount{
        text-align:right;
        font-weight:600;
    }
    .action-btn{
        padding:8px 14px;
        background:#e67e22;
        color:white;
        border:none;
        border-radius:6px;
        cursor:pointer;
        font-size:0.9rem;
    }
    .action-btn:hover{
        background:#d35400;
    }
    .actions{
        text-align:center;
        margin:30px 0;
    }
    .export-btn{
        display:inline-block;
        padding:14px 32px;
        background:#27ae60;
        color:white;
        border-radius:50px;
        text-decoration:none;
        font-weight:bold;
        box-shadow:0 8px 20px rgba(39,174,96,0.3);
    }
    .export-btn:hover{
        background:#219a52;
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
    .no-data{
        text-align:center;
        padding:60px;
        color:#8e44ad;
        font-size:1.2rem;
    }
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
            <input type="text" name="date" placeholder="BS Date (YYYY-MM-DD)" value="<?=htmlspecialchars($search_date)?>">
            <select name="payment_method">
                <option value="">All Final Payment</option>
                <option value="cash" <?= $search_payment === 'cash' ? 'selected' : '' ?>>Cash</option>
                <option value="online" <?= $search_payment === 'online' ? 'selected' : '' ?>>Online</option>
            </select>
            <select name="advance_payment_method">
                <option value="">All Advance Payment</option>
                <option value="cash" <?= $search_advance_payment === 'cash' ? 'selected' : '' ?>>Cash (Advance)</option>
                <option value="online" <?= $search_advance_payment === 'online' ? 'selected' : '' ?>>Online (Advance)</option>
            </select>
            <button type="submit">Search</button>
            <a href="sales_return.php"><button type="button" class="clear-btn">Clear</button></a>
        </form>
    </div>

    <div class="actions">
        <a href="sales_return.php?export=excel<?php 
            $query = $_SERVER['QUERY_STRING'] ?? '';
            echo $query ? '&' . htmlentities($query) : '';
        ?>" class="export-btn">
            <i class="fas fa-file-excel"></i> Export to Excel
        </a>
    </div>

    <?php if (empty($sales)): ?>
        <div class="no-data">
            <i class="fas fa-receipt fa-3x" style="color:#8e44ad;margin-bottom:20px;"></i><br>
            No eligible sales found in the last 7 days.
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
                    <th class="numeric">Total</th>
                    <th class="numeric">Advance</th>
                    <th class="numeric">Final</th>
                    <th>Final Pay</th>
                    <th>Adv. Pay</th>
                    <th>Items</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sales as $i => $s): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong>#<?= htmlspecialchars($s['bill_number']) ?></strong></td>
                    <td><?= htmlspecialchars($s['bs_date']) ?></td>
                    <td><?= htmlspecialchars($s['customer_name'] ?: 'Walk-in') ?></td>
                    <td><?= htmlspecialchars($s['school_name']) ?></td>
                    <td class="numeric">Rs. <?= number_format($s['total'], 2) ?></td>
                    <td class="numeric">Rs. <?= number_format($s['advance_amount'] > 0 ? $s['advance_amount'] : 0, 2) ?></td>
                    <td class="numeric">Rs. <?= number_format($s['final_amount'], 2) ?></td>
                    <td><?= ucfirst($s['payment_method'] ?? '') ?></td>
                    <td><?= $s['advance_payment_method'] ? ucfirst($s['advance_payment_method']) : '-' ?></td>
                    <td style="font-size:0.9rem;">
                        <?= $s['items_display'] ?>
                    </td>
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