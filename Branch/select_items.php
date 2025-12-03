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

// Pre-calculate how many brands each item has
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
        .size-box.selected {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-color: #5a6fd8;
            transform: scale(1.05);
        }
        .size-box.selected::after {
            content: '✓';
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
        .size-label {
            font-weight: 700;
            font-size: 1.3rem;
            margin-bottom: 8px;
        }
        .size-price {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        .qty-badge {
            display: inline-block;
            background: rgba(255,255,255,0.3);
            color: white;
            font-weight: bold;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.9rem;
            min-width: 28px;
            margin-top: 8px;
        }
        .brand-header {
            grid-column: 1 / -1;
            font-size: 1.4rem;
            font-weight: 700;
            color: #667eea;
            text-align: center;
            margin: 25px 0 15px;
            padding: 10px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 12px;
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

        <?php foreach ($items as $item_name => $sizes): 
            $safe_key = preg_replace('/[^a-zA-Z0-9]/', '_', $item_name);
        ?>
            <input type="hidden" name="selected_sizes[<?php echo $safe_key; ?>]" id="selected_<?php echo $safe_key; ?>" value="<?php echo htmlspecialchars($selectedSizes[$safe_key] ?? ''); ?>">
        <?php endforeach; ?>

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
            <button type="button" class="next-btn" onclick="showSummary()">Pay Bill (0)</button>
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
    <div class="confirm-modal" id="confirmModal">
        <div class="confirm-content">
            <div class="confirm-header">
                <span class="confirm-icon">Warning</span>
                <h3>Leave This Page?</h3>
            </div>
            <div class="confirm-body">
                <p>You have selected items. Going back will clear all selections.</p>
                <div class="confirm-warning">This action cannot be undone.</div>
            </div>
            <div class="confirm-actions">
                <button type="button" class="confirm-cancel-btn" onclick="closeConfirm()">Cancel</button>
                <button type="button" class="confirm-clear-btn" onclick="confirmClear()">Yes, Clear & Go Back</button>
            </div>
        </div>
    </div>
</div>

<form method="POST" id="clearForm" style="display:none;">
    <input type="hidden" name="clear_selections" value="1">
</form>

<script>
const allItems = <?php echo json_encode($items); ?>;
const brandCountPerItem = <?php echo json_encode($brandCountPerItem); ?>;
let selectedSizes = <?php echo json_encode($selectedSizes); ?>;
let currentKey = '';
let currentItemName = '';

// Parse "size|brand:qty" format
function parse(str) {
    const map = {};
    if (str) str.split(',').forEach(p => {
        const [code, qty] = p.split(':');
        if (code) map[code] = parseInt(qty || 1);
    });
    return map;
}
function stringify(map) {
    return Object.entries(map)
        .filter(([_, q]) => q > 0)
        .map(([c, q]) => `${c}:${q}`)
        .join(',');
}

function showSizeModal(key, name, emoji) {
    currentKey = key;
    currentItemName = name;
    document.getElementById('modalIcon').textContent = emoji;
    document.getElementById('modalTitle').textContent = name;
    const grid = document.getElementById('sizesGrid');
    grid.innerHTML = '';

    let sizes = [...(allItems[name] || [])];
    sizes.sort((a, b) => a.brand_id - b.brand_id || 
        (a.size.includes(',') ? -1 : 1) || 
        parseFloat(a.size.replace(/[^0-9.]/g, '')) - parseFloat(b.size.replace(/[^0-9.]/g, ''))
    );

    const selected = parse(selectedSizes[key] || '');
    const showBrandHeader = brandCountPerItem[name] > 1;
    let lastBrand = null;

    sizes.forEach(s => {
        // Show brand header only if multiple brands exist
        if (showBrandHeader && s.brand !== lastBrand) {
            lastBrand = s.brand;
            const h = document.createElement('div');
            h.className = 'brand-header';
            h.textContent = s.brand + ' Brand';
            grid.appendChild(h);
        }

        const code = `${s.size}|${s.brand}`;
        const qty = selected[code] || 0;

        const box = document.createElement('div');
        box.className = `size-box ${qty > 0 ? 'selected' : ''}`;
        box.onclick = () => addOne(key, code);

        box.innerHTML = `
            <div class="size-label">${s.size}</div>
            <div class="size-price">Rs. ${Number(s.price).toLocaleString()}</div>
            ${qty > 0 ? `<div class="qty-badge">${qty}</div>` : ''}
        `;
        grid.appendChild(box);
    });

    document.getElementById('sizeModal').style.display = 'flex';
    updateIndicators();
}

function addOne(key, code) {
    const map = parse(selectedSizes[key] || '');
    map[code] = (map[code] || 0) + 1;
    selectedSizes[key] = stringify(map);
    if (!selectedSizes[key]) delete selectedSizes[key];
    document.getElementById('selected_' + key).value = selectedSizes[key] || '';
    showSizeModal(key, currentItemName, document.getElementById('modalIcon').textContent);
    updateIndicators();
    save();
}

function clearCurrentItem() {
    delete selectedSizes[currentKey];
    document.getElementById('selected_' + currentKey).value = '';
    showSizeModal(currentKey, currentItemName, document.getElementById('modalIcon').textContent);
    updateIndicators();
    save();
}

function updateIndicators() {
    let totalItems = 0;
    document.querySelectorAll('.category-card').forEach(card => {
        const match = card.getAttribute('onclick').match(/'([^']+)'/);
        const key = match ? match[1] : null;
        const indicator = card.querySelector('.selected-indicator');

        if (key && selectedSizes[key]) {
            const qty = Object.values(parse(selectedSizes[key])).reduce((a, b) => a + b, 0);
            totalItems += qty;

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

    document.querySelector('.next-btn').textContent = `Pay Bill (${totalItems})`;
}

function showSummary() {
    const list = document.getElementById('summaryList');
    list.innerHTML = '';
    let totalAmount = 0;

    Object.entries(selectedSizes).forEach(([key, str]) => {
        if (!str) return;
        const name = Object.keys(allItems).find(n => n.replace(/[^a-zA-Z0-9]/g, '_') === key);
        const map = parse(str);

        Object.entries(map).forEach(([code, qty]) => {
            const [size, brand] = code.split('|');
            const item = allItems[name].find(i => i.size === size && i.brand === brand);
            if (!item) return;

            const amount = item.price * qty;
            totalAmount += amount;

            const showBrand = brandCountPerItem[name] > 1;

            const div = document.createElement('div');
            div.className = 'selected-item';
            div.innerHTML = `
                <div class="item-details">
                    <div class="item-name">${name}</div>
                    <div class="item-size">${size} ${showBrand ? `(${brand})` : ''} × ${qty}</div>
                </div>
                <div class="item-price">Rs. ${amount.toLocaleString()}</div>
            `;
            list.appendChild(div);
        });
    });

    if (totalAmount === 0) {
        list.innerHTML = '<div class="no-items">No items selected yet</div>';
    } else {
        document.getElementById('totalSummary').style.display = 'block';
        document.getElementById('grandTotal').textContent = totalAmount.toLocaleString();
    }

    document.getElementById('selectedItemsModal').style.display = 'flex';
}

function save() {
    const fd = new FormData();
    fd.append('save_session', '1');
    Object.entries(selectedSizes).forEach(([k, v]) => fd.append(`selected_sizes[${k}]`, v));
    fetch('', { method: 'POST', body: fd });
}

function closeSizeModal() { document.getElementById('sizeModal').style.display = 'none'; updateIndicators(); }
function closeSummary() { document.getElementById('selectedItemsModal').style.display = 'none'; }
function closeConfirm() { document.getElementById('confirmModal').style.display = 'none'; }
function checkAndShowConfirmModal() {
    if (Object.keys(selectedSizes).length > 0) {
        document.getElementById('confirmModal').style.display = 'flex';
    } else {
        location.href = 'dashboard.php';
    }
}
function confirmClear() { document.getElementById('clearForm').submit(); }

document.addEventListener('DOMContentLoaded', updateIndicators);
setInterval(save, 8000);
</script>
</body>
</html>