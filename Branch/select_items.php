<?php
session_start();
require_once '../Common/connection.php';
require_once 'shirt_selector.php'; // Use the shirt selector class

$schoolId = $_SESSION['selected_school_id'] ?? 0;
$schoolName = $_SESSION['selected_school_name'] ?? 'Other';

// Initialize shirt selector
$shirtSelector = new ShirtSelector($pdo, $schoolName);

if (!isset($_SESSION['selected_sizes'])) {
    $_SESSION['selected_sizes'] = [];
}
$selectedSizes = $_SESSION['selected_sizes'];

$categories = [
    'shirts' => ['name' => 'Shirts', 'icon' => '👔', 'sizes' => $shirtSelector->getShirts()],
    'pants' => ['name' => 'Pants', 'icon' => '👖', 'sizes' => []],
    'skirts' => ['name' => 'Skirts', 'icon' => '👗', 'sizes' => []],
    'coats' => ['name' => 'Coats', 'icon' => '🧥', 'sizes' => []],
    'tracksuits' => ['name' => 'Tracksuits', 'icon' => '🏃‍♂️', 'sizes' => []],
    'sweaters' => ['name' => 'Sweaters', 'icon' => '🧶', 'sizes' => []],
    'stockings' => ['name' => 'Stockings', 'icon' => '🧦', 'sizes' => []],
    'shoes' => ['name' => 'Shoes', 'icon' => '👞', 'sizes' => []]
];

// Load other categories (temporary - will create selectors later)
try {
    // PANTS - temporary default
    $stmt = $pdo->query("SELECT size, price_other FROM pants ORDER BY size");
    $pants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pants as &$pant) {
        $pant['display_price'] = $pant['price_other'];
    }
    $categories['pants']['sizes'] = $pants;
    
    // SKIRTS - temporary default
    $stmt = $pdo->query("SELECT size, price_indian FROM skirts ORDER BY size");
    $skirts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($skirts as &$skirt) {
        $skirt['display_price'] = $skirt['price_indian'];
    }
    $categories['skirts']['sizes'] = $skirts;
    
    // COATS
    $stmt = $pdo->query("SELECT size, price FROM coats ORDER BY size");
    $coats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($coats as &$coat) {
        $coat['display_price'] = $coat['price'];
    }
    $categories['coats']['sizes'] = $coats;
    
    // TRACKSUITS
    $stmt = $pdo->query("SELECT size, price_3pic FROM tracksuits ORDER BY CAST(size AS UNSIGNED)");
    $tracksuits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($tracksuits as &$tracksuit) {
        $tracksuit['display_price'] = $tracksuit['price_3pic'];
    }
    $categories['tracksuits']['sizes'] = $tracksuits;
    
    // SWEATERS - temporary default
    $stmt = $pdo->query("SELECT size, price_other FROM sweaters ORDER BY CAST(size AS UNSIGNED)");
    $sweaters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($sweaters as &$sweater) {
        $sweater['display_price'] = $sweater['price_other'];
    }
    $categories['sweaters']['sizes'] = $sweaters;
    
    // STOCKINGS
    $stmt = $pdo->query("SELECT name as size, price FROM stockings ORDER BY name");
    $stockings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($stockings as &$stocking) {
        $stocking['display_price'] = $stocking['price'];
    }
    $categories['stockings']['sizes'] = $stockings;
    
    // SHOES
    $stmt = $pdo->query("SELECT size, price_white FROM shoes ORDER BY size");
    $shoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($shoes as &$shoe) {
        $shoe['display_price'] = $shoe['price_white'];
    }
    $categories['shoes']['sizes'] = $shoes;
    
} catch(PDOException $e) {
    $error = "Error loading items: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Sizes - Uniform Shop</title>
    <link rel="stylesheet" href="select_items.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>👕 Select Item Sizes</h1>
            <p>School: <strong><?php echo htmlspecialchars($schoolName); ?></strong></p>
            <?php if (count($selectedSizes) > 0): ?>
                <div class="selected-count">
                    ✅ <strong><?php echo count($selectedSizes); ?></strong> item<?php echo count($selectedSizes) > 1 ? 's' : ''; ?> selected
                </div>
            <?php endif; ?>
        </header>

        <?php if (isset($error)): ?>
            <div style="background: #fee; color: #c33; padding: 15px; border-radius: 10px; margin: 20px; text-align: center;"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Size Selection Modal -->
        <div class="modal" id="sizeModal">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="modal-icon" id="modalIcon"></span>
                    <h3 id="modalTitle">Select Size</h3>
                    <button type="button" class="back-btn-modal" onclick="closeSizeModal()">← Back</button>
                </div>
                <div class="sizes-grid" id="sizesGrid">
                    <!-- Sizes will be populated by JavaScript -->
                </div>
            </div>
        </div>

        <!-- Selected Items Preview Modal -->
        <div class="modal" id="selectedItemsModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Order Summary</h3>
                    <div class="school-name"><?php echo htmlspecialchars($schoolName); ?></div>
                    <span class="close" onclick="closeSelectedItemsModal()">&times;</span>
                </div>
                
                <div class="selected-items-list" id="selectedItemsList">
                    <!-- Items will be populated by JavaScript -->
                </div>
                
                <div class="modal-actions">
                    <button class="action-btn cancel-btn" onclick="closeSelectedItemsModal()">
                        Continue Selecting
                    </button>
                    <button type="submit" form="itemsForm" class="action-btn proceed-btn" id="confirmProceedBtn">
                        Proceed to Bill
                    </button>
                </div>
            </div>
        </div>

        <!-- MAIN FORM -->
        <form method="POST" action="bill.php" id="itemsForm">
            <input type="hidden" name="school_id" value="<?php echo $schoolId; ?>">
            <input type="hidden" name="school_name" value="<?php echo htmlspecialchars($schoolName); ?>">
            
            <?php foreach ($categories as $key => $category): ?>
                <input type="hidden" name="selected_sizes[<?php echo $key; ?>]" id="selected_<?php echo $key; ?>" value="<?php echo htmlspecialchars($selectedSizes[$key] ?? ''); ?>">
            <?php endforeach; ?>
            
            <div class="categories-grid" id="categoriesGrid">
                <?php foreach ($categories as $key => $category): ?>
                    <div class="category-card <?php echo !empty($category['sizes']) ? '' : 'disabled'; ?> <?php echo isset($selectedSizes[$key]) ? 'selected-category' : ''; ?>" 
                         onclick="showSizeModal('<?php echo $key; ?>', '<?php echo $category['name']; ?>', '<?php echo $category['icon']; ?>')">
                        <div class="category-icon"><?php echo $category['icon']; ?></div>
                        <div class="category-name"><?php echo $category['name']; ?></div>
                        <?php if (isset($selectedSizes[$key])): ?>
                            <div class="selected-size">✓ <?php echo htmlspecialchars($selectedSizes[$key]); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- MAIN ACTION BUTTONS -->
            <div class="actions">
                <a href="dashboard.php" class="back-btn">← Back to Schools</a>
                <button type="button" class="next-btn" id="proceedBtn" onclick="showSelectedItemsModal()" <?php echo count($selectedSizes) == 0 ? 'disabled' : ''; ?>>
                    <?php echo count($selectedSizes) == 0 ? 'Proceed to Bill' : 'Proceed to Bill (' . count($selectedSizes) . ' items)'; ?>
                </button>
            </div>
        </form>
    </div>

    <script>
        let selectedSizes = <?php echo json_encode($selectedSizes); ?>;
        const categoriesData = <?php echo json_encode($categories); ?>;
        let currentCategoryKey = '';

        function showSizeModal(categoryKey, categoryName, categoryIcon) {
            currentCategoryKey = categoryKey;
            const modal = document.getElementById('sizeModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalIcon = document.getElementById('modalIcon');
            const sizesGrid = document.getElementById('sizesGrid');
            
            modalTitle.textContent = `Select ${categoryName} Size`;
            modalIcon.textContent = categoryIcon;
            
            sizesGrid.innerHTML = '';
            const sizes = categoriesData[categoryKey].sizes;
            
            if (sizes.length === 0) {
                sizesGrid.innerHTML = '<div class="no-sizes">No sizes available</div>';
            } else {
                sizes.forEach(sizeData => {
                    const sizeBtn = document.createElement('div');
                    sizeBtn.className = 'size-btn';
                    const isSelected = selectedSizes[categoryKey] === sizeData.size;
                    if (isSelected) sizeBtn.classList.add('selected');
                    
                    const price = sizeData.display_price || 0;
                    
                    sizeBtn.innerHTML = `
                        <div class="size-label">${sizeData.size}</div>
                        <div class="size-price">Rs. ${parseFloat(price).toLocaleString()}</div>
                    `;
                    
                    sizeBtn.onclick = () => selectSize(categoryKey, sizeData.size, sizeBtn);
                    sizesGrid.appendChild(sizeBtn);
                });
            }
            
            modal.style.display = 'flex';
        }

        function selectSize(categoryKey, size, button) {
            document.querySelectorAll('#sizesGrid .size-btn').forEach(btn => btn.classList.remove('selected'));
            button.classList.add('selected');
            selectedSizes[categoryKey] = size;
            document.getElementById(`selected_${categoryKey}`).value = size;
            updateCategoryCard(categoryKey);
        }

        function closeSizeModal() {
            document.getElementById('sizeModal').style.display = 'none';
            updateProceedButton();
            saveToSession();
        }

        function updateCategoryCard(categoryKey) {
            const categoryCards = document.querySelectorAll('.category-card');
            categoryCards.forEach(card => {
                const onclickAttr = card.getAttribute('onclick');
                if (onclickAttr && onclickAttr.includes(categoryKey)) {
                    card.classList.add('selected-category');
                    const existingSize = card.querySelector('.selected-size');
                    if (existingSize) {
                        existingSize.textContent = `✓ ${selectedSizes[categoryKey]}`;
                    } else {
                        const sizeDiv = document.createElement('div');
                        sizeDiv.className = 'selected-size';
                        sizeDiv.textContent = `✓ ${selectedSizes[categoryKey]}`;
                        card.appendChild(sizeDiv);
                    }
                }
            });
        }

        function updateProceedButton() {
            const selectedCount = Object.keys(selectedSizes).filter(key => selectedSizes[key]).length;
            const proceedBtn = document.getElementById('proceedBtn');
            const header = document.querySelector('.header');
            
            if (selectedCount === 0) {
                proceedBtn.disabled = true;
                proceedBtn.style.opacity = '0.5';
                proceedBtn.innerHTML = 'Proceed to Bill';
                if (document.querySelector('.selected-count')) {
                    document.querySelector('.selected-count').remove();
                }
            } else {
                proceedBtn.disabled = false;
                proceedBtn.style.opacity = '1';
                proceedBtn.innerHTML = `Proceed to Bill (${selectedCount} items)`;
                
                if (!document.querySelector('.selected-count')) {
                    const countDiv = document.createElement('div');
                    countDiv.className = 'selected-count';
                    countDiv.innerHTML = `✅ <strong>${selectedCount}</strong> item${selectedCount > 1 ? 's' : ''} selected`;
                    header.appendChild(countDiv);
                } else {
                    document.querySelector('.selected-count').innerHTML = `✅ <strong>${selectedCount}</strong> item${selectedCount > 1 ? 's' : ''} selected`;
                }
            }
        }

        function showSelectedItemsModal() {
            const modal = document.getElementById('selectedItemsModal');
            const itemsList = document.getElementById('selectedItemsList');
            
            itemsList.innerHTML = '';
            let hasItems = false;
            let totalAmount = 0;
            
            for (const [categoryKey, size] of Object.entries(selectedSizes)) {
                if (size) {
                    const category = categoriesData[categoryKey];
                    const sizeData = category.sizes.find(s => s.size === size);
                    
                    if (sizeData) {
                        hasItems = true;
                        const price = sizeData.display_price || 0;
                        totalAmount += parseFloat(price);
                        
                        const itemDiv = document.createElement('div');
                        itemDiv.className = 'selected-item';
                        itemDiv.innerHTML = `
                            <div class="item-details">
                                <span class="item-name">${category.name}</span>
                                <span class="item-size">Size: ${size}</span>
                            </div>
                            <div class="item-price">Rs. ${parseFloat(price).toLocaleString()}</div>
                        `;
                        itemsList.appendChild(itemDiv);
                    }
                }
            }
            
            if (!hasItems) {
                itemsList.innerHTML = '<div class="no-items">No items selected yet</div>';
            } else {
                const totalDiv = document.createElement('div');
                totalDiv.style.cssText = `
                    background: linear-gradient(135deg, #27ae60, #2ecc71);
                    color: white;
                    padding: 20px;
                    border-radius: 15px;
                    margin-top: 15px;
                    text-align: center;
                    font-size: 1.3rem;
                    font-weight: 700;
                `;
                totalDiv.innerHTML = `💰 Total: Rs. ${totalAmount.toLocaleString()}`;
                itemsList.appendChild(totalDiv);
            }
            
            modal.style.display = 'flex';
        }

        function closeSelectedItemsModal() {
            document.getElementById('selectedItemsModal').style.display = 'none';
        }

        function saveToSession() {
            const formData = new FormData();
            formData.append('save_session', '1');
            Object.keys(selectedSizes).forEach(key => {
                if (selectedSizes[key]) {
                    formData.append(`selected_sizes[${key}]`, selectedSizes[key]);
                }
            });
            
            fetch('', {
                method: 'POST',
                body: formData
            });
        }

        // Initialize
        updateProceedButton();
    </script>
</body>
</html>