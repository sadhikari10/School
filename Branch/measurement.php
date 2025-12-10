<?php
session_start();
include '../Common/connection.php';

// ==================== SECURITY CHECK ====================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}

$user_role   = $_SESSION['role'];        // 'admin' or 'staff'
$outlet_id   = $_SESSION['outlet_id'] ?? null;  // null for admin, number for staff

// Handle Mark as Done
if (isset($_GET['done']) && $_GET['done'] != '') {
    $item_id = (int)$_GET['done'];
    
    // Extra safety: staff can only mark their own outlet's items
    if ($user_role === 'staff' && $outlet_id !== null) {
        $stmt = $pdo->prepare("UPDATE custom_measurement_items SET done = 1 WHERE id = ? AND outlet_id = ?");
        $stmt->execute([$item_id, $outlet_id]);
    } else {
        // Admin can mark any
        $stmt = $pdo->prepare("UPDATE custom_measurement_items SET done = 1 WHERE id = ?");
        $stmt->execute([$item_id]);
    }
    
    echo "<script>alert('Item marked as completed!'); window.location='measurement.php';</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Uniform Orders</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; padding: 20px; }
        .card-header { background-color: #0d6efd; color: white; font-weight: bold; }
        .measurement-item { 
            background-color: #e9ecef; padding: 8px 14px; border-radius: 8px; 
            margin: 4px 6px 4px 0; display: inline-block; font-size: 0.95em;
        }
        .back-btn { position: fixed; top: 20px; right: 20px; z-index: 1000; }
        .done-btn { font-size: 1.2em; min-width: 120px; }
        .branch-badge { font-size: 0.9rem; }
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

    <?php
    // Build query with outlet filter for staff
    $where_clause = "WHERE cmi.done = 0";
    $params = [];

    if ($user_role === 'staff' && $outlet_id !== null) {
        $where_clause .= " AND cmi.outlet_id = ?";
        $params[] = $outlet_id;
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

    if (empty($rows)) {
        echo "<div class='alert alert-success text-center fs-2'>All orders completed!</div>";
        echo "<div class='text-center mt-4'><a href='dashboard.php' class='btn btn-primary btn-lg px-5'>Back to Dashboard</a></div>";
    } else {
        $current_bill = null;
        foreach ($rows as $row) {
            $measurements = json_decode($row['measurement_json'], true);

            // Show branch name for Admin only
            $branch_name = '';
            if ($user_role === 'admin') {
                $loc_stmt = $pdo->prepare("SELECT location FROM outlets WHERE outlet_id = ?");
                $loc_stmt->execute([$row['outlet_id']]);
                $branch_name = $loc_stmt->fetchColumn() ?: 'Unknown Branch';
            }

            if ($current_bill !== $row['bill_number']) {
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
            }

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

            // Done Button
            echo "<div class='col-lg-4 text-end'>";
            echo "<a href='?done={$row['item_id']}' class='btn btn-success btn-lg done-btn shadow'
                    onclick=\"return confirm('Mark « " . htmlspecialchars($row['item_name']) . " » as completed?')\">
                    Done
                  </a>";
            echo "</div>";

            echo "</div>"; // end row
        }
        echo "</div></div></div>";
    }
    ?>

    <div class="text-center my-5">
        <a href="dashboard.php" class="btn btn-lg btn-primary px-5">Back to Dashboard</a>
    </div>
</div>

</body>
</html>