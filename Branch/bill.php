<?php
// bill.php - UPDATED FOR NEW order[] FORMAT
session_start();
require '../Common/connection.php';
require '../Common/nepali_date.php';

date_default_timezone_set('Asia/Kathmandu');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';
$branch = $_SESSION['branch'] ?? '';
$outlet_id = $_SESSION['outlet_id'] ?? 0;

// Fetch shop details
$stmt_user = $pdo->prepare("SELECT shop_name FROM login WHERE id = :id");
$stmt_user->execute([':id' => $user_id]);
$shop_name = $stmt_user->fetchColumn() ?: 'Clothes Store';

$stmt_outlet = $pdo->prepare("SELECT phone_number, location FROM outlets WHERE outlet_id = :oid LIMIT 1");
$stmt_outlet->execute([':oid' => $outlet_id]);
$outlet = $stmt_outlet->fetch(PDO::FETCH_ASSOC);
$phone_number = $outlet['phone_number'] ?? 'N/A';
$location     = $outlet['location'] ?? 'N/A';
$printed_by   = $username;

// Bill number logic
$print_time_db = nepali_date_time();
$bs_parts = explode(' ', $print_time_db);
$bs_date = $bs_parts[0];
$fiscal_year = get_fiscal_year($bs_date);

$stmt_next = $pdo->prepare("SELECT COALESCE(last_bill_number, 0) + 1 as next_bill FROM bill_counter WHERE branch = :branch AND fiscal_year = :fy");
$stmt_next->execute([':branch' => $branch, ':fy' => $fiscal_year]);
$bill_number = (int)($stmt_next->fetchColumn() ?: 1);

$detailed_items = [];
$subtotal = 0.0;
$customer_name = '';
$items_json = '[]';

// ================================================
// NEW: Process clean order[] array from select_items.php
// ================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_mark_paid']) && !empty($_POST['order'])) {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $order_items = $_POST['order'];

    $items_processed = [];

    foreach ($order_items as $item) {
        $item_name = $item['item_name'] ?? 'Unknown Item';
        $size      = $item['size'] ?? '';
        $brand     = $item['brand'] ?? '';
        $price     = (float)($item['price'] ?? 0);
        $qty       = (int)($item['quantity'] ?? 1);

        if ($price <= 0) $price = 1500.00;
        $amount = $price * $qty;

        // Format display name
        $display_name = $item_name;
        if ($brand && strtolower($brand) !== 'nepali' && !empty(trim($brand))) {
            $display_name .= " - " . trim($brand);
        }

        // Format size
        $display_size = (empty($size) || stripos($size, 'not available') !== false) ? 'N/A' : $size;

        $detailed_items[] = [
            'name'   => $display_name,
            'size'   => $display_size,
            'qty'    => $qty,
            'price'  => $price,
            'amount' => $amount
        ];

        $subtotal += $amount;

        $items_processed[] = [
            'name'     => $display_name,
            'size'     => $display_size,
            'price'    => $price,
            'quantity' => $qty
        ];
    }

    $items_json = json_encode($items_processed, JSON_UNESCAPED_UNICODE);

    // Store in session for print/reprint
    $_SESSION['temp_bill_items'] = $detailed_items;
    $_SESSION['temp_subtotal'] = $subtotal;
    $_SESSION['temp_customer_name'] = $customer_name;
    $_SESSION['temp_items_json'] = $items_json;
    $_SESSION['temp_school_name'] = $_POST['school_name'] ?? $_SESSION['selected_school_name'] ?? '';
} else {
    // Load from session (for reprint or back button)
    $detailed_items = $_SESSION['temp_bill_items'] ?? [];
    $subtotal       = $_SESSION['temp_subtotal'] ?? 0.0;
    $customer_name  = $_SESSION['temp_customer_name'] ?? '';
    $items_json     = $_SESSION['temp_items_json'] ?? '[]';
}

$printed_date_display = nepali_date_time();

// ================================================
// AJAX: Save bill to database
// ================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_mark_paid'])) {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $advance_payment = (float)($_POST['advance_payment'] ?? 0);
    $payment_method = $_POST['payment_method'] === 'online' ? 'online' : 'cash';
    $school_name = $_SESSION['temp_school_name'] ?? '';

    if (empty($detailed_items)) {
        echo json_encode(['success' => false, 'error' => 'No items']);
        exit;
    }

    // Update bill counter
    $pdo->prepare("INSERT INTO bill_counter (branch, fiscal_year, last_bill_number) VALUES (?, ?, ?) 
                   ON DUPLICATE KEY UPDATE last_bill_number = VALUES(last_bill_number)")
        ->execute([$branch, $fiscal_year, $bill_number]);

    // Insert sale record
    $pdo->prepare("INSERT INTO sales 
        (bill_number, branch, fiscal_year, school_name, customer_name, total, payment_method, printed_by, printed_at, bs_datetime, items_json)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)")
        ->execute([$bill_number, $branch, $fiscal_year, $school_name, $customer_name, $subtotal, $payment_method, $printed_by, $print_time_db, $items_json]);

    // Clear temp session
    unset($_SESSION['temp_bill_items'], $_SESSION['temp_subtotal'], $_SESSION['temp_customer_name'], $_SESSION['temp_items_json'], $_SESSION['temp_school_name']);

    echo json_encode([
        'success' => true,
        'bill_number' => $bill_number,
        'customer' => $customer_name ?: 'Customer',
        'advance' => $advance_payment,
        'remaining' => $subtotal - $advance_payment
    ]);
    exit;
}

// Clear session for new bill
if (isset($_GET['clear_dashboard'])) {
    unset($_SESSION['temp_bill_items'], $_SESSION['temp_subtotal'], $_SESSION['temp_customer_name'], $_SESSION['temp_items_json'], $_SESSION['temp_school_name']);
    header('Location: dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bill #<?php echo $bill_number; ?></title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Courier New', monospace; font-size:12px; line-height:1.3; padding:10px; background:white; }
        .bill { max-width:80mm; margin:auto; border:2px dashed #000; padding:15px; }
        .header { text-align:center; border-bottom:2px dashed #000; padding-bottom:10px; margin-bottom:15px; }
        .header h1 { font-size:18px; font-weight:bold; margin-bottom:5px; }
        .header p { font-size:11px; margin:2px 0; }
        .info { font-size:11px; margin:8px 0; }
        .info strong { display:inline-block; width:80px; }
        table { width:100%; border-collapse:collapse; margin:15px 0; }
        th, td { padding:5px 3px; font-size:11px; }
        th { border-bottom:2px solid #000; text-align:center; font-weight:bold; }
        td:nth-child(1) { width:10%; text-align:center; }
        td:nth-child(2) { width:40%; }
        td:nth-child(3) { width:20%; text-align:center; }
        td:nth-child(4) { width:10%; text-align:center; }
        td:nth-child(5) { width:20%; text-align:right; }
        .total-section { margin-top:10px; font-size:12px; text-align:right; }
        .total-row { display:flex; justify-content:space-between; font-weight:bold; padding:6px 0; border-top:1px dotted #000; max-width:280px; margin-left:auto; }
        .grand-total { font-size:14px!important; font-weight:bold; border-top:2px double #000; padding-top:8px; }
        .footer-note { text-align:center; margin-top:15px; font-size:11px; font-weight:bold; }
        .not-tax { text-align:center; margin-top:20px; font-weight:bold; font-size:13px; padding:10px; border:2px dashed #000; }
        .no-print { margin-top:25px; text-align:center; }
        input, select { width:90%; max-width:280px; padding:10px; margin:8px 0; font-size:14px; border:1px solid #000; }
        .btn { padding:12px 20px; margin:8px; border:none; color:white; border-radius:5px; cursor:pointer; font-size:14px; }
        .btn-success { background:#27ae60; }
        .btn-primary { background:#2980b9; }
        .btn-warning { background:#e67e22; }
        @media print { .no-print { display:none !important; } }
        @page { margin:5mm; }
    </style>
</head>
<body>

<div class="bill" id="printableBill">
    <div class="header">
        <h1><?php echo htmlspecialchars($shop_name); ?></h1>
        <p>Phone: <?php echo htmlspecialchars($phone_number); ?></p>
        <p><?php echo htmlspecialchars($location); ?></p>
    </div>

    <div class="info"><strong>Bill No:</strong> <?php echo $bill_number; ?></div>
    <div class="info"><strong>Date:</strong> <?php echo $printed_date_display; ?></div>
    <div class="info"><strong>Customer:</strong> <span id="customerDisplay"><?php echo htmlspecialchars($customer_name ?: 'Walking Customer'); ?></span></div>
    <?php if (!empty($_SESSION['temp_school_name'])): ?>
        <div class="info"><strong>School:</strong> <?php echo htmlspecialchars($_SESSION['temp_school_name']); ?></div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>S.N</th>
                <th>Item</th>
                <th>Size</th>
                <th>Qty</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($detailed_items as $i => $item): ?>
            <tr>
                <td><?php echo $i + 1; ?>.</td>
                <td><?php echo htmlspecialchars($item['name']); ?></td>
                <td style="text-align:center;"><?php echo htmlspecialchars($item['size']); ?></td>
                <td style="text-align:center;"><?php echo $item['qty']; ?></td>
                <td style="text-align:right;"><?php echo number_format($item['amount'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($detailed_items)): ?>
            <tr><td colspan="5" style="text-align:center;padding:20px;">No items selected</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="total-section">
        <div class="total-row">
            <span>Sub Total:</span>
            <span>Rs. <?php echo number_format($subtotal, 2); ?></span>
        </div>
        <div class="total-row">
            <span>Advance Paid:</span>
            <span id="advanceDisplay">Rs. 0.00</span>
        </div>
        <div class="total-row">
            <span>Remaining:</span>
            <span id="remainingDisplay">Rs. <?php echo number_format($subtotal, 2); ?></span>
        </div>
        <div class="total-row grand-total">
            <span>GRAND TOTAL:</span>
            <span id="grandTotalDisplay">Rs. <?php echo number_format($subtotal, 2); ?></span>
        </div>
    </div>

    <div class="footer-note">
        Note: Exchange available within seven days
    </div>

    <div class="not-tax">
        THIS IS NOT A TAX BILL
    </div>
</div>

<div class="no-print">
    <input type="text" id="customerName" placeholder="Customer Name (optional)" value="<?php echo htmlspecialchars($customer_name); ?>">
    <input type="number" id="advanceInput" placeholder="Advance / Paid Amount" step="1" min="0" value="0">
    <select id="paymentMethod">
        <option value="cash">Cash</option>
        <option value="online">Online / UPI</option>
    </select>

    <?php if ($subtotal > 0): ?>
        <button id="savePrintBtn" class="btn btn-success">Mark as Paid & Print</button>
    <?php else: ?>
        <div style="color:red;font-weight:bold;">No items in bill!</div>
    <?php endif; ?>

    <div style="margin-top:20px;">
        <button onclick="window.print()" class="btn btn-primary">Print Only</button>
        <a href="select_items.php" class="btn btn-primary">Add More Items</a>
        <a href="?clear_dashboard=1" class="btn btn-warning">New Bill</a>
    </div>
</div>

<script>
const totalAmount = <?php echo $subtotal; ?>;

function updateAmounts() {
    const advance = parseFloat(document.getElementById('advanceInput').value) || 0;
    const remaining = totalAmount - advance;
    document.getElementById('advanceDisplay').textContent = 'Rs. ' + advance.toFixed(2);
    document.getElementById('remainingDisplay').textContent = 'Rs. ' + (remaining > 0 ? remaining.toFixed(2) : '0.00');
    document.getElementById('grandTotalDisplay').textContent = 'Rs. ' + totalAmount.toFixed(2);
}

document.getElementById('advanceInput').addEventListener('input', updateAmounts);
document.getElementById('customerName').addEventListener('input', () => {
    document.getElementById('customerDisplay').textContent = document.getElementById('customerName').value || 'Walking Customer';
});

document.getElementById('savePrintBtn')?.addEventListener('click', function() {
    const customer = document.getElementById('customerName').value.trim();
    const advance = parseFloat(document.getElementById('advanceInput').value) || 0;
    const method = document.getElementById('paymentMethod').value;

    this.disabled = true;
    this.textContent = 'Saving...';

    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            ajax_mark_paid: '1',
            customer_name: customer,
            advance_payment: advance,
            payment_method: method
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            updateAmounts();
            alert('Bill #' + data.bill_number + ' saved successfully!');
            setTimeout(() => window.print(), 500);
        } else {
            alert('Error: ' + (data.error || 'Failed'));
            this.disabled = false;
            this.textContent = 'Mark as Paid & Print';
        }
    })
    .catch(() => {
        alert('Network error');
        this.disabled = false;
        this.textContent = 'Mark as Paid & Print';
    });
});

updateAmounts();
</script>

</body>
</html>