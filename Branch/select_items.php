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
require_once 'MeasurementHelper.php';

$outlet_id   = $_SESSION['outlet_id'];
$school_id   = $_SESSION['selected_school_id'];
$school_name = $_SESSION['selected_school_name'];

$selector = new UniformSelector($pdo, $school_id, $outlet_id);
$itemsData = $selector->getItems();
$items = $itemsData['items'];

$measHelper = new MeasurementHelper($pdo);

// Add this at the very top of select_items.php, right after session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_measurement') {
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 1);
        $measurements = json_decode($_POST['measurements'] ?? '{}', true) ?? [];

        $success = $measHelper->addItem($name, $measurements, $price, $quantity);

        header('Content-Type: application/json');
        echo json_encode(['success' => $success]);
        exit;
    }

    if ($_POST['action'] === 'remove_measurement') {
        $id = (int)($_POST['id'] ?? 0);

        header('Content-Type: application/json');

        if ($id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid ID']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM temp_measurements WHERE id = ?");
            $stmt->execute([$id]);
            $deleted = $stmt->rowCount() > 0;

            echo json_encode(['success' => $deleted]);
        } catch (Exception $e) {
            error_log("Delete failed: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'DB Error']);
        }
        exit;
    }
}
// Clear everything (custom measurements + selected sizes) and go back
if (isset($_POST['clear_and_back'])) {
    // 1. Delete all custom measurements for this session
    $stmt = $pdo->prepare("DELETE FROM temp_measurements WHERE session_id = ?");
    $stmt->execute([session_id()]);

    // 2. Clear selected sizes from session
    unset($_SESSION['selected_sizes']);

    // Optional: clear any other temp data if you have
    // $_SESSION['something_else'] = null;

    echo json_encode(['success' => true]);
    exit;
}
// Pre-calculate brand count per item
$brandCountPerItem = [];
foreach ($items as $name => $sizes) {
    $brands = array_unique(array_column($sizes, 'brand'));
    $brandCountPerItem[$name] = count($brands);
}

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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700&display=swap">
    <style>
    .size-box {
        background: #f8f9fa;
        border: 2px solid #e1e8ed;
        border-radius: 16px;
        padding: 18px;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        text-align: center;
    }
    .size-box:hover {
        border-color: #667eea;
        transform: translateY(-4px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.2);
    }

    /* Beautiful GREEN CHECKMARK inside popup – NO TEXT */
    .size-box.selected-in-modal {
        border-color: #27ae60 !important;
        background: #f0fff4 !important;
    }
    .size-box.selected-in-modal::after {
        content: '✓';        /* This is the real checkmark */
        position: absolute;
        top: -10px;
        right: -10px;
        background: #27ae60;
        color: white;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 18px;
        box-shadow: 0 4px 15px rgba(39, 174, 96, 0.4);
    }

    /* No visual change outside popup */
    .size-box.has-quantity,
    .category-card.has-selection {
        border-color: #e1e8ed !important;
        background: #f8f9fa !important;
    }

    .qty-badge {
        display: inline-block;
        background: #27ae60;
        color: white;
        font-weight: bold;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.9rem;
        min-width: 28px;
        margin-top: 8px;
    }

    .selected-indicator {
        position: absolute;
        top: -10px;
        right: -10px;
        background: #27ae60;
        color: white;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 14px;
        box-shadow: 0 4px 12px rgba(39, 174, 96, 0.5);
        z-index: 10;
    }

    .category-card:hover {
        border-color: #667eea;
        transform: translateY(-5px);
        box-shadow: 0 12px 30px rgba(102, 126, 234, 0.25);
    }
</style>
</head>
<body>

<div class="container">
    <header class="header">
        <h1>Select Item Sizes</h1>
        <p>School: <strong style="color:#8e44ad; font-size:1.5rem;"><?php echo htmlspecialchars($school_name); ?></strong></p>
    </header>

    <form method="POST" action="bill.php" id="itemsForm">
        <input type="hidden" name="school_id" value="<?php echo $school_id; ?>">
        <input type="hidden" name="school_name" value="<?php echo htmlspecialchars($school_name); ?>">
        <div id="dynamicInputs"></div>

        <div class="categories-grid">
            <?php foreach ($items as $item_name => $sizes): 
                $safe_key = preg_replace('/[^a-zA-Z0-9]/', '_', $item_name);
                $hasSelection = !empty($selectedSizes[$safe_key] ?? '');
            ?>
                <div class="category-card <?php echo $hasSelection ? 'has-selection' : ''; ?>" 
                     onclick="showSizeModal('<?php echo $safe_key; ?>', '<?php echo htmlspecialchars(addslashes($item_name)); ?>', '<?php echo $selector->getEmoji($item_name); ?>')">
                    <div class="category-icon"><?php echo $selector->getEmoji($item_name); ?></div>
                    <div class="category-name"><?php echo htmlspecialchars($item_name); ?></div>
                    <?php if ($hasSelection): ?>
                        <div class="selected-indicator">0</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="actions">
            <button type="button" class="back-btn" onclick="checkAndShowConfirmModal()">Back to Schools</button>
            <button type="button" class="next-btn" style="background:#e67e22;" onclick="openMeasurementModal()">Take Measurement</button>
            <button type="button" class="next-btn" onclick="showSummary()">Proceed to Payment (0)</button>
        </div>
    </form>

    <!-- Size Selection Modal -->
    <div class="modal" id="sizeModal">
        <div class="modal-content">
            <div class="modal-header">
                <div style="display:flex; align-items:center; gap:15px; flex:1;">
                    <span class="modal-icon" id="modalIcon"></span>
                    <h3 id="modalTitle"></h3>
                </div>
                <button type="button" class="back-btn-modal" onclick="closeSizeModal()">Back</button>
            </div>
            <div class="sizes-grid" id="sizesGrid"></div>
            <div class="modal-actions">
                <button type="button" class="continue-shopping-btn" onclick="clearCurrentItem()">Clear This Item</button>
            </div>
        </div>
    </div>

    <!-- Order Summary Modal -->
    <div class="modal" id="selectedItemsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Order Summary</h3>
                <div class="school-name"><?php echo htmlspecialchars($school_name); ?></div>
                <span class="close" onclick="closeSummary()">×</span>
            </div>
            <div class="selected-items-list" id="summaryList">
                <div class="no-items">No items selected yet</div>
            </div>
            <div class="total-summary" id="totalSummary" style="display:none;">
                Total: Rs. <span id="grandTotal">0</span>
            </div>
            <div class="modal-actions">
                <button type="button" class="continue-shopping-btn" onclick="closeSummary()">Continue Shopping</button>
                <button type="submit" form="itemsForm" class="pay-bill-btn">Pay Bill</button>
            </div>
        </div>
    </div>

    <!-- Confirm Back Modal -->
    <!-- Beautiful Confirmation Modal for "Back to Schools" -->
<div class="modal" id="confirmModal" style="display:none; align-items:center; justify-content:center; z-index:9999;">
    <div class="modal-content" style="max-width:460px; width:90%; border-radius:18px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,0.35); background:white;">
        
        <!-- Header – Red-Orange gradient -->
        <div style="background:linear-gradient(135deg, #e74c3c, #c0392b); color:white; padding:22px 20px; text-align:center;">
            <h3 style="margin:0; font-size:24px; font-weight:700;">Leave This Page?</h3>
        </div>

        <!-- Body -->
        <div style="padding:28px 25px; text-align:center; color:#2c3e50;">
            <p style="font-size:17px; margin:0 0 18px 0; line-height:1.5;">
                You have selected items and/or added custom measurements.
            </p>
            <div style="background:#fff0f0; border:2px solid #e74c3c; border-radius:12px; padding:16px; color:#c0392b; font-weight:600; font-size:15px;">
                Going back will <strong>clear everything</strong> and cannot be undone.
            </div>
        </div>

        <!-- Buttons – Big, bold, colorful -->
        <div style="display:flex; gap:15px; padding:0 25px 30px;">
            <button type="button" 
                    class="confirm-cancel-btn" 
                    onclick="closeConfirm()"
                    style="flex:1; padding:16px; background:#95a5a6; color:white; border:none; border-radius:12px; font-size:17px; font-weight:600; cursor:pointer; transition:0.2s;">
                Cancel
            </button>
            <button type="button" 
                    class="confirm-clear-btn" 
                    onclick="confirmClear()"
                    style="flex:1; padding:16px; background:#e74c3c; color:white; border:none; border-radius:12px; font-size:17px; font-weight:600; cursor:pointer; transition:0.2s; box-shadow:0 6px 20px rgba(231,76,60,0.4);">
                Yes, Clear & Go Back
            </button>
        </div>
    </div>
</div>
</div>
<!-- Beautiful Centered Confirm Modal -->
<div class="modal" id="confirmDeleteModal" style="display:none; align-items:center; justify-content:center;">
    <div class="modal-content" style="max-width:420px; width:90%; border-radius:16px; box-shadow:0 20px 50px rgba(0,0,0,0.3);">
        <div class="modal-header" style="background:#e74c3c; color:white; border-radius:16px 16px 0 0; padding:20px; text-align:center;">
            <h3 style="margin:0; font-size:22px;">Remove Item?</h3>
        </div>
        <div style="padding:25px; text-align:center; font-size:16px; color:#444;">
            <p style="margin:0 0 15px 0;">Are you sure you want to remove this custom item?</p>
            <p style="margin:0; font-weight:600; color:#2c3e50;" id="confirmItemName"></p>
        </div>
        <div style="display:flex; gap:15px; padding:0 25px 25px;">
            <button type="button" 
                    onclick="closeConfirmDelete()" 
                    style="flex:1; padding:14px; background:#95a5a6; color:white; border:none; border-radius:10px; font-size:16px; cursor:pointer;">
                Cancel
            </button>
            <button type="button" 
                    id="confirmDeleteBtn" 
                    style="flex:1; padding:14px; background:#e74c3c; color:white; border:none; border-radius:10px; font-size:16px; font-weight:600; cursor:pointer;">
                Yes, Remove
            </button>
        </div>
    </div>
</div>
<!-- Hidden form to clear session -->
<form method="POST" id="clearForm" style="display:none;">
    <input type="hidden" name="clear_selections" value="1">
</form>

<!-- Measurement Modal -->
<?php $measHelper->renderModal(); ?>

<script>
// Global data
const allItems = <?php echo json_encode($items); ?>;
const brandCountPerItem = <?php echo json_encode($brandCountPerItem); ?>;
let selectedSizes = <?php echo json_encode($selectedSizes); ?>;
let currentKey = '';
let currentItemName = '';

// Helpers
function parse(str) {
    const map = {};
    if (str) str.split(',').forEach(p => {
        const [code, qty] = p.split(':');
        if (code) map[code] = parseInt(qty || 1);
    });
    return map;
}
function stringify(map) {
    return Object.entries(map).filter(([_, q]) => q > 0).map(([c, q]) => `${c}:${q}`).join(',');
}

// Modal Controls
function openMeasurementModal() {
    document.getElementById('measurementModal').style.display = 'flex';
}
function closeMeasurementModal() {
    document.getElementById('measurementModal').style.display = 'none';
}
function closeSizeModal() {
    document.getElementById('sizeModal').style.display = 'none';
    updateIndicators();
    updateHiddenOrderFields();
}
function closeSummary() {
    document.getElementById('selectedItemsModal').style.display = 'none';
}
function closeConfirm() {
    document.getElementById('confirmModal').style.display = 'none';
}

function checkAndShowConfirmModal() {
    if (Object.keys(selectedSizes).length > 0) {
        document.getElementById('confirmModal').style.display = 'flex';
    } else {
        location.href = 'dashboard.php';
    }
}
function confirmClear() {
    // First: delete everything via AJAX
    fetch(location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'clear_and_back=1'
    })
    .then(() => {
        // Then go back to dashboard
        location.href = 'dashboard.php';
    })
    .catch(() => {
        // Even if AJAX fails, still go back (data already safe or non-existent)
        location.href = 'dashboard.php';
    });
}
function showSummary() {
    const list = document.getElementById('summaryList');
    list.innerHTML = '';
    let totalAmount = 0;
    let totalQty = 0;

    Object.entries(selectedSizes).forEach(([key, str]) => {
        if (!str) return;
        const name = Object.keys(allItems).find(n => n.replace(/[^a-zA-Z0-9]/g, '_') === key);
        if (!name) return;
        const map = parse(str);
        Object.entries(map).forEach(([code, qty]) => {
            totalQty += qty;
            const [size, brand] = code.split('|');
            const item = allItems[name].find(i => i.size === size && i.brand === brand);
            if (!item) return;
            const amount = item.price * qty;
            totalAmount += amount;
            const showBrand = brandCountPerItem[name] > 1;
            const div = document.createElement('div');
            div.className = 'selected-item';
            div.innerHTML = `<div class="item-details"><div class="item-name">${name}</div><div class="item-size">${size} ${showBrand ? '('+brand+')' : ''} × ${qty}</div></div><div class="item-price">Rs. ${amount.toLocaleString()}</div>`;
            list.appendChild(div);
        });
    });

    if (totalAmount === 0) {
        list.innerHTML = '<div class="no-items">No items selected yet</div>';
        document.getElementById('totalSummary').style.display = 'none';
    } else {
        document.getElementById('totalSummary').style.display = 'block';
        document.getElementById('grandTotal').textContent = totalAmount.toLocaleString();
    }

    document.querySelectorAll('.next-btn').forEach(btn => {
        if (btn.onclick?.toString().includes('showSummary')) {
            btn.textContent = `Proceed to Payment (${totalQty})`;
        }
    });

    document.getElementById('selectedItemsModal').style.display = 'flex';
}

function updateIndicators() {
    let total = 0;
    document.querySelectorAll('.category-card').forEach(card => {
        const match = card.getAttribute('onclick')?.match(/'([^']+)'/);
        const key = match ? match[1] : null;
        const indicator = card.querySelector('.selected-indicator');
        if (key && selectedSizes[key]) {
            const qty = Object.values(parse(selectedSizes[key])).reduce((a,b) => a+b, 0);
            total += qty;
            if (indicator) {
                indicator.textContent = qty;
                indicator.style.display = 'flex';
            } else {
                const badge = document.createElement('div');
                badge.className = 'selected-indicator';
                badge.textContent = qty;
                card.appendChild(badge);
            }
            card.classList.add('has-selection');
        } else {
            if (indicator) indicator.style.display = 'none';
            card.classList.remove('has-selection');
        }
    });
    document.querySelectorAll('.next-btn').forEach(btn => {
        if (btn.textContent.includes('Proceed') || btn.textContent.includes('Payment')) {
            btn.textContent = `Proceed to Payment (${total})`;
        }
    });
}

function updateHiddenOrderFields() {
    const container = document.getElementById('dynamicInputs');
    container.innerHTML = '';
    Object.entries(selectedSizes).forEach(([key, str]) => {
        if (!str) return;
        const itemName = Object.keys(allItems).find(n => n.replace(/[^a-zA-Z0-9]/g, '_') === key);
        if (!itemName) return;
        const map = parse(str);
        Object.entries(map).forEach(([code, qty]) => {
            const [size, brand] = code.split('|');
            const item = allItems[itemName].find(i => i.size === size && i.brand === brand);
            if (!item) return;
            const index = btoa(Math.random()).substring(0,12);
            ['item_name', 'size', 'brand', 'price', 'quantity'].forEach(f => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `order[${index}][${f}]`;
                input.value = f === 'item_name' ? itemName : f === 'size' ? size : f === 'brand' ? brand : f === 'price' ? item.price : qty;
                container.appendChild(input);
            });
        });
    });
}

function save() {
    const fd = new FormData();
    fd.append('save_session', '1');
    Object.entries(selectedSizes).forEach(([k,v]) => fd.append(`selected_sizes[${k}]`, v));
    fetch('', { method: 'POST', body: fd });
}

function showSizeModal(key, name, emoji) {
    currentKey = key;
    currentItemName = name;
    document.getElementById('modalIcon').textContent = emoji;
    document.getElementById('modalTitle').textContent = name;
    const grid = document.getElementById('sizesGrid');
    grid.innerHTML = '';
    let sizes = [...(allItems[name] || [])];
    sizes.sort((a,b) => a.brand_id - b.brand_id || (a.size.includes(',') ? -1 : 1) || parseFloat(a.size.replace(/[^0-9.]/g,'')) - parseFloat(b.size.replace(/[^0-9.]/g,'')));
    const selected = parse(selectedSizes[key] || '');
    const showHeader = brandCountPerItem[name] > 1;
    let lastBrand = null;
        if (showHeader) {
        // Group sizes by brand
        const brands = {};
        sizes.forEach(s => {
            if (!brands[s.brand]) brands[s.brand] = [];
            brands[s.brand].push(s);
        });

        Object.keys(brands).forEach(brand => {
            // Brand Header - full row
            const header = document.createElement('div');
            header.style.cssText = 'grid-column: 1 / -1; background: #f0f2ff; padding: 14px 20px; border-radius: 12px; font-weight: 700; font-size: 1.1rem; color: #4a5568; margin: 20px 0 12px 0; text-align: left; border-left: 5px solid #667eea;';
            header.textContent = brand + ' Brand';
            grid.appendChild(header);

            // Sizes for this brand - in their own row
            brands[brand].forEach(s => {
                const code = `${s.size}|${s.brand}`;
                const qty = selected[code] || 0;

                const box = document.createElement('div');
                box.className = 'size-box' + (qty > 0 ? ' selected-in-modal has-quantity' : '');
                box.onclick = () => {
                    const map = parse(selectedSizes[key] || '');
                    map[code] = (map[code] || 0) + 1;
                    selectedSizes[key] = stringify(map);
                    if (!selectedSizes[key]) delete selectedSizes[key];

                    updateHiddenOrderFields();   // ← ADD THIS LINE ONLY
                    updateIndicators();          // ← AND THIS ONE

                    showSizeModal(key, name, emoji);
                    save();
                };
                box.innerHTML = `
                    <div class="size-label">${s.size}</div>
                    <div class="size-price">Rs. ${Number(s.price).toLocaleString()}</div>
                    ${qty > 0 ? `<div class="qty-badge">${qty}</div>` : ''}
                `;
                grid.appendChild(box);
            });
        });
    } else {
        // Single brand - just list sizes normally
        sizes.forEach(s => {
            const code = `${s.size}|${s.brand}`;
            const qty = selected[code] || 0;

            const box = document.createElement('div');
            box.className = 'size-box' + (qty > 0 ? ' selected-in-modal has-quantity' : '');
            box.onclick = () => {
                const map = parse(selectedSizes[key] || '');
                map[code] = (map[code] || 0) + 1;
                selectedSizes[key] = stringify(map);
                if (!selectedSizes[key]) delete selectedSizes[key];
                showSizeModal(key, name, emoji);
                save();
            };
            box.innerHTML = `
                <div class="size-label">${s.size}</div>
                <div class="size-price">Rs. ${Number(s.price).toLocaleString()}</div>
                ${qty > 0 ? `<div class="qty-badge">${qty}</div>` : ''}
            `;
            grid.appendChild(box);
        });
    }
    document.getElementById('sizeModal').style.display = 'flex';
}

function clearCurrentItem() {
    delete selectedSizes[currentKey];
    showSizeModal(currentKey, currentItemName, document.getElementById('modalIcon').textContent);
    save();
}

// Measurement Functions
function addMeasField() {
    const container = document.getElementById('measFields');
    const row = document.createElement('div');
    row.className = 'meas-row';
    row.style.cssText = 'display:flex;gap:10px;margin-bottom:10px;';
    row.innerHTML = `<input type="text" placeholder="Field (e.g. Collar)" class="meas-label" style="flex:1;padding:12px;border-radius:8px;border:1px solid #ccc;">
                     <input type="text" placeholder="Value (e.g. 41)" class="meas-value" style="width:100px;padding:12px;border-radius:8px;border:1px solid #ccc;">
                     <button type="button" onclick="this.parentElement.remove()" style="background:#e74c3c;color:white;border:none;padding:0 12px;border-radius:6px;">×</button>`;
    container.appendChild(row);
}

function saveMeasurementItem() {
    const name = document.getElementById('measItemName').value.trim();
    const price = parseFloat(document.getElementById('measPrice').value) || 0;
    const rows = document.querySelectorAll('#measFields .meas-row');
    const measurements = {};
    for (const row of rows) {
        const label = row.querySelector('.meas-label').value.trim();
        const value = row.querySelector('.meas-value').value.trim();
        if (label && value) measurements[label] = value;
    }
    const quantity = parseInt(document.getElementById('measQuantity').value) || 1;
    if (!name || price <= 0 || Object.keys(measurements).length === 0) {
        alert('Please fill all fields: name, price, and at least one measurement.');
        return;
    }
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'add_measurement', name, price,quantity:quantity, measurements: JSON.stringify(measurements) })
    }).then(() => location.reload());
}
function removeMeasItem(id) {
    if (!confirm('Remove this custom item?')) return;

    fetch(location.href, {  // better than ''
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'action': 'remove_measurement',
            'id': id
        })
    })
    .then(r => r.json())
    .then(result => {
        console.log('Delete result:', result);
        if (result.success) {
            location.reload();  // or better: remove DOM element without reload
        } else {
            alert('Failed to delete. Item may already be removed.');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Network error');
    });
}
// Close modal when clicking outside
document.addEventListener('click', e => {
    ['measurementModal', 'sizeModal', 'selectedItemsModal', 'confirmModal'].forEach(id => {
        const modal = document.getElementById(id);
        if (e.target === modal) modal.style.display = 'none';
    });
});

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    updateIndicators();
    updateHiddenOrderFields();
    setInterval(save, 8000);
});
function openMeasurementModal() {
    document.getElementById('measurementModal').style.display = 'flex';
    
    // Reset form fields
    document.getElementById('measItemName').value = '';
    document.getElementById('measPrice').value = '';
    document.getElementById('measQuantity').value = '1';  // ← Always start with 1
    
    // Clear all measurement rows except the first one
    const container = document.getElementById('measFields');
    const firstRow = container.querySelector('.meas-row');
    container.innerHTML = '';
    if (firstRow) container.appendChild(firstRow.cloneNode(true));
    
    // Clear inputs in the first row
    const inputs = container.querySelectorAll('input');
    inputs.forEach(input => input.value = '');
}
</script>
</body>
</html>