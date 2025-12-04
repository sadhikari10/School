<?php
session_start();
require '../Common/connection.php';
require '../Common/nepali_date.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Common/login.php');
    exit();
}

$outlet_id = $_SESSION['outlet_id'] ?? 0;
$username = $_SESSION['username'] ?? 'Staff';

// --- REQUIRED FUNCTION (Nepali Fiscal Year) ---
if (!function_exists('get_current_fiscal_year')) {
    function get_current_fiscal_year() {
        $bs = nepali_date_time();  // From nepali_date.php
        $parts = explode('-', $bs);
        $year = (int)$parts[0];
        $month = (int)$parts[1];
        return ($month >= 4) ? "$year/" . ($year + 1) : ($year - 1) . "/$year";
    }
}
$fiscal_year = get_current_fiscal_year();

// --- Check if linked to a uniform bill ---
$linked_bill = $_GET['bill'] ?? 0;
$linked_bill = (int)$linked_bill;
$is_linked_mode = $linked_bill > 0;

$success = $error = '';

// --- Save temporarily to SESSION only ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_temp_measurement'])) {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($customer_name)) {
        $error = "Customer name is required!";
    } else {
        $items = [];
        $total_price = 0;

        if (isset($_POST['category']) && is_array($_POST['category'])) {
            foreach ($_POST['category'] as $idx => $garment) {
                $garment = trim($garment);
                $price = (float)($_POST['price'][$idx] ?? 0);

                if ($garment === '' || $price <= 0) continue;

                $fields = $_POST['field_name'][$idx] ?? [];
                $values = $_POST['field_value'][$idx] ?? [];
                $measurements = [];

                for ($i = 0; $i < count($fields); $i++) {
                    $f = trim($fields[$i] ?? '');
                    $v = trim($values[$i] ?? '');
                    if ($f !== '' && $v !== '') {
                        $measurements[$f] = $v;
                    }
                }

                $items[] = [
                    'garment' => $garment,
                    'price' => $price,
                    'measurements' => $measurements
                ];
                $total_price += $price;
            }
        }

        if (empty($items)) {
            $error = "Please add at least one garment with a valid price.";
        } else {
            $_SESSION['temp_custom_order'] = [
                'bill_number' => $is_linked_mode ? $linked_bill : 0,
                'customer_name' => $customer_name,
                'phone' => $phone,
                'items' => $items,
                'total_price' => $total_price,
                'saved_at' => date('Y-m-d H:i:s')
            ];

            $success = "Custom stitching items saved successfully!<br>
                        Total: <strong>Rs. " . number_format($total_price) . "</strong><br><br>
                        <a href='bill.php' style='background:#8e24aa;color:white;padding:12px 30px;border-radius:8px;text-decoration:none;font-weight:bold;'>
                            Go to Final Bill →
                        </a>";
        }
    }
}

// Load from session if exists
$temp_data = $_SESSION['temp_custom_order'] ?? null;
$temp_items = $temp_data['items'] ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custom Stitching - <?php echo $is_linked_mode ? "Bill #$linked_bill" : "New Order"; ?></title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f9; margin:0; padding:20px; color:#2c3e50; }
        .container { max-width: 1000px; margin: auto; background: white; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: linear-gradient(135deg, #8e24aa, #9c27b0); color: white; padding: 30px; text-align: center; }
        .header h1 { margin:0; font-size:28px; }
        .header h2 { margin:10px 0 0; font-size:20px; font-weight:normal; }
        .section { padding: 30px; }
        .info, .error { padding:15px; border-radius:8px; margin:20px 0; text-align:center; font-weight:bold; }
        .info { background:#d4edda; color:#155724; }
        .error { background:#f8d7da; color:#721c24; }
        .group { background:#f8f0ff; padding:20px; border-radius:12px; margin:15px 0; border:2px dashed #8e24aa; }
        .group-header { display:flex; gap:12px; align-items:center; margin-bottom:15px; flex-wrap:wrap; }
        .group-header input[type="text"] { flex:1; min-width:200px; padding:12px; border-radius:8px; border:1px solid #ddd; }
        .price-input { width:130px; padding:12px; border-radius:8px; border:1px solid #8e24aa; font-weight:bold; color:#8e24aa; font-size:16px; }
        .field-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin:8px 0; }
        .field-row input { padding:10px; border:1px solid #ccc; border-radius:6px; }
        .add-btn { background:#27ae60; color:white; padding:10px 20px; border:none; border-radius:8px; cursor:pointer; }
        .remove-btn { background:#e74c3c; color:white; padding:8px 12px; border:none; border-radius:6px; cursor:pointer; }
        .btn-save { background:#8e24aa; color:white; padding:18px 50px; font-size:20px; border:none; border-radius:12px; cursor:pointer; }
        .btn-save:hover { background:#6a1b9a; }
        .skip-link { display:block; text-align:center; margin-top:25px; }
        .skip-link a { color:#8e24aa; font-weight:bold; font-size:18px; text-decoration:none; }
        .skip-link a:hover { text-decoration:underline; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Custom Stitching Order</h1>
        <?php if ($is_linked_mode): ?>
            <h2>Linked to Uniform Bill #<?php echo $linked_bill; ?></h2>
        <?php else: ?>
            <h2>Standalone Custom Order</h2>
        <?php endif; ?>
    </div>

    <div class="section">
        <?php if ($success): ?>
            <div class="info"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="save_temp_measurement" value="1">

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:25px;">
                <div>
                    <strong>Customer Name <span style="color:red;">*</span></strong>
                    <input type="text" name="customer_name" required value="<?php echo htmlspecialchars($temp_data['customer_name'] ?? ''); ?>" placeholder="Enter full name">
                </div>
                <div>
                    <strong>Phone (Optional)</strong>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($temp_data['phone'] ?? ''); ?>" placeholder="98xxxxxxxx">
                </div>
            </div>

            <h3 style="color:#8e24aa; margin:30px 0 15px;">Garments & Measurements</h3>
            <div id="groups">
                <!-- Dynamic groups will be added here by JavaScript -->
            </div>

            <button type="button" class="add-btn" onclick="addGroup()">+ Add Another Garment</button>

            <div style="text-align:center; margin-top:40px;">
                <button type="submit" class="btn-save">
                    Save Custom Items & Go to Final Bill
                </button>
            </div>
        </form>

        <div class="skip-link">
            <a href="bill.php">Skip Custom Stitching & Go to Bill →</a>
        </div>
    </div>
</div>

<script>
// Load saved items from session
const savedItems = <?php echo json_encode($temp_items); ?>;
let groupIndex = 0;

function addGroup(garment = '', price = '', measurements = {}) {
    const container = document.getElementById('groups');
    const div = document.createElement('div');
    div.className = 'group';
    div.innerHTML = `
        <div class="group-header">
            <input type="text" name="category[]" placeholder="e.g. Shirt, Pant, Coat, Kurta" value="${garment}" required>
            <input type="number" name="price[]" class="price-input" placeholder="Price" min="1" step="1" value="${price}" required>
            <button type="button" class="remove-btn" onclick="this.closest('.group').remove()">Remove</button>
        </div>
        <div class="fields-container">
            <div class="field-row">
                <input type="text" name="field_name[${groupIndex}][]" placeholder="e.g. Length, Chest, Waist">
                <input type="text" name="field_value[${groupIndex}][]" placeholder="e.g. 40, 42, 32">
            </div>
        </div>
        <button type="button" class="add-btn" onclick="addField(this)" style="margin-top:10px; font-size:14px;">+ Add Measurement Field</button>
    `;
    container.appendChild(div);

    // Add saved measurements
    Object.entries(measurements).forEach(([field, value]) => {
        addField(div.querySelector('.add-btn'), field, value);
    });

    groupIndex++;
}

function addField(btn, fieldName = '', fieldValue = '') {
    const container = btn.closest('.group').querySelector('.fields-container');
    const row = document.createElement('div');
    row.className = 'field-row';
    const idx = groupIndex - 1;
    row.innerHTML = `
        <input type="text" name="field_name[${idx}][]" placeholder="Field name" value="${fieldName}">
        <input type="text" name="field_value[${idx}][]" placeholder="Value" value="${fieldValue}">
    `;
    container.appendChild(row);
}

// Load saved items or start with one blank
if (savedItems.length > 0) {
    savedItems.forEach(item => {
        addGroup(item.garment, item.price, item.measurements);
    });
} else {
    addGroup(); // Start with one empty garment
}
</script>
</body>
</html>