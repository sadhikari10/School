<?php
// bill.php - ONLY ADDED PRINT BUTTON + RENAMED EXECUTE → SAVE BILL
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
$outlet_id = $_SESSION['outlet_id'] ?? 0;

$stmt_user = $pdo->prepare("SELECT shop_name FROM login WHERE id = :id");
$stmt_user->execute([':id' => $user_id]);
$shop_name = $stmt_user->fetchColumn() ?: 'Clothes Store';

$stmt_outlet = $pdo->prepare("SELECT phone_number, location FROM outlets WHERE outlet_id = :oid LIMIT 1");
$stmt_outlet->execute([':oid' => $outlet_id]);
$outlet = $stmt_outlet->fetch(PDO::FETCH_ASSOC);
$phone_number = $outlet['phone_number'] ?? 'N/A';
$location     = $outlet['location'] ?? 'Unknown Branch';
$printed_by   = $username;

$print_time_db = nepali_date_time();
$bs_parts = explode(' ', $print_time_db);
$bs_date = $bs_parts[0];
$fiscal_year = get_fiscal_year($bs_date);

function getNextBillNumber($pdo, $branch, $fiscal_year) {
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(bill_number), 0) + 1 as next_bill 
                            FROM (
                                SELECT bill_number FROM sales WHERE branch = ? AND fiscal_year = ?
                                UNION ALL
                                SELECT bill_number FROM advance_payment WHERE branch = ? AND fiscal_year = ?
                            ) combined");
    $stmt->execute([$branch, $fiscal_year, $branch, $fiscal_year]);
    return (int)$stmt->fetchColumn();
}

$bill_number = getNextBillNumber($pdo, $location, $fiscal_year);

$detailed_items = [];
$subtotal = 0.0;
$customer_name = '';
$items_json = '[]';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_mark_paid']) && !isset($_POST['ajax_save_advance']) && !empty($_POST['order'])) {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $order_items = $_POST['order'];
    $items_processed = [];

    foreach ($order_items as $item) {
        $item_name = $item['item_name'] ?? 'Unknown';
        $size = $item['size'] ?? '';
        $brand = $item['brand'] ?? '';
        $price = (float)($item['price'] ?? 0);
        $qty = (int)($item['quantity'] ?? 1);
        if ($price <= 0) $price = 1500;

        $amount = $price * $qty;
        $display_name = $item_name . ($brand && strtolower($brand) !== 'nepali' ? " - $brand" : '');
        $display_size = empty($size) || stripos($size, 'not available') !== false ? 'N/A' : $size;

        $detailed_items[] = ['name' => $display_name, 'size' => $display_size, 'qty' => $qty, 'price' => $price, 'amount' => $amount];
        $subtotal += $amount;
        $items_processed[] = ['name' => $display_name, 'size' => $display_size, 'price' => $price, 'quantity' => $qty];
    }

    $items_json = json_encode($items_processed, JSON_UNESCAPED_UNICODE);

    $_SESSION['temp_bill_items'] = $detailed_items;
    $_SESSION['temp_subtotal'] = $subtotal;
    $_SESSION['temp_customer_name'] = $customer_name;
    $_SESSION['temp_items_json'] = $items_json;
    $_SESSION['temp_school_name'] = $_POST['school_name'] ?? $_SESSION['selected_school_name'] ?? '';
} else {
    $detailed_items = $_SESSION['temp_bill_items'] ?? [];
    $subtotal       = $_SESSION['temp_subtotal'] ?? 0.0;
    $customer_name  = $_SESSION['temp_customer_name'] ?? '';
    $items_json     = $_SESSION['temp_items_json'] ?? '[]';
}

$printed_date_display = nepali_date_time();

// Check bill status
$is_advance_saved = false;
$is_paid_saved = false;

$stmt = $pdo->prepare("SELECT 'advance' as type FROM advance_payment WHERE bill_number = ? AND branch = ? AND fiscal_year = ? 
                       UNION ALL 
                       SELECT 'paid' as type FROM sales WHERE bill_number = ? AND branch = ? AND fiscal_year = ?");
$stmt->execute([$bill_number, $location, $fiscal_year, $bill_number, $location, $fiscal_year]);

while ($row = $stmt->fetch()) {
    if ($row['type'] === 'advance') $is_advance_saved = true;
    if ($row['type'] === 'paid') $is_paid_saved = true;
}

// AJAX: Save as ADVANCE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_advance'])) {
    if ($is_paid_saved) {
        echo json_encode(['success' => false, 'error' => 'Bill already marked as PAID!']);
        exit;
    }

    $customer_name = trim($_POST['customer_name'] ?? '');
    $advance_amount = (float)($_POST['advance_payment'] ?? 0);
    $payment_method = $_POST['payment_method'] === 'online' ? 'online' : 'cash';
    $school_name = $_SESSION['temp_school_name'] ?? '';

    if ($advance_amount <= 0 || empty($detailed_items)) {
        echo json_encode(['success' => false, 'error' => 'Invalid advance amount']);
        exit;
    }

    $pdo->prepare("INSERT INTO advance_payment 
    (bill_number, branch, fiscal_year, school_name, customer_name, advance_amount, total, payment_method, printed_by, bs_datetime, items_json)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
    ->execute([$bill_number, $location, $fiscal_year, $school_name, $customer_name, $advance_amount, $subtotal, $payment_method, $printed_by, $print_time_db, $items_json]);
    echo json_encode(['success' => true, 'bill_number' => $bill_number]);
    exit;
}

// AJAX: Mark as PAID
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_mark_paid'])) {
    if ($is_advance_saved) {
        echo json_encode(['success' => false, 'error' => 'Bill already saved as ADVANCE!']);
        exit;
    }

    $customer_name = trim($_POST['customer_name'] ?? '');
    $payment_method = $_POST['payment_method'] === 'online' ? 'online' : 'cash';
    $school_name = $_SESSION['temp_school_name'] ?? '';

    if (empty($detailed_items)) {
        echo json_encode(['success' => false, 'error' => 'No items']);
        exit;
    }

    $pdo->prepare("INSERT INTO sales 
        (bill_number, branch, fiscal_year, school_name, customer_name, total, payment_method, printed_by, printed_at, bs_datetime, items_json)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)")
        ->execute([$bill_number, $location, $fiscal_year, $school_name, $customer_name, $subtotal, $payment_method, $printed_by, $print_time_db, $items_json]);

    unset($_SESSION['temp_bill_items'], $_SESSION['temp_subtotal'], $_SESSION['temp_customer_name'], $_SESSION['temp_items_json'], $_SESSION['temp_school_name']);

    echo json_encode(['success' => true, 'bill_number' => $bill_number]);
    exit;
}

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
    <link rel="stylesheet" href="bill.css">
</head>
<body>

<!-- PRINTABLE BILL (unchanged) -->
<div class="bill" id="printableBill">
    <div class="header">
        <h1><?php echo htmlspecialchars($shop_name); ?></h1>
        <p>Phone: <?php echo htmlspecialchars($phone_number); ?></p>
        <p><?php echo htmlspecialchars($location); ?></p>
    </div>
    <div class="info"><strong>Bill No:</strong> <?php echo $bill_number; ?></div>
    <div class="info"><strong>Date:</strong> <?php echo $printed_date_display; ?></div>
    <div class="info"><strong>Customer:</strong> <span id="customerDisplay"><?php echo htmlspecialchars($customer_name ?: 'Customer'); ?></span></div>
    <!-- <?php if (!empty($_SESSION['temp_school_name'])): ?>
        <div class="info"><strong>School:</strong> <?php echo htmlspecialchars($_SESSION['temp_school_name']); ?></div>
    <?php endif; ?> -->

    <table>
        <thead><tr><th>S.N</th><th>Item</th><th>Size</th><th>Qty</th><th>Amount</th></tr></thead>
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
        </tbody>
    </table>

    <div class="total-section">
        <div class="total-row"><span>Sub Total:</span><span>Rs. <?php echo number_format($subtotal, 2); ?></span></div>
        <div class="total-row"><span>Advance Paid:</span><span id="advanceDisplay">Rs. 0.00</span></div>
        <div class="total-row"><span>Remaining:</span><span id="remainingDisplay">Rs. <?php echo number_format($subtotal, 2); ?></span></div>
        <div class="total-row grand-total"><span>GRAND TOTAL:</span><span>Rs. <?php echo number_format($subtotal, 2); ?></span></div>
    </div>

    <div class="footer-note">Note: Exchange available within seven days</div>
    <div class="not-tax">THIS IS NOT A TAX BILL</div>
</div>

<!-- NON-PRINTABLE CLEAN UI -->
<div class="no-print controls">
    <!-- Line 1 -->
    <div class="control-row">
        <label>Customer Name: <span style="color:red;">*</span></label>
        <input type="text" id="customerName" value="<?php echo htmlspecialchars($customer_name); ?>" placeholder="Enter customer name" required>
    </div>

    <!-- Line 2 -->
    <div class="control-row">
        <label>Payment Method:</label>
        <select id="paymentMethod">
            <option value="cash">Cash</option>
            <option value="online">Online</option>
        </select>

        <label>Advance Amount:</label>
        <input type="number" id="advanceInput" value="0" min="0" step="1">
    </div>

    <!-- Action Buttons Line -->
    <?php if ($subtotal > 0 && !$is_advance_saved && !$is_paid_saved): ?>
    <div class="control-row buttons-row">
        <select id="billAction">
            <option value="">-- Select Action --</option>
            <option value="advance">Save as Advance</option>
            <option value="paid">Mark as Paid & Print</option>
        </select>

        <button id="saveBillBtn">Save Bill</button>   <!-- RENAMED -->
        <button id="printBtn">Print</button>         <!-- NEW PRINT BUTTON -->
        <button id="showQrBtn">Show QR</button>
        <a href="select_items.php">Add More Items</a>
        <a href="?clear_dashboard=1">New Bill</a>
    </div>
    <?php elseif ($subtotal > 0): ?>
    <div class="control-row" style="text-align:center; color:green; font-weight:bold; font-size:18px;">
        <?php echo $is_advance_saved ? "Advance Already Saved" : "Bill Already Marked as PAID"; ?>
        (Bill #<?php echo $bill_number; ?>)
    </div>
    <?php endif; ?>
</div>

<!-- QR Popup -->
<div class="qr-popup" id="qrPopup">
    <span class="close-qr" onclick="document.getElementById('qrPopup').style.display='none'">×</span>
    <img src="../QR/1.jpeg" alt="Payment QR">
</div>

<!-- Alert -->
<div class="overlay" id="alertOverlay"></div>
<div class="custom-alert" id="alertBox">
    <h3 id="alertMessage">Success!</h3>
    <button onclick="closeAlert()">OK</button>
</div>

<!-- ALL CSS + JS -->
<style>
    @media print { .no-print { display: none !important; } }
    .controls { margin:20px 0; padding:15px; background:#f8f9fa; border-radius:8px; font-family:Arial,sans-serif; }
    .control-row { display:flex; flex-wrap:wrap; gap:15px; align-items:center; margin-bottom:15px; }
    .control-row label { width:140px; font-weight:bold; color:#2c3e50; }
    .control-row input, .control-row select { padding:10px; font-size:16px; border:1px solid #ddd; border-radius:5px; flex:1; min-width:200px; }
    .buttons-row { justify-content:center; gap:12px; }
    .buttons-row select, .buttons-row button, .buttons-row a {
        padding:12px 22px; font-size:16px; border:none; border-radius:5px; cursor:pointer; text-decoration:none; color:white;
    }
    #billAction { background:#34495e; }
    #saveBillBtn { background:#27ae60; }
    #printBtn { background:#8e44ad; }
    #showQrBtn { background:#3498db; }
    .buttons-row a:first-of-type { background:#2980b9; }
    .buttons-row a:last-of-type { background:#e67e22; }
    .buttons-row button:hover, .buttons-row a:hover { opacity:0.9; }
    .qr-popup { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.95); justify-content:center; align-items:center; z-index:9999; }
    .qr-popup img { max-width:90%; max-height:90%; border:10px solid white; border-radius:15px; }
    .close-qr { position:absolute; top:20px; right:30px; font-size:50px; color:white; cursor:pointer; }
    .overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9998; }
    .custom-alert { display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); background:white; padding:30px; border-radius:10px; text-align:center; z-index:9999; box-shadow:0 5px 20px rgba(0,0,0,0.3); }
    .custom-alert button { margin-top:15px; padding:10px 25px; background:#27ae60; color:white; border:none; border-radius:5px; cursor:pointer; }
</style>

<script>
const totalAmount = <?php echo $subtotal; ?>;

function updateDisplay() {
    const advance = parseFloat(document.getElementById('advanceInput').value) || 0;
    document.getElementById('advanceDisplay').textContent = 'Rs. ' + advance.toFixed(2);
    document.getElementById('remainingDisplay').textContent = 'Rs. ' + (totalAmount - advance).toFixed(2);
    document.getElementById('customerDisplay').textContent = document.getElementById('customerName').value || 'Customer';
}
document.getElementById('advanceInput').addEventListener('input', updateDisplay);
document.getElementById('customerName').addEventListener('input', updateDisplay);

document.getElementById('saveBillBtn')?.addEventListener('click', function() {
    const action = document.getElementById('billAction').value;
    const customerName = document.getElementById('customerName').value.trim();

    if (!customerName) {
        showAlert('Customer name is required!');
        document.getElementById('customerName').focus();
        return;
    }

    if (!action) {
        showAlert('Please select an action');
        return;
    }

    const advance = parseFloat(document.getElementById('advanceInput').value) || 0;
    if (action === 'advance' && advance <= 0) {
        showAlert('Enter advance amount');
        return;
    }

    this.disabled = true;
    this.textContent = 'Saving...';

    const data = new URLSearchParams({
        customer_name: customerName,
        payment_method: document.getElementById('paymentMethod').value
    });

    if (action === 'advance') {
        data.append('ajax_save_advance', '1');
        data.append('advance_payment', advance);
    } else {
        data.append('ajax_mark_paid', '1');
        data.append('advance_payment', advance);
    }

    fetch('', { method: 'POST', body: data })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showAlert(action === 'advance' ? 'Advance saved!' : 'Bill saved & ready to print!');
            setTimeout(() => action === 'paid' ? window.print() : location.reload(), 1200);
        } else {
            showAlert(d.error || 'Failed to save');
            this.disabled = false;
            this.textContent = 'Save Bill';
        }
    })
    .catch(() => {
        showAlert('Network error');
        this.disabled = false;
        this.textContent = 'Save Bill';
    });
});

// PRINT BUTTON (new)
document.getElementById('printBtn')?.addEventListener('click', () => window.print());

// Show QR
document.getElementById('showQrBtn')?.addEventListener('click', () => {
    document.getElementById('qrPopup').style.display = 'flex';
});

function showAlert(msg) {
    document.getElementById('alertMessage').textContent = msg;
    document.getElementById('alertOverlay').style.display = 'block';
    document.getElementById('alertBox').style.display = 'block';
}
function closeAlert() {
    document.getElementById('alertOverlay').style.display = 'none';
    document.getElementById('alertBox').style.display = 'none';
}

updateDisplay();
</script>
</body>
</html>