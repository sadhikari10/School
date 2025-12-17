<?php
session_start();
require '../Common/connection.php';
require '../Common/nepali_date.php'; // Has ad_to_bs(), bs_to_ad(), nepali_date_time()
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// ==================== SECURITY CHECK ====================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}

$user_role = $_SESSION['role'];
$outlet_id = $_SESSION['outlet_id'] ?? null;

// ==================== GET TODAY'S BS DATE ====================
$today_bs_full = nepali_date_time(); // e.g. '2082-09-02 15:30:00'
$today_bs_date = explode(' ', $today_bs_full)[0]; // '2082-09-02'

// ==================== DETERMINE FILTER MODE ====================
$filter_mode = $_GET['filter_mode'] ?? 'today'; // 'today' or 'all'

// For export, use the same mode
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $use_today_filter = ($filter_mode === 'today');
} else {
    $use_today_filter = ($filter_mode === 'today');
}

// ==================== EXCEL EXPORT ====================
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $where_clause = "WHERE cmi.done = 0";
    $params = [];

    if ($user_role === 'staff' && $outlet_id !== null) {
        $where_clause .= " AND cmi.outlet_id = ?";
        $params[] = $outlet_id;
    }

    if ($use_today_filter) {
        $today_ad = date('Y-m-d'); // Current AD date
        $where_clause .= " AND DATE(cm.created_at) = ?";
        $params[] = $today_ad;
    }

    $sql = "
        SELECT 
            cm.bill_number,
            cm.fiscal_year,
            cm.customer_name,
            cm.phone,
            cm.created_at AS bill_date_ad,
            cmi.item_name,
            cmi.price AS unit_price,
            cmi.quantity,
            (cmi.price * cmi.quantity) AS total_amount,
            JSON_EXTRACT(cm.measurements, CONCAT('$.\"', cmi.item_index, '\"')) AS measurement_json,
            o.location AS branch_name
        FROM customer_measurements cm
        JOIN custom_measurement_items cmi ON cm.bill_number = cmi.bill_number AND cm.fiscal_year = cmi.fiscal_year
        LEFT JOIN outlets o ON cmi.outlet_id = o.outlet_id
        $where_clause
        ORDER BY cm.created_at DESC, cm.bill_number DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Pending Measurements');

    $headers = ['S.N', 'Bill No.', 'Fiscal Year', 'Customer', 'Phone', 'Item Name', 'Qty', 'Unit Price', 'Total', 'Branch', 'Date (BS)', 'Measurements'];
    $col = 'A';
    foreach ($headers as $h) $sheet->setCellValue($col++ . '1', $h);

    $sheet->getStyle('A1:L1')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A1:L1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0d6efd');

    $row = 2;
    $sn = 1;
    $grand_total = 0;

    foreach ($rows as $r) {
        $bs_date = ad_to_bs(date('Y-m-d', strtotime($r['bill_date_ad'])));
        $measurements = json_decode($r['measurement_json'], true) ?? [];
        $measText = '';
        if (!empty($measurements)) {
            foreach ($measurements as $k => $v) {
                $k = ucwords(str_replace('_', ' ', $k));
                $measText .= "$k: $v | ";
            }
            $measText = rtrim($measText, ' | ');
        } else {
            $measText = 'No measurements';
        }

        $sheet->setCellValue('A' . $row, $sn++);
        $sheet->setCellValue('B' . $row, $r['bill_number']);
        $sheet->setCellValue('C' . $row, $r['fiscal_year']);
        $sheet->setCellValue('D' . $row, $r['customer_name']);
        $sheet->setCellValue('E' . $row, $r['phone'] ?? '');
        $sheet->setCellValue('F' . $row, $r['item_name']);
        $sheet->setCellValue('G' . $row, $r['quantity']);
        $sheet->setCellValue('H' . $row, $r['unit_price']);
        $sheet->setCellValue('I' . $row, $r['total_amount']);
        $sheet->setCellValue('J' . $row, $r['branch_name'] ?? 'Unknown');
        $sheet->setCellValue('K' . $row, $bs_date);
        $sheet->setCellValue('L' . $row, $measText);

        $grand_total += $r['total_amount'];
        $row++;
    }

    $sheet->setCellValue('H' . $row, 'GRAND TOTAL');
    $sheet->setCellValue('I' . $row, $grand_total);
    $sheet->getStyle('H' . $row . ':I' . $row)->getFont()->setBold(true)->setSize(13);
    $sheet->getStyle('H2:I' . $row)->getNumberFormat()->setFormatCode('#,##0.00');

    foreach (range('A', 'L') as $c) $sheet->getColumnDimension($c)->setAutoSize(true);
    $sheet->getColumnDimension('L')->setWidth(80);

    $title = $use_today_filter ? "Today_" . $today_bs_date : "All_Pending";
    $filename = "Pending_Measurements_" . $title . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// ==================== MARK AS DONE ====================
if (isset($_GET['done']) && $_GET['done'] != '') {
    $item_id = (int)$_GET['done'];
    try {
        if ($user_role === 'staff' && $outlet_id !== null) {
            $stmt = $pdo->prepare("UPDATE custom_measurement_items SET done = 1 WHERE id = ? AND outlet_id = ?");
            $stmt->execute([$item_id, $outlet_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE custom_measurement_items SET done = 1 WHERE id = ?");
            $stmt->execute([$item_id]);
        }
        $mode_param = $filter_mode === 'all' ? '?filter_mode=all' : '';
        echo "<script>alert('Item marked as completed!'); window.location='measurement.php$mode_param';</script>";
    } catch (Exception $e) {
        echo "<script>alert('Error updating item.');</script>";
    }
    exit;
}

// ==================== QUERY WITH FILTER ====================
$where_clause = "WHERE cmi.done = 0";
$params = [];

if ($user_role === 'staff' && $outlet_id !== null) {
    $where_clause .= " AND cmi.outlet_id = ?";
    $params[] = $outlet_id;
}

if ($filter_mode === 'today') {
    $today_ad = date('Y-m-d'); // Current AD date (server time)
    $where_clause .= " AND DATE(cm.created_at) = ?";
    $params[] = $today_ad;
}

$sql = "
    SELECT 
        cm.bill_number,
        cm.fiscal_year,
        cm.customer_name,
        cm.phone,
        cm.created_at AS bill_date_ad,
        cmi.id AS item_id,
        cmi.item_index,
        cmi.item_name,
        cmi.price AS unit_price,
        cmi.quantity,
        (cmi.price * cmi.quantity) AS total_amount,
        JSON_EXTRACT(cm.measurements, CONCAT('$.\"', cmi.item_index, '\"')) AS measurement_json,
        cmi.outlet_id
    FROM customer_measurements cm
    JOIN custom_measurement_items cmi ON cm.bill_number = cmi.bill_number AND cm.fiscal_year = cmi.fiscal_year
    $where_clause
    ORDER BY cm.created_at DESC, cm.bill_number DESC, cmi.item_index
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Uniform Orders</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; padding: 20px; }
        .card-header { background-color: #0d6efd; color: white; font-weight: bold; }
        .measurement-item { 
            background-color: #e9ecef; padding: 8px 14px; border-radius: 8px; 
            margin: 4px 6px 4px 0; display: inline-block; font-size: 0.95em;
        }
        .done-btn { font-size: 1.2em; min-width: 120px; }
        .branch-badge { font-size: 0.9rem; }
        .filter-form { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin-bottom: 30px; }
    </style>
</head>
<body>

<div class="container mt-5 pt-4">
    <h1 class="text-primary mb-4 text-center">
        Pending Custom Uniform Orders
        <?php if ($user_role === 'staff'): ?>
            <small class="d-block text-muted fs-5">Your Branch Only</small>
        <?php endif; ?>
    </h1>

    <!-- Filter Options + Export -->
    <div class="filter-form text-center">
        <form method="GET" class="d-inline">
            <button type="submit" name="filter_mode" value="today" class="btn <?= $filter_mode === 'today' ? 'btn-primary' : 'btn-outline-primary' ?> btn-lg mx-2">
                Today (<?= $today_bs_date ?>)
            </button>
            <button type="submit" name="filter_mode" value="all" class="btn <?= $filter_mode === 'all' ? 'btn-primary' : 'btn-outline-primary' ?> btn-lg mx-2">
                All Time
            </button>
        </form>

        <div class="mt-4">
            <a href="measurement.php?export=excel&filter_mode=<?= $filter_mode ?>" class="btn btn-success btn-lg px-5">
                <i class="fas fa-file-excel"></i> Export to Excel
            </a>
        </div>
    </div>

    <?php if (empty($rows)): ?>
        <div class="alert alert-success text-center fs-2 mt-5">
            <i class="fas fa-check-circle"></i> All orders completed!
        </div>
    <?php else: 
        $current_bill = null;
        foreach ($rows as $row): 
            $bs_date_str = ad_to_bs(date('Y-m-d', strtotime($row['bill_date_ad'])));
            $measurements = json_decode($row['measurement_json'], true) ?? [];

            $branch_name = '';
            if ($user_role === 'admin') {
                $loc_stmt = $pdo->prepare("SELECT location FROM outlets WHERE outlet_id = ?");
                $loc_stmt->execute([$row['outlet_id']]);
                $branch_name = $loc_stmt->fetchColumn() ?: 'Unknown Branch';
            }

            if ($current_bill !== $row['bill_number']):
                if ($current_bill !== null) echo "</div></div></div><hr class='my-5'>";
                $current_bill = $row['bill_number'];

                echo "<div class='card mb-4 shadow-lg'>";
                echo "<div class='card-header bg-primary text-white d-flex justify-content-between align-items-center flex-wrap gap-2'>";
                echo "<div>";
                echo "<h5 class='mb-0'>Bill No: <strong>{$row['bill_number']}</strong> • {$row['fiscal_year']}</h5>";
                if ($user_role === 'admin') {
                    echo "<span class='branch-badge badge bg-light text-dark'>Branch: " . htmlspecialchars($branch_name) . "</span>";
                }
                echo "</div>";
                echo "<small>BS Date: <strong>{$bs_date_str}</strong></small>";
                echo "</div>";
                echo "<div class='card-body'>";
                echo "<h4 class='text-success'>Customer: <strong>" . htmlspecialchars($row['customer_name']) . "</strong>";
                if ($row['phone']) echo " • " . htmlspecialchars($row['phone']);
                echo "</h4><hr>";
            endif;

            echo "<div class='row align-items-center mb-3 p-3 bg-light rounded border-start border-success border-4'>";
            echo "<div class='col-lg-8'>";
            echo "<h5 class='text-success fw-bold mb-2'>" . htmlspecialchars($row['item_name']) . "</h5>";
            echo "<p class='mb-2'><strong>Price:</strong> NPR " . number_format($row['unit_price'], 2) . 
                 " × {$row['quantity']} = <strong class='text-success fs-5'>NPR " . number_format($row['total_amount'], 2) . "</strong></p>";

            if (!empty($measurements)) {
                foreach ($measurements as $field => $value) {
                    $field = ucwords(str_replace('_', ' ', $field));
                    echo "<span class='measurement-item'><strong>$field:</strong> " . htmlspecialchars($value) . "</span> ";
                }
            } else {
                echo "<em class='text-muted'>No measurements recorded</em>";
            }
            echo "</div>";

            echo "<div class='col-lg-4 text-end'>";
            echo "<a href='?done={$row['item_id']}&filter_mode=" . $filter_mode . "' 
                     class='btn btn-success btn-lg done-btn shadow'
                     onclick=\"return confirm('Mark « " . htmlspecialchars($row['item_name']) . " » as completed?')\">
                     Done
                  </a>";
            echo "</div>";
            echo "</div>";
        endforeach;
        echo "</div></div></div>";
    endif; ?>

    <div class="text-center my-5">
        <a href="dashboard.php" class="btn btn-lg btn-primary px-5">Back to Dashboard</a>
    </div>
</div>

</body>
</html>