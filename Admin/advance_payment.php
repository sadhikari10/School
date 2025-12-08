<?php
session_start();
require '../Common/connection.php';
require '../Common/nepali_date.php';
require '../vendor/autoload.php'; // PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Security check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['selected_outlet_id'])) {
    header("Location: index.php");
    exit();
}

$outlet_id   = $_SESSION['selected_outlet_id'];
$outlet_name = $_SESSION['selected_outlet_name'] ?? 'Unknown Outlet';

// =============================================
// EXCEL EXPORT LOGIC
// =============================================
if (isset($_GET['export']) && $_GET['export'] === 'excel') {

    $params = [$outlet_id];
    $filters = ["status = 'unpaid'"];

    // Apply all filters (same logic as display)
    if (!empty($_GET['fiscal_year'])) { $filters[] = "fiscal_year = ?"; $params[] = $_GET['fiscal_year']; }
    if (!empty($_GET['school_name'])) { $filters[] = "school_name LIKE ?"; $params[] = "%{$_GET['school_name']}%"; }
    if (!empty($_GET['customer_name'])) { $filters[] = "customer_name LIKE ?"; $params[] = "%{$_GET['customer_name']}%"; }
    if (!empty($_GET['payment_method'])) { $filters[] = "payment_method = ?"; $params[] = $_GET['payment_method']; }
    if (!empty($_GET['printed_by'])) { $filters[] = "printed_by LIKE ?"; $params[] = "%{$_GET['printed_by']}%"; }
    if (!empty($_GET['bill_number'])) { $filters[] = "bill_number LIKE ?"; $params[] = "%{$_GET['bill_number']}%"; }

    if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
        $filters[] = "bs_datetime BETWEEN ? AND ?";
        $params[] = $_GET['start_date'] . ' 00:00:00';
        $params[] = $_GET['end_date'] . ' 23:59:59';
    } elseif (empty($_GET['start_date']) && empty($_GET['end_date'])) {
        $today = explode(' ', nepali_date_time())[0];
        [$y, $m, $d] = explode('-', $today);
        $last7 = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = max(1, (int)$d - $i);
            $last7[] = sprintf('%04d-%02d-%02d', $y, $m, $day);
        }
        $placeholders = implode(',', array_fill(0, count($last7), '?'));
        $filters[] = "DATE(bs_datetime) IN ($placeholders)";
        $params = array_merge($params, $last7);
    }

    $sql = "SELECT * FROM advance_payment WHERE outlet_id = ? AND " . implode(' AND ', $filters) . " ORDER BY bs_datetime DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Create Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Advance Payments');

    // Headers
    $headers = ['S.N', 'Bill Number', 'Fiscal Year', 'School', 'Customer', 'Advance Amount', 'Payment Method', 'Printed By', 'Date & Time', 'Items'];
    $col = 'A';
    foreach ($headers as $h) $sheet->setCellValue($col++ . '1', $h);

    // Style header
    $sheet->getStyle('A1:' . $col . '1')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A1:' . $col . '1')->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FF8E44AD');

    // Fill data
    $row = 2;
    foreach ($payments as $i => $p) {
        $items = json_decode($p['items_json'], true) ?? [];
        $itemsText = '';
        foreach ($items as $it) {
            $itemsText .= $it['name'] . ' (' . ($it['size'] ?? '') . ') ×' . $it['quantity'] . ' @' . number_format($it['price'], 2) . "\n";
        }

        $sheet->setCellValue('A' . $row, $i + 1);
        $sheet->setCellValue('B' . $row, $p['bill_number']);
        $sheet->setCellValue('C' . $row, $p['fiscal_year']);
        $sheet->setCellValue('D' . $row, $p['school_name']);
        $sheet->setCellValue('E' . $row, $p['customer_name'] ?: 'Walk-in');
        $sheet->setCellValue('F' . $row, $p['advance_amount']);
        $sheet->setCellValue('G' . $row, $p['payment_method']);
        $sheet->setCellValue('H' . $row, $p['printed_by'] ?? '');
        $sheet->setCellValue('I' . $row, $p['bs_datetime']);
        $sheet->setCellValue('J' . $row, $itemsText);
        $sheet->getStyle('J' . $row)->getAlignment()->setWrapText(true);
        $row++;
    }

    // Grand Total
    $sheet->setCellValue('E' . $row, 'GRAND TOTAL');
    $sheet->setCellValue('F' . $row, '=SUM(F2:F' . ($row - 1) . ')');
    $sheet->getStyle('E' . $row . ':F' . $row)->getFont()->setBold(true)->setSize(13);

    // Auto-size
    foreach (range('A', 'J') as $c) $sheet->getColumnDimension($c)->setAutoSize(true);

    // Download
    $filename = "Advance_Payments_Unpaid_" . date('Y-m-d') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// =============================================
// NORMAL DISPLAY (same as before)
// =============================================
$params = [$outlet_id];
$filters = ["status = 'unpaid'"];

if (!empty($_GET['fiscal_year'])) { $filters[] = "fiscal_year = ?"; $params[] = $_GET['fiscal_year']; }
if (!empty($_GET['school_name'])) { $filters[] = "school_name LIKE ?"; $params[] = "%{$_GET['school_name']}%"; }
if (!empty($_GET['customer_name'])) { $filters[] = "customer_name LIKE ?"; $params[] = "%{$_GET['customer_name']}%"; }
if (!empty($_GET['payment_method'])) { $filters[] = "payment_method = ?"; $params[] = $_GET['payment_method']; }
if (!empty($_GET['printed_by'])) { $filters[] = "printed_by LIKE ?"; $params[] = "%{$_GET['printed_by']}%"; }
if (!empty($_GET['bill_number'])) { $filters[] = "bill_number LIKE ?"; $params[] = "%{$_GET['bill_number']}%"; }

if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    $filters[] = "bs_datetime BETWEEN ? AND ?";
    $params[] = $_GET['start_date'] . ' 00:00:00';
    $params[] = $_GET['end_date'] . ' 23:59:59';
} elseif (empty($_GET['start_date']) && empty($_GET['end_date'])) {
    $today = explode(' ', nepali_date_time())[0];
    [$y, $m, $d] = explode('-', $today);
    $last7 = [];
    for ($i = 6; $i >= 0; $i--) {
        $day = max(1, (int)$d - $i);
        $last7[] = sprintf('%04d-%02d-%02d', $y, $m, $day);
    }
    $placeholders = implode(',', array_fill(0, count($last7), '?'));
    $filters[] = "DATE(bs_datetime) IN ($placeholders)";
    $params = array_merge($params, $last7);
}

$sql = "SELECT * FROM advance_payment WHERE outlet_id = ? AND " . implode(' AND ', $filters) . " ORDER BY bs_datetime DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Advance Payments (Unpaid) - <?php echo htmlspecialchars($outlet_name); ?></title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
    body{font-family:'Segoe UI',sans-serif;padding:20px;background:#f4f6f9;}
    .container{max-width:1300px;margin:0 auto;}
    h1{color:#2c3e50;text-align:center;margin-bottom:20px;}
    .actions{text-align:center;margin:25px 0;}
    .btn{padding:12px 32px;margin:0 10px;border-radius:50px;color:white;text-decoration:none;font-weight:bold;}
    .btn-excel{background:#e67e22;}
    .btn-excel:hover{background:#d35400;}
    .btn-back{background:#3498db;}
    .btn-back:hover{background:#2980b9;}
    table{width:100%;border-collapse:collapse;background:white;box-shadow:0 5px 20px rgba(0,0,0,0.1);margin-top:20px;}
    th,td{padding:12px;border:1px solid #ddd;text-align:left;font-size:0.95rem;}
    th{background:#8e44ad;color:white;}
    tr:hover{background:#f8f5ff;}
    .items-list{padding-left:20px;margin:5px 0;}
    .filter-form{background:white;padding:20px;border-radius:10px;box-shadow:0 3px 15px rgba(0,0,0,0.1);text-align:center;margin-bottom:20px;}
    .filter-form input{padding:10px 14px;margin:5px;border:1px solid #ddd;border-radius:6px;font-size:0.9rem;}
    .filter-form button{padding:10px 24px;background:#27ae60;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:bold;}
    .filter-form button:hover{background:#219150;}
</style>
</head>
<body>
<div class="container">
    <h1>Advance Payments (Unpaid) - <?php echo htmlspecialchars($outlet_name); ?></h1>

    <form method="GET" class="filter-form">
        <input type="text" name="fiscal_year" placeholder="Fiscal Year" value="<?=htmlspecialchars($_GET['fiscal_year']??'')?>">
        <input type="text" name="school_name" placeholder="School" value="<?=htmlspecialchars($_GET['school_name']??'')?>">
        <input type="text" name="customer_name" placeholder="Customer" value="<?=htmlspecialchars($_GET['customer_name']??'')?>">
        <input type="text" name="payment_method" placeholder="Payment" value="<?=htmlspecialchars($_GET['payment_method']??'')?>">
        <input type="text" name="printed_by" placeholder="Printed By" value="<?=htmlspecialchars($_GET['printed_by']??'')?>">
        <input type="text" name="bill_number" placeholder="Bill No." value="<?=htmlspecialchars($_GET['bill_number']??'')?>">
        <input type="text" name="start_date" placeholder="Start YYYY-MM-DD" value="<?=htmlspecialchars($_GET['start_date']??'')?>">
        <input type="text" name="end_date" placeholder="End YYYY-MM-DD" value="<?=htmlspecialchars($_GET['end_date']??'')?>">
        <button type="submit">Filter</button>
    </form>

    <div class="actions">
        <a href="advance_payment.php?export=excel<?php 
            echo !empty($_SERVER['QUERY_STRING']) ? '&' . htmlentities($_SERVER['QUERY_STRING']) : ''; 
        ?>" class="btn btn-excel">
            Export to Excel
        </a>
        <a href="dashboard.php" class="btn btn-back">Back to Dashboard</a>
    </div>

    <?php if(empty($payments)): ?>
        <p style="text-align:center;color:#7f8c8d;font-size:1.2rem;padding:40px;">No unpaid advance payments found.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Bill Number</th>
                    <th>Fiscal Year</th>
                    <th>School</th>
                    <th>Customer</th>
                    <th>Amount</th>
                    <th>Payment</th>
                    <th>Printed By</th>
                    <th>Date & Time</th>
                    <th>Items</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($payments as $i => $p): 
                $items = json_decode($p['items_json'], true) ?? [];
            ?>
                <tr>
                    <td><?=$i+1?></td>
                    <td><?=htmlspecialchars($p['bill_number'])?></td>
                    <td><?=htmlspecialchars($p['fiscal_year'])?></td>
                    <td><?=htmlspecialchars($p['school_name'])?></td>
                    <td><?=htmlspecialchars($p['customer_name'] ?: 'Walk-in')?></td>
                    <td><?=number_format($p['advance_amount'],2)?></td>
                    <td><?=htmlspecialchars($p['payment_method'])?></td>
                    <td><?=htmlspecialchars($p['printed_by'] ?? '')?></td>
                    <td><?=htmlspecialchars($p['bs_datetime'])?></td>
                    <td>
                        <?php if($items): ?>
                            <ul class="items-list">
                                <?php foreach($items as $it): ?>
                                    <li><?=htmlspecialchars($it['name'])?> (<?=htmlspecialchars($it['size']??'')?>) × <?=$it['quantity']?> @ <?=number_format($it['price'],2)?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: echo '-'; endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>