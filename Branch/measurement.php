<?php
include '../Common/connection.php';

// Handle Mark as Done – works on PHP 7.0 to 8.3
if (isset($_GET['done']) && $_GET['done'] != '') {
    $item_id = (int)$_GET['done'];
    $stmt = $pdo->prepare("UPDATE custom_measurement_items SET done = 1 WHERE id = ?");
    $stmt->execute([$item_id]);
    
    // Small success message + refresh
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
    </style>
</head>
<body>


<div class="container mt-5 pt-4">
    <h1 class="text-primary mb-4">Pending Uniform Orders</h1>

    <?php
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
            JSON_EXTRACT(cm.measurements, CONCAT('$.\"', cmi.item_index, '\"')) AS measurement_json
        FROM customer_measurements cm
        JOIN custom_measurement_items cmi 
            ON cm.bill_number = cmi.bill_number 
            AND cm.fiscal_year = cmi.fiscal_year
        WHERE cmi.done = 0
        ORDER BY cm.created_at DESC, cm.bill_number DESC, cmi.item_index
    ";

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        echo "<div class='alert alert-success text-center fs-2'>All orders completed!</div>";
        echo "<div class='text-center mt-4'><a href='dashboard.php' class='btn btn-primary btn-lg'>Back to Dashboard</a></div>";
    } else {
        $current_bill = null;
        foreach ($rows as $row) {
            $measurements = json_decode($row['measurement_json'], true);

            if ($current_bill !== $row['bill_number']) {
                if ($current_bill !== null) echo "</div></div></div><hr class='my-5'>";
                $current_bill = $row['bill_number'];

                echo "<div class='card mb-4 shadow-lg'>";
                echo "<div class='card-header bg-primary text-white d-flex justify-content-between align-items-center'>";
                echo "<h5 class='mb-0'>Bill No: <strong>{$row['bill_number']}</strong> • {$row['fiscal_year']}</h5>";
                echo "<small>" . date('d M Y, h:i A', strtotime($row['bill_date'])) . "</small>";
                echo "</div>";
                echo "<div class='card-body'>";
                echo "<h4 class='text-success'>Customer: <strong>{$row['customer_name']}</strong>";
                if ($row['phone']) echo " • {$row['phone']}";
                echo "</h4><hr>";
            }

            echo "<div class='row align-items-center mb-3 p-3 bg-light rounded position-relative border-start border-success border-4'>";

            echo "<div class='col-lg-8'>";
            echo "<h5 class='text-success fw-bold mb-2'>{$row['item_name']}</h5>";
            echo "<p class='mb-2'><strong>Price:</strong> NPR " . number_format($row['unit_price'],2) . 
                 " × {$row['quantity']} = <strong class='text-success fs-5'>NPR " . number_format($row['total_amount'],2) . "</strong></p>";

            if (is_array($measurements) && !empty($measurements)) {
                foreach ($measurements as $field => $value) {
                    $field = ucwords(str_replace('_', ' ', $field));
                    echo "<span class='measurement-item'><strong>$field:</strong> $value</span> ";
                }
            } else {
                echo "<em class='text-muted'>No measurements recorded</em>";
            }
            echo "</div>";

            // Done Button
            echo "<div class='col-lg-4 text-end'>";
            echo "<a href='?done={$row['item_id']}' class='btn btn-success btn-lg done-btn shadow'
                    onclick=\"return confirm('Mark « {$row['item_name']} » as completed?')\">
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