<?php
session_start();
require '../Common/connection.php';
require '../vendor/autoload.php'; // Make sure PhpSpreadsheet is installed

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['selected_outlet_id'])) {
    header("Location: index.php");
    exit();
}

$outlet_id   = $_SESSION['selected_outlet_id'];
$outlet_name = $_SESSION['selected_outlet_name'];

// =============================================
// EXCEL EXPORT LOGIC
// =============================================
if (isset($_GET['export']) && $_GET['export'] === 'excel') {

    $stmt = $pdo->prepare("
        SELECT rel.*,
               COALESCE(l.username, 'Unknown User') AS logged_by_name
        FROM return_exchange_log rel
        LEFT JOIN login l ON rel.user_id = l.id
        WHERE rel.outlet_id = ? AND rel.action = 'returned'
        ORDER BY rel.logged_datetime DESC
    ");
    $stmt->execute([$outlet_id]);
    $returns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Returned Orders');

    // Headers
    $headers = ['S.N', 'Log ID', 'Bill ID', 'Reason', 'Amt Returned by Customer', 'Amt Refunded to Customer', 'Logged By', 'Date & Time'];
    $col = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue($col++ . '1', $h);
    }

    // Style header
    $headerRange = 'A1:' . $col . '1';
    $sheet->getStyle($headerRange)->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle($headerRange)->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FF8E44AD');

    // Fill data
    $row = 2;
    foreach ($returns as $i => $r) {
        $sheet->setCellValue('A' . $row, $i + 1);
        $sheet->setCellValue('B' . $row, $r['id']);
        $sheet->setCellValue('C' . $row, $r['bill_id']);
        $sheet->setCellValue('D' . $row, $r['reason'] ?: '-');
        $sheet->setCellValue('E' . $row, number_format($r['amount_returned_by_customer'], 2));
        $sheet->setCellValue('F' . $row, number_format($r['amount_returned_to_customer'], 2));
        $sheet->setCellValue('G' . $row, $r['logged_by_name']);
        $sheet->setCellValue('H' . $row, date('d-m-Y H:i', strtotime($r['logged_datetime'])));
        $row++;
    }

    // Auto-size columns
    foreach (range('A', 'H') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Download
    $filename = "Returned_Orders_" . date('Y-m-d') . ".xlsx";
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
$stmt = $pdo->prepare("
    SELECT rel.*,
           COALESCE(l.username, 'Unknown User') AS logged_by_name
    FROM return_exchange_log rel
    LEFT JOIN login l ON rel.user_id = l.id
    WHERE rel.outlet_id = ? AND rel.action = 'returned'
    ORDER BY rel.logged_datetime DESC
");
$stmt->execute([$outlet_id]);
$returns = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Returned Orders - <?php echo htmlspecialchars($outlet_name); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body{font-family:'Segoe UI',sans-serif;background:linear-gradient(135deg,#8e44ad,#9b59b6);min-height:100vh;padding:40px 20px;color:#2c3e50;}
        .container{max-width:1300px;margin:0 auto;background:rgba(255,255,255,0.98);border-radius:20px;padding:30px;box-shadow:0 20px 50px rgba(0,0,0,0.2);}
        h1{text-align:center;color:#8e44ad;margin-bottom:30px;font-size:2rem;}
        table{width:100%;border-collapse:collapse;margin-top:20px;}
        th,td{padding:15px 12px;text-align:left;border-bottom:1px solid #ddd;}
        th{background:#8e44ad;color:white;font-weight:600;}
        tr:hover{background:#f8f1ff;}
        .no-data{text-align:center;padding:40px;font-size:1.3rem;color:#7f8c8d;}
        .btn{display:inline-block;margin:15px 10px;padding:12px 32px;background:#e74c3c;color:white;border-radius:50px;text-decoration:none;font-weight:600;font-size:1rem;}
        .btn:hover{background:#c0392b;transform:scale(1.05);}
        .btn-back{background:#8e44ad;}
        .btn-back:hover{background:#732d91;}
        .amount{text-align:right;font-weight:600;}
    </style>
</head>
<body>
<div class="container">
    <h1>Returned Orders – <?php echo htmlspecialchars($outlet_name); ?></h1>

    <?php if (empty($returns)): ?>
        <p class="no-data">No returned orders found.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Bill ID</th>
                    <th>Reason</th>
                    <th class="amount">Amt Returned<br><small>(by Customer)</small></th>
                    <th class="amount">Amt Refunded<br><small>(to Customer)</small></th>
                    <th>Logged By</th>
                    <th>Date & Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($returns as $row): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($row['bill_id']); ?></strong></td>
                    <td><?php echo htmlspecialchars($row['reason'] ?: '-'); ?></td>
                    <td class="amount"><?php echo number_format($row['amount_returned_by_customer'], 2); ?></td>
                    <td class="amount"><?php echo number_format($row['amount_returned_to_customer'], 2); ?></td>
                    <td><strong><?php echo htmlspecialchars($row['logged_by_name']); ?></strong></td>
                    <td><?php echo date('d-m-Y H:i', strtotime($row['logged_datetime'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div style="text-align:center;">
        <a href="order_return.php?export=excel" class="btn">
            Export to Excel
        </a>
        <a href="dashboard.php" class="btn btn-back">Back to Dashboard</a>
    </div>
</div>
</body>
</html>