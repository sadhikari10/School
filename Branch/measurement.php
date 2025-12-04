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

function get_current_fiscal_year() {
    $bs = nepali_date_time();
    $parts = explode('-', $bs);
    $year = (int)$parts[0];
    $month = (int)$parts[1];
    return ($month >= 4) ? "$year/" . ($year + 1) : ($year - 1) . "/$year";
}

$fiscal_year = get_current_fiscal_year();

// Safe bill number generation
function getNextMeasurementBill($pdo, $outlet_id, $fiscal_year) {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT last_bill_number FROM bill_counter WHERE outlet_id = ? AND fiscal_year = ? FOR UPDATE");
        $stmt->execute([$outlet_id, $fiscal_year]);
        $row = $stmt->fetch();

        if ($row) {
            $next = $row['last_bill_number'] + 1;
            $pdo->prepare("UPDATE bill_counter SET last_bill_number = ? WHERE outlet_id = ? AND fiscal_year = ?")
                ->execute([$next, $outlet_id, $fiscal_year]);
        } else {
            $next = 1;
            $pdo->prepare("INSERT INTO bill_counter (outlet_id, fiscal_year, last_bill_number) VALUES (?, ?, ?)")
                ->execute([$outlet_id, $fiscal_year, 1]);
        }
        $pdo->commit();
        return $next;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

$success = $error = '';
$edit_mode = false;
$edit_id = 0;
$edit_name = $edit_phone = '';
$edit_measurements = [];
$last_saved_bill = 0;

// Handle Edit (via POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['load_edit'])) {
    $edit_id = (int)$_POST['record_id'];
    $stmt = $pdo->prepare("SELECT * FROM customer_measurements WHERE id = ?");
    $stmt->execute([$edit_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $edit_mode = true;
        $edit_name = $row['customer_name'];
        $edit_phone = $row['phone'] ?? '';
        $edit_measurements = json_decode($row['measurements'], true) ?: [];
        $last_saved_bill = $row['bill_number'];
    }
}

// Save Measurement (only save)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_measurement'])) {
    $customer_name = trim($_POST['customer_name']);
    $phone = trim($_POST['phone'] ?? '');

    if (empty($customer_name)) {
        $error = "Customer name is required!";
    } else {
        $measurements = [];
        if (isset($_POST['category']) && is_array($_POST['category'])) {
            foreach ($_POST['category'] as $idx => $cat) {
                $cat = trim($cat);
                if ($cat === '') continue;
                $fields = $_POST['field_name'][$idx] ?? [];
                $values = $_POST['field_value'][$idx] ?? [];
                $group = [];
                for ($i = 0; $i < count($fields); $i++) {
                    if (trim($fields[$i]) !== '' && trim($values[$i]) !== '') {
                        $group[trim($fields[$i])] = trim($values[$i]);
                    }
                }
                if (!empty($group)) $measurements[$cat] = $group;
            }
        }

        if (empty($measurements)) {
            $error = "Please add at least one measurement.";
        } else {
            $json = json_encode($measurements, JSON_UNESCAPED_UNICODE);
            $bs_datetime = nepali_date_time();

            try {
                if ($edit_mode && $edit_id > 0) {
                    $sql = "UPDATE customer_measurements SET customer_name=?, phone=?, measurements=? WHERE id=?";
                    $pdo->prepare($sql)->execute([$customer_name, $phone, $json, $edit_id]);
                    $success = "Measurement updated successfully!";
                    $last_saved_bill = $edit_id > 0 ? ($_POST['current_bill'] ?? 0) : 0;
                } else {
                    $bill_number = getNextMeasurementBill($pdo, $outlet_id, $fiscal_year);
                    $sql = "INSERT INTO customer_measurements 
                            (bill_number, fiscal_year, customer_name, phone, measurements, created_by, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $pdo->prepare($sql)->execute([
                        $bill_number, $fiscal_year, $customer_name, $phone, $json, $username, $bs_datetime
                    ]);
                    $success = "Measurement saved! Bill Number: <strong>#$bill_number</strong>";
                    $last_saved_bill = $bill_number;
                }
            } catch (Exception $e) {
                $error = "Error saving: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Measurements</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f2f5; margin:0; padding:20px; color:#2c3e50; }
        .container { max-width: 1000px; margin: auto; background: white; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); overflow: hidden; }
        .header h1 { margin:0; font-size:28px; }
        .section { padding: 30px; }
        input, button { padding: 12px 20px; border-radius: 8px; border: none; font-size: 15px; cursor: pointer; margin: 5px; }
        input { border: 1px solid #ddd; width: 100%; box-sizing: border-box; }
        button { color: white; font-weight: bold; }
        .btn-save { background: #27ae60; }
        .btn-save:hover { background: #219653; }
        .btn-bill { background: #3498db; }
        .btn-bill:hover { background: #2980b9; }
        .group { background:#f8f9fa; padding:15px; border-radius:10px; margin:15px 0; border:1px dashed #ddd; }
        .group-header { display:flex; gap:10px; align-items:center; margin-bottom:10px; }
        .group-header input { flex:1; }
        .field-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin:8px 0; }
        .add-btn { background:#3498db; padding:8px 16px; font-size:14px; }
        .remove-btn { background:#e74c3c; padding:8px 12px; font-size:14px; }
        .back-btn { background:#667eea; padding:14px 30px; font-size:16px; text-decoration:none; display:inline-block; margin-top:20px; border-radius:8px; }
        .back-btn:hover { background:#5a6fd8; }
    </style>
</head>
<body>
<div class="container">

    <div class="section">
        <?php if ($success): ?>
            <div style="background:#d4edda; color:#155724; padding:15px; border-radius:8px; text-align:center; font-weight:bold; margin-bottom:20px;">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div style="background:#f8d7da; color:#721c24; padding:15px; border-radius:8px; text-align:center; font-weight:bold; margin-bottom:20px;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="save_measurement" value="1">
            <?php if ($edit_mode): ?>
                <input type="hidden" name="current_bill" value="<?php echo $last_saved_bill; ?>">
            <?php endif; ?>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
                <div>
                    <strong>Customer Name *</strong>
                    <input type="text" name="customer_name" required value="<?php echo htmlspecialchars($edit_name); ?>">
                </div>
                <div>
                    <strong>Phone</strong>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($edit_phone); ?>">
                </div>
            </div>

            <h3 style="color:#667eea; margin:25px 0 15px;">Measurement Groups</h3>
            <div id="groups">
                <?php 
                $group_index = 0;
                if ($edit_mode && !empty($edit_measurements)): 
                    foreach ($edit_measurements as $cat => $fields): ?>
                        <div class="group">
                            <div class="group-header">
                                <input type="text" name="category[]" placeholder="e.g. Shirt" value="<?php echo htmlspecialchars($cat); ?>" required>
                                <button type="button" class="add-btn" onclick="addField(this)">+ Field</button>
                                <button type="button" class="remove-btn" onclick="this.closest('.group').remove()">Remove</button>
                            </div>
                            <?php foreach ($fields as $k => $v): ?>
                                <div class="field-row">
                                    <input type="text" name="field_name[<?php echo $group_index; ?>][]" placeholder="Field" value="<?php echo htmlspecialchars($k); ?>">
                                    <input type="text" name="field_value[<?php echo $group_index; ?>][]" placeholder="Value" value="<?php echo htmlspecialchars($v); ?>">
                                </div>
                            <?php $group_index++; endforeach; ?>
                        </div>
                    <?php endforeach; 
                else: ?>
                    <div class="group">
                        <div class="group-header">
                            <input type="text" name="category[]" placeholder="e.g. Shirt" required>
                            <button type="button" class="add-btn" onclick="addField(this)">+ Field</button>
                            <button type="button" class="remove-btn" onclick="this.closest('.group').remove()">Remove</button>
                        </div>
                        <div class="field-row">
                            <input type="text" name="field_name[0][]" placeholder="Length">
                            <input type="text" name="field_value[0][]" placeholder="40">
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <button type="button" onclick="addGroup()" style="background:#667eea; color:white;">+ Add Garment</button>

            <div style="text-align:center; margin-top:40px;">
                <button type="submit" class="btn-save" style="padding:18px 50px; font-size:20px;">
                    Save Measurement
                </button>

                <?php if ($last_saved_bill > 0): ?>
                    <form method="POST" action="measurement_bill.php" style="display:inline; margin-left:20px;">
                        <input type="hidden" name="view_bill" value="<?php echo $last_saved_bill; ?>">
                        <button type="submit" class="btn-bill" style="padding:18px 50px; font-size:20px;">
                            Go to Bill #<?php echo $last_saved_bill; ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </form>
        <!-- Add this button anywhere in the bottom section, e.g. near "Back to Dashboard" -->

</div>
    </div>

</div>

<script>
let groupIndex = <?php echo $edit_mode ? count($edit_measurements) : 1; ?>;

function addGroup() {
    const container = document.getElementById('groups');
    const div = document.createElement('div');
    div.className = 'group';
    div.innerHTML = `
        <div class="group-header">
            <input type="text" name="category[]" placeholder="e.g. Pant" required>
            <button type="button" class="add-btn" onclick="addField(this)">+ Field</button>
            <button type="button" class="remove-btn" onclick="this.closest('.group').remove()">Remove</button>
        </div>
        <div class="field-row">
            <input type="text" name="field_name[${groupIndex}][]" placeholder="Waist">
            <input type="text" name="field_value[${groupIndex}][]" placeholder="32">
        </div>
    `;
    container.appendChild(div);
    groupIndex++;
}

function addField(btn) {
    const group = btn.closest('.group');
    const row = document.createElement('div');
    row.className = 'field-row';
    const index = group.querySelector('input[name^="field_name"]')?.name.match(/\[(\d+)\]/)?.[1] || (groupIndex - 1);
    row.innerHTML = `
        <input type="text" name="field_name[${index}][]" placeholder="Field">
        <input type="text" name="field_value[${index}][]" placeholder="Value">
    `;
    group.appendChild(row);
}
</script>
</body>
</html>