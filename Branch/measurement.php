<?php
session_start();
require '../Common/connection.php';
require '../Common/nepali_date.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// ==================== SECURITY CHECK ====================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}

$user_id   = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'];
$outlet_id = $_SESSION['outlet_id'] ?? null;

// ==================== DATE FILTERS (B.S.) ====================
$start_bs = trim($_GET['start_bs'] ?? '');
$end_bs   = trim($_GET['end_bs'] ?? '');

$apply_date_filter = (!empty($start_bs) && !empty($end_bs));

// ==================== EXCEL EXPORT (UPDATED: ONLY REQUESTED COLUMNS) ====================
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $where = "WHERE cmi.done = 0 AND cm.outlet_id = ? AND cm.created_by = ?";
    $params = [$outlet_id, $user_id];

    if ($apply_date_filter) {
        $where .= " AND DATE(cmi.bs_datetime) BETWEEN ? AND ?";
        $params[] = $start_bs;
        $params[] = $end_bs;
    }

    if ($user_role === 'admin') {
        $where = "WHERE cmi.done = 0 AND cm.outlet_id = ?";
        $params = [$outlet_id];
        if ($apply_date_filter) {
            $where .= " AND DATE(cmi.bs_datetime) BETWEEN ? AND ?";
            $params[] = $start_bs;
            $params[] = $end_bs;
        }
    }

    $sql = "
        SELECT 
            cm.bill_number,
            cm.customer_name,
            cmi.item_name,
            cmi.quantity,
            JSON_EXTRACT(cm.measurements, CONCAT('$.\"', cmi.item_index, '\"')) AS measurement_json
        FROM customer_measurements cm
        JOIN custom_measurement_items cmi ON cm.bill_number = cmi.bill_number AND cm.fiscal_year = cmi.fiscal_year
        $where
        ORDER BY cmi.bs_datetime DESC, cm.bill_number DESC, cmi.item_index
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Pending Orders');

    // Only these headers
    $headers = ['S.N', 'Bill No.', 'Customer', 'Item', 'Quantity', 'Measurements'];

    $col = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue("{$col}1", $h);
        $col++;
    }

    $sheet->getStyle('A1:F1')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0d6efd');

    $row = 2;
    $sn = 1;

    foreach ($rows as $r) {
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

        $sheet->setCellValue("A{$row}", $sn++);
        $sheet->setCellValue("B{$row}", $r['bill_number']);
        $sheet->setCellValue("C{$row}", $r['customer_name']);
        $sheet->setCellValue("D{$row}", $r['item_name']);
        $sheet->setCellValue("E{$row}", $r['quantity']);
        $sheet->setCellValue("F{$row}", $measText);

        $row++;
    }

    // Auto-size columns
    foreach (range('A', 'F') as $c) {
        $sheet->getColumnDimension($c)->setAutoSize(true);
    }
    $sheet->getColumnDimension('F')->setWidth(80);

    $title = $apply_date_filter ? "Pending_{$start_bs}_to_{$end_bs}" : "All_Pending";
    ob_end_clean();
    $filename = "Pending_Orders_" . $title . ".xlsx";
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

    $check_sql = "SELECT cm.bill_number 
                  FROM customer_measurements cm
                  JOIN custom_measurement_items cmi ON cm.bill_number = cmi.bill_number AND cm.fiscal_year = cmi.fiscal_year
                  WHERE cmi.id = ? AND cm.outlet_id = ? AND cm.created_by = ?";
    $params_check = [$item_id, $outlet_id, $user_id];

    if ($user_role === 'admin') {
        $check_sql = "SELECT cm.bill_number FROM customer_measurements cm
                      JOIN custom_measurement_items cmi ON cm.bill_number = cmi.bill_number AND cm.fiscal_year = cmi.fiscal_year
                      WHERE cmi.id = ? AND cm.outlet_id = ?";
        $params_check = [$item_id, $outlet_id];
    }

    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute($params_check);

    if ($check_stmt->fetch()) {
        $stmt = $pdo->prepare("UPDATE custom_measurement_items SET done = 1 WHERE id = ?");
        $stmt->execute([$item_id]);
        $msg = 'Item marked as completed!';
        $success = true;
    } else {
        $msg = 'Unauthorized action.';
        $success = false;
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['success' => $success, 'message' => $msg]);
        exit;
    }

    $query_params = http_build_query(array_filter(['start_bs' => $start_bs, 'end_bs' => $end_bs]));
    $redirect = $query_params ? "?$query_params" : '';
    echo "<script>alert('$msg'); window.location='measurement.php$redirect';</script>";
    exit;
}

// ==================== MAIN QUERY (ONLY PENDING) ====================
$where = "WHERE cmi.done = 0 AND cm.outlet_id = ? AND cm.created_by = ?";
$params = [$outlet_id, $user_id];

if ($user_role === 'admin') {
    $where = "WHERE cmi.done = 0 AND cm.outlet_id = ?";
    $params = [$outlet_id];
}

if ($apply_date_filter) {
    $where .= " AND DATE(cmi.bs_datetime) BETWEEN ? AND ?";
    $params[] = $start_bs;
    $params[] = $end_bs;
}

$sql = "
    SELECT 
        cm.bill_number,
        cm.fiscal_year,
        cm.customer_name,
        cm.phone,
        cmi.bs_datetime,
        cmi.id AS item_id,
        cmi.item_index,
        cmi.item_name,
        cmi.price AS unit_price,
        cmi.quantity,
        (cmi.price * cmi.quantity) AS total_amount,
        JSON_EXTRACT(cm.measurements, CONCAT('$.\"', cmi.item_index, '\"')) AS measurement_json
    FROM customer_measurements cm
    JOIN custom_measurement_items cmi ON cm.bill_number = cmi.bill_number AND cm.fiscal_year = cmi.fiscal_year
    $where
    ORDER BY cmi.bs_datetime DESC, cm.bill_number DESC, cmi.item_index
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
    <title>My Pending Custom Orders</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; padding: 20px; }
        .card-header { background-color: #0d6efd; color: white; font-weight: bold; }
        .measurement-item { background-color: #e9ecef; padding: 8px 14px; border-radius: 8px; margin: 4px 6px 4px 0; display: inline-block; font-size: 0.95em; }
        .done-btn { font-size: 1.2em; min-width: 120px; }
        .filter-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin-bottom: 30px; }
    </style>
</head>
<body>

<div class="container mt-5 pt-4">
    <h1 class="text-primary mb-4 text-center">
        My Pending Custom Orders
        <?php if ($user_role === 'admin'): ?>
            <small class="d-block text-muted fs-5">(All in Branch)</small>
        <?php endif; ?>
    </h1>

    <div class="filter-card">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-bold">Start Date (B.S.)</label>
                <input type="text" name="start_bs" class="form-control form-control-lg" 
                       placeholder="e.g. 2082-08-09" 
                       value="<?= htmlspecialchars($start_bs) ?>" 
                       pattern="[0-9]{4}-[0-9]{2}-[0-9]{2}">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">End Date (B.S.)</label>
                <input type="text" name="end_bs" class="form-control form-control-lg" 
                       placeholder="e.g. 2082-09-02" 
                       value="<?= htmlspecialchars($end_bs) ?>" 
                       pattern="[0-9]{4}-[0-9]{2}-[0-9]{2}">
            </div>
            <div class="col-md-4 text-center">
                <button type="submit" class="btn btn-primary btn-lg px-4 me-2">
                    <i class="fas fa-search"></i> Search
                </button>
                <a href="measurement.php" class="btn btn-secondary btn-lg px-4">
                    <i class="fas fa-times"></i> Clear
                </a>
            </div>
        </form>

        <div class="text-center mt-4">
            <a href="measurement.php?export=excel&start_bs=<?= urlencode($start_bs) ?>&end_bs=<?= urlencode($end_bs) ?>" 
               class="btn btn-success btn-lg me-3">
                <i class="fas fa-file-excel"></i> Export My Orders
            </a>
            <a href="dashboard.php" class="btn btn-primary btn-lg">
                <i class="fas fa-home"></i> Back to Dashboard
            </a>
        </div>

        <div id="success-message" class="mt-4"></div>
    </div>

    <?php if (empty($rows)): ?>
        <div class="alert alert-success text-center fs-2 mt-5">
            <i class="fas fa-check-circle"></i> No pending orders!
        </div>
    <?php else: 
        $current_bill = null;
        foreach ($rows as $row): 
            $bs_date = explode(' ', $row['bs_datetime'])[0];
            $measurements = json_decode($row['measurement_json'], true) ?? [];

            $measTextList = '';
            $measTextModal = '';
            if (!empty($measurements)) {
                foreach ($measurements as $field => $value) {
                    $field = ucwords(str_replace('_', ' ', $field));
                    $measTextList .= "<span class='measurement-item'><strong>$field:</strong> " . htmlspecialchars($value) . "</span> ";
                    $measTextModal .= "<span class='badge bg-secondary me-2 mb-1'>$field: " . htmlspecialchars($value) . "</span>";
                }
            } else {
                $measTextList = "<em class='text-muted'>No measurements recorded</em>";
                $measTextModal = "<em class='text-muted'>No measurements recorded</em>";
            }

            if ($current_bill !== $row['bill_number']):
                if ($current_bill !== null) echo "</div></div></div><hr class='my-5'>";
                $current_bill = $row['bill_number'];

                echo "<div class='card mb-4 shadow-lg' id='bill-card-{$row['bill_number']}'>";
                echo "<div class='card-header bg-primary text-white d-flex justify-content-between align-items-center flex-wrap gap-2'>";
                echo "<div>";
                echo "<h5 class='mb-0'>Bill No: <strong>{$row['bill_number']}</strong> • {$row['fiscal_year']}</h5>";
                echo "</div>";
                echo "<small>BS Date: <strong>{$bs_date}</strong></small>";
                echo "</div>";
                echo "<div class='card-body'>";
                echo "<h4 class='text-success'>Customer: <strong>" . htmlspecialchars($row['customer_name']) . "</strong>";
                if ($row['phone']) echo " • " . htmlspecialchars($row['phone']);
                echo "</h4><hr>";
            endif;

            echo "<div class='row align-items-center mb-3 p-3 bg-light rounded border-start border-success border-4' id='item-row-{$row['item_id']}'>";
            echo "<div class='col-lg-8'>";
            echo "<h5 class='text-success fw-bold mb-2'>" . htmlspecialchars($row['item_name']) . "</h5>";
            echo "<p class='mb-2'><strong>Price:</strong> NPR " . number_format($row['unit_price'], 2) . 
                 " × {$row['quantity']} = <strong class='text-success fs-5'>NPR " . number_format($row['total_amount'], 2) . "</strong></p>";
            echo $measTextList;
            echo "</div>";

            echo "<div class='col-lg-4 text-end'>";
            echo "<button type='button' class='btn btn-success btn-lg done-btn shadow'
                     data-bs-toggle='modal' data-bs-target='#confirmDoneModal'
                     data-item-id='{$row['item_id']}'
                     data-bill='{$row['bill_number']}'
                     data-item-name='" . htmlspecialchars($row['item_name'], ENT_QUOTES) . "'
                     data-customer='" . htmlspecialchars($row['customer_name'], ENT_QUOTES) . "'
                     data-quantity='{$row['quantity']}'
                     data-total='" . number_format($row['total_amount'], 2) . "'
                     data-measurements='" . htmlspecialchars($measTextModal, ENT_QUOTES) . "'>
                     Done
                  </button>";
            echo "</div>";
            echo "</div>";
        endforeach;
        echo "</div></div></div>";
    endif; ?>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmDoneModal" tabindex="-1" aria-labelledby="confirmDoneModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title" id="confirmDoneModalLabel">Confirm Mark as Done</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to mark the following item as <strong>completed</strong>?</p>
            <hr>
            <p><strong>Bill No:</strong> <span id="modal-bill"></span></p>
            <p><strong>Customer:</strong> <span id="modal-customer"></span></p>
            <p><strong>Item:</strong> <span id="modal-item-name"></span></p>
            <p><strong>Quantity:</strong> <span id="modal-quantity"></span></p>
            <p><strong>Total Amount:</strong> NPR <span id="modal-total"></span></p>
            <p><strong>Measurements:</strong></p>
            <div id="modal-measurements"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-success" id="confirm-done-btn">Yes, Mark as Done</button>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    const confirmDoneModal = document.getElementById('confirmDoneModal');
    let currentItemId = null;
    const currentQueryParams = "<?= http_build_query(array_filter(['start_bs' => $start_bs, 'end_bs' => $end_bs])) ?>";

    confirmDoneModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;

        currentItemId = button.getAttribute('data-item-id');

        document.getElementById('modal-bill').textContent = button.getAttribute('data-bill');
        document.getElementById('modal-customer').textContent = button.getAttribute('data-customer');
        document.getElementById('modal-item-name').textContent = button.getAttribute('data-item-name');
        document.getElementById('modal-quantity').textContent = button.getAttribute('data-quantity');
        document.getElementById('modal-total').textContent = button.getAttribute('data-total');
        document.getElementById('modal-measurements').innerHTML = button.getAttribute('data-measurements');
    });

    document.getElementById('confirm-done-btn').addEventListener('click', function () {
        if (!currentItemId) return;

        const url = `measurement.php?done=${currentItemId}&${currentQueryParams}`;

        fetch(url, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const modal = bootstrap.Modal.getInstance(confirmDoneModal);
                modal.hide();

                const successDiv = document.getElementById('success-message');
                successDiv.innerHTML = `<div class="alert alert-success alert-dismissible fade show">
                                            <strong>Success!</strong> ${data.message}
                                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                        </div>`;

                const itemRow = document.getElementById('item-row-' + currentItemId);
                if (itemRow) itemRow.remove();

                const billCard = document.getElementById('bill-card-' + document.getElementById('modal-bill').textContent);
                if (billCard) {
                    const remainingItems = billCard.querySelectorAll('.row.mb-3');
                    if (remainingItems.length === 0) {
                        billCard.nextElementSibling?.remove();
                        billCard.remove();
                    }
                }

                if (document.querySelectorAll('.card').length === 0) {
                    document.querySelector('.container').innerHTML += `
                        <div class="alert alert-success text-center fs-2 mt-5">
                            <i class="fas fa-check-circle"></i> No pending orders!
                        </div>`;
                }
            } else {
                alert(data.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error marking item as done. Please try again.');
        });
    });
    </script>

</div>
</body>
</html>