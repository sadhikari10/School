<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../Common/login.php");
    exit();
}
if (!isset($_SESSION['selected_school_id']) || !isset($_SESSION['selected_school_name'])) {
    header("Location: dashboard.php");
    exit();
}

require_once '../Common/connection.php';
require_once 'UniformSelector.php';

$outlet_id   = $_SESSION['outlet_id'];
$school_id   = $_SESSION['selected_school_id'];
$school_name = $_SESSION['selected_school_name'];

$selector = new UniformSelector($pdo, $school_id, $outlet_id);
$itemsData = $selector->getItems();
$items = $itemsData['items'];
$brandCount = $itemsData['brandCount'];

$selectedSizes = $_POST['selected_sizes'] ?? $_SESSION['selected_sizes'] ?? [];
$_SESSION['selected_sizes'] = $selectedSizes;

if (isset($_POST['clear_selections'])) {
    $_SESSION['selected_sizes'] = [];
    header("Location: dashboard.php");
    exit();
}
if ($_POST['save_session'] ?? false) {
    $_SESSION['selected_sizes'] = $_POST['selected_sizes'] ?? [];
    exit(json_encode(['status' => 'saved']));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Select Sizes - <?php echo htmlspecialchars($school_name); ?></title>
<link rel="stylesheet" href="select_items.css">
<style>
.category-icon { font-family: "Segoe UI Emoji", "Apple Color Emoji", sans-serif !important; font-size: 4.8rem; margin-bottom: 12px; }
.selected-indicator { position: absolute; top: 10px; right: 10px; background: #27ae60; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold; box-shadow: 0 4px 12px rgba(39,174,96,0.4); animation: pop 0.3s ease; }
@keyframes pop { 0% { transform: scale(0); } 80% { transform: scale(1.2); } 100% { transform: scale(1); } }
.category-card { position: relative; padding: 32px 20px; border-radius: 24px; background: white; box-shadow: 0 10px 30px rgba(0,0,0,0.12); transition: all 0.3s ease; cursor: pointer; text-align: center; }
.category-card:hover { transform: translateY(-14px); box-shadow: 0 24px 60px rgba(142,68,173,0.3); }
.category-name { font-size: 1.35rem; font-weight: 600; color: #2c3e50; margin-top: 8px; }

.size-btn { padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 18px; background: #fdfdfd; display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; cursor: pointer; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
.size-btn.selected { border-color: #8e44ad; background: #f3e8ff; box-shadow: 0 8px 20px rgba(142,68,173,0.2); }
.size-label { font-size: 1.2rem; font-weight: bold; color: #333; }
.size-price { font-size: 1.1rem; color: #27ae60; font-weight: bold; margin-left: 8px; }
.qty-controls { display: flex; align-items: center; }
.qty-controls button { background: #8e44ad; color: #fff; border: none; border-radius: 50%; width: 28px; height: 28px; margin: 0 6px; cursor: pointer; font-size: 1.1rem; line-height: 1; }
.qty-count { min-width: 24px; text-align: center; font-weight: bold; }
.modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); align-items:center; justify-content:center; z-index:1000; }
.modal-content { background:#fff; padding:24px; border-radius:16px; width:90%; max-width:500px; max-height:80%; overflow-y:auto; }
.modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
.modal-header .modal-icon { font-size:3rem; }
.modal-actions { margin-top:20px; text-align:right; }
.modal-actions button { padding:12px 20px; margin-left:12px; border:none; border-radius:8px; cursor:pointer; font-weight:bold; }
.continue-shopping-btn { background:#f3e8ff; color:#8e44ad; }
.pay-bill-btn { background:#8e44ad; color:#fff; }
.confirm-modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); align-items:center; justify-content:center; z-index:1100; }
.confirm-content { background:#fff; padding:24px; border-radius:16px; width:90%; max-width:400px; text-align:center; }
</style>
</head>
<body>

<div class="container">
<header class="header">
    <h1>Select Item Sizes</h1>
    <p>School: <strong style="color:#8e44ad; font-size:1.4rem;"><?php echo htmlspecialchars($school_name); ?></strong></p>
</header>

<form method="POST" action="bill.php" id="itemsForm">
<input type="hidden" name="school_id" value="<?php echo $school_id; ?>">
<input type="hidden" name="school_name" value="<?php echo htmlspecialchars($school_name); ?>">

<?php foreach ($items as $item_name => $sizes): 
    $safe_key = preg_replace('/[^a-zA-Z0-9]/', '_', $item_name);
?>
    <input type="hidden" name="selected_sizes[<?php echo $safe_key; ?>]" id="selected_<?php echo $safe_key; ?>" value="<?php echo htmlspecialchars($selectedSizes[$safe_key] ?? ''); ?>">
<?php endforeach; ?>

<div class="categories-grid">
<?php foreach ($items as $item_name => $sizes): 
    $safe_key = preg_replace('/[^a-zA-Z0-9]/', '_', $item_name);
    $hasSelection = !empty($selectedSizes[$safe_key]);
?>
    <div class="category-card" onclick="showSizeModal('<?php echo $safe_key; ?>', '<?php echo htmlspecialchars(addslashes($item_name)); ?>', '<?php echo $selector->getEmoji($item_name); ?>')">
        <div class="category-icon"><?php echo $selector->getEmoji($item_name); ?></div>
        <div class="category-name"><?php echo htmlspecialchars($item_name); ?></div>
        <?php if ($hasSelection): ?>
            <div class="selected-indicator">✔</div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</div>

<div class="actions">
    <button type="button" class="back-btn" onclick="checkAndShowConfirmModal()">Back to Schools</button>
    <button type="button" class="next-btn" onclick="showSelectedItemsModal()">
        Pay Bill (0 items)
    </button>
</div>
</form>

<!-- Size Modal -->
<div class="modal" id="sizeModal">
    <div class="modal-content">
        <div class="modal-header">
            <span class="modal-icon" id="modalIcon"></span>
            <h3 id="modalTitle"></h3>
            <button type="button" class="back-btn-modal" onclick="closeSizeModal()">Back</button>
        </div>
        <div id="sizesGrid"></div>
    </div>
</div>

<!-- Order Summary Modal -->
<div class="modal" id="selectedItemsModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Order Summary</h3>
            <span class="close" onclick="closeSelectedItemsModal()">×</span>
        </div>
        <div class="selected-items-list" id="selectedItemsList"></div>
        <div class="modal-actions">
            <button type="button" class="continue-shopping-btn" onclick="closeSelectedItemsModal()">Continue Shopping</button>
            <button type="submit" form="itemsForm" class="pay-bill-btn">Pay Bill</button>
        </div>
    </div>
</div>

<!-- Confirm Clear Modal -->
<div class="confirm-modal" id="confirmClearModal">
    <div class="confirm-content">
        <h3>Clear All Selections?</h3>
        <p>This will remove all selected items.</p>
        <button type="button" onclick="closeConfirmModal()">Cancel</button>
        <button type="button" onclick="confirmClearSelections()">Yes, Clear</button>
    </div>
</div>

<form method="POST" id="clearForm" style="display:none;">
    <input type="hidden" name="clear_selections" value="1">
</form>

<script>
const allItems = <?php echo json_encode($items); ?>;
let selectedSizes = <?php echo json_encode($selectedSizes); ?>;

function getItemNameFromKey(key) {
    return Object.keys(allItems).find(name => name.replace(/[^a-zA-Z0-9]/g,'_') === key);
}

function showSizeModal(key, name, emoji) {
    document.getElementById('modalIcon').textContent = emoji;
    document.getElementById('modalTitle').textContent = name;
    const grid = document.getElementById('sizesGrid');
    grid.innerHTML = '';

    const sizes = allItems[name] || [];
    if (sizes.length === 0) {
        grid.innerHTML = '<div style="text-align:center;padding:60px;color:#999;">No prices available</div>';
        document.getElementById('sizeModal').style.display = 'flex';
        return;
    }

    sizes.forEach(s => {
        const code = s.size + '|' + s.brand;
        const count = (selectedSizes[key]?.split(',').filter(x => x === code).length) || 0;

        const btn = document.createElement('div');
        btn.className = 'size-btn' + (count > 0 ? ' selected' : '');
        btn.innerHTML = `
            <span class="size-label">${s.size}</span>
            <span class="size-price">Rs. ${Number(s.price).toLocaleString()}</span>
            <div class="qty-controls">
                <button type="button" onclick="decrementQty('${key}','${code}')">-</button>
                <span class="qty-count">${count}</span>
                <button type="button" onclick="incrementQty('${key}','${code}')">+</button>
            </div>
        `;
        grid.appendChild(btn);
    });

    document.getElementById('sizeModal').style.display = 'flex';
}

function incrementQty(key, code) {
    let arr = (selectedSizes[key] || '').split(',').filter(Boolean);
    arr.push(code);
    selectedSizes[key] = arr.join(',');
    document.getElementById('selected_'+key).value = selectedSizes[key];
    showSizeModal(key,getItemNameFromKey(key),allItems[getItemNameFromKey(key)][0] ? '<?php echo $selector->getEmoji("Dummy"); ?>' : '');
    updatePayButton();
    saveToSession();
}
function decrementQty(key, code) {
    let arr = (selectedSizes[key] || '').split(',').filter(Boolean);
    const idx = arr.indexOf(code);
    if(idx!==-1) arr.splice(idx,1);
    selectedSizes[key] = arr.join(',');
    if(!selectedSizes[key]) delete selectedSizes[key];
    document.getElementById('selected_'+key).value = selectedSizes[key] || '';
    showSizeModal(key,getItemNameFromKey(key),allItems[getItemNameFromKey(key)][0] ? '<?php echo $selector->getEmoji("Dummy"); ?>' : '');
    updatePayButton();
    saveToSession();
}

function updatePayButton() {
    let count = 0;
    Object.values(selectedSizes).forEach(str=>{
        if(str) count += str.split(',').filter(Boolean).length;
    });
    document.querySelector('.next-btn').textContent = `Pay Bill (${count} items)`;
}

function saveToSession() {
    const fd = new FormData();
    fd.append('save_session','1');
    Object.entries(selectedSizes).forEach(([k,v])=> fd.append(`selected_sizes[${k}]`,v));
    fetch('',{method:'POST',body:fd});
}

function showSelectedItemsModal() {
    const list = document.getElementById('selectedItemsList');
    list.innerHTML = '';
    let total=0;
    Object.entries(selectedSizes).forEach(([key,codes])=>{
        if(!codes) return;
        const itemName = getItemNameFromKey(key);
        if(!itemName) return;
        codes.split(',').forEach(code=>{
            const [size,brand]=code.split('|');
            const item = allItems[itemName].find(i=>i.size===size && i.brand===brand);
            if(!item) return;
            total+=item.price;
            list.innerHTML += `<div style="display:flex;justify-content:space-between;padding:12px;border-bottom:1px solid #eee;">
                <div>${itemName} - ${size} (${brand})</div>
                <div>Rs. ${item.price}</div>
            </div>`;
        });
    });
    list.innerHTML += `<div style="text-align:right;font-weight:bold;padding:12px;">TOTAL: Rs. ${total.toLocaleString()}</div>`;
    document.getElementById('selectedItemsModal').style.display='flex';
}

function closeSizeModal(){document.getElementById('sizeModal').style.display='none';}
function closeSelectedItemsModal(){document.getElementById('selectedItemsModal').style.display='none';}
function checkAndShowConfirmModal(){if(Object.keys(selectedSizes).length>0){document.getElementById('confirmClearModal').style.display='flex';}else{location.href='dashboard.php';}}
function closeConfirmModal(){document.getElementById('confirmClearModal').style.display='none';}
function confirmClearSelections(){document.getElementById('clearForm').submit();}

setInterval(saveToSession,7000);
updatePayButton();
</script>

</body>
</html>
