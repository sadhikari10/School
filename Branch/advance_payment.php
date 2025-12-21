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

// Handle Complete Bill redirect
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_bill'])) {
    $bill_number = (int)$_POST['bill_number'];
    if ($bill_number > 0) {
        $_SESSION['complete_bill_number'] = $bill_number;
        header("Location: final_bill.php");
        exit();
    }
}

// Handle Exchange/Return Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exchange_return'])) {
    $bill_number = (int)$_POST['bill_number'];
    $action = $_POST['action_type'];
    $reason = trim($_POST['reason']);
    $amount_paid = (float)$_POST['amount_paid'];
    $amount_returned = (float)$_POST['amount_returned'];
    $user_id = $_SESSION['user_id'];
    $outlet_id = $_SESSION['selected_outlet_id'] ?? 1;
    $bs_date = nepali_date_time();

    $stmt = $pdo->prepare("INSERT INTO return_exchange_log 
        (bill_id, user_id, outlet_id, action, reason, amount_returned_by_customer, amount_returned_to_customer, logged_datetime)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $bill_number, $user_id, $outlet_id, $action, $reason, $amount_paid, $amount_returned, $bs_date
    ]);

    $_SESSION['message'] = "Exchange/Return logged successfully for Bill #$bill_number";
    header("Location: advance_payment.php");
    exit();
}
// ============= EXCEL EXPORT LOGIC =============
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $search_bill = trim($_GET['bill'] ?? '');
    $search_customer = trim($_GET['customer'] ?? '');
    $search_date = trim($_GET['date'] ?? '');
    $search_payment = trim($_GET['payment_method'] ?? '');
    $outlet_id = $_SESSION['outlet_id'] ?? 0;

    $sql = "SELECT ap.bill_number, ap.bs_datetime, ap.customer_name, ap.advance_amount, ap.total,
            (ap.total - ap.advance_amount) AS remaining, ap.payment_method, ap.items_json,
            o.location AS branch_name
            FROM advance_payment ap
            LEFT JOIN outlets o ON ap.outlet_id = o.outlet_id
            WHERE ap.status = 'unpaid' AND ap.outlet_id = ?";
    $params = [$outlet_id];

    if ($search_bill !== '') {
        $sql .= " AND ap.bill_number LIKE ?";
        $params[] = '%' . $search_bill . '%';
    }
    if ($search_customer !== '') {
        $sql .= " AND ap.customer_name LIKE ?";
        $params[] = '%' . $search_customer . '%';
    }
    if ($search_date !== '') {
        $sql .= " AND ap.bs_datetime LIKE ?";
        $params[] = $search_date . '%';
    }
    if ($search_payment !== '') {
        $sql .= " AND ap.payment_method = ?";
        $params[] = $search_payment;
    }

    $sql .= " ORDER BY ap.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $advances = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare items text for Excel
    foreach ($advances as &$adv) {
        $items = json_decode($adv['items_json'] ?? '', true) ?? [];
        $itemsText = '';
        foreach ($items as $it) {
            $itemsText .= ($it['name'] ?? '') . ' (' . ($it['size'] ?? 'N/A') . ') x' .
                          ($it['quantity'] ?? 0) . ' @ ' . number_format($it['price'] ?? 0, 2) . "\n";
        }
        $adv['items_text'] = $itemsText ?: '';
    }
    unset($adv);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Advance Payments');

    // Headers
    $headers = ['S.N', 'Bill No.', 'Date (BS)', 'Customer', 'Advance Amount', 'Total Bill', 'Remaining', 'Payment Method', 'Branch', 'Items'];
    $col = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue($col . '1', $h);
        $col++;
    }

    // Header styling
    $sheet->getStyle('A1:J1')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A1:J1')->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FF667eea');

    // Data rows
    $row = 2;
    $sn = 1;
    $grand_advance = $grand_total = $grand_remaining = 0;

    foreach ($advances as $adv) {
        $remaining = $adv['remaining'];
        $payment = ucfirst($adv['payment_method'] ?? 'N/A');

        $sheet->setCellValue('A' . $row, $sn++);
        $sheet->setCellValue('B' . $row, $adv['bill_number']);
        $sheet->setCellValue('C' . $row, $adv['bs_datetime']);
        $sheet->setCellValue('D' . $row, $adv['customer_name'] ?: 'Walk-in');
        $sheet->setCellValue('E' . $row, $adv['advance_amount']);
        $sheet->setCellValue('F' . $row, $adv['total']);
        $sheet->setCellValue('G' . $row, $remaining);
        $sheet->setCellValue('H' . $row, $payment);
        $sheet->setCellValue('I' . $row, $adv['branch_name'] ?? 'Unknown');
        $sheet->setCellValue('J' . $row, $adv['items_text']);
        $sheet->getStyle('J' . $row)->getAlignment()->setWrapText(true);

        $grand_advance += $adv['advance_amount'];
        $grand_total += $adv['total'];
        $grand_remaining += $remaining;

        $row++;
    }

    // Grand Total Row
    $sheet->setCellValue('D' . $row, 'GRAND TOTAL');
    $sheet->setCellValue('E' . $row, $grand_advance);
    $sheet->setCellValue('F' . $row, $grand_total);
    $sheet->setCellValue('G' . $row, $grand_remaining);
    $sheet->getStyle('D' . $row . ':J' . $row)->getFont()->setBold(true)->setSize(13);
    $sheet->getStyle('E' . $row . ':G' . $row)->getNumberFormat()->setFormatCode('#,##0.00');

    $row += 3; // Space before summary

    // === ADVANCE COLLECTION SUMMARY (CASH vs ONLINE) ===
    $advance_cash = 0;
    $advance_online = 0;
    $grand_advance_total = 0;

    foreach ($advances as $adv) {
        $advance_amount = (float)$adv['advance_amount'];
        $pay_method = strtolower(trim($adv['payment_method'] ?? ''));

        $grand_advance_total += $advance_amount;

        if ($pay_method === 'cash') {
            $advance_cash += $advance_amount;
        } elseif ($pay_method === 'online') {
            $advance_online += $advance_amount;
        }
    }

    $sheet->setCellValue('A' . $row, 'ADVANCE COLLECTION SUMMARY');
    $sheet->mergeCells('A' . $row . ':J' . $row);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FFFFFFFF');
    $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF667eea');
    $row++;

    $sheet->setCellValue('A' . $row, 'Cash Advance Collected');
    $sheet->setCellValue('C' . $row, $advance_cash);
    $row++;

    $sheet->setCellValue('A' . $row, 'Online Advance Collected');
    $sheet->setCellValue('C' . $row, $advance_online);
    $row++;

    $sheet->setCellValue('A' . $row, 'Total Advance Collected');
    $sheet->setCellValue('C' . $row, $grand_advance_total);
    $sheet->getStyle('A' . ($row-2) . ':C' . $row)->getFont()->setBold(true)->setSize(13);
    $sheet->getStyle('C' . ($row-2) . ':C' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle('C' . ($row-2) . ':C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setSize(15)->getColor()->setARGB('FF667eea');

    // General formatting
    $sheet->getStyle('E2:G' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle('E2:G' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    foreach (range('A', 'J') as $c) {
        $sheet->getColumnDimension($c)->setAutoSize(true);
    }
    $sheet->getColumnDimension('J')->setWidth(60);
    $sheet->freezePane('A2');

    // Download
    ob_end_clean();
    $filename = "Advance_Payments_" . date('Y-m-d') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}


// ============= NORMAL DISPLAY LOGIC =============
$search_bill = trim($_GET['bill'] ?? '');
$search_customer = trim($_GET['customer'] ?? '');
$search_date = trim($_GET['date'] ?? '');
$search_payment = trim($_GET['payment_method'] ?? '');
$outlet_id = $_SESSION['outlet_id'] ?? 0;

$sql = "SELECT ap.*, o.location AS branch_name 
        FROM advance_payment ap 
        LEFT JOIN outlets o ON ap.outlet_id = o.outlet_id 
        WHERE ap.status = 'unpaid' AND ap.outlet_id = ?";
$params = [$outlet_id];

if ($search_bill !== '') {
    $sql .= " AND ap.bill_number LIKE ?";
    $params[] = '%' . $search_bill . '%';
}
if ($search_customer !== '') {
    $sql .= " AND ap.customer_name LIKE ?";
    $params[] = '%' . $search_customer . '%';
}
if ($search_date !== '') {
    $sql .= " AND ap.bs_datetime LIKE ?";
    $params[] = $search_date . '%';
}
if ($search_payment !== '') {
    $sql .= " AND ap.payment_method = ?";
    $params[] = $search_payment;
}

$sql .= " ORDER BY ap.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$advances = $stmt->fetchAll(PDO::FETCH_ASSOC);

$advance_cash = 0;
$advance_online = 0;
$grand_advance_total = 0;

foreach ($advances as $adv) {
    $advance_amount = (float)$adv['advance_amount'];
    $pay_method = strtolower(trim($adv['payment_method'] ?? ''));

    $grand_advance_total += $advance_amount;

    if ($pay_method === 'cash') {
        $advance_cash += $advance_amount;
    } elseif ($pay_method === 'online') {
        $advance_online += $advance_amount;
    }
}
// Prepare items display for HTML
foreach ($advances as &$adv) {
    $items = json_decode($adv['items_json'] ?? '', true) ?? [];
    $itemsText = '';
    foreach ($items as $it) {
        $itemsText .= htmlspecialchars($it['name'] ?? '') .
                      ' (' . htmlspecialchars($it['size'] ?? 'N/A') . ') x ' .
                      ($it['quantity'] ?? 0) . ' @ ' .
                      number_format($it['price'] ?? 0, 2) . "<br>";
    }
    $adv['items_display'] = $itemsText ?: '-';
}
unset($adv);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advance Payments Record</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; margin: 0; padding: 20px; color: #2c3e50; }
        .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 28px; font-weight: 600; }
        .header p { margin: 10px 0 0; opacity: 0.95; }

        .search-bar { padding: 20px; background: #f8f9fa; border-bottom: 1px solid #eee; display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; align-items: center; }
        .search-bar input, .search-bar select, .search-bar button { padding: 12px 16px; font-size: 15px; border: 1px solid #ddd; border-radius: 8px; }
        .search-bar input, .search-bar select { width: 200px; }
        .search-bar button { background: #667eea; color: white; cursor: pointer; font-weight: bold; border: none; }
        .search-bar button:hover { background: #5a6fd8; }
        .clear-btn { background: #95a5a6 !important; }
        .clear-btn:hover { background: #7f8c8d !important; }

        .actions { text-align: center; padding: 15px; background: #f8f9fa; }
        .export-btn { display: inline-block; padding: 12px 28px; background: #27ae60; color: white; text-decoration: none; border-radius: 50px; font-weight: bold; margin: 0 10px; }
        .export-btn:hover { background: #219653; }

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
        .payment { font-weight: 600; text-transform: capitalize; }

        .date { font-family: 'Courier New', monospace; color: #7f8c8d; }

        .action-btn, .exchange-btn {
            background: #27ae60; color: white; border: none; padding: 10px 18px;
            border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; margin: 0 4px;
        }
        .action-btn:hover, .exchange-btn:hover { background: #219653; }

        .no-data { text-align: center; padding: 80px 20px; color: #95a5a6; font-size: 20px; }
            .back-btn { 
        display: inline-block; 
        margin-left: 20px;
        padding: 12px 28px; 
        background: #667eea; 
        color: white; 
        text-decoration: none; 
        border-radius: 50px; 
        font-weight: bold; 
    }
    .back-btn:hover { 
        background: #5a6fd8; 
    }
        .modal { display: none; position: fixed; z-index: 100; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 10% auto; padding: 30px; border: 1px solid #888; width: 400px; border-radius: 12px; position: relative; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; position: absolute; right: 20px; top: 10px; cursor: pointer; }
        .close:hover { color: black; }
        .modal-content input, .modal-content select, .modal-content textarea { width: 100%; padding: 10px; margin: 10px 0; border-radius: 6px; border: 1px solid #ddd; font-size: 14px; }
        .modal-content button { width: 100%; padding: 12px; border: none; background: #667eea; color: white; font-size: 16px; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .modal-content button:hover { background: #5a6fd8; }

        @media (max-width: 768px) {
            .search-bar { flex-direction: column; }
            .search-bar input, .search-bar select { width: 100%; }
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

    <?php if(isset($_SESSION['message'])): ?>
        <div class="stats"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
    <?php endif; ?>

    <!-- Search Bar with Payment Method Filter -->
    <div class="search-bar">
        <form method="GET">
            <input type="text" name="bill" placeholder="Bill Number" value="<?php echo htmlspecialchars($search_bill); ?>">
            <input type="text" name="customer" placeholder="Customer Name" value="<?php echo htmlspecialchars($search_customer); ?>">
            <input type="text" name="date" placeholder="Date (e.g. 2081-08-15)" value="<?php echo htmlspecialchars($search_date); ?>">
            <select name="payment_method">
                <option value="">All Payment Methods</option>
                <option value="cash" <?php echo $search_payment === 'cash' ? 'selected' : ''; ?>>Cash</option>
                <option value="online" <?php echo $search_payment === 'online' ? 'selected' : ''; ?>>Online</option>
            </select>
            <button type="submit">Search</button>
            <a href="advance_payment.php"><button type="button" class="clear-btn">Clear</button></a>
        </form>
    </div>

    <!-- Export Button -->
    <div class="actions">
        <a href="advance_payment.php?export=excel<?php 
            $query = $_SERVER['QUERY_STRING'] ?? '';
            echo $query ? '&' . htmlentities($query) : '';
        ?>" class="export-btn">
            <i class="fas fa-file-excel"></i> Export to Excel
        </a>
        <a href="dashboard.php" class="back-btn">
            <i class="fas fa-home"></i> Back to Dashboard
        </a>
    </div>

    
    <?php if (empty($advances)): ?>
        <div class="no-data">
            <?php if (!empty($search_bill) || !empty($search_customer) || !empty($search_date) || !empty($search_payment)): ?>
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
                <th>Payment Method</th>
                <th>Branch</th>
                <th>Items</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($advances as $i => $row): 
                $remaining = $row['total'] - $row['advance_amount'];
                $payment = ucfirst($row['payment_method'] ?? 'N/A');
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
                    <td data-label="Payment Method" class="payment"><?php echo $payment; ?></td>
                    <td data-label="Branch"><?php echo htmlspecialchars($row['branch_name'] ?? 'Unknown'); ?></td>
                    <td data-label="Items" style="font-size:0.9rem;">
                        <?= $row['items_display'] ?>
                    </td>
                    <td data-label="Action">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="complete_bill" value="1">
                            <input type="hidden" name="bill_number" value="<?php echo $row['bill_number']; ?>">
                            <button type="submit" class="action-btn">Complete Bill</button>
                        </form>
                        <button class="exchange-btn" onclick="openModal('<?php echo $row['bill_number']; ?>')">Exchange/Return</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <div class="stats" style="background:#f0f8ff; border:2px solid #667eea; border-radius:16px; padding:20px; margin:20px 0; text-align:center; font-size:18px;">
        <strong>TOTAL ADVANCE COLLECTED</strong><br>
        <span style="font-size:28px; color:#667eea; font-weight:bold;">
            Rs. <?= number_format($grand_advance_total, 2) ?>
        </span><br><br>

        <div style="display:flex; justify-content:center; gap:60px; flex-wrap:wrap; margin-top:10px;">
            <div>
                <strong style="color:#27ae60;">Cash Advance</strong><br>
                <span style="font-size:22px; color:#27ae60;">Rs. <?= number_format($advance_cash, 2) ?></span>
            </div>
            <div>
                <strong style="color:#e67e22;">Online Advance</strong><br>
                <span style="font-size:22px; color:#e67e22;">Rs. <?= number_format($advance_online, 2) ?></span>
            </div>
        </div>

        <?php if (!empty($search_bill) || !empty($search_customer) || !empty($search_date) || !empty($search_payment)): ?>
            <div style="margin-top:20px; font-size:15px; color:#7f8c8d;">
                Filtered Results: <strong><?= count($advances) ?></strong> unpaid advance bill(s)
            </div>
        <?php else: ?>
            <div style="margin-top:20px; font-size:15px; color:#7f8c8d;">
                Total Unpaid Advance Bills: <strong><?= count($advances) ?></strong>
            </div>
        <?php endif; ?>
    </div>


</div>

<!-- Modal -->
<div id="exchangeModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h3>Exchange / Return</h3>
        <form method="POST">
            <input type="hidden" id="modal_bill_number" name="bill_number">
            <label>Action Type</label>
            <select name="action_type" required>
                <option value="returned">Returned</option>
                <option value="exchanged">Exchanged</option>
            </select>
            <label>Reason</label>
            <textarea name="reason" placeholder="Enter reason" required></textarea>
            <label>Amount Paid by Customer</label>
            <input type="number" step="0.01" name="amount_paid" required>
            <label>Amount Returned to Customer</label>
            <input type="number" step="0.01" name="amount_returned" required>
            <button type="submit" name="exchange_return">Submit</button>
        </form>
    </div>
</div>

<script>
    function openModal(billNumber) {
        document.getElementById('modal_bill_number').value = billNumber;
        document.getElementById('exchangeModal').style.display = 'block';
    }
    function closeModal() {
        document.getElementById('exchangeModal').style.display = 'none';
    }
    window.onclick = function(event) {
        if (event.target == document.getElementById('exchangeModal')) {
            closeModal();
        }
    }
</script>
</body>
</html>