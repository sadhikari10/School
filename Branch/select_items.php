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
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>👕 Select Item Sizes</h1>
            <p>School: <strong><?php echo htmlspecialchars($schoolName); ?></strong></p>
            <?php if (count(array_filter($selectedSizes)) > 0): ?>
                <div class="selected-count">
                    ✅ <strong><?php echo count(array_filter($selectedSizes)); ?></strong> item<?php echo count(array_filter($selectedSizes)) > 1 ? 's' : ''; ?> selected
                </div>
            <?php endif; ?>
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
                    <div class="category-card <?php echo !empty($category['sizes']) ? '' : 'disabled'; ?> <?php echo isset($selectedSizes[$key]) && $selectedSizes[$key] ? 'selected-category' : ''; ?>" 
                         onclick="showSizeModal('<?php echo $key; ?>', '<?php echo $category['name']; ?>', '<?php echo $category['icon']; ?>')">
                        <div class="category-icon"><?php echo $category['icon']; ?></div>
                        <div class="category-name"><?php echo $category['name']; ?></div>
                        <?php if (isset($selectedSizes[$key]) && $selectedSizes[$key]): ?>
                            <div class="selected-size">✓ <?php echo htmlspecialchars($selectedSizes[$key]); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- MAIN ACTION BUTTONS -->
            <div class="actions">
                <a href="dashboard.php" class="back-btn">← Back to Schools</a>
                <button type="button" class="next-btn" id="proceedBtn" onclick="showSelectedItemsModal()" <?php echo count(array_filter($selectedSizes)) == 0 ? 'disabled' : ''; ?>>
                    <?php echo count(array_filter($selectedSizes)) == 0 ? 'Proceed to Bill' : 'Proceed to Bill (' . count(array_filter($selectedSizes)) . ' items)'; ?>
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
                    // Section header (skip for single section categories)
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
                    
                    // Sizes for this section
                    sections[sectionName].forEach(sizeData => {
                        const sizeKey = sizeData.section ? `${sizeData.size}|${sizeData.section}` : sizeData.size;
                        const isSelected = selectedSizes[categoryKey] === sizeKey;
                        
                        const sizeBtn = document.createElement('div');
                        sizeBtn.className = `size-btn ${isSelected ? 'selected' : ''}`;
                        const price = sizeData.display_price || 0;
                        
                        sizeBtn.innerHTML = `
                            <div class="size-label">${sizeData.size}</div>
                            <div class="size-price">Rs. ${parseFloat(price).toLocaleString()}</div>
                        `;
                        
                        // Tracksuits: Multiple selection support
                        if (categoryKey === 'tracksuits') {
                            sizeBtn.onclick = () => toggleTracksuitSelection(categoryKey, sizeKey, sizeBtn);
                        } else {
                            sizeBtn.onclick = () => selectSize(categoryKey, sizeKey, sizeBtn);
                        }
                        
                        sizesGrid.appendChild(sizeBtn);
                    });
                });
            }
            
            modal.style.display = 'flex';
        }

        function selectSize(categoryKey, sizeKey, button) {
            // Remove previous selection for non-tracksuit categories
            if (categoryKey !== 'tracksuits') {
                document.querySelectorAll(`#sizesGrid .size-btn`).forEach(btn => btn.classList.remove('selected'));
                button.classList.add('selected');
                selectedSizes[categoryKey] = sizeKey;
            }
            
            document.getElementById(`selected_${categoryKey}`).value = selectedSizes[categoryKey] || '';
            updateCategoryCard(categoryKey);
            closeSizeModal();
        }

        function toggleTracksuitSelection(categoryKey, sizeKey, button) {
            button.classList.toggle('selected');
            const index = selectedSizes[categoryKey] ? selectedSizes[categoryKey].indexOf(sizeKey) : -1;
            
            if (index > -1) {
                // Remove from selection
                const current = selectedSizes[categoryKey].split(',');
                current.splice(index, 1);
                selectedSizes[categoryKey] = current.join(',');
            } else {
                // Add to selection
                selectedSizes[categoryKey] = selectedSizes[categoryKey] ? 
                    `${selectedSizes[categoryKey]},${sizeKey}` : sizeKey;
            }
            
            document.getElementById(`selected_${categoryKey}`).value = selectedSizes[categoryKey] || '';
            updateCategoryCard(categoryKey);
            closeSizeModal();
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
                    card.classList.toggle('selected-category', !!hasSelection);
                    
                    const existingSize = card.querySelector('.selected-size');
                    if (hasSelection) {
                        if (existingSize) {
                            if (categoryKey === 'tracksuits') {
                                const count = (selectedSizes[categoryKey].match(/,/g) || []).length + 1;
                                existingSize.textContent = `✓ ${count} item${count > 1 ? 's' : ''}`;
                            } else {
                                const sizeParts = selectedSizes[categoryKey].split('|');
                                existingSize.textContent = `✓ ${sizeParts[0]} ${sizeParts[1] || ''}`.trim();
                            }
                        } else {
                            const sizeDiv = document.createElement('div');
                            sizeDiv.className = 'selected-size';
                            if (categoryKey === 'tracksuits') {
                                const count = (selectedSizes[categoryKey].match(/,/g) || []).length + 1;
                                sizeDiv.textContent = `✓ ${count} item${count > 1 ? 's' : ''}`;
                            } else {
                                const sizeParts = selectedSizes[categoryKey].split('|');
                                sizeDiv.textContent = `✓ ${sizeParts[0]} ${sizeParts[1] || ''}`.trim();
                            }
                            card.appendChild(sizeDiv);
                        }
                    } else if (existingSize) {
                        existingSize.remove();
                    }
                }
            });
        }

        function updateProceedButton() {
            const selectedCount = Object.values(selectedSizes).filter(val => val).reduce((total, val) => {
                return total + (val.includes(',') ? val.split(',').length : 1);
            }, 0);
            
            const proceedBtn = document.getElementById('proceedBtn');
            const header = document.querySelector('.header');
            
            if (selectedCount === 0) {
                proceedBtn.disabled = true;
                proceedBtn.style.opacity = '0.5';
                proceedBtn.innerHTML = 'Proceed to Bill';
                const existingCount = document.querySelector('.selected-count');
                if (existingCount) existingCount.remove();
            } else {
                proceedBtn.disabled = false;
                proceedBtn.style.opacity = '1';
                proceedBtn.innerHTML = `Proceed to Bill (${selectedCount} items)`;
                
                let existingCount = document.querySelector('.selected-count');
                if (!existingCount) {
                    existingCount = document.createElement('div');
                    existingCount.className = 'selected-count';
                    header.appendChild(existingCount);
                }
                existingCount.innerHTML = `✅ <strong>${selectedCount}</strong> item${selectedCount > 1 ? 's' : ''} selected`;
            }
        }

        function showSelectedItemsModal() {
            const modal = document.getElementById('selectedItemsModal');
            const itemsList = document.getElementById('selectedItemsList');
            
            itemsList.innerHTML = '';
            let totalAmount = 0;
            
            for (const [categoryKey, selection] of Object.entries(selectedSizes)) {
                if (selection) {
                    const category = categoriesData[categoryKey];
                    
                    if (categoryKey === 'tracksuits') {
                        // Multiple tracksuit items
                        const tracksuitItems = selection.split(',');
                        tracksuitItems.forEach(itemKey => {
                            const [size, section] = itemKey.split('|');
                            const sizeData = category.sizes.find(s => s.size === size && s.section === section);
                            if (sizeData) {
                                const price = sizeData.display_price || 0;
                                totalAmount += parseFloat(price);
                                
                                const itemDiv = document.createElement('div');
                                itemDiv.className = 'selected-item';
                                itemDiv.innerHTML = `
                                    <div class="item-details">
                                        <span class="item-name">${category.name} - ${section}</span>
                                        <span class="item-size">Size: ${size}</span>
                                    </div>
                                    <div class="item-price">Rs. ${parseFloat(price).toLocaleString()}</div>
                                `;
                                itemsList.appendChild(itemDiv);
                            }
                        });
                    } else {
                        // Single item
                        const [size, section] = selection.split('|');
                        const sizeData = category.sizes.find(s => 
                            (s.size === size && s.section === section) || 
                            (s.size === selection && !s.section)
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
                    }
                }
            }
            
            if (itemsList.children.length === 0) {
                itemsList.innerHTML = '<div class="no-items">No items selected yet</div>';
            } else {
                // Add total
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