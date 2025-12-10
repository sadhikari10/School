<?php
// bill.php - ONLY BILL NUMBER LOGIC FIXED (everything else 100% original)
session_start();
ob_start();
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

// ————————————————————————————————————————————————
// FIXED BILL NUMBER LOGIC (counter increases ONLY on save)
// ————————————————————————————————————————————————

// Show next number (don't increment yet)
function getNextBillNumber($pdo, $outlet_id, $fiscal_year) {
    $stmt = $pdo->prepare("SELECT last_bill_number FROM bill_counter WHERE outlet_id = ? AND fiscal_year = ?");
    $stmt->execute([$outlet_id, $fiscal_year]);
    $row = $stmt->fetch();

    if ($row) {
        return $row['last_bill_number'] + 1;
    } else {
        $pdo->prepare("INSERT INTO bill_counter (outlet_id, fiscal_year, last_bill_number) VALUES (?, ?, 0)")->execute([$outlet_id, $fiscal_year]);
        return 1;
    }
}

// Increment only when saving (atomic + safe)
function incrementBillCounter($pdo, $outlet_id, $fiscal_year) {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT last_bill_number FROM bill_counter WHERE outlet_id = ? AND fiscal_year = ? FOR UPDATE");
        $stmt->execute([$outlet_id, $fiscal_year]);
        $row = $stmt->fetch();

        $next = $row ? $row['last_bill_number'] + 1 : 1;

        if ($row) {
            $pdo->prepare("UPDATE bill_counter SET last_bill_number = last_bill_number + 1 WHERE outlet_id = ? AND fiscal_year = ?")
                ->execute([$outlet_id, $fiscal_year]);
        } else {
            $pdo->prepare("INSERT INTO bill_counter (outlet_id, fiscal_year, last_bill_number) VALUES (?, ?, ?)")
                ->execute([$outlet_id, $fiscal_year, $next]);
        }
        $pdo->commit();
        return $next;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}


// Use saved number if exists, otherwise show next available
if (isset($_SESSION['current_bill_number'])) {
    $bill_number = $_SESSION['current_bill_number'];
} else {
    $bill_number = getNextBillNumber($pdo, $outlet_id, $fiscal_year);
    $_SESSION['current_bill_number'] = $bill_number;
}

// ————————————————————————————————————————————————
// YOUR ORIGINAL CODE FROM HERE (unchanged)
// ————————————————————————————————————————————————

$detailed_items = [];
$subtotal = 0.0;
$customer_name = '';
$items_json = '[]';

// ———————————————————————————————————————
// 1. LOAD REGULAR ITEMS FROM POST (if coming from select_items.php)
// ———————————————————————————————————————
$regular_items = [];  // Will hold processed regular items
$custom_items  = [];  // Will hold processed measurement items

if ($_SERVER['REQUEST_METHOD'] === 'POST' 
    && !isset($_POST['ajax_mark_paid']) 
    && !isset($_POST['ajax_save_advance']) 
    && !empty($_POST['order'])) {

    $customer_name = trim($_POST['customer_name'] ?? '');
    $_SESSION['temp_school_name'] = $_POST['school_name'] ?? $_SESSION['selected_school_name'] ?? '';

    foreach ($_POST['order'] as $item) {
        $item_name = $item['item_name'] ?? 'Unknown';
        $size      = $item['size'] ?? '';
        $brand     = $item['brand'] ?? '';
        $price     = (float)($item['price'] ?? 0);
        $qty       = (int)($item['quantity'] ?? 1);
        if ($price <= 0) $price = 1500;

        $amount       = $price * $qty;
        $display_name = $item_name . ($brand && strtolower($brand) !== 'nepali' ? " - $brand" : '');
        $display_size = (empty($size) || stripos($size, 'not available') !== false) ? 'N/A' : $size;

        $regular_items[] = [
            'name'     => $display_name,
            'size'     => $display_size,
            'qty'      => $qty,
            'price'    => $price,
            'amount'   => $amount
        ];
    }

    // Save to session immediately
    $_SESSION['temp_customer_name'] = $customer_name;
    $_SESSION['temp_regular_items'] = $regular_items;

} else {
    // Restore from session when page reloads or coming back
    $customer_name   = $_SESSION['temp_customer_name'] ?? '';
    $regular_items   = $_SESSION['temp_regular_items'] ?? [];
}

// ———————————————————————————————————————
// 2. ALWAYS ADD CUSTOM MEASUREMENT ITEMS (from MeasurementHelper)
// ———————————————————————————————————————
require_once 'MeasurementHelper.php';
$measHelper = new MeasurementHelper($pdo);

foreach ($measHelper->getForOrder() as $m) {
    $price   = (float)($m['price'] ?? 0);
    $qty     = (int)($m['quantity'] ?? 1);
    if ($price <= 0) $price = 1500;

    $amount       = $price * $qty;
    $clean_name = preg_replace('/\s*\(Custom Made\)$/i', '', $m['item_name']);                  // e.g. "Full Suit (Custom Made)"
    $display_size = 'Custom';              // shows measurements or "Custom"

    $custom_items[] = [
        'name'   => trim($clean_name),
        'size'   => $display_size,
        'qty'    => $qty,
        'price'  => $price,
        'amount' => $amount
    ];
}

// ———————————————————————————————————————
// 3. FINAL: COMBINE BOTH + CALCULATE SUBTOTAL + JSON
// ———————————————————————————————————————
$detailed_items = array_merge($regular_items, $custom_items);
$subtotal       = 0.0;
$items_processed = [];

foreach ($detailed_items as $item) {
    $subtotal += $item['amount'];
    $items_processed[] = [
        'name'     => $item['name'],
        'size'     => $item['size'],
        'price'    => $item['price'],
        'quantity' => $item['qty']
    ];
}

$items_json = json_encode($items_processed, JSON_UNESCAPED_UNICODE);

// Update session with final values (important for reloads)
$_SESSION['temp_bill_items'] = $detailed_items;
$_SESSION['temp_subtotal']   = $subtotal;
$_SESSION['temp_items_json'] = $items_json;

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
// ————————————————————————————————————————
// AJAX: Save as ADVANCE → Save measurements + school_id + outlet_id
// ————————————————————————————————————————
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_advance'])) {
    if ($is_paid_saved) {
        echo json_encode(['success' => false, 'error' => 'Bill already marked as PAID!']);
        exit;
    }

    $customer_name   = trim($_POST['customer_name'] ?? '');
    $advance_amount  = (float)($_POST['advance_payment'] ?? 0);
    $payment_method  = $_POST['payment_method'] === 'online' ? 'online' : 'cash';
    $school_name     = $_SESSION['temp_school_name'] ?? '';

    if ($advance_amount <= 0 || empty($detailed_items)) {
        echo json_encode(['success' => false, 'error' => 'Invalid advance amount']);
        exit;
    }

    // Increment bill number
    $final_bill_number = incrementBillCounter($pdo, $outlet_id, $fiscal_year);
    $bill_number = $final_bill_number;
    $_SESSION['current_bill_number'] = $bill_number;// ——————— SAVE CUSTOM MEASUREMENTS PROPERLY (USING IDs) ———————
require_once 'MeasurementHelper.php';
$measHelper  = new MeasurementHelper($pdo);
$customItems = $measHelper->getItems();

if (!empty($customItems)) {
    $measurements = [];
    $prices       = [];

    // First: Clear old entries for this bill (in case of update)
    $pdo->prepare("DELETE FROM custom_measurement_items WHERE bill_number = ? AND fiscal_year = ?")
        ->execute([$bill_number, $fiscal_year]);

    foreach ($customItems as $index => $item) {
        $itemIndex = $index + 1; // 1, 2, 3...

        // Clean name for display (same logic you already use)
        $rawName = $item['item_name'] ?? $item['name'] ?? 'Custom Item';
        $cleanName = trim(preg_replace('/\s*\(Custom Made\)$/i', '', $rawName));
        if ($cleanName === '') $cleanName = 'Custom Item';

        // Save item name + price + qty in the new table
        $pdo->prepare("
            INSERT INTO custom_measurement_items 
                (bill_number, fiscal_year, item_index, item_name, price, quantity)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            $bill_number,
            $fiscal_year,
            $itemIndex,
            $cleanName,
            (float)($item['price'] ?? 0),
            max(1, (int)($item['quantity'] ?? 1))
        ]);

        // Use numeric key in measurements & prices
        $key = (string)$itemIndex;  // "1", "2", "3"...

        $prices[$key] = (float)($item['price'] ?? 0) * max(1, (int)($item['quantity'] ?? 1));

        // Save measurements
        $rawMeas = $item['measurements'] ?? null;
        if (is_array($rawMeas)) {
            $measurements[$key] = $rawMeas;
        } elseif (is_string($rawMeas) && $rawMeas !== '') {
            $decoded = json_decode($rawMeas, true);
            $measurements[$key] = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
                ? $decoded : ['size' => 'Custom'];
        } else {
            $measurements[$key] = ['size' => 'Custom'];
        }
    }

    $measurements_json = json_encode($measurements, JSON_UNESCAPED_UNICODE);
    $prices_json       = json_encode($prices, JSON_UNESCAPED_UNICODE);

    // Save only the JSON (with numeric keys)
    $stmt = $pdo->prepare("
        INSERT INTO customer_measurements 
            (bill_number, fiscal_year, school_id, outlet_id, customer_name, measurements, prices, created_at, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ON DUPLICATE KEY UPDATE
            measurements = VALUES(measurements),
            prices = VALUES(prices),
            customer_name = VALUES(customer_name),
            school_id = VALUES(school_id),
            outlet_id = VALUES(outlet_id)
    ");

    $stmt->execute([
        $bill_number, $fiscal_year,
        $_SESSION['selected_school_id'] ?? null,
        $_SESSION['outlet_id'] ?? null,
        $customer_name, $measurements_json, $prices_json, $printed_by
    ]);
}
    // Save advance payment (unchanged)
    $pdo->prepare("INSERT INTO advance_payment 
        (bill_number, branch, outlet_id, fiscal_year, school_name, customer_name, advance_amount, total, payment_method, printed_by, bs_datetime, items_json, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'unpaid')")
        ->execute([
            $bill_number, $location, $outlet_id, $fiscal_year, $school_name,
            $customer_name, $advance_amount, $subtotal, $payment_method,
            $printed_by, $print_time_db, $items_json
        ]);

    ob_clean(); // REMOVE ANY PREVIOUS OUTPUT
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'bill_number' => $bill_number]);
    exit;

}

// ————————————————————————————————————————
// AJAX: Mark as PAID → Save measurements + school_id + outlet_id
// ————————————————————————————————————————
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_mark_paid'])) {
    if ($is_advance_saved) {
        echo json_encode(['success' => false, 'error' => 'Bill already saved as ADVANCE!']);
        exit;
    }

    $customer_name  = trim($_POST['customer_name'] ?? '');
    $payment_method = $_POST['payment_method'] === 'online' ? 'online' : 'cash';
    $school_name    = $_SESSION['temp_school_name'] ?? '';

    if (empty($detailed_items)) {
        echo json_encode(['success' => false, 'error' => 'No items']);
        exit;
    }

    // Increment bill number
    $final_bill_number = incrementBillCounter($pdo, $outlet_id, $fiscal_year);
    $bill_number = $final_bill_number;
    $_SESSION['current_bill_number'] = $bill_number;

    // ——————— SAVE CUSTOM MEASUREMENTS (with school_id & outlet_id) ———————
    require_once 'MeasurementHelper.php';
    $measHelper = new MeasurementHelper($pdo);
    $customItems = $measHelper->getItems();

    if (!empty($customItems)) {
        $measurements = [];
        $prices = [];

        foreach ($customItems as $item) {
            $clean_name = preg_replace('/\s*\(Custom Made\)$/i', '', $item['name']);
            $measurements[$clean_name] = $item['measurements'];
            $prices[$clean_name] = $item['price'] * $item['quantity'];
        }

        $measurements_json = json_encode($measurements, JSON_UNESCAPED_UNICODE);
        $prices_json       = json_encode($prices, JSON_UNESCAPED_UNICODE);

        $school_id = $_SESSION['selected_school_id'] ?? null;
        $outlet_id_current = $_SESSION['outlet_id'] ?? null;

        $stmt = $pdo->prepare("INSERT INTO customer_measurements 
            (bill_number, fiscal_year, school_id, outlet_id, customer_name, measurements, prices, created_at, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE
            measurements = VALUES(measurements),
            prices = VALUES(prices),
            customer_name = VALUES(customer_name),
            school_id = VALUES(school_id),
            outlet_id = VALUES(outlet_id),
            created_at = NOW(),
            created_by = VALUES(created_by)");

        $stmt->execute([
            $bill_number,
            $fiscal_year,
            $school_id,
            $outlet_id_current,
            $customer_name,
            $measurements_json,
            $prices_json,
            $printed_by
        ]);
    }

    // Save full sale
    $pdo->prepare("INSERT INTO sales 
        (bill_number, branch, outlet_id, fiscal_year, school_name, customer_name, total, payment_method, printed_by, printed_at, bs_datetime, items_json)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)")
        ->execute([
            $bill_number, $location, $outlet_id, $fiscal_year, $school_name,
            $customer_name, $subtotal, $payment_method, $printed_by,
            $print_time_db, $items_json
        ]);

    // Full cleanup
    unset($_SESSION['temp_bill_items'], $_SESSION['temp_subtotal'], $_SESSION['temp_customer_name'], 
          $_SESSION['temp_items_json'], $_SESSION['temp_school_name'], $_SESSION['current_bill_number']);
    $measHelper->clearAll();

    echo json_encode(['success' => true, 'bill_number' => $bill_number]);
    exit;
}

// Secure New Bill
if (isset($_POST['start_new_bill'])) {
    unset(
        $_SESSION['temp_bill_items'],
        $_SESSION['temp_subtotal'],
        $_SESSION['temp_customer_name'],
        $_SESSION['temp_items_json'],
        $_SESSION['temp_school_name'],
        $_SESSION['selected_sizes'],
        $_SESSION['current_bill_number'],
        $_SESSION['temp_regular_items']
    );

    require_once 'MeasurementHelper.php';
    $measHelper = new MeasurementHelper($pdo);
    $measHelper->clearAllForCurrentSession(
        $_SESSION['user_id'], 
        $_SESSION['outlet_id'], 
        $_SESSION['selected_school_id'] ?? null
    );

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
        <p>Phone: 01-<?php echo htmlspecialchars($phone_number); ?></p>
        <p><?php echo htmlspecialchars($location); ?></p>
    </div>
    <div class="info"><strong>Bill No:</strong> <?php echo $bill_number; ?></div>
    <div class="info"><strong>Date:</strong> <?php echo $printed_date_display; ?></div>
    <div class="info"><strong>Customer:</strong> <span id="customerDisplay"><?php echo htmlspecialchars($customer_name ?: 'Customer'); ?></span></div>
    <?php if (!empty($_SESSION['temp_school_name'])): ?>
        <div class="info"><strong>School:</strong> <?php echo htmlspecialchars($_SESSION['temp_school_name']); ?></div>
        <?php endif; ?>
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
            <option value="advance">Advance Payment</option>
            <option value="paid">Full Payment</option>
        </select>

        <button id="saveBillBtn">Save Bill</button>
        <button id="printBtn">Print</button>
        <button id="showQrBtn">Show QR</button>
        <a href="select_items.php">Add More Items</a>
        <form method="POST"  id="newBillForm" style="display:inline;">
            <input type="hidden" name="start_new_bill" value="1">
            <a href="javascript:void(0)" onclick="confirmNewBill()" class="new-bill-link">New Bill</a>
        </form>
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

<!-- ALL YOUR ORIGINAL CSS + JS (100% unchanged) -->
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

// Function to disable all bill actions (used on save + page load if already saved)
function disableBillActions() {
    const saveBtn = document.getElementById('saveBillBtn');
    const addMoreLink = document.querySelector('a[href="select_items.php"]');
    const actionSelect = document.getElementById('billAction');

    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saved';
        saveBtn.style.background = '#95a5a6';
        saveBtn.style.cursor = 'not-allowed';
    }

    if (addMoreLink) {
        addMoreLink.style.pointerEvents = 'none';
        addMoreLink.style.opacity = '0.5';
        addMoreLink.style.color = '#999';
        addMoreLink.style.textDecoration = 'none';
        addMoreLink.title = 'Cannot add items after bill is saved';
        addMoreLink.innerHTML = 'Add More Items (saved)';
    }

    if (actionSelect) {
        actionSelect.disabled = true;
    }
}

function updateDisplay() {
    const advance = parseFloat(document.getElementById('advanceInput').value) || 0;
    document.getElementById('advanceDisplay').textContent = 'Rs. ' + advance.toFixed(2);
    document.getElementById('remainingDisplay').textContent = 'Rs. ' + (totalAmount - advance).toFixed(2);
    document.getElementById('customerDisplay').textContent = document.getElementById('customerName').value.trim() || 'Customer';
}

// Live update when typing
document.getElementById('advanceInput')?.addEventListener('input', updateDisplay);
document.getElementById('customerName')?.addEventListener('input', updateDisplay);

// Save Bill Button Logic
document.getElementById('saveBillBtn')?.addEventListener('click', function() {
    if (this.disabled || this.textContent === 'Saved') {
        showAlert('Bill is already saved!');
        return;
    }

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
        showAlert('Advance Amount must be greater than 0!');
        document.getElementById('advanceInput').focus();
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
    }

    fetch('', { method: 'POST', body: data })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            disableBillActions(); // This disables Save + Add More Items + Dropdown

            if (action === 'advance') {
                showAlert('Advance Payment Saved Successfully!\nYou can now print the bill.');
            } else {
                showAlert('Full Payment Completed! Printing...');
                setTimeout(() => window.print(), 800);
            }
        } else {
            showAlert(d.error || 'Failed to save bill');
            this.disabled = false;
            this.textContent = 'Save Bill';
        }
    })
    .catch(() => {
        showAlert('Connection error. Please try again.');
        this.disabled = false;
        this.textContent = 'Save Bill';
    });
});

// Other buttons
document.getElementById('printBtn')?.addEventListener('click', () => window.print());
document.getElementById('showQrBtn')?.addEventListener('click', () => {
    document.getElementById('qrPopup').style.display = 'flex';
});

// Alert functions
function showAlert(msg) {
    document.getElementById('alertMessage').textContent = msg;
    document.getElementById('alertOverlay').style.display = 'block';
    document.getElementById('alertBox').style.display = 'block';
}

function closeAlert() {
    document.getElementById('alertOverlay').style.display = 'none';
    document.getElementById('alertBox').style.display = 'none';
}

// Confirm New Bill
function confirmNewBill() {
    document.getElementById('alertMessage').innerHTML = 
        'Are you sure you want to start a <strong>New Bill</strong>?<br><br>All current items will be cleared and you will go to dashboard.';

    document.getElementById('alertOverlay').style.display = 'block';
    document.getElementById('alertBox').style.display = 'block';

    const okBtn = document.querySelector('#alertBox button');
    okBtn.textContent = 'Yes, Start New Bill';
    okBtn.onclick = function() {
        closeAlert();
        document.getElementById('newBillForm').submit();
    };

    document.getElementById('alertOverlay').onclick = function() {
        closeAlert();
        document.querySelector('#alertBox button').textContent = 'OK';
        document.querySelector('#alertBox button').onclick = closeAlert;
    };
}

// Run on page load: if bill already saved → disable buttons immediately
<?php if ($is_advance_saved || $is_paid_saved): ?>
    document.addEventListener('DOMContentLoaded', disableBillActions);
<?php endif; ?>

// Initial display update
updateDisplay();
</script>

</body>
</html>