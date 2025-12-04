<?php
session_start();
require '../Common/connection.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Common/login.php');
    exit();
}

$success = $error = '';
$edit_mode = false;
$edit_bill = $edit_name = $edit_phone = '';
$edit_measurements = [];

// Edit existing
if (isset($_GET['edit'])) {
    $bill = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM customer_measurements WHERE bill_number = ?");
    $stmt->execute([$bill]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $edit_mode = true;
        $edit_bill = $row['bill_number'];
        $edit_name = $row['customer_name'];
        $edit_phone = $row['phone'];
        $edit_measurements = json_decode($row['measurements'], true) ?: [];
    }
}

// Save / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_measurement'])) {
    $bill_number = (int)trim($_POST['bill_number']);
    $customer_name = trim($_POST['customer_name']);
    $phone = trim($_POST['phone'] ?? '');

    if ($bill_number <= 0) {
        $error = "Valid Bill Number is required!";
    } elseif (empty($customer_name)) {
        $error = "Customer name is required!";
    } else {
        // Collect grouped measurements
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
                if (!empty($group)) {
                    $measurements[$cat] = $group;
                }
            }
        }

        if (empty($measurements)) {
            $error = "Please add at least one measurement group.";
        } else {
            $json = json_encode($measurements, JSON_UNESCAPED_UNICODE);

            try {
                if ($edit_mode) {
                    $sql = "UPDATE customer_measurements SET customer_name=?, phone=?, measurements=? WHERE bill_number=?";
                    $pdo->prepare($sql)->execute([$customer_name, $phone, $json, $bill_number]);
                    $success = "Measurement updated for Bill #$bill_number";
                } else {
                    $sql = "INSERT INTO customer_measurements (bill_number, customer_name, phone, measurements, created_by) 
                            VALUES (?, ?, ?, ?, ?)";
                    $pdo->prepare($sql)->execute([$bill_number, $customer_name, $phone, $json, $_SESSION['username'] ?? 'Staff']);
                    $success = "Measurement saved for Bill #$bill_number";
                }
            } catch (Exception $e) {
                $error = "Bill number already exists or database error.";
            }
        }
    }
}

// Search by Bill Number
$search = trim($_GET['search'] ?? '');
$records = [];
if ($search !== '') {
    $stmt = $pdo->prepare("SELECT * FROM customer_measurements WHERE bill_number LIKE ? ORDER BY id DESC");
    $stmt->execute(["%$search%"]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 30px; text-align: center; }
        .header h1 { margin:0; font-size:28px; }
        .section { padding: 30px; }
        input, button { padding: 12px; border-radius: 8px; border: 1px solid #ddd; font-size: 15px; }
        input:focus { outline:none; border-color:#667eea; }
        button { background:#27ae60; color:white; cursor:pointer; font-weight:bold; }
        button:hover { background:#219653; }
        .group { background:#f8f9fa; padding:15px; border-radius:10px; margin:15px 0; border:1px dashed #ddd; }
        .group-header { display:flex; gap:10px; align-items:center; margin-bottom:10px; }
        .group-header input { flex:1; }
        .field-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin:8px 0; }
        .add-btn { background:#3498db; padding:8px 16px; font-size:14px; }
        .remove-btn { background:#e74c3c; padding:8px 12px; font-size:14px; }
        .search-box { text-align:center; margin:20px 0; }
        .search-box input { width:300px; padding:14px; }
        .result { background:#f8f9fa; padding:20px; border-radius:12px; margin:15px 0; border-left:5px solid #667eea; }
        .back-btn { display:inline-block; margin:20px; padding:14px 30px; background:#667eea; color:white; text-decoration:none; border-radius:10px; }
        .back-btn:hover { background:#5a6fd8; }
        .no-data { text-align:center; padding:60px; color:#95a5a6; font-size:18px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Customer Measurements</h1>
        <p>Record measurements by Bill Number</p>
    </div>

    <div class="section">
        <?php if ($success): ?><p style="color:green; text-align:center; font-weight:bold;">Success: <?php echo $success; ?></p><?php endif; ?>
        <?php if ($error): ?><p style="color:red; text-align:center; font-weight:bold;">Error: <?php echo $error; ?></p><?php endif; ?>

        <form method="POST">
            <input type="hidden" name="save_measurement" value="1">
            
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px; margin-bottom:20px;">
                <div>
                    <strong>Bill Number *</strong>
                    <input type="number" name="bill_number" required value="<?php echo $edit_mode ? $edit_bill : ''; ?>" 
                           <?php echo $edit_mode ? 'readonly' : ''; ?> placeholder="e.g. 1205">
                </div>
                <div>
                    <strong>Customer Name *</strong>
                    <input type="text" name="customer_name" required value="<?php echo htmlspecialchars($edit_name); ?>">
                </div>
                <div>
                    <strong>Phone</strong>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($edit_phone); ?>">
                </div>
            </div>

            <h3 style="color:#667eea; margin:25px 0 15px;">Add Measurement Groups</h3>
            <div id="groups">
                <?php if ($edit_mode && !empty($edit_measurements)): ?>
                    <?php foreach ($edit_measurements as $cat => $fields): ?>
                        <div class="group">
                            <div class="group-header">
                                <input type="text" name="category[]" placeholder="e.g. Shirt, Pant, Kurta" value="<?php echo htmlspecialchars($cat); ?>">
                                <button type="button" class="add-btn" onclick="addField(this)">+ Field</button>
                                <button type="button" class="remove-btn" onclick="this.closest('.group').remove()">Remove</button>
                            </div>
                            <?php foreach ($fields as $k => $v): ?>
                                <div class="field-row">
                                    <input type="text" name="field_name[<?php echo count($edit_measurements)-1; ?>][]" placeholder="Field" value="<?php echo htmlspecialchars($k); ?>">
                                    <input type="text" name="field_value[<?php echo count($edit_measurements)-1; ?>][]" placeholder="Value" value="<?php echo htmlspecialchars($v); ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="group">
                        <div class="group-header">
                            <input type="text" name="category[]" placeholder="e.g. Shirt" required>
                            <button type="button" class="add-btn" onclick="addField(this)">+ Field</button>
                            <button type="button" class="remove-btn" onclick="this.closest('.group').remove()">Remove</button>
                        </div>
                        <div class="field-row">
                            <input type="text" name="field_name[0][]" placeholder="e.g. Length">
                            <input type="text" name="field_value[0][]" placeholder="e.g. 40">
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <button type="button" onclick="addGroup()" style="margin:15px 0; background:#667eea;">+ Add New Category (e.g. Pant)</button>

            <div style="text-align:center; margin-top:30px;">
                <button type="submit" style="padding:16px 50px; font-size:18px;">
                    <?php echo $edit_mode ? 'Update Measurement' : 'Save Measurement'; ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Search -->
    <div class="section">
        <div class="search-box">
            <form method="GET">
                <input type="number" name="search" placeholder="Search by Bill Number" value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">Search</button>
                <?php if ($search): ?><a href="measurement.php"><button type="button" style="background:#95a5a6;">Clear</button></a><?php endif; ?>
            </form>
        </div>

        <?php if ($search === ''): ?>
            <p style="text-align:center; color:#7f8c8d;">Enter a bill number above to view measurements</p>
        <?php elseif (empty($records)): ?>
            <div class="no-data">No measurement found for Bill #<?php echo htmlspecialchars($search); ?></div>
        <?php else: ?>
            <?php foreach ($records as $row): 
                $meas = json_decode($row['measurements'], true);
            ?>
                <div class="result">
                    <h3>Bill #<?php echo $row['bill_number']; ?> - <?php echo htmlspecialchars($row['customer_name']); ?>
                        <?php if ($row['phone']): ?> (<?php echo htmlspecialchars($row['phone']); ?>)<?php endif; ?>
                        <a href="?edit=<?php echo $row['bill_number']; ?>" style="float:right; color:#3498db; font-size:14px;">Edit</a>
                    </h3>
                    <?php foreach ($meas as $cat => $fields): ?>
                        <div style="margin:15px 0; padding:15px; background:white; border-radius:8px; border:1px solid #eee;">
                            <strong style="color:#667eea; font-size:18px;"><?php echo htmlspecialchars($cat); ?></strong>
                            <table width="100%" style="margin-top:10px;">
                                <?php foreach ($fields as $k => $v): ?>
                                    <tr>
                                        <td width="50%" style="padding:5px 0; font-weight:bold;"><?php echo htmlspecialchars($k); ?></td>
                                        <td style="padding:5px 0;"><?php echo htmlspecialchars($v); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div style="text-align:center; padding:20px;">
        <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
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
            <input type="text" name="category[]" placeholder="e.g. Pant, Kurta" required>
            <button type="button" class="add-btn" onclick="addField(this)">+ Field</button>
            <button type="button" class="remove-btn" onclick="this.closest('.group').remove()">Remove</button>
        </div>
        <div class="field-row">
            <input type="text" name="field_name[${groupIndex}][]" placeholder="Field name">
            <input type="text" name="field_value[${groupIndex}][]" placeholder="Value">
        </div>
    `;
    container.appendChild(div);
    groupIndex++;
}

function addField(btn) {
    const group = btn.closest('.group');
    const row = document.createElement('div');
    row.className = 'field-row';
    row.innerHTML = `
        <input type="text" name="field_name[${groupIndex}][]" placeholder="Field name">
        <input type="text" name="field_value[${groupIndex}][]" placeholder="Value">
    `;
    group.appendChild(row);
}
</script>
</body>
</html>