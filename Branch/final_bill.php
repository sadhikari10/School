<?php
session_start();
require '../Common/connection.php';
require '../Common/nepali_date.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Common/login.php');
    exit();
}

// Get shop_name
$stmt_user = $pdo->prepare("SELECT shop_name FROM login WHERE id = ?");
$stmt_user->execute([$_SESSION['user_id']]);
$shop_name = $stmt_user->fetchColumn() ?: 'Clothes Store';

// Get outlet info: phone + location
$outlet_id = $_SESSION['outlet_id'] ?? 0;
$stmt_outlet = $pdo->prepare("SELECT phone_number, location FROM outlets WHERE outlet_id = ? LIMIT 1");
$stmt_outlet->execute([$outlet_id]);
$outlet = $stmt_outlet->fetch(PDO::FETCH_ASSOC);
$phone_number = $outlet['phone_number'] ?? 'N/A';
$branch_name = $outlet['location'] ?? 'Unknown Branch';

// Get bill number from session
$bill_number = $_SESSION['complete_bill_number'] ?? 0;

if ($bill_number <= 0) {
    die("<h2 style='text-align:center; color:red; margin-top:50px;'>Invalid bill number.</h2>");
}

// Fetch advance record
$stmt = $pdo->prepare("
    SELECT ap.*, o.phone_number, o.location AS branch_name 
    FROM advance_payment ap 
    LEFT JOIN outlets o ON ap.outlet_id = o.outlet_id 
    WHERE ap.bill_number = ? AND ap.status = 'unpaid' 
    LIMIT 1
");
$stmt->execute([$bill_number]);
$advance = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$advance) {
    die("<h2 style='text-align:center; color:red; margin-top:50px;'>Bill not found or already paid.</h2>");
}

$items = json_decode($advance['items_json'], true) ?: [];
$total = $advance['total'];
$advance_amount = $advance['advance_amount'];
$remaining = $total - $advance_amount;

$customer_name = $advance['customer_name'] ?: 'Customer';
$school_name = $advance['school_name'] ?: '';
$printed_by = $_SESSION['username'] ?? 'Staff';

$bs_datetime = nepali_date_time();

$branch_display = $advance['branch_name'] ?? $advance['branch'];
$phone_display = $advance['phone_number'] ?? $phone_number;

// Handle final payment
$payment_success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalize_payment'])) {
    $final_payment = (float)($_POST['final_payment'] ?? 0);
    $payment_method = in_array($_POST['payment_method'], ['cash', 'online']) ? $_POST['payment_method'] : 'cash';

    if ($final_payment >= $remaining || $final_payment >= $total * 0.95) {
        try {
            $pdo->beginTransaction();

            $pdo->prepare("UPDATE advance_payment SET status = 'paid' WHERE bill_number = ?")->execute([$bill_number]);

            $pdo->prepare("INSERT INTO sales 
                (bill_number, branch, outlet_id, fiscal_year, school_name, customer_name, total, payment_method, printed_by, printed_at, bs_datetime, items_json)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)")
                ->execute([
                    $bill_number,
                    $branch_display,
                    $outlet_id,
                    $advance['fiscal_year'],
                    $school_name,
                    $customer_name,
                    $total,
                    $payment_method,
                    $printed_by,
                    $bs_datetime,
                    $advance['items_json']
                ]);

            $pdo->commit();
            $payment_success = true;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Payment failed. Please try again.";
        }
    } else {
        $error = "Received amount is less than remaining balance!";
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
        body { color: #000000; }
        .payment-box { 
            background:#f8f9fa; 
            padding:20px; 
            border-radius:12px; 
            margin:20px 0; 
            border:2px solid #ddd; 
            text-align:center; 
            font-size:18px; 
            color:#2c3e50;
        }
        .payment-box strong { 
            color:#2c3e50; 
            font-size:24px; 
            font-weight:bold;
        }
        .form-group { margin:15px 0; text-align:center; }
        .form-group input, .form-group select { 
            padding:12px; font-size:16px; margin:5px; border-radius:8px; border:1px solid #ccc; width:250px; 
        }
        .btn-pay, .btn-print { 
            padding:14px 32px; 
            font-size:18px; 
            border:none; 
            border-radius:10px; 
            cursor:pointer; 
            font-weight:bold;
            margin: 0 10px;
        }
        .btn-pay { background:#27ae60; color:white; }
        .btn-pay:hover { background:#219653; }
        .btn-print { background:#3498db; color:white; }
        .btn-print:hover { background:#2980b9; }
        .back-link { 
            display:inline-block; margin:20px; padding:12px 25px; background:#667eea; color:white; text-decoration:none; border-radius:8px; 
        }

        /* Modal Styles */
        .overlay { 
            display:none; 
            position:fixed; top:0; left:0; width:100%; height:100%; 
            background:rgba(0,0,0,0.6); z-index:9998; 
            justify-content:center; align-items:center;
        }
        .confirm-box, .success-box {
            background:white; padding:30px; border-radius:12px; text-align:center; width:90%; max-width:400px; 
            box-shadow:0 10px 30px rgba(0,0,0,0.3);
        }
        .confirm-box h3, .success-box h3 { margin:0 0 15px; font-size:22px; color:#2c3e50; }
        .confirm-box p, .success-box p { font-size:18px; margin:10px 0 25px; }
        .btn-group button {
            padding:12px 25px; margin:8px; font-size:16px; border:none; border-radius:8px; cursor:pointer; font-weight:bold;
        }
        .btn-yes { background:#27ae60; color:white; }
        .btn-no { background:#e74c3c; color:white; }
        .success-box p { color:#27ae60; font-size:24px; font-weight:bold; margin:20px 0; }
    </style>
</head>
<body>

<div class="bill" id="printableBill">
    <div class="header">
        <h1><?php echo htmlspecialchars($shop_name); ?></h1>
        <?php if ($phone_display !== 'N/A'): ?>
            <p>Phone: <?php echo htmlspecialchars($phone_display); ?></p>
        <?php endif; ?>
        <p><?php echo htmlspecialchars($branch_display); ?></p>
    </div>

    <div class="info"><strong>Bill No:</strong> <?php echo $bill_number; ?></div>
    <div class="info"><strong>Date:</strong> <?php echo $bs_datetime; ?></div>
    <div class="info"><strong>Customer:</strong> <?php echo htmlspecialchars($customer_name); ?></div>

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
        <div class="total-row" style="font-weight:bold;">
            <span>Remaining Amount:</span>
            <span>Rs. <?php echo number_format($remaining, 2); ?></span>
        </div>
        <div class="total-row grand-total">
            <span>GRAND TOTAL:</span>
            <span>Rs. <?php echo number_format($total, 2); ?></span>
        </div>

        <!-- "All amounts cleared." – Same size & black color as other text – only when fully paid -->
        <?php if ($payment_success): ?>
        <div style="text-align:center; margin-top:15px; font-size:14px; color:#000;">
           ** All amounts cleared **
        </div>
        <?php endif; ?>
    </div>

    <div class="footer-note">Note: Exchange available within seven days</div>
    <div class="not-tax">This is not a tax bill</div>
</div>

<?php if (!$payment_success): ?>
<div class="no-print payment-box">
    <strong>Remaining to Pay: Rs. <?php echo number_format($remaining, 2); ?></strong>
    <?php if ($error): ?>
        <p style="color:red; font-weight:bold; margin:10px 0;"><?php echo $error; ?></p>
    <?php endif; ?>

    <form method="POST" id="paymentForm" class="form-group" style="display:inline-block;">
        <input type="number" name="final_payment" min="<?php echo max(1, $remaining - 10); ?>" 
               placeholder="Amount received" value="<?php echo $remaining; ?>" required>
        <select name="payment_method">
            <option value="cash">Cash</option>
            <option value="online">Online</option>
        </select>
        <br><br>
        <button type="button" class="btn-pay" onclick="showConfirmModal()">
            Complete Payment
        </button>
        <input type="hidden" name="finalize_payment" value="1">
    </form>

    <button type="button" class="btn-print" onclick="window.print();">
        Print Bill
    </button>
</div>
<?php else: ?>
<div class="no-print payment-box">
    <p style="color:green; font-size:20px; font-weight:bold;">Payment completed successfully!</p>
    <button type="button" class="btn-print" onclick="window.print();">
        Print Final Bill
    </button>
</div>
<?php endif; ?>

<div class="no-print" style="text-align:center; margin:30px;">
    <a href="advance_payment.php" class="back-link">Go Back</a>
</div>

<!-- Confirmation Modal -->
<div class="overlay" id="confirmModal">
    <div class="confirm-box">
        <h3>Confirm Payment</h3>
        <p>Are you sure you want to complete this payment?</p>
        <p><strong>Amount:</strong> Rs. <span id="confirmAmount"><?php echo number_format($remaining, 2); ?></span></p>
        <div class="btn-group">
            <button class="btn-yes" onclick="document.getElementById('paymentForm').submit()">Yes</button>
            <button class="btn-no" onclick="document.getElementById('confirmModal').style.display='none'">No</button>
        </div>
    </div>
</div>

<!-- Success Modal (only after payment) -->
<?php if ($payment_success): ?>
<div class="overlay" id="successModal" style="display:flex;">
    <div class="success-box">
        <h3>Success!</h3>
        <p>Payment Completed Successfully</p>
        <div class="btn-group">
            <button class="btn-print" onclick="window.print()">
                Print Final Bill
            </button>
            <button class="btn-yes" onclick="document.getElementById('successModal').style.display='none'" style="background:#34495e;">
                Close
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function showConfirmModal() {
    const amount = document.querySelector('input[name="final_payment"]').value;
    document.getElementById('confirmAmount').textContent = parseFloat(amount).toLocaleString('en-NP', {minimumFractionDigits: 2});
    document.getElementById('confirmModal').style.display = 'flex';
}

// Close modals when clicking outside
document.getElementById('confirmModal')?.addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
document.getElementById('successModal')?.addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

</body>
</html>