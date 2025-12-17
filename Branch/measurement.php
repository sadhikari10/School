<?php
session_start();
require '../Common/connection.php';
require '../Common/nepali_date.php'; // Now has bs_to_ad()
require '../vendor/autoload.php'; // PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// ==================== SECURITY CHECK ====================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}

$user_role   = $_SESSION['role'];
$outlet_id   = $_SESSION['outlet_id'] ?? null;

// ==================== EXCEL EXPORT ====================
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $filter_bs = trim($_GET['filter_date'] ?? '');

    $where_clause = "WHERE cmi.done = 0";
    $params = [];

    if ($user_role === 'staff' && $outlet_id !== null) {
        $where_clause .= " AND cmi.outlet_id = ?";
        $params[] = $outlet_id;
    }

    if ($filter_bs !== '') {
        $filter_ad = bs_to_ad($filter_bs); // ← Fixed: use bs_to_ad()
        if ($filter_ad) {
            $where_clause .= " AND DATE(cm.created_at) = ?";
            $params[] = $filter_ad;
        }
    }

    $sql = "
        SELECT 
            cm.bill_number,
            cm.fiscal_year,
            cm.customer_name,
            cm.phone,
            cm.created_at AS bill_date,
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

    $headers = ['S.N', 'Bill No.', 'Fiscal Year', 'Customer', 'Phone', 'Item Name', 'Qty', 'Unit Price', 'Total', 'Branch', 'Date (AD)', 'Measurements'];
    $col = 'A';
    foreach ($headers as $h) $sheet->setCellValue($col++ . '1', $h);

    $sheet->getStyle('A1:L1')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A1:L1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0d6efd');

    $row = 2;
    $sn = 1;
    $grand_total = 0;

    foreach ($rows as $r) {
        $measurements = json_decode($r['measurement_json'], true);
        $measText = '';
        if (is_array($measurements)) {
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
        $sheet->setCellValue('K' . $row, date('Y-m-d H:i', strtotime($r['bill_date'])));
        $sheet->setCellValue('L' . $row, $measText);

        $grand_total += $r['total_amount'];
        $row++;
    }

    $sheet->setCellValue('H' . $row, 'GRAND TOTAL');
    $sheet->setCellValue('I' . $row, $grand_total);
    $sheet->getStyle('H' . $row . ':I' . $row)->getFont()->setBold(true)->setSize(13);
    $sheet->getStyle('H2:I' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle('H2:I' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    foreach (range('A', 'L') as $c) $sheet->getColumnDimension($c)->setAutoSize(true);
    $sheet->getColumnDimension('L')->setWidth(80);

    $filename = "Pending_Measurements_" . ($filter_bs ?: 'All') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// ==================== HANDLE MARK AS DONE ====================
if (isset($_GET['done']) && $_GET['done'] != '') {
    $item_id = (int)$_GET['done'];
    
    if ($user_role === 'staff' && $outlet_id !== null) {
        $stmt = $pdo->prepare("UPDATE custom_measurement_items SET done = 1 WHERE id = ? AND outlet_id = ?");
        $stmt->execute([$item_id, $outlet_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE custom_measurement_items SET done = 1 WHERE id = ?");
        $stmt->execute([$item_id]);
    }
    
    $filter_param = isset($_GET['filter_date']) ? '&filter_date=' . urlencode($_GET['filter_date']) : '';
    echo "<script>alert('Item marked as completed!'); window.location='measurement.php$filter_param';</script>";
    exit;
}

// ==================== SINGLE DATE FILTER ====================
$filter_bs = trim($_GET['filter_date'] ?? '');

$where_clause = "WHERE cmi.done = 0";
$params = [];

if ($user_role === 'staff' && $outlet_id !== null) {
    $where_clause .= " AND cmi.outlet_id = ?";
    $params[] = $outlet_id;
}

if ($filter_bs !== '') {
    $filter_ad = bs_to_ad($filter_bs); // ← Fixed: use bs_to_ad()
    if ($filter_ad) {
        $where_clause .= " AND DATE(cm.created_at) = ?";
        $params[] = $filter_ad;
    }
}

$sql = "
    SELECT 
        cm.bill_number,
        cm.fiscal_year,
        cm.customer_name,
        cm.phone,
        cm.created_at AS bill_date,
        cmi.id AS item_id,
        cmi.item_index,
        cmi.item_name,
        cmi.price AS unit_price,
        cmi.quantity,
        (cmi.price * cmi.quantity) AS total_amount,
        JSON_EXTRACT(cm.measurements, CONCAT('$.\"', cmi.item_index, '\"')) AS measurement_json,
        cmi.outlet_id
    FROM customer_measurements cm
    JOIN custom_measurement_items cmi 
        ON cm.bill_number = cmi.bill_number 
        AND cm.fiscal_year = cmi.fiscal_year
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

    <!-- Single Date Filter + Export -->
    <div class="filter-form text-center">
        <form method="GET" class="row g-3 justify-content-center align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-bold">Filter by BS Date</label>
                <input type="text" name="filter_date" class="form-control form-control-lg text-center" 
                       placeholder="e.g. 2082-09-02" value="<?=htmlspecialchars($filter_bs)?>">
                <small class="text-muted">Leave empty to show all pending</small>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-lg w-100">Apply Filter</button>
            </div>
            <div class="col-md-2">
                <a href="measurement.php" class="btn btn-secondary btn-lg w-100">Clear</a>
            </div>
        </form>

        <div class="mt-4">
            <a href="measurement.php?export=excel<?php 
                echo $filter_bs ? '&filter_date=' . htmlentities($filter_bs) : '';
            ?>" class="btn btn-success btn-lg px-5">
                <i class="fas fa-file-excel"></i> Export to Excel
            </a>
        </div>
    </div>

    <?php if (empty($rows)): ?>
        <div class="alert alert-success text-center fs-2">All orders completed!</div>
    <?php else: 
        $current_bill = null;
        foreach ($rows as $row): 
            $measurements = json_decode($row['measurement_json'], true);

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
                echo "<small>" . date('d M Y, h:i A', strtotime($row['bill_date'])) . "</small>";
                echo "</div>";
                echo "<div class='card-body'>";
                echo "<h4 class='text-success'>Customer: <strong>" . htmlspecialchars($row['customer_name']) . "</strong>";
                if ($row['phone']) echo " • " . htmlspecialchars($row['phone']);
                echo "</h4><hr>";
            endif;

            echo "<div class='row align-items-center mb-3 p-3 bg-light rounded position-relative border-start border-success border-4'>";
            echo "<div class='col-lg-8'>";
            echo "<h5 class='text-success fw-bold mb-2'>" . htmlspecialchars($row['item_name']) . "</h5>";
            echo "<p class='mb-2'><strong>Price:</strong> NPR " . number_format($row['unit_price'], 2) . 
                 " × {$row['quantity']} = <strong class='text-success fs-5'>NPR " . number_format($row['total_amount'], 2) . "</strong></p>";

            if (is_array($measurements) && !empty($measurements)) {
                foreach ($measurements as $field => $value) {
                    $field = ucwords(str_replace('_', ' ', $field));
                    echo "<span class='measurement-item'><strong>$field:</strong> " . htmlspecialchars($value) . "</span> ";
                }
            } else {
                echo "<em class='text-muted'>No measurements recorded</em>";
            }
            echo "</div>";

            echo "<div class='col-lg-4 text-end'>";
            echo "<a href='?done={$row['item_id']}" . ($filter_bs ? "&filter_date=" . urlencode($filter_bs) : '') . "' 
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