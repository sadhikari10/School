<?php
session_start();

// **CRITICAL: Check if user is logged in**
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// **CRITICAL: Check if school is selected**
if (!isset($_SESSION['selected_school_id']) || !isset($_SESSION['selected_school_name'])) {
    header("Location: dashboard.php");
    exit();
}

require_once '../Common/connection.php';

// Include ALL selectors
require_once 'shirt_selector.php';
require_once 'pant_selector.php';
require_once 'skirt_selector.php';
require_once 'coat_selector.php';
require_once 'tracksuit_selector.php';
require_once 'sweater_selector.php';
require_once 'stocking_selector.php';
require_once 'shoe_selector.php';

// ============================================
// **PERSISTENT SELECTIONS - SAVE/LOAD LOGIC**
// ============================================

$schoolId = $_SESSION['selected_school_id'];
$schoolName = $_SESSION['selected_school_name'];

// **CLEAR ALL SELECTIONS** via POST
if (isset($_POST['clear_selections']) && $_POST['clear_selections'] === '1') {
    $_SESSION['selected_sizes'] = [];
    header('Location: dashboard.php');
    exit;
}

// **LOAD selections from SESSION**
if (!isset($_SESSION['selected_sizes'])) {
    $_SESSION['selected_sizes'] = [];
}

// **SAVE selections to SESSION**
if ($_POST['save_session'] ?? false) {
    foreach ($_POST['selected_sizes'] ?? [] as $key => $value) {
        $_SESSION['selected_sizes'][$key] = $value;
    }
    exit(json_encode(['status' => 'saved']));
}

$selectedSizes = $_SESSION['selected_sizes'];

// Initialize ALL selectors
$shirtSelector = new ShirtSelector($pdo, $schoolName);
$pantSelector = new PantSelector($pdo, $schoolName);
$skirtSelector = new SkirtSelector($pdo);
$coatSelector = new CoatSelector($pdo);
$tracksuitSelector = new TracksuitSelector($pdo);
$sweaterSelector = new SweaterSelector($pdo, $schoolName);
$stockingSelector = new StockingSelector($pdo);
$shoeSelector = new ShoeSelector($pdo);

$categories = [
    'shirts' => ['name' => 'Shirts', 'icon' => '👔', 'sizes' => $shirtSelector->getShirts()],
    'pants' => ['name' => 'Pants', 'icon' => '👖', 'sizes' => $pantSelector->getPants()],
    'skirts' => ['name' => 'Skirts', 'icon' => '👗', 'sizes' => $skirtSelector->getSkirts()],
    'coats' => ['name' => 'Coats', 'icon' => '🧥', 'sizes' => $coatSelector->getCoats()],
    'tracksuits' => ['name' => 'Tracksuits', 'icon' => '🏃‍♂️', 'sizes' => $tracksuitSelector->getTracksuits()],
    'sweaters' => ['name' => 'Sweaters', 'icon' => '🧶', 'sizes' => $sweaterSelector->getSweaters()],
    'stockings' => ['name' => 'Stockings', 'icon' => '🧦', 'sizes' => $stockingSelector->getStockings()],
    'shoes' => ['name' => 'Shoes', 'icon' => '👞', 'sizes' => $shoeSelector->getShoes()]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Sizes - Uniform Shop</title>
    <link rel="stylesheet" href="select_items.css">
    <style>
        /* Logout button - top right */
        .logout-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        .logout-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: bold;
        }
        .logout-btn:hover {
            background: #c0392b;
        }
    </style>
</head>
<body>
    <!-- Logout button - top right -->
    <div class="logout-container">
        <a href="logout.php" class="logout-btn" onclick="return confirm('Are you sure you want to logout?')">
            Logout
        </a>
    </div>

    <div class="container">
        <header class="header">
            <h1>👕 Select Item Sizes</h1>
            <p>School: <strong><?php echo htmlspecialchars($schoolName); ?></strong></p>
        </header>

        <!-- Rest of your HTML remains EXACTLY the same -->
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
                    <h3>🛒 Order Summary</h3>
                    <div class="school-name"><?php echo htmlspecialchars($schoolName); ?></div>
                    <span class="close" onclick="closeSelectedItemsModal()">&times;</span>
                </div>
                
                <div class="selected-items-list" id="selectedItemsList">
                    <!-- Items will be populated by JavaScript -->
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="continue-shopping-btn" onclick="closeSelectedItemsModal()">
                        🛍️ Continue Shopping
                    </button>
                    <button type="submit" form="itemsForm" class="pay-bill-btn" id="confirmProceedBtn">
                        💳 Pay Bill
                    </button>
                </div>
            </div>
        </div>

        <!-- **CUSTOM CONFIRMATION POPUP** -->
        <div class="confirm-modal" id="confirmClearModal">
            <div class="confirm-content">
                <div class="confirm-header">
                    <span class="confirm-icon">🗑️</span>
                    <h3>Clear All Selections?</h3>
                </div>
                <div class="confirm-body">
                    <p>You have selected items. This action will <strong>clear everything</strong> and return you to the schools list.</p>
                    <p class="confirm-warning">This cannot be undone!</p>
                </div>
                <div class="confirm-actions">
                    <button type="button" class="confirm-cancel-btn" onclick="closeConfirmModal()">
                        ❌ Cancel
                    </button>
                    <button type="button" class="confirm-clear-btn" onclick="confirmClearSelections()">
                        ✅ Yes, Clear All
                    </button>
                </div>
            </div>
        </div>

        <!-- HIDDEN FORM FOR CLEARING SELECTIONS -->
        <form method="POST" id="clearForm" style="display: none;">
            <input type="hidden" name="clear_selections" value="1">
        </form>

        <!-- MAIN FORM -->
        <form method="POST" action="bill.php" id="itemsForm">
            <input type="hidden" name="school_id" value="<?php echo $schoolId; ?>">
            <input type="hidden" name="school_name" value="<?php echo htmlspecialchars($schoolName); ?>">
            
            <?php foreach ($categories as $key => $category): ?>
                <input type="hidden" name="selected_sizes[<?php echo $key; ?>]" id="selected_<?php echo $key; ?>" value="<?php echo htmlspecialchars($selectedSizes[$key] ?? ''); ?>">
            <?php endforeach; ?>
            
            <div class="categories-grid" id="categoriesGrid">
                <?php foreach ($categories as $key => $category): ?>
                    <div class="category-card <?php echo !empty($category['sizes']) ? '' : 'disabled'; ?>" 
                         onclick="showSizeModal('<?php echo $key; ?>', '<?php echo $category['name']; ?>', '<?php echo $category['icon']; ?>')"
                         style="position: relative;">
                        <div class="category-icon"><?php echo $category['icon']; ?></div>
                        <div class="category-name"><?php echo $category['name']; ?></div>
                        <?php if (isset($selectedSizes[$key]) && $selectedSizes[$key]): ?>
                            <div class="selected-indicator">✓</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- VISIBLE BOTTOM BUTTONS -->
            <div class="actions">
                <button type="button" class="back-btn" onclick="checkAndShowConfirmModal()" title="Return to schools">
                    ← Back to Schools
                </button>
                <button type="button" class="next-btn" id="proceedBtn" onclick="showSelectedItemsModal()">
                    💳 Pay Bill (<?php 
                        $totalSelected = 0;
                        foreach ($selectedSizes as $selection) {
                            if ($selection) $totalSelected += substr_count($selection, ',') + 1;
                        }
                        echo $totalSelected ?: 0; 
                    ?> items)
                </button>
            </div>
        </form>
    </div>

    <!-- Your existing JavaScript remains EXACTLY the same -->
    <script>
        // **LOAD PERSISTENT SELECTIONS** from PHP
        let selectedSizes = <?php echo json_encode($selectedSizes); ?>;
        const categoriesData = <?php echo json_encode($categories); ?>;
        let currentCategoryKey = '';
        let currentCategorySelections = {};

        // **CHECK IF NEEDS CONFIRMATION AND SHOW MODAL**
        function checkAndShowConfirmModal() {
            const hasSelections = Object.values(selectedSizes).some(val => val);
            if (hasSelections) {
                document.getElementById('confirmClearModal').style.display = 'flex';
            } else {
                // No selections, clear immediately
                clearAllSelectionsAndRedirect();
            }
        }

        // **CLOSE CONFIRMATION MODAL**
        function closeConfirmModal() {
            document.getElementById('confirmClearModal').style.display = 'none';
        }

        // **CONFIRM CLEAR AND PROCEED**
        function confirmClearSelections() {
            closeConfirmModal();
            clearAllSelectionsAndRedirect();
        }

        // **CLEAR ALL SELECTIONS AND REDIRECT**
        function clearAllSelectionsAndRedirect() {
            // Clear JavaScript object immediately
            selectedSizes = {};
            
            // Clear all hidden form inputs
            const hiddenInputs = document.querySelectorAll('input[name^="selected_sizes"]');
            hiddenInputs.forEach(input => {
                input.value = '';
            });
            
            // Remove all selection indicators
            const categoryCards = document.querySelectorAll('.category-card');
            categoryCards.forEach(card => {
                const indicator = card.querySelector('.selected-indicator');
                if (indicator) indicator.remove();
            });
            
            // Update proceed button
            updateProceedButton();
            
            console.log('🗑️ All selections cleared!');
            
            // Submit hidden form to clear PHP SESSION and redirect
            document.getElementById('clearForm').submit();
            
            return false;
        }

        function showSizeModal(categoryKey, categoryName, categoryIcon) {
            currentCategoryKey = categoryKey;
            const modal = document.getElementById('sizeModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalIcon = document.getElementById('modalIcon');
            const sizesGrid = document.getElementById('sizesGrid');
            
            modalTitle.textContent = `Select ${categoryName} Size`;
            modalIcon.textContent = categoryIcon;
            
            // **LOAD PREVIOUS SELECTIONS** for this category
            currentCategorySelections = {};
            if (selectedSizes[categoryKey]) {
                selectedSizes[categoryKey].split(',').forEach(item => {
                    currentCategorySelections[item] = true;
                });
            }
            
            sizesGrid.innerHTML = '';
            const sizes = categoriesData[categoryKey].sizes;
            
            if (sizes.length === 0) {
                sizesGrid.innerHTML = '<div class="no-sizes">No sizes available</div>';
            } else {
                const sections = {};
                sizes.forEach(sizeData => {
                    const section = sizeData.section || 'Default';
                    if (!sections[section]) sections[section] = [];
                    sections[section].push(sizeData);
                });
                
                Object.keys(sections).sort().forEach(sectionName => {
                    if (Object.keys(sections).length > 1) {
                        const sectionHeader = document.createElement('div');
                        sectionHeader.style.cssText = `
                            grid-column: 1 / -1;
                            background: linear-gradient(135deg, #667eea, #764ba2);
                            color: white;
                            padding: 15px;
                            border-radius: 10px;
                            font-weight: 700;
                            font-size: 1.1rem;
                            text-align: center;
                            margin-bottom: 15px;
                            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                        `;
                        sectionHeader.innerHTML = `<strong>${sectionName}</strong>`;
                        sizesGrid.appendChild(sectionHeader);
                    }
                    
                    sections[sectionName].forEach(sizeData => {
                        const sizeKey = sizeData.section ? `${sizeData.size}|${sizeData.section}` : sizeData.size;
                        const isSelected = currentCategorySelections[sizeKey];
                        
                        const sizeBtn = document.createElement('div');
                        sizeBtn.className = `size-btn ${isSelected ? 'selected' : ''}`;
                        const price = sizeData.display_price || 0;
                        
                        sizeBtn.innerHTML = `
                            <div class="size-label">${sizeData.size}</div>
                            <div class="size-price">Rs. ${parseFloat(price).toLocaleString()}</div>
                        `;
                        
                        sizeBtn.onclick = () => toggleSizeSelection(categoryKey, sizeKey, sizeBtn);
                        sizesGrid.appendChild(sizeBtn);
                    });
                });
            }
            
            modal.style.display = 'flex';
        }

        function toggleSizeSelection(categoryKey, sizeKey, button) {
            button.classList.toggle('selected');
            if (button.classList.contains('selected')) {
                currentCategorySelections[sizeKey] = true;
            } else {
                delete currentCategorySelections[sizeKey];
            }
            
            // **AUTO-SAVE** to session immediately
            if (Object.keys(currentCategorySelections).length > 0) {
                selectedSizes[categoryKey] = Object.keys(currentCategorySelections).join(',');
            } else {
                delete selectedSizes[categoryKey];
            }
            
            document.getElementById(`selected_${categoryKey}`).value = selectedSizes[categoryKey] || '';
            updateCategoryCard(categoryKey);
            saveToSession();
        }

        function closeSizeModal() {
            document.getElementById('sizeModal').style.display = 'none';
            updateProceedButton();
        }

        function updateCategoryCard(categoryKey) {
            const categoryCards = document.querySelectorAll('.category-card');
            categoryCards.forEach(card => {
                const onclickAttr = card.getAttribute('onclick');
                if (onclickAttr && onclickAttr.includes(categoryKey)) {
                    const hasSelection = selectedSizes[categoryKey];
                    const existingIndicator = card.querySelector('.selected-indicator');
                    
                    if (hasSelection) {
                        if (!existingIndicator) {
                            const indicator = document.createElement('div');
                            indicator.className = 'selected-indicator';
                            indicator.textContent = '✓';
                            card.appendChild(indicator);
                        }
                    } else if (existingIndicator) {
                        existingIndicator.remove();
                    }
                }
            });
        }

        function updateProceedButton() {
            const selectedCount = Object.values(selectedSizes).filter(val => val).reduce((total, val) => {
                return total + val.split(',').length;
            }, 0);
            
            const proceedBtn = document.getElementById('proceedBtn');
            
            if (selectedCount === 0) {
                proceedBtn.disabled = true;
                proceedBtn.style.opacity = '0.6';
                proceedBtn.innerHTML = '💳 Pay Bill';
            } else {
                proceedBtn.disabled = false;
                proceedBtn.style.opacity = '1';
                proceedBtn.innerHTML = `💳 Pay Bill (${selectedCount} items)`;
            }
        }

        function saveToSession() {
            const formData = new FormData();
            formData.append('save_session', '1');
            Object.entries(selectedSizes).forEach(([key, value]) => {
                if (value) {
                    formData.append(`selected_sizes[${key}]`, value);
                }
            });
            
            fetch(window.location.href, { 
                method: 'POST', 
                body: formData 
            }).then(response => response.json())
              .then(data => {
                  console.log('✅ Selections saved');
              }).catch(error => {
                  console.error('❌ Save error:', error);
              });
        }

        function showSelectedItemsModal() {
            const modal = document.getElementById('selectedItemsModal');
            const itemsList = document.getElementById('selectedItemsList');
            
            itemsList.innerHTML = '';
            let totalAmount = 0;
            let itemCount = 0;
            
            for (const [categoryKey, selection] of Object.entries(selectedSizes)) {
                if (selection) {
                    const category = categoriesData[categoryKey];
                    const items = selection.split(',');
                    
                    items.forEach(itemKey => {
                        const [size, section] = itemKey.split('|');
                        const sizeData = category.sizes.find(s => 
                            (s.size === size && s.section === section) || 
                            (s.size === itemKey && !s.section)
                        );
                        
                        if (sizeData) {
                            const price = parseFloat(sizeData.display_price || 0);
                            totalAmount += price;
                            itemCount++;
                            
                            const itemDiv = document.createElement('div');
                            itemDiv.className = 'selected-item';
                            itemDiv.innerHTML = `
                                <div class="item-details">
                                    <span class="item-name">${category.name}${section ? ` - ${section}` : ''}</span>
                                    <span class="item-size">Size: ${size}</span>
                                </div>
                                <div class="item-price">Rs. ${price.toLocaleString()}</div>
                            `;
                            itemsList.appendChild(itemDiv);
                        }
                    });
                }
            }
            
            if (itemCount === 0) {
                itemsList.innerHTML = '<div class="no-items" style="text-align: center; padding: 40px; font-size: 1.1rem; color: #666;">No items selected yet</div>';
            } else {
                const totalDiv = document.createElement('div');
                totalDiv.className = 'total-summary';
                totalDiv.innerHTML = `💰 TOTAL: Rs. ${totalAmount.toLocaleString()} (${itemCount} items)`;
                itemsList.appendChild(totalDiv);
            }
            
            modal.style.display = 'flex';
        }

        function closeSelectedItemsModal() {
            document.getElementById('selectedItemsModal').style.display = 'none';
        }

        // **AUTO-SAVE every 5 seconds**
        setInterval(() => {
            if (Object.values(selectedSizes).some(val => val)) {
                saveToSession();
            }
        }, 5000);

        // Initialize
        updateProceedButton();
    </script>
</body>
</html>