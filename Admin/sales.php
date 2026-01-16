<?php
session_start();
require '../Common/connection.php';
require '../Common/nepali_date.php';
require '../vendor/autoload.php'; // PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// ============= SECURITY =============
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
    $where  = ["outlet_id = ?"];

    // Apply filters (same as display)
    if (!empty($_GET['fiscal_year'])) { $where[] = "fiscal_year = ?"; $params[] = $_GET['fiscal_year']; }
    if (!empty($_GET['school_name'])) { $where[] = "school_name LIKE ?"; $params[] = "%{$_GET['school_name']}%"; }
    if (!empty($_GET['customer_name'])) { $where[] = "customer_name LIKE ?"; $params[] = "%{$_GET['customer_name']}%"; }
    if (!empty($_GET['payment_method'])) { $where[] = "payment_method = ?"; $params[] = $_GET['payment_method']; }
    if (!empty($_GET['advance_payment_method'])) { $where[] = "advance_payment_method = ?"; $params[] = $_GET['advance_payment_method']; }
    if (!empty($_GET['printed_by'])) { $where[] = "printed_by LIKE ?"; $params[] = "%{$_GET['printed_by']}%"; }
    if (!empty($_GET['bill_number'])) { $where[] = "bill_number LIKE ?"; $params[] = "%{$_GET['bill_number']}%"; }

    // Date range filter only if provided
    if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
        $where[] = "bs_datetime BETWEEN ? AND ?";
        $params[] = $_GET['start_date'] . ' 00:00:00';
        $params[] = $_GET['end_date'] . ' 23:59:59';
    }
    // Advance date filter
    if (!empty($_GET['adv_start_date']) && !empty($_GET['adv_end_date'])) {
        $where[] = "advance_date BETWEEN ? AND ?";
        $params[] = $_GET['adv_start_date'];
        $params[] = $_GET['adv_end_date'];
    }

    $sql = "SELECT * FROM sales WHERE " . implode(' AND ', $where) . " ORDER BY bs_datetime DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Grand totals
    $grand_total = $grand_advance = $grand_final = 0;
    foreach ($sales as $s) {
        $grand_total   += $s['total'];
        $grand_advance += $s['advance_amount'] > 0 ? $s['advance_amount'] : 0;
        $grand_final   += $s['final_amount'];
    }

    // Aggregate items
    $aggregatedItems = [];
    foreach ($sales as $s) {
        $rawItems = json_decode($s['items_json'], true) ?? [];
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
    $sheet->setTitle('Sales Report');

    // Detailed Section
    $headers = ['S.N', 'Bill No.', 'Fiscal Year', 'School', 'Customer', 'Total', 'Advance', 'Adv. Date', 'Final Paid', 'Final Pay', 'Adv. Pay', 'Printed By', 'Date & Time', 'Items'];
    $col = 'A';
    foreach ($headers as $h) $sheet->setCellValue($col++ . '1', $h);

    $sheet->getStyle('A1:N1')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A1:N1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF8E44AD');

    $row = 2;
    $sn = 1;
    foreach ($sales as $s) {
        $items = json_decode($s['items_json'], true) ?? [];
        $itemsText = '';
        foreach ($items as $it) {
            $itemsText .= $it['name'] . ' (' . ($it['size'] ?? 'N/A') . ') ×' . $it['quantity'] . ' @ ' . number_format($it['price'],2) . "\n";
        }

        $sheet->setCellValue('A' . $row, $sn++);
        $sheet->setCellValue('B' . $row, $s['bill_number']);
        $sheet->setCellValue('C' . $row, $s['fiscal_year']);
        $sheet->setCellValue('D' . $row, $s['school_name']);
        $sheet->setCellValue('E' . $row, $s['customer_name'] ?: 'Walk-in');
        $sheet->setCellValue('F' . $row, $s['total']);
        $sheet->setCellValue('G' . $row, $s['advance_amount'] > 0 ? $s['advance_amount'] : 0);
        $sheet->setCellValue('H' . $row, $s['advance_date'] ?? '');
        $sheet->setCellValue('I' . $row, $s['final_amount']);
        $sheet->setCellValue('J' . $row, ucfirst($s['payment_method'] ?? ''));
        $sheet->setCellValue('K' . $row, $s['advance_payment_method'] ? ucfirst($s['advance_payment_method']) : '');
        $sheet->setCellValue('L' . $row, $s['printed_by'] ?? '');
        $sheet->setCellValue('M' . $row, $s['bs_datetime']);
        $sheet->setCellValue('N' . $row, $itemsText);
        $sheet->getStyle('N' . $row)->getAlignment()->setWrapText(true);
        $row++;
    }

    // Grand Total Row
    $sheet->setCellValue('E' . $row, 'GRAND TOTAL');
    $sheet->setCellValue('F' . $row, $grand_total);
    $sheet->setCellValue('G' . $row, $grand_advance);
    $sheet->setCellValue('I' . $row, $grand_final);
    $sheet->getStyle('E' . $row . ':I' . $row)->getFont()->setBold(true)->setSize(13);
    $sheet->getStyle('F2:I' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
    $row += 2;

    // Aggregated Summary
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
        $sheet->getStyle('E' . ($row - count($aggregatedItems) - 1) . ':G' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
    }

    foreach (range('A', 'N') as $c) $sheet->getColumnDimension($c)->setAutoSize(true);
    $sheet->getColumnDimension('N')->setWidth(60);

    ob_end_clean();
    $filename = "Sales_Report_" . date('Y-m-d') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
// =============================================
// NORMAL DISPLAY LOGIC (with default last 30 days)
// =============================================
$params = [$outlet_id];
$where  = ["outlet_id = ?"];

if (!empty($_GET['fiscal_year'])) { $where[] = "fiscal_year = ?"; $params[] = $_GET['fiscal_year']; }
if (!empty($_GET['school_name'])) { $where[] = "school_name LIKE ?"; $params[] = "%{$_GET['school_name']}%"; }
if (!empty($_GET['customer_name'])) { $where[] = "customer_name LIKE ?"; $params[] = "%{$_GET['customer_name']}%"; }
if (!empty($_GET['payment_method'])) { $where[] = "payment_method = ?"; $params[] = $_GET['payment_method']; }
if (!empty($_GET['advance_payment_method'])) { $where[] = "advance_payment_method = ?"; $params[] = $_GET['advance_payment_method']; }
if (!empty($_GET['printed_by'])) { $where[] = "printed_by LIKE ?"; $params[] = "%{$_GET['printed_by']}%"; }
if (!empty($_GET['bill_number'])) { $where[] = "bill_number LIKE ?"; $params[] = "%{$_GET['bill_number']}%"; }

// ─────────────────────────────────────────────
// Date filtering logic ─ default: last ~30 days
// ─────────────────────────────────────────────
$default_range_applied = false;

if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    // User gave explicit bs_datetime range
    $where[] = "bs_datetime BETWEEN ? AND ?";
    $params[] = $_GET['start_date'] . ' 00:00:00';
    $params[] = $_GET['end_date'] . ' 23:59:59';
}
elseif (!empty($_GET['adv_start_date']) && !empty($_GET['adv_end_date'])) {
    // User gave explicit advance_date range
    $where[] = "advance_date BETWEEN ? AND ?";
    $params[] = $_GET['adv_start_date'];
    $params[] = $_GET['adv_end_date'];
}
else {
    // DEFAULT: last 30 days in BS date
    $default_range_applied = true;

    $today_str = nepali_date_time();                    // e.g. "2082-04-01 14:30"
    $today_date = explode(' ', $today_str)[0];          // "2082-04-01"
    list($yy, $mm, $dd) = explode('-', $today_date);

    $yy = (int)$yy;
    $mm = (int)$mm;
    $dd = (int)$dd;

    $dates = [];

    // Generate approx last 30 days (simple backward subtraction)
    for ($i = 0; $i < 30; $i++) {
        $day = $dd - $i;
        $month = $mm;
        $year = $yy;

        while ($day < 1) {
            $month--;
            if ($month < 1) {
                $month = 12;
                $year--;
            }
            $day += 30; // rough approximation — enough for filtering
        }

        $dates[] = sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    if (!empty($dates)) {
        $placeholders = implode(',', array_fill(0, count($dates), '?'));
        $where[] = "DATE(bs_datetime) IN ($placeholders)";
        $params = array_merge($params, $dates);
    }
}

// Build and execute query
$sql = "SELECT * FROM sales WHERE " . implode(' AND ', $where) . " ORDER BY bs_datetime DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Grand totals for display
$grand_total = $grand_advance = $grand_final = 0;
foreach ($sales as $s) {
    $grand_total   += $s['total'];
    $grand_advance += $s['advance_amount'] > 0 ? $s['advance_amount'] : 0;
    $grand_final   += $s['final_amount'];
}

// Aggregate items
$aggregatedItems = [];
foreach ($sales as $s) {
    $rawItems = json_decode($s['items_json'], true) ?? [];
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
<title>Sales Report - <?php echo htmlspecialchars($outlet_name); ?></title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
    body{font-family:'Segoe UI',sans-serif;padding:20px;background:#f4f6f9;}
    .container{max-width:1600px;margin:0 auto;}
    h1{color:#2c3e50;text-align:center;margin-bottom:20px;}
    h2{color:#27ae60;margin-top:50px;text-align:center;}
    .actions{text-align:center;margin:25px 0;}
    .btn{padding:12px 32px;margin:0 10px;border-radius:50px;color:white;text-decoration:none;font-weight:bold;}
    .btn-excel{background:#27ae60;}
    .btn-excel:hover{background:#219a52;}
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
    .filter-form input, .filter-form select{padding:10px 14px;border:1px solid #ddd;border-radius:6px;font-size:0.9rem;min-width:140px;}
    .filter-form button{padding:10px 24px;background:#27ae60;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:bold;}
    .filter-form button:hover{background:#219150;}
    .summary-table th{background:#27ae60;}
    .summary-table .grand-total{background:#d5f4e6;}
</style>
</head>
<body>
<div class="container">
    <h1>Sales Report - <?php echo htmlspecialchars($outlet_name); ?></h1>

    <form method="GET" class="filter-form">
        <input type="text" name="fiscal_year" placeholder="Fiscal Year" value="<?=htmlspecialchars($_GET['fiscal_year']??'')?>">
        <input type="text" name="school_name" placeholder="School" value="<?=htmlspecialchars($_GET['school_name']??'')?>">
        <input type="text" name="customer_name" placeholder="Customer" value="<?=htmlspecialchars($_GET['customer_name']??'')?>">
        <select name="payment_method">
            <option value="">All Final Payment</option>
            <option value="cash" <?=($_GET['payment_method']??'')==='cash'?'selected':''?>>Cash</option>
            <option value="online" <?=($_GET['payment_method']??'')==='online'?'selected':''?>>Online</option>
            <option value="card" <?=($_GET['payment_method']??'')==='card'?'selected':''?>>Card</option>
            <option value="cheque" <?=($_GET['payment_method']??'')==='cheque'?'selected':''?>>Cheque</option>
            <option value="bank_transfer" <?=($_GET['payment_method']??'')==='bank_transfer'?'selected':''?>>Bank Transfer</option>
        </select>
        <select name="advance_payment_method">
            <option value="">All Advance Payment</option>
            <option value="cash" <?=($_GET['advance_payment_method']??'')==='cash'?'selected':''?>>Cash (Advance)</option>
            <option value="online" <?=($_GET['advance_payment_method']??'')==='online'?'selected':''?>>Online (Advance)</option>
        </select>
        <input type="text" name="printed_by" placeholder="Printed By" value="<?=htmlspecialchars($_GET['printed_by']??'')?>">
        <input type="text" name="bill_number" placeholder="Bill No." value="<?=htmlspecialchars($_GET['bill_number']??'')?>">
        <input type="text" name="start_date" placeholder="Start YYYY-MM-DD" value="<?=htmlspecialchars($_GET['start_date']??'')?>">
        <input type="text" name="end_date" placeholder="End YYYY-MM-DD" value="<?=htmlspecialchars($_GET['end_date']??'')?>">
        <input type="text" name="adv_start_date" placeholder="Adv Start YYYY-MM-DD" value="<?=htmlspecialchars($_GET['adv_start_date']??'')?>">
        <input type="text" name="adv_end_date" placeholder="Adv End YYYY-MM-DD" value="<?=htmlspecialchars($_GET['adv_end_date']??'')?>">
        <button type="submit">Filter</button>
    </form>

    <div class="actions">
        <a href="sales.php?export=excel<?php echo !empty($_SERVER['QUERY_STRING']) ? '&' . htmlentities($_SERVER['QUERY_STRING']) : ''; ?>" class="btn btn-excel">
            <i class="fas fa-file-excel"></i> Export to Excel
        </a>
        <a href="dashboard.php" class="btn btn-back">Back to Dashboard</a>
    </div>

    <?php if(empty($sales)): ?>
        <p style="text-align:center;color:#7f8c8d;font-size:1.2rem;padding:40px;">No sales found.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Bill No.</th>
                    <th>Fiscal Year</th>
                    <th>School</th>
                    <th>Customer</th>
                    <th class="numeric">Total</th>
                    <th class="numeric">Advance</th>
                    <th>Adv. Date</th>
                    <th class="numeric">Final Paid</th>
                    <th>Final Pay</th>
                    <th>Adv. Pay</th>
                    <th>Printed By</th>
                    <th>Date & Time</th>
                    <th>Items</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($sales as $i => $s): 
                    $items = json_decode($s['items_json'], true) ?? [];
                ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($s['bill_number']) ?></td>
                    <td><?= htmlspecialchars($s['fiscal_year']) ?></td>
                    <td><?= htmlspecialchars($s['school_name']) ?></td>
                    <td><?= htmlspecialchars($s['customer_name'] ?: 'Walk-in') ?></td>
                    <td class="numeric"><?= number_format($s['total'],2) ?></td>
                    <td class="numeric"><?= $s['advance_amount'] > 0 ? number_format($s['advance_amount'],2) : '0.00' ?></td>
                    <td><?= $s['advance_date'] ?? '-' ?></td>
                    <td class="numeric"><?= number_format($s['final_amount'],2) ?></td>
                    <td><?= ucfirst($s['payment_method'] ?? '') ?></td>
                    <td><?= $s['advance_payment_method'] ? ucfirst($s['advance_payment_method']) : '-' ?></td>
                    <td><?= htmlspecialchars($s['printed_by'] ?? '') ?></td>
                    <td><?= htmlspecialchars($s['bs_datetime']) ?></td>
                    <td>
                        <ul class="items-list">
                            <?php foreach($items as $it): ?>
                                <li><?= htmlspecialchars($it['name']) ?> (<?= htmlspecialchars($it['size'] ?? 'N/A') ?>) × <?= $it['quantity'] ?> @ <?= number_format($it['price'],2) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr class="grand-total">
                    <td colspan="5" style="text-align:right;"><strong>GRAND TOTAL</strong></td>
                    <td class="numeric"><strong><?= number_format($grand_total, 2) ?></strong></td>
                    <td class="numeric"><strong><?= number_format($grand_advance, 2) ?></strong></td>
                    <td colspan="2"></td>
                    <td class="numeric"><strong><?= number_format($grand_final, 2) ?></strong></td>
                    <td colspan="4"></td>
                </tr>
            </tbody>
        </table>

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