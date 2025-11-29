<?php
// bill.php – REAL BILL NUMBER + CLEAN FORMAT
session_start();
require '../Common/connection.php';
require '../Common/nepali_date.php';

date_default_timezone_set('Asia/Kathmandu');

function numberToWordsWithPaisa($amount) {
    $rupees = (int)$amount;
    $paisa = round(($amount - $rupees) * 100);
    $ones = ["", "One", "Two", "Three", "Four", "Five", "Six", "Seven", "Eight", "Nine", "Ten",
             "Eleven", "Twelve", "Thirteen", "Fourteen", "Fifteen", "Sixteen", "Seventeen", "Eighteen", "Nineteen"];
    $tens = ["", "", "Twenty", "Thirty", "Forty", "Fifty", "Sixty", "Seventy", "Eighty", "Ninety"];
    $words = "";
    if ($rupees == 0) $words = "Zero";
    else {
        $thousands = ["", "Thousand", "Million", "Billion"];
        $i = 0;
        do {
            $n = $rupees % 1000;
            if ($n != 0) $words = _convertHundreds($n, $ones, $tens) . " " . $thousands[$i] . " " . $words;
            $rupees = (int)($rupees / 1000);
            $i++;
        } while ($rupees > 0);
    }
    $words = ucfirst(trim($words));
    if ($paisa > 0) {
        $paisa_words = $paisa < 20 ? $ones[$paisa] : $tens[(int)($paisa / 10)] . ($paisa % 10 ? " " . $ones[$paisa % 10] : "");
        $words .= " Rupees and " . ucfirst($paisa_words) . " Paisa";
    } else {
        $words .= " Rupees";
    }
    return $words . " Only";
}

function _convertHundreds($n, $ones, $tens) {
    $str = "";
    if ($n > 99) $str .= $ones[(int)($n / 100)] . " Hundred ";
    $n %= 100;
    if ($n < 20) $str .= $ones[$n];
    else $str .= $tens[(int)($n / 10)] . ($n % 10 ? " " . $ones[$n % 10] : "");
    return trim($str);
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';
$branch = $_SESSION['branch'] ?? '';

$stmt_user = $pdo->prepare("SELECT shop_name, phone_number FROM login WHERE id = :id");
$stmt_user->execute([':id' => $user_id]);
$user = $stmt_user->fetch(PDO::FETCH_ASSOC) ?: ['shop_name' => 'Clothes Store', 'phone_number' => 'N/A'];
$shop_name = $user['shop_name'];
$phone_number = $user['phone_number'];
$printed_by = $username;

// ✅ GET REAL BILL NUMBER (before processing items)
$print_time_db = nepali_date_time();
$bs_parts = explode(' ', $print_time_db);
$bs_date = $bs_parts[0];
$fiscal_year = get_fiscal_year($bs_date);

$stmt_next = $pdo->prepare("
    SELECT COALESCE(last_bill_number, 0) + 1 as next_bill 
    FROM bill_counter 
    WHERE branch = :branch AND fiscal_year = :fy
");
$stmt_next->execute([':branch' => $branch, ':fy' => $fiscal_year]);
$next_res = $stmt_next->fetch(PDO::FETCH_ASSOC);
$bill_number = (int)($next_res['next_bill'] ?? 1);

$detailed_items = [];
$subtotal = 0.0;
$school_name = '';
$items_json = '[]';

// === PROCESS POST DATA ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_mark_paid'])) {
    $school_name = $_POST['school_name'] ?? '';
    $selected_sizes = $_POST['selected_sizes'] ?? [];
    
    error_log("Bill POST selected_sizes: " . print_r($selected_sizes, true));
    
    if (!empty($selected_sizes)) {
        $category_names = [
            'shirts' => 'Shirt',
            'pants' => 'Pant', 
            'skirts' => 'Skirt',
            'coats' => 'Coat',
            'tracksuits' => 'Tracksuit',
            'sweaters' => 'Sweater',
            'stockings' => 'Stocking',
            'shoes' => 'Shoe'
        ];
        
        $items_processed = [];
        foreach ($selected_sizes as $category_key => $selection) {
            if (empty($selection)) continue;
            
            $item_name = $category_names[$category_key] ?? ucfirst($category_key);
            $items = explode(',', $selection);
            
            foreach ($items as $item_index => $itemKey) {
                $itemKey = trim($itemKey);
                if (empty($itemKey)) continue;
                
                // Parse size|section
                $parts = explode('|', $itemKey);
                $size = $parts[0];
                $section = count($parts) > 1 ? $parts[1] : '';
                
                // ✅ SIMPLIFIED PRICING with error handling
                $price = 1500.00; // Default fallback
                
                try {
                    switch ($category_key) {
                        case 'shirts':
                            require_once 'shirt_selector.php';
                            $selector = new ShirtSelector($pdo, $school_name);
                            $sizes = $selector->getShirts();
                            break;
                        case 'pants':
                            require_once 'pant_selector.php';
                            $selector = new PantSelector($pdo, $school_name);
                            $sizes = $selector->getPants();
                            break;
                        case 'skirts':
                            require_once 'skirt_selector.php';
                            $selector = new SkirtSelector($pdo);
                            $sizes = $selector->getSkirts();
                            break;
                        case 'coats':
                            require_once 'coat_selector.php';
                            $selector = new CoatSelector($pdo);
                            $sizes = $selector->getCoats();
                            break;
                        case 'tracksuits':
                            require_once 'tracksuit_selector.php';
                            $selector = new TracksuitSelector($pdo);
                            $sizes = $selector->getTracksuits();
                            break;
                        case 'sweaters':
                            require_once 'sweater_selector.php';
                            $selector = new SweaterSelector($pdo, $school_name);
                            $sizes = $selector->getSweaters();
                            break;
                        case 'stockings':
                            require_once 'stocking_selector.php';
                            $selector = new StockingSelector($pdo);
                            $sizes = $selector->getStockings();
                            break;
                        case 'shoes':
                            require_once 'shoe_selector.php';
                            $selector = new ShoeSelector($pdo);
                            $sizes = $selector->getShoes();
                            break;
                        default:
                            $sizes = [];
                    }
                    
                    // ✅ SAFE ARRAY/OBJECT ACCESS
                    foreach ($sizes as $sizeData) {
                        $sizeDataSize = is_object($sizeData) ? ($sizeData->size ?? '') : ($sizeData['size'] ?? '');
                        $sizeDataSection = is_object($sizeData) ? ($sizeData->section ?? '') : ($sizeData['section'] ?? '');
                        $sizeDataPrice = is_object($sizeData) ? ($sizeData->display_price ?? $sizeData->price ?? 0) : ($sizeData['display_price'] ?? $sizeData['price'] ?? 0);
                        
                        $sizeKey = $sizeDataSection ? $sizeDataSize . '|' . $sizeDataSection : $sizeDataSize;
                        
                        if ($sizeKey === $itemKey) {
                            $price = (float)$sizeDataPrice;
                            break;
                        }
                    }
                } catch (Exception $e) {
                    error_log("Price lookup error for $itemKey: " . $e->getMessage());
                }
                
                $display_name = $item_name;
                if ($section) $display_name .= " - " . $section;
                $display_name .= " " . $size;
                
                $detailed_items[] = [
                    'name' => $item_name,
                    'size' => $size,
                    'section' => $section,
                    'price' => $price,
                    'display_name' => $display_name
                ];
                $subtotal += $price;
                $items_processed[] = [
                    'name' => $display_name,
                    'size' => $size,
                    'price' => $price,
                    'quantity' => 1
                ];
            }
        }
        $items_json = json_encode($items_processed);
    }
    
    $_SESSION['temp_bill_items'] = $detailed_items;
    $_SESSION['temp_subtotal'] = $subtotal;
    $_SESSION['temp_school_name'] = $school_name;
    $_SESSION['temp_items_json'] = $items_json;
    $_SESSION['selected_sizes'] = []; // Clear after processing
}
// === LOAD TEMP DATA ===
else {
    $detailed_items = $_SESSION['temp_bill_items'] ?? [];
    $subtotal = $_SESSION['temp_subtotal'] ?? 0.0;
    $school_name = $_SESSION['temp_school_name'] ?? '';
    $items_json = $_SESSION['temp_items_json'] ?? '[]';
}

$amount_in_words = numberToWordsWithPaisa($subtotal);
$printed_date_display = nepali_date_time();

// ✅ ALWAYS SHOW REAL BILL NUMBER
$bill_no_display = $bill_number;

// === AJAX: Mark as Paid ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_mark_paid'])) {
    $payment_method = in_array($_POST['payment_method'] ?? '', ['cash', 'online']) ? $_POST['payment_method'] : 'cash';

    if (empty($detailed_items)) {
        echo json_encode(['success' => false, 'error' => 'No items selected']);
        exit;
    }

    // ✅ Bill number already generated above - use it
    $stmt_ups = $pdo->prepare("
        INSERT INTO bill_counter (branch, fiscal_year, last_bill_number) 
        VALUES (:branch, :fy, :bill) 
        ON DUPLICATE KEY UPDATE last_bill_number = :bill
    ");
    $stmt_ups->execute([':branch' => $branch, ':fy' => $fiscal_year, ':bill' => $bill_number]);

    $stmt_sales = $pdo->prepare("
        INSERT INTO sales (bill_number, branch, school_name, items_json, total_amount, payment_method, printed_by, printed_at, customer_name) 
        VALUES (:bill, :branch, :school, :items, :total, :pm, :by, :at, '')
    ");
    $stmt_sales->execute([
        ':bill' => $bill_number, ':branch' => $branch, ':school' => $school_name,
        ':items' => $items_json, ':total' => $subtotal, ':pm' => $payment_method,
        ':by' => $printed_by, ':at' => $print_time_db
    ]);

    unset($_SESSION['temp_bill_items'], $_SESSION['temp_subtotal'], $_SESSION['temp_school_name'], $_SESSION['temp_items_json']);

    $bill_details = [
        'bill_number' => $bill_number,
        'printed_date_display' => $print_time_db,
        'amount_in_words' => numberToWordsWithPaisa($subtotal),
        'printed_by' => $printed_by,
        'payment_method' => $payment_method
    ];

    echo json_encode(['success' => true, 'bill_details' => $bill_details]);
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bill #<?php echo $bill_number; ?> - <?php echo htmlspecialchars($shop_name); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Courier New', monospace; 
            font-size: 12px; 
            line-height: 1.2; 
            color: #000;
            background: white;
            padding: 10px;
        }
        .bill { 
            max-width: 80mm; 
            margin: 0 auto; 
            border: 2px dashed #000;
            padding: 15px;
        }
        .header { 
            text-align: center; 
            border-bottom: 2px dashed #000; 
            padding-bottom: 10px; 
            margin-bottom: 15px;
        }
        .header h1 { font-size: 16px; font-weight: bold; margin-bottom: 5px; }
        .header p { margin: 2px 0; font-size: 11px; }
        .bill-info { margin-bottom: 15px; }
        .bill-info p { margin: 3px 0; font-size: 11px; }
        
        /* ✅ NO TABLE BORDERS */
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 15px 0;
            border: none !important;
        }
        th, td { 
            border: none !important; 
            padding: 4px 2px; 
            font-size: 11px;
            background: white;
        }
        /* ✅ LINE UNDER HEADERS ONLY */
        th { 
            border-bottom: 2px solid #000; 
            font-weight: bold; 
            text-align: center; 
            padding-bottom: 6px;
        }
        /* ✅ LINE UNDER EACH ITEM */
        tbody tr { 
            border-bottom: 1px solid #000; 
        }
        th:first-child, td:first-child { width: 10%; text-align: center; }
        th:nth-child(2), td:nth-child(2) { width: 40%; }
        th:nth-child(3), td:nth-child(3) { width: 25%; }
        th:last-child, td:last-child { width: 25%; text-align: right; }
        
        .total-row { 
            border-top: 3px double #000 !important; 
            border-bottom: 2px solid #000 !important;
            font-weight: bold; 
            font-size: 12px;
            padding-top: 6px !important;
        }
        .footer { 
            margin-top: 15px; 
            border-top: 2px dashed #000;
            padding-top: 10px;
        }
        .footer p { margin: 4px 0; font-size: 11px; }
        .amount-words { 
            font-weight: bold; 
            font-style: italic;
            border-bottom: 1px dotted #000;
            padding-bottom: 5px;
            margin-bottom: 8px;
        }
        /* ✅ NO UNDERLINE FOR CUSTOMER NAME */
        .customer-name { 
            display: inline-block;
            width: 200px;
            height: 20px;
            border-bottom: none !important;
            background: transparent;
        }
        .non-official { 
            text-align: center; 
            font-size: 10px;
            margin-top: 15px;
            padding: 8px;
            border: 1px dashed #666;
            font-style: italic;
        }
        .no-print { margin-top: 20px; text-align: center; }
        .btn { 
            padding: 10px 20px; 
            margin: 5px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 12px;
            color: white;
            text-decoration: none;
            display: inline-block;
        }
        .btn-success { background: #28a745; }
        .btn-primary { background: #007bff; }
        .btn-secondary { background: #6c757d; }
        .btn-warning { background: #ffc107; color: #000; }
        .form-group { margin-bottom: 15px; text-align: center; }
        .form-select { 
            padding: 8px 12px; 
            border: 1px solid #ccc; 
            border-radius: 4px;
            font-size: 12px;
            width: 200px;
        }
        @media print { .no-print { display: none !important; } }
        @page { margin: 5mm; }
    </style>
</head>
<body>
    <div class="bill">
        <!-- HEADER -->
        <div class="header">
            <h1><?php echo htmlspecialchars($shop_name); ?></h1>
            <p>Phone: <?php echo htmlspecialchars($phone_number); ?></p>
            <p>Address: <?php echo htmlspecialchars($branch); ?></p>
        </div>

        <!-- BILL INFO -->
        <div class="bill-info">
            <p><strong>Bill No:</strong> <span id="billNoDisplay"><?php echo $bill_no_display; ?></span></p>
            <p><strong>Date:</strong> <span id="printTime"><?php echo $printed_date_display; ?></span></p>
            <p><strong>Customer Name:</strong> <span class="customer-name"></span></p>
        </div>

        <!-- ITEMS TABLE - CLEAN FORMAT -->
        <table>
            <thead>
                <tr>
                    <th>S.N.</th>
                    <th>Name</th>
                    <th>Size</th>
                    <th>Price</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($detailed_items)): ?>
                    <?php foreach ($detailed_items as $index => $item): ?>
                        <tr>
                            <td><?php echo $index + 1; ?>.</td>
                            <td><?php echo htmlspecialchars($item['name']); ?><?php echo !empty($item['section']) ? ' - ' . htmlspecialchars($item['section']) : ''; ?></td>
                            <td><?php echo htmlspecialchars($item['size']); ?></td>
                            <td style="text-align: right;">Rs. <?php echo number_format($item['price'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align: center; padding: 20px;">No items selected</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="3" style="text-align: right; font-weight: bold;">TOTAL:</td>
                    <td style="text-align: right;"><strong>Rs. <?php echo number_format($subtotal, 2); ?></strong></td>
                </tr>
            </tfoot>
        </table>

        <!-- FOOTER -->
        <div class="footer">
            <p class="amount-words">
                <strong>Amount in Words:</strong> <span id="words"><?php echo htmlspecialchars($amount_in_words); ?></span>
            </p>
            <p><strong>Printed By:</strong> <span id="printedBy"><?php echo htmlspecialchars($printed_by); ?></span></p>
            <p id="paymentInfo" style="display: none;">
                <strong>Payment Method:</strong> <span id="paymentMethod"></span>
            </p>
        </div>

        <div class="non-official">
            <strong>⚠️ THIS IS A NON-OFFICIAL BILL ⚠️</strong>
        </div>
    </div>

    <div class="no-print">
        <div class="form-group">
            <select id="payment_method" class="form-select">
                <option value="cash">Cash</option>
                <option value="online">Online</option>
            </select>
            <br><br>
            <?php if ($subtotal > 0): ?>
                <button id="markPaidBtn" class="btn btn-success">✅ Mark as Paid & Generate Bill</button>
            <?php else: ?>
                <div style="color: red; padding: 15px;">
                    <p>❌ No items selected!</p>
                    <p><a href="select_items.php" class="btn btn-warning">← Go back to select items</a></p>
                </div>
            <?php endif; ?>
        </div>
        <div style="margin-top: 15px;">
            <button onclick="window.print()" class="btn btn-primary">🖨️ Print Bill</button>
            <a href="select_items.php" class="btn btn-secondary">🔄 New Order</a>
            <a href="dashboard.php" class="btn btn-warning">🏠 Back to Dashboard</a>
        </div>
    </div>

    <script>
    document.getElementById('markPaidBtn')?.addEventListener('click', function() {
        const payment_method = document.getElementById('payment_method').value;
        const btn = this;
        const originalText = btn.innerHTML;
        btn.innerHTML = '⏳ Processing...';
        btn.disabled = true;

        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'ajax_mark_paid=1&payment_method=' + encodeURIComponent(payment_method)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const bd = data.bill_details;
                document.getElementById('billNoDisplay').innerText = bd.bill_number;
                document.getElementById('printTime').innerText = bd.printed_date_display;
                document.getElementById('words').innerText = bd.amount_in_words;
                document.getElementById('printedBy').innerText = bd.printed_by;
                document.getElementById('paymentMethod').innerText = 
                    bd.payment_method.charAt(0).toUpperCase() + bd.payment_method.slice(1);
                document.getElementById('paymentInfo').style.display = 'block';
                
                btn.parentElement.style.display = 'none';
                
                setTimeout(() => {
                    window.print();
                    setTimeout(() => {
                        window.location.href = 'select_items.php';
                    }, 2000);
                }, 1000);
            } else {
                alert('❌ Error: ' + (data.error || 'Failed to process payment'));
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Payment error:', error);
            alert('❌ Network error. Please try again.');
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    });
    </script>
</body>
</html>