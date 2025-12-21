<?php
session_start();
require '../Common/connection.php';
require '../Common/nepali_date.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Security
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['selected_outlet_id'])) {
    header("Location: index.php");
    exit();
}

$outlet_id     = $_SESSION['selected_outlet_id'];
$outlet_name   = $_SESSION['selected_outlet_name'] ?? 'Unknown Outlet';

// Get filters from POST (only if form was submitted)
$fiscal_year   = trim($_POST['fiscal_year'] ?? '');
$customer_name = trim($_POST['customer_name'] ?? '');
$entered_by    = trim($_POST['entered_by'] ?? '');
$start_date    = trim($_POST['start_date'] ?? '');
$end_date      = trim($_POST['end_date'] ?? '');

// If "Clear" button clicked, reset all filters
if (isset($_POST['clear'])) {
    $fiscal_year = $customer_name = $entered_by = $start_date = $end_date = '';
}

// Excel Export
if (isset($_POST['export']) && $_POST['export'] === 'excel') {
    $where = "WHERE cm.outlet_id = ?";
    $params = [$outlet_id];

    if ($fiscal_year !== '') { 
        $where .= " AND cm.fiscal_year = ?"; 
        $params[] = $fiscal_year; 
    }
    if ($customer_name !== '') { 
        $where .= " AND cm.customer_name LIKE ?"; 
        $params[] = "%$customer_name%"; 
    }
    if ($entered_by !== '') { 
        $where .= " AND l.username LIKE ?"; 
        $params[] = "%$entered_by%"; 
    }
    if ($start_date !== '' && $end_date !== '') {
        $where .= " AND DATE(cmi.bs_datetime) BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
    }

    $sql = "
        SELECT cm.*, cmi.item_name, cmi.price, cmi.quantity, cmi.bs_datetime as item_date,
               JSON_EXTRACT(cm.measurements, CONCAT('$.\"', cmi.item_index, '\"')) as measurement_json,
               l.username as entered_by_name
        FROM customer_measurements cm
        JOIN custom_measurement_items cmi ON cm.bill_number = cmi.bill_number AND cm.fiscal_year = cmi.fiscal_year
        LEFT JOIN login l ON cm.created_by = l.id
        $where
        ORDER BY cmi.bs_datetime DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // [Excel export code - same as before]
    $aggregated = [];
    foreach ($rows as $r) {
        $name = $r['item_name'];
        $price = $r['price'];
        $qty = $r['quantity'];
        $key = "$name|$price";
        if (!isset($aggregated[$key])) {
            $aggregated[$key] = ['name' => $name, 'price' => $price, 'quantity' => 0];
        }
        $aggregated[$key]['quantity'] += $qty;
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Measurement Report');

    $headers = ['S.N', 'Bill No.', 'Fiscal Year', 'Customer', 'Entered By', 'Item', 'Qty', 'Price', 'Total', 'Date (BS)', 'Measurements'];
    $col = 'A';
    foreach ($headers as $h) $sheet->setCellValue($col++ . '1', $h);
    $sheet->getStyle('A1:K1')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A1:K1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF8E44AD');

    $row = 2; $sn = 1; $grand_total = 0;
    foreach ($rows as $r) {
        $measurements = json_decode($r['measurement_json'], true) ?? [];
        $measText = '';
        foreach ($measurements as $k => $v) {
            $k = ucwords(str_replace('_', ' ', $k));
            $measText .= "$k: $v | ";
        }
        $measText = rtrim($measText, ' | ') ?: 'None';

        $total = $r['price'] * $r['quantity'];
        $grand_total += $total;
        $bs_date = explode(' ', $r['item_date'])[0];

        $sheet->setCellValue('A' . $row, $sn++);
        $sheet->setCellValue('B' . $row, $r['bill_number']);
        $sheet->setCellValue('C' . $row, $r['fiscal_year']);
        $sheet->setCellValue('D' . $row, $r['customer_name']);
        $sheet->setCellValue('E' . $row, $r['entered_by_name'] ?? 'ID: ' . $r['created_by']);
        $sheet->setCellValue('F' . $row, $r['item_name']);
        $sheet->setCellValue('G' . $row, $r['quantity']);
        $sheet->setCellValue('H' . $row, number_format($r['price'], 2));
        $sheet->setCellValue('I' . $row, number_format($total, 2));
        $sheet->setCellValue('J' . $row, $bs_date);
        $sheet->setCellValue('K' . $row, $measText);
        $row++;
    }

    if (!empty($aggregated)) {
        $sheet->setCellValue('A' . $row, 'AGGREGATED ITEMS SUMMARY');
        $sheet->mergeCells("A{$row}:K{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row += 2;

        $sheet->setCellValue('A' . $row, 'Item Name');
        $sheet->setCellValue('B' . $row, 'Price');
        $sheet->setCellValue('C' . $row, 'Total Qty');
        $sheet->setCellValue('D' . $row, 'Total Amount');
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
        $row++;

        foreach ($aggregated as $item) {
            $total = $item['quantity'] * $item['price'];
            $sheet->setCellValue('A' . $row, $item['name']);
            $sheet->setCellValue('B' . $row, number_format($item['price'], 2));
            $sheet->setCellValue('C' . $row, $item['quantity']);
            $sheet->setCellValue('D' . $row, number_format($total, 2));
            $row++;
        }
    }

    foreach (range('A', 'K') as $c) $sheet->getColumnDimension($c)->setAutoSize(true);
    $sheet->getColumnDimension('K')->setWidth(60);

    ob_end_clean();
    $filename = "Measurement_Report_" . date('Y-m-d') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// Main Query - uses current POST filters
$where = "WHERE cm.outlet_id = ?";
$params = [$outlet_id];

if ($fiscal_year !== '') { 
    $where .= " AND cm.fiscal_year = ?"; 
    $params[] = $fiscal_year; 
}
if ($customer_name !== '') { 
    $where .= " AND cm.customer_name LIKE ?"; 
    $params[] = "%$customer_name%"; 
}
if ($entered_by !== '') { 
    $where .= " AND l.username LIKE ?"; 
    $params[] = "%$entered_by%"; 
}
if ($start_date !== '' && $end_date !== '') {
    $where .= " AND DATE(cmi.bs_datetime) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}

$sql = "
    SELECT cm.*, cmi.item_name, cmi.price, cmi.quantity, cmi.bs_datetime as item_date,
           JSON_EXTRACT(cm.measurements, CONCAT('$.\"', cmi.item_index, '\"')) as measurement_json,
           l.username as entered_by_name
    FROM customer_measurements cm
    JOIN custom_measurement_items cmi ON cm.bill_number = cmi.bill_number AND cm.fiscal_year = cmi.fiscal_year
    LEFT JOIN login l ON cm.created_by = l.id
    $where
    ORDER BY cmi.bs_datetime DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$measurements = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Measurement Report - <?= htmlspecialchars($outlet_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; padding: 20px; }
        .container { max-width: 1400px; }
        .filter-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #8e44ad; color: white; }
        .numeric { text-align: right; }
        .measurement-item { background: #e9ecef; padding: 4px 8px; border-radius: 6px; margin: 2px; display: inline-block; font-size: 0.9em; }
        .grand-total { background: #f0e6ff; font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <h1 class="text-center text-primary mb-4">Measurement Report - <?= htmlspecialchars($outlet_name) ?></h1>

    <div class="filter-card">
        <form method="POST" class="row g-3">
            <div class="col-md-3">
                <label>Fiscal Year (Exact)</label>
                <input type="text" name="fiscal_year" class="form-control" placeholder="e.g. 2081/82 or 2082/83" value="<?= htmlspecialchars($fiscal_year) ?>">
                <small class="text-muted">Enter full year like 2081/82</small>
            </div>
            <div class="col-md-3">
                <label>Customer Name</label>
                <input type="text" name="customer_name" class="form-control" value="<?= htmlspecialchars($customer_name) ?>">
            </div>
            <div class="col-md-3">
                <label>Entered By (User)</label>
                <input type="text" name="entered_by" class="form-control" placeholder="Enter username" value="<?= htmlspecialchars($entered_by) ?>">
            </div>
            <div class="col-md-3">
                <label>Start Date (B.S.)</label>
                <input type="text" name="start_date" class="form-control" placeholder="2082-08-01" value="<?= htmlspecialchars($start_date) ?>">
            </div>
            <div class="col-md-3">
                <label>End Date (B.S.)</label>
                <input type="text" name="end_date" class="form-control" placeholder="2082-09-02" value="<?= htmlspecialchars($end_date) ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Apply Filter</button>
                <button type="submit" name="clear" value="1" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</button>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" name="export" value="excel" class="btn btn-success w-100">
                    <i class="fas fa-file-excel"></i> Export Excel
                </button>
            </div>
        </form>
    </div>

    <?php if (empty($measurements)): ?>
        <p class="text-center text-muted fs-4">No measurement records found.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Bill No.</th>
                    <th>Fiscal Year</th>
                    <th>Customer</th>
                    <th>Entered By</th>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Total</th>
                    <th>Date (BS)</th>
                    <th>Measurements</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sn = 1; $grand_total = 0;
                foreach ($measurements as $m): 
                    $total = $m['price'] * $m['quantity'];
                    $grand_total += $total;
                    $bs_date = explode(' ', $m['item_date'])[0];
                    $measurements_data = json_decode($m['measurement_json'], true) ?? [];
                ?>
                <tr>
                    <td><?= $sn++ ?></td>
                    <td><?= htmlspecialchars($m['bill_number']) ?></td>
                    <td><?= htmlspecialchars($m['fiscal_year']) ?></td>
                    <td><?= htmlspecialchars($m['customer_name']) ?></td>
                    <td><?= htmlspecialchars($m['entered_by_name'] ?? 'ID: ' . $m['created_by']) ?></td>
                    <td><?= htmlspecialchars($m['item_name']) ?></td>
                    <td class="numeric"><?= $m['quantity'] ?></td>
                    <td class="numeric"><?= number_format($m['price'], 2) ?></td>
                    <td class="numeric"><?= number_format($total, 2) ?></td>
                    <td><?= $bs_date ?></td>
                    <td>
                        <?php if (!empty($measurements_data)): ?>
                            <?php foreach ($measurements_data as $k => $v): 
                                $k = ucwords(str_replace('_', ' ', $k));
                            ?>
                                <span class="measurement-item"><?= "$k: $v" ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <em class="text-muted">None</em>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr class="grand-total">
                    <td colspan="8" style="text-align:right;"><strong>GRAND TOTAL</strong></td>
                    <td class="numeric"><strong><?= number_format($grand_total, 2) ?></strong></td>
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="text-center my-5">
        <a href="dashboard.php" class="btn btn-primary btn-lg px-5">Back to Dashboard</a>
    </div>
</div>
</body>
</html>