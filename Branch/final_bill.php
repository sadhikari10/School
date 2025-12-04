<?php
session_start();
require '../Common/connection.php';
require '../Common/nepali_date.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Common/login.php');
    exit();
}

// Get bill number from session (secure POST from advance_payment.php)
$bill_number = $_SESSION['complete_bill_number'] ?? 0;
unset($_SESSION['complete_bill_number']);

if ($bill_number <= 0) {
    die("Invalid bill number.");
}

// Fetch advance payment record
$stmt = $pdo->prepare("SELECT * FROM advance_payment WHERE bill_number = ? AND status = 'unpaid' LIMIT 1");
$stmt->execute([$bill_number]);
$advance = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$advance) {
    die("Bill not found or already paid.");
}

// Parse items
$items = json_decode($advance['items_json'], true);
$total = $advance['total'];
$advance_amount = $advance['advance_amount'];
$remaining = $total - $advance_amount;

$customer_name = $advance['customer_name'] ?: 'Customer';
$school_name = $advance['school_name'] ?: '';
$printed_by = $_SESSION['username'] ?? 'Staff';
$bs_datetime = $advance['bs_datetime'];

// Handle final payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalize_payment'])) {
    $final_payment = (float)($_POST['final_payment'] ?? 0);
    $payment_method = $_POST['payment_method'] === 'online' ? 'online' : 'cash';

    if ($final_payment >= $remaining) {
        // Mark as paid in advance_payment
        $pdo->prepare("UPDATE advance_payment SET status = 'paid' WHERE bill_number = ?")
            ->execute([$bill_number]);

        // Insert into sales table
        $pdo->prepare("INSERT INTO sales 
            (bill_number, branch, fiscal_year, school_name, customer_name, total, payment_method, printed_by, printed_at, bs_datetime, items_json)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)")
            ->execute([
                $bill_number,
                $advance['branch'],
                $advance['fiscal_year'],
                $advance['school_name'],
                $advance['customer_name'],
                $total,
                $payment_method,
                $printed_by,
                $bs_datetime,
                $advance['items_json']
            ]);

        // Success
        $payment_success = true;
    } else {
        $error = "Payment amount is less than remaining!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Final Bill #<?php echo $bill_number; ?></title>
    <link rel="stylesheet" href="bill.css">
    <style>
        .final-payment {
            background: #fff8e1;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            border: 2px solid #ffeb3b;
            text-align: center;
            font-size: 18px;
        }
        .final-payment strong { color: #e67e22; font-size: 24px; }
        .payment-form { margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 12px; }
        .payment-form input, .payment-form select { padding: 12px; font-size: 16px; margin: 10px; border-radius: 8px; border: 1px solid #ddd; }
        .btn-final { background: #e67e22; color: white; padding: 14px 30px; font-size: 18px; border: none; border-radius: 10px; cursor: pointer; }
        .btn-final:hover { background: #d35400; }
    </style>
</head>
<body>

<div class="bill" id="printableBill">
    <div class="header">
        <h1><?php echo htmlspecialchars($_SESSION['shop_name'] ?? 'Clothes Store'); ?></h1>
        <p><?php echo htmlspecialchars($advance['branch']); ?></p>
    </div>

    <div class="info"><strong>Bill No:</strong> <?php echo $bill_number; ?></div>
    <div class="info"><strong>Date:</strong> <?php echo $bs_datetime; ?></div>
    <div class="info"><strong>Customer:</strong> <?php echo htmlspecialchars($customer_name); ?></div>
    <?php if ($school_name): ?>
        <div class="info"><strong>School:</strong> <?php echo htmlspecialchars($school_name); ?></div>
    <?php endif; ?>

    <table>
        <thead>
            <tr><th>S.N</th><th>Item</th><th>Size</th><th>Qty</th><th>Amount</th></tr>
        </thead>
        <tbody>
            <?php foreach ($items as $i => $item): ?>
                <tr>
                    <td><?php echo $i + 1; ?>.</td>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td style="text-align:center;"><?php echo htmlspecialchars($item['size'] ?? 'N/A'); ?></td>
                    <td style="text-align:center;"><?php echo $item['quantity']; ?></td>
                    <td style="text-align:right;"><?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="total-section">
        <div class="total-row"><span>Sub Total:</span><span>Rs. <?php echo number_format($total, 2); ?></span></div>
        <div class="total-row"><span>Advance Paid:</span><span>Rs. <?php echo number_format($advance_amount, 2); ?></span></div>
        <div class="total-row"><span style="font-weight:bold; color:#e67e22;">Remaining:</span>
            <span style="font-weight:bold; color:#e67e22;">Rs. <?php echo number_format($remaining, 2); ?></span>
        </div>
        <div class="total-row grand-total">
            <span>GRAND TOTAL:</span><span>Rs. <?php echo number_format($total, 2); ?></span>
        </div>
    </div>

    <div class="footer-note">Thank you for your payment!</div>
    <div class="not-tax">FINAL BILL - PAYMENT COMPLETED</div>
</div>

<?php if (!isset($payment_success)): ?>
<div class="no-print payment-form">
    <div class="final-payment">
        <strong>Remaining Amount: Rs. <?php echo number_format($remaining, 2); ?></strong>
    </div>

    <?php if (isset($error)): ?>
        <div style="color:red; font-weight:bold; margin:15px;"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="number" name="final_payment" min="<?php echo $remaining; ?>" step="1" 
               placeholder="Enter amount received" required style="width:250px;">
        <select name="payment_method">
            <option value="cash">Cash</option>
            <option value="online">Online</option>
        </select>
        <button type="submit" name="finalize_payment" class="btn-final">
            Complete Payment & Print Final Bill
        </button>
    </form>
</div>
<?php else: ?>
<script>
    alert("Final Payment Completed! Printing bill...");
    window.print();
    setTimeout(() => { window.location.href = 'advance_payment.php'; }, 2000);
</script>
<?php endif; ?>

<div class="no-print" style="text-align:center; margin:30px;">
    <a href="advance_payment.php" class="back-btn">Back to Advance List</a>
</div>

</body>
</html>