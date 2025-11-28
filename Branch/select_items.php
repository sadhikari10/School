<?php
session_start();
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

$schoolId = $_SESSION['selected_school_id'] ?? 0;
$schoolName = $_SESSION['selected_school_name'] ?? 'Other';

if (!isset($_SESSION['selected_sizes'])) {
    $_SESSION['selected_sizes'] = [];
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
        /* Scroll for pants/skirts/tracksuits */
        .sizes-grid {
            max-height: 60vh;
            overflow-y: auto;
            padding: 10px;
            scrollbar-width: thin;
            scrollbar-color: #667eea #f1f5f9;
        }
        .sizes-grid::-webkit-scrollbar {
            width: 6px;
        }
        .sizes-grid::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        .sizes-grid::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 10px;
        }
        .sizes-grid::-webkit-scrollbar-thumb:hover {
            background: #5a67d8;
        }
        
        /* Order summary modal scroll */
        .selected-items-list {
            max-height: 60vh;
            overflow-y: auto;
            padding-right: 10px;
            scrollbar-width: thin;
            scrollbar-color: #27ae60 #f1f5f9;
        }
        .selected-items-list::-webkit-scrollbar {
            width: 6px;
        }
        .selected-items-list::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        .selected-items-list::-webkit-scrollbar-thumb {
            background: #27ae60;
            border-radius: 10px;
        }
        .selected-items-list::-webkit-scrollbar-thumb:hover {
            background: #219a52;
        }
        
        /* Clear button styling */
        .clear-selection-btn {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }
        .clear-selection-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>👕 Select Item Sizes</h1>
            <p>School: <strong><?php echo htmlspecialchars($schoolName); ?></strong></p>
        </header>

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
                    <button type="button" class="clear-selection-btn" onclick="clearAllSelections()" id="clearBtn" style="display: none;">
                        🗑️ Clear All Selections
                    </button>
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
                    <div class="category-card <?php echo !empty($category['sizes']) ? '' : 'disabled'; ?>" 
                         onclick="showSizeModal('<?php echo $key; ?>', '<?php echo $category['name']; ?>', '<?php echo $category['icon']; ?>')">
                        <div class="category-icon"><?php echo $category['icon']; ?></div>
                        <div class="category-name"><?php echo $category['name']; ?></div>
                        <?php if (isset($selectedSizes[$key]) && $selectedSizes[$key]): ?>
                            <div class="selected-indicator">✓</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- MAIN ACTION BUTTONS -->
            <div class="actions">
                <a href="dashboard.php" class="back-btn">← Back to Schools</a>
                <button type="button" class="next-btn" id="proceedBtn" onclick="showSelectedItemsModal()" <?php echo count(array_filter($selectedSizes)) == 0 ? 'disabled' : ''; ?>>
                    <?php $selectedCount = count(array_filter($selectedSizes)); echo $selectedCount == 0 ? 'Proceed to Bill' : 'Proceed to Bill (' . $selectedCount . ' items)'; ?>
                </button>
            </div>
        </form>
    </div>

    <script>
        let selectedSizes = <?php echo json_encode($selectedSizes); ?>;
        const categoriesData = <?php echo json_encode($categories); ?>;
        let currentCategoryKey = '';
        let currentCategorySelections = {}; // Track selections per category

        function showSizeModal(categoryKey, categoryName, categoryIcon) {
            currentCategoryKey = categoryKey;
            const modal = document.getElementById('sizeModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalIcon = document.getElementById('modalIcon');
            const sizesGrid = document.getElementById('sizesGrid');
            
            modalTitle.textContent = `Select ${categoryName} Size`;
            modalIcon.textContent = categoryIcon;
            
            // Initialize selections for this category
            currentCategorySelections = {};
            if (selectedSizes[categoryKey]) {
                if (categoryKey === 'tracksuits') {
                    selectedSizes[categoryKey].split(',').forEach(item => {
                        currentCategorySelections[item] = true;
                    });
                } else {
                    currentCategorySelections[selectedSizes[categoryKey]] = true;
                }
            }
            
            sizesGrid.innerHTML = '';
            const sizes = categoriesData[categoryKey].sizes;
            
            if (sizes.length === 0) {
                sizesGrid.innerHTML = '<div class="no-sizes">No sizes available</div>';
            } else {
                // Group sizes by section
                const sections = {};
                sizes.forEach(sizeData => {
                    const section = sizeData.section || 'Default';
                    if (!sections[section]) {
                        sections[section] = [];
                    }
                    sections[section].push(sizeData);
                });
                
                // Create section headers and sizes
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
                        
                        // Multiple selection for ALL categories
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
            
            // Update main selection
            if (Object.keys(currentCategorySelections).length > 0) {
                selectedSizes[categoryKey] = Object.keys(currentCategorySelections).join(',');
            } else {
                delete selectedSizes[categoryKey];
            }
            
            document.getElementById(`selected_${categoryKey}`).value = selectedSizes[categoryKey] || '';
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
                proceedBtn.style.opacity = '0.5';
                proceedBtn.innerHTML = 'Proceed to Bill';
            } else {
                proceedBtn.disabled = false;
                proceedBtn.style.opacity = '1';
                proceedBtn.innerHTML = `Proceed to Bill (${selectedCount} items)`;
            }
        }

        function clearAllSelections() {
            if (confirm('Are you sure you want to clear ALL selections?')) {
                Object.keys(selectedSizes).forEach(key => {
                    selectedSizes[key] = '';
                    document.getElementById(`selected_${key}`).value = '';
                });
                
                document.querySelectorAll('.selected-indicator').forEach(indicator => indicator.remove());
                updateProceedButton();
                saveToSession();
                closeSelectedItemsModal();
            }
        }

        function showSelectedItemsModal() {
            const modal = document.getElementById('selectedItemsModal');
            const itemsList = document.getElementById('selectedItemsList');
            const clearBtn = document.getElementById('clearBtn');
            
            itemsList.innerHTML = '';
            let totalAmount = 0;
            
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
                            const price = sizeData.display_price || 0;
                            totalAmount += parseFloat(price);
                            
                            const itemDiv = document.createElement('div');
                            itemDiv.className = 'selected-item';
                            itemDiv.innerHTML = `
                                <div class="item-details">
                                    <span class="item-name">${category.name}${section ? ` - ${section}` : ''}</span>
                                    <span class="item-size">Size: ${size}</span>
                                </div>
                                <div class="item-price">Rs. ${parseFloat(price).toLocaleString()}</div>
                            `;
                            itemsList.appendChild(itemDiv);
                        }
                    });
                }
            }
            
            if (itemsList.children.length === 0) {
                itemsList.innerHTML = '<div class="no-items">No items selected yet</div>';
                clearBtn.style.display = 'none';
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
                clearBtn.style.display = 'inline-block';
            }
            
            modal.style.display = 'flex';
        }

        function closeSelectedItemsModal() {
            document.getElementById('selectedItemsModal').style.display = 'none';
        }

        function saveToSession() {
            const formData = new FormData();
            formData.append('save_session', '1');
            Object.entries(selectedSizes).forEach(([key, value]) => {
                if (value) {
                    formData.append(`selected_sizes[${key}]`, value);
                }
            });
            fetch('', { method: 'POST', body: formData });
        }

        // Initialize
        updateProceedButton();
    </script>
</body>
</html>