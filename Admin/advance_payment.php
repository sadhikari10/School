<?php
session_start();
require '../Common/connection.php';
require '../Common/nepali_date.php';
require '../vendor/autoload.php'; // PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Security check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['selected_outlet_id'])) {
    header("Location: index.php");
    exit();
}

$outlet_id   = $_SESSION['selected_outlet_id'];
$outlet_name = $_SESSION['selected_outlet_name'] ?? 'Unknown Outlet';

// =============================================
// EXCEL EXPORT LOGIC (FULLY WORKING + AGGREGATED ITEMS)
// =============================================
if (isset($_GET['export']) && $_GET['export'] === 'excel') {

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

    // Calculate grand totals
    $grand_advance = $grand_total = $grand_remaining = 0;
    foreach ($payments as $p) {
        $grand_advance += $p['advance_amount'];
        $grand_total += $p['total'];
        $grand_remaining += ($p['total'] - $p['advance_amount']);
    }

    // Aggregate items
    $aggregatedItems = [];
    foreach ($payments as $p) {
        $rawItems = json_decode($p['items_json'], true) ?? [];
        foreach ($rawItems as $it) {
            $fullName = $it['name'] ?? '';
            $name = $fullName;
            $brand = 'Unknown';
            if (strpos($fullName, ' - ') !== false) {
                $parts = explode(' - ', $fullName, 2);
                $name = trim($parts[0]);
                $brand = trim($parts[1]);
            }
            $size = $it['size'] ?? 'N/A';
            $price = $it['price'] ?? 0;
            $qty = $it['quantity'] ?? 0;

            $key = $name . '|' . $brand . '|' . $size . '|' . $price;

            if (!isset($aggregatedItems[$key])) {
                $aggregatedItems[$key] = [
                    'name' => $name,
                    'brand' => $brand,
                    'size' => $size,
                    'price' => $price,
                    'quantity' => 0
                ];
            }
            $aggregatedItems[$key]['quantity'] += $qty;
        }
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Advance Payments');

    // Detailed Section
    $headers = ['S.N', 'Bill Number', 'Fiscal Year', 'School', 'Customer', 'Advance', 'Total Bill', 'Remaining', 'Payment', 'Printed By', 'Date & Time', 'Items'];
    $col = 'A';
    foreach ($headers as $h) $sheet->setCellValue($col++ . '1', $h);

    $sheet->getStyle('A1:L1')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A1:L1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF8E44AD');

    $row = 2;
    $sn = 1;
    foreach ($payments as $p) {
        $items = json_decode($p['items_json'], true) ?? [];
        $itemsText = '';
        foreach ($items as $it) {
            $itemsText .= $it['name'] . ' (' . ($it['size'] ?? '') . ') ×' . $it['quantity'] . ' -> ' . number_format($it['price'], 2) . "\n";
        }
        $remaining = $p['total'] - $p['advance_amount'];

        $sheet->setCellValue('A' . $row, $sn++);
        $sheet->setCellValue('B' . $row, $p['bill_number']);
        $sheet->setCellValue('C' . $row, $p['fiscal_year']);
        $sheet->setCellValue('D' . $row, $p['school_name']);
        $sheet->setCellValue('E' . $row, $p['customer_name'] ?: 'Walk-in');
        $sheet->setCellValue('F' . $row, $p['advance_amount']);
        $sheet->setCellValue('G' . $row, $p['total']);
        $sheet->setCellValue('H' . $row, $remaining);
        $sheet->setCellValue('I' . $row, ucfirst($p['payment_method']));
        $sheet->setCellValue('J' . $row, $p['printed_by'] ?? '');
        $sheet->setCellValue('K' . $row, $p['bs_datetime']);
        $sheet->setCellValue('L' . $row, $itemsText);
        $sheet->getStyle('L' . $row)->getAlignment()->setWrapText(true);
        $row++;
    }

    // Grand Total (Detailed)
    $sheet->setCellValue('E' . $row, 'GRAND TOTAL');
    $sheet->setCellValue('F' . $row, $grand_advance);
    $sheet->setCellValue('G' . $row, $grand_total);
    $sheet->setCellValue('H' . $row, $grand_remaining);
    $sheet->getStyle('E' . $row . ':H' . $row)->getFont()->setBold(true)->setSize(13);
    $sheet->getStyle('F2:H' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle('F2:H' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $row += 2;

    // Aggregated Items Section
    if (!empty($aggregatedItems)) {
        $sheet->setCellValue('A' . $row, 'AGGREGATED ITEM SUMMARY');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
        $sheet->mergeCells('A' . $row . ':G' . $row);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;

        $summaryHeaders = ['S.N', 'Name', 'Brand', 'Size', 'Quantity', 'Price Per Item', 'Total Amount'];
        $col = 'A';
        foreach ($summaryHeaders as $h) $sheet->setCellValue($col++ . $row, $h);

        $sheet->getStyle('A' . $row . ':G' . $row)->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A' . $row . ':G' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF27ae60');
        $row++;

        $summarySn = 1;
        $grandItemTotal = 0;
        foreach ($aggregatedItems as $item) {
            $lineTotal = $item['quantity'] * $item['price'];
            $grandItemTotal += $lineTotal;

            $sheet->setCellValue('A' . $row, $summarySn++);
            $sheet->setCellValue('B' . $row, $item['name']);
            $sheet->setCellValue('C' . $row, $item['brand']);
            $sheet->setCellValue('D' . $row, $item['size']);
            $sheet->setCellValue('E' . $row, $item['quantity']);
            $sheet->setCellValue('F' . $row, $item['price']);
            $sheet->setCellValue('G' . $row, $lineTotal);
            $row++;
        }

        $sheet->setCellValue('E' . $row, 'GRAND TOTAL (Items)');
        $sheet->setCellValue('G' . $row, $grandItemTotal);
        $sheet->getStyle('E' . $row . ':G' . $row)->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('F' . ($row - count($aggregatedItems) - 1) . ':G' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('E' . ($row - count($aggregatedItems) - 1) . ':G' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    // Formatting
    foreach (range('A', 'L') as $c) $sheet->getColumnDimension($c)->setAutoSize(true);
    $sheet->getColumnDimension('L')->setWidth(60);

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
// NORMAL DISPLAY LOGIC
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

// Grand totals for display
$grand_advance = $grand_total = $grand_remaining = 0;
foreach ($payments as $p) {
    $grand_advance += $p['advance_amount'];
    $grand_total += $p['total'];
    $grand_remaining += ($p['total'] - $p['advance_amount']);
}

// Aggregate items for display
$aggregatedItems = [];
foreach ($payments as $p) {
    $rawItems = json_decode($p['items_json'], true) ?? [];
    foreach ($rawItems as $it) {
        $fullName = $it['name'] ?? '';
        $name = $fullName;
        $brand = 'Unknown';
        if (strpos($fullName, ' - ') !== false) {
            $parts = explode(' - ', $fullName, 2);
            $name = trim($parts[0]);
            $brand = trim($parts[1]);
        }
        $size = $it['size'] ?? 'N/A';
        $price = $it['price'] ?? 0;
        $qty = $it['quantity'] ?? 0;

        $key = $name . '|' . $brand . '|' . $size . '|' . $price;

        if (!isset($aggregatedItems[$key])) {
            $aggregatedItems[$key] = [
                'name' => $name,
                'brand' => $brand,
                'size' => $size,
                'price' => $price,
                'quantity' => 0
            ];
        }
        $aggregatedItems[$key]['quantity'] += $qty;
    }
}
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
    .container{max-width:1400px;margin:0 auto;}
    h1{color:#2c3e50;text-align:center;margin-bottom:20px;}
    h2{color:#27ae60;margin-top:50px;text-align:center;}
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
    .numeric{text-align:right;font-weight:600;}
    .grand-total{background:#f0e6ff;font-weight:bold;font-size:1.1rem;}
    .items-list{padding-left:20px;margin:5px 0;font-size:0.9rem;}
    .filter-form{background:white;padding:20px;border-radius:10px;box-shadow:0 3px 15px rgba(0,0,0,0.1);text-align:center;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:10px;justify-content:center;}
    .filter-form input{padding:10px 14px;border:1px solid #ddd;border-radius:6px;font-size:0.9rem;min-width:140px;}
    .filter-form button{padding:10px 24px;background:#27ae60;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:bold;}
    .filter-form button:hover{background:#219150;}
    .summary-table th{background:#27ae60;}
    .summary-table .grand-total{background:#d5f4e6;}
</style>
</head>
<body>
<div class="container">
    <h1>Advance Payments (Unpaid) - <?php echo htmlspecialchars($outlet_name); ?></h1>

    <form method="GET" class="filter-form">
        <input type="text" name="fiscal_year" placeholder="Fiscal Year" value="<?=htmlspecialchars($_GET['fiscal_year']??'')?>">
        <input type="text" name="school_name" placeholder="School" value="<?=htmlspecialchars($_GET['school_name']??'')?>">
        <input type="text" name="customer_name" placeholder="Customer" value="<?=htmlspecialchars($_GET['customer_name']??'')?>">
        <input type="text" name="payment_method" placeholder="Payment (cash/online)" value="<?=htmlspecialchars($_GET['payment_method']??'')?>">
        <input type="text" name="printed_by" placeholder="Printed By" value="<?=htmlspecialchars($_GET['printed_by']??'')?>">
        <input type="text" name="bill_number" placeholder="Bill No." value="<?=htmlspecialchars($_GET['bill_number']??'')?>">
        <input type="text" name="start_date" placeholder="Start YYYY-MM-DD" value="<?=htmlspecialchars($_GET['start_date']??'')?>">
        <input type="text" name="end_date" placeholder="End YYYY-MM-DD" value="<?=htmlspecialchars($_GET['end_date']??'')?>">
        <button type="submit">Filter</button>
    </form>

    <div class="actions">
        <a href="advance_payment.php?export=excel<?php echo !empty($_SERVER['QUERY_STRING']) ? '&' . htmlentities($_SERVER['QUERY_STRING']) : ''; ?>" class="btn btn-excel">
            <i class="fas fa-file-excel"></i> Export to Excel (Single Sheet)
        </a>
        <a href="dashboard.php" class="btn btn-back">Back to Dashboard</a>
    </div>

    <?php if(empty($payments)): ?>
        <p style="text-align:center;color:#7f8c8d;font-size:1.2rem;padding:40px;">No unpaid advance payments found.</p>
    <?php else: ?>
        <!-- Detailed Table -->
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Bill Number</th>
                    <th>Fiscal Year</th>
                    <th>School</th>
                    <th>Customer</th>
                    <th class="numeric">Advance</th>
                    <th class="numeric">Total Bill</th>
                    <th class="numeric">Remaining</th>
                    <th>Payment</th>
                    <th>Printed By</th>
                    <th>Date & Time</th>
                    <th>Items</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($payments as $i => $p): 
                    $items = json_decode($p['items_json'], true) ?? [];
                    $remaining = $p['total'] - $p['advance_amount'];
                ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($p['bill_number']) ?></td>
                    <td><?= htmlspecialchars($p['fiscal_year']) ?></td>
                    <td><?= htmlspecialchars($p['school_name']) ?></td>
                    <td><?= htmlspecialchars($p['customer_name'] ?: 'Walk-in') ?></td>
                    <td class="numeric"><?= number_format($p['advance_amount'], 2) ?></td>
                    <td class="numeric"><?= number_format($p['total'], 2) ?></td>
                    <td class="numeric"><?= number_format($remaining, 2) ?></td>
                    <td><?= ucfirst($p['payment_method']) ?></td>
                    <td><?= htmlspecialchars($p['printed_by'] ?? '') ?></td>
                    <td><?= htmlspecialchars($p['bs_datetime']) ?></td>
                    <td>
                        <ul class="items-list">
                            <?php foreach($items as $it): ?>
                                <li><?= htmlspecialchars($it['name']) ?> (<?= htmlspecialchars($it['size'] ?? '') ?>) × <?= $it['quantity'] ?> -> <?= number_format($it['price'], 2) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr class="grand-total">
                    <td colspan="5" style="text-align:right;"><strong>GRAND TOTAL</strong></td>
                    <td class="numeric"><strong><?= number_format($grand_advance, 2) ?></strong></td>
                    <td class="numeric"><strong><?= number_format($grand_total, 2) ?></strong></td>
                    <td class="numeric"><strong><?= number_format($grand_remaining, 2) ?></strong></td>
                    <td colspan="4"></td>
                </tr>
            </tbody>
        </table>

        <!-- Aggregated Items Summary -->
        <?php if (!empty($aggregatedItems)): ?>
            <h2>Aggregated Items Summary</h2>
            <table class="summary-table">
                <thead>
                    <tr>
                        <th>S.N</th>
                        <th>Name</th>
                        <th>Brand</th>
                        <th>Size</th>
                        <th class="numeric">Quantity</th>
                        <th class="numeric">Price Per Item</th>
                        <th class="numeric">Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $summarySn = 1;
                    $grandItemTotal = 0;
                    foreach ($aggregatedItems as $item): 
                        $lineTotal = $item['quantity'] * $item['price'];
                        $grandItemTotal += $lineTotal;
                    ?>
                    <tr>
                        <td><?= $summarySn++ ?></td>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td><?= htmlspecialchars($item['brand']) ?></td>
                        <td><?= htmlspecialchars($item['size']) ?></td>
                        <td class="numeric"><?= $item['quantity'] ?></td>
                        <td class="numeric"><?= number_format($item['price'], 2) ?></td>
                        <td class="numeric"><?= number_format($lineTotal, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="grand-total">
                        <td colspan="6" style="text-align:right;"><strong>GRAND TOTAL (Items)</strong></td>
                        <td class="numeric"><strong><?= number_format($grandItemTotal, 2) ?></strong></td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>