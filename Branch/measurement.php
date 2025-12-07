<?php
include '../Common/connection.php'; // This gives us $pdo
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Measurements & Items</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; padding: 20px; }
        .card-header { background-color: #0d6efd; color: white; font-weight: bold; }
        .measurement-item { 
            background-color: #e9ecef; 
            padding: 8px 14px; 
            border-radius: 8px; 
            margin: 4px 6px 4px 0; 
            display: inline-block; 
            font-size: 0.95em;
        }
        .back-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
    </style>
</head>
<body>

<!-- Back to Dashboard Button (Floating) -->
<a href="dashboard.php" class="btn btn-dark btn-lg shadow back-btn">
    Dashboard
</a>

<div class="container mt-5 pt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="text-primary">
            Customer Uniform Measurements
        </h1>
        <a href="dashboard.php" class="btn btn-outline-primary">
            Back to Dashboard
        </a>
    </div>

    <?php
    $sql = "
        SELECT 
            cm.bill_number,
            cm.fiscal_year,
            cm.customer_name,
            cm.phone,
            cm.created_at AS bill_date,
            cmi.item_index,
            cmi.item_name,
            cmi.price AS unit_price,
            cmi.quantity,
            (cmi.price * cmi.quantity) AS total_amount,
            JSON_EXTRACT(cm.measurements, CONCAT('$.\"', cmi.item_index, '\"')) AS measurement_json
        FROM customer_measurements cm
        LEFT JOIN custom_measurement_items cmi 
            ON cm.bill_number = cmi.bill_number 
            AND cm.fiscal_year = cmi.fiscal_year
        WHERE cmi.item_name IS NOT NULL
        ORDER BY cm.bill_number DESC, cmi.item_index ASC
    ";

    try {
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            echo "<div class='alert alert-info text-center fs-4'>No measurement records found yet.</div>";
            echo "<div class='text-center mt-4'>
                    <a href='dashboard.php' class='btn btn-primary btn-lg'>Go to Dashboard</a>
                  </div>";
        } else {
            $current_bill = null;

            foreach ($rows as $row) {
                $measurements = json_decode($row['measurement_json'], true);

                if ($current_bill !== $row['bill_number']) {
                    if ($current_bill !== null) {
                        echo "</div></div></div><hr>";
                    }
                    $current_bill = $row['bill_number'];

                    echo "<div class='card mb-4 shadow-lg border-0'>";
                    echo "<div class='card-header bg-primary text-white d-flex justify-content-between align-items-center'>";
                    echo "<div><h5 class='mb-0'>Bill No: <strong>{$row['bill_number']}</strong> • {$row['fiscal_year']}</h5></div>";
                    echo "<small>" . date('d M Y, h:i A', strtotime($row['bill_date'])) . "</small>";
                    echo "</div>";
                    echo "<div class='card-body'>";
                    echo "<h4 class='text-success'>Customer: <strong>{$row['customer_name']}</strong>";
                    if ($row['phone']) echo " • Phone: {$row['phone']}";
                    echo "</h4><hr>";
                }

                echo "<div class='row align-items-start mb-4 p-3 bg-light rounded'>";
                echo "<div class='col-md-5'>";
                echo "<h5 class='text-success fw-bold'>" . htmlspecialchars($row['item_name']) . "</h5>";
                echo "<p class='mb-0'><strong>Price:</strong> NPR " . number_format($row['unit_price'], 2) . 
                     " × {$row['quantity']} = <strong class='text-success fs-5'>NPR " . number_format($row['total_amount'], 2) . "</strong></p>";
                echo "</div>";

                echo "<div class='col-md-7'>";
                if (is_array($measurements) && !empty($measurements)) {
                    foreach ($measurements as $field => $value) {
                        $field = ucwords(str_replace('_', ' ', $field));
                        echo "<span class='measurement-item'>
                                <strong>$field:</strong> " . htmlspecialchars($value) . "
                              </span>";
                    }
                } else {
                    echo "<em class='text-muted'>No measurements recorded</em>";
                }
                echo "</div>";
                echo "</div>";
            }
            echo "</div></div></div>";
        }

    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    ?>

    <!-- Bottom Back Button -->
    <div class="text-center my-5">
        <a href="dashboard.php" class="btn btn-lg btn-success px-5">
            Back to Dashboard
        </a>
    </div>
</div>

</body>
</html>