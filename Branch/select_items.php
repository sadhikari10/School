<?php
session_start();
require_once '../Common/connection.php';

$schoolId = $_SESSION['selected_school_id'] ?? 0;
$schoolName = $_SESSION['selected_school_name'] ?? 'Other';

// Reordered categories with corrected icons
$categories = [
    'shirts' => ['name' => 'Shirts', 'icon' => '👔', 'sizes' => []],
    'pants' => ['name' => 'Pants', 'icon' => '👖', 'sizes' => []],
    'skirts' => ['name' => 'Skirts', 'icon' => '👗', 'sizes' => []],
    'coats' => ['name' => 'Coats', 'icon' => '🧥', 'sizes' => []],
    'tracksuits' => ['name' => 'Tracksuits', 'icon' => '🏃‍♂️', 'sizes' => []], // Corrected to tracksuit icon
    'sweaters' => ['name' => 'Sweaters', 'icon' => '🧶', 'sizes' => []],
    'stockings' => ['name' => 'Stockings', 'icon' => '🧦', 'sizes' => []], // Already correct (socks)
    'shoes' => ['name' => 'Shoes', 'icon' => '👞', 'sizes' => []]
];

try {
    // Shirts
    $stmt = $pdo->query("SELECT size, price_other, school_prices FROM shirts ORDER BY CAST(size AS UNSIGNED)");
    $categories['shirts']['sizes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Pants
    $stmt = $pdo->query("SELECT size, price_other, price_indian, price_timeline FROM pants ORDER BY size");
    $categories['pants']['sizes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Skirts
    $stmt = $pdo->query("SELECT size, price_indian, price_nepali FROM skirts ORDER BY size");
    $categories['skirts']['sizes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Coats
    $stmt = $pdo->query("SELECT size, price FROM coats ORDER BY size");
    $categories['coats']['sizes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Tracksuits
    $stmt = $pdo->query("SELECT size, price_3pic, price_2pic, price_tshirt, price_trouser, price_jacket FROM tracksuits ORDER BY CAST(size AS UNSIGNED)");
    $categories['tracksuits']['sizes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Sweaters
    $stmt = $pdo->query("SELECT size, price_other, price_tileline_devkota_winter, price_lla_winter, price_subha_rims_akashdep_winter FROM sweaters ORDER BY CAST(size AS UNSIGNED)");
    $categories['sweaters']['sizes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Stockings
    $stmt = $pdo->query("SELECT name as size, price FROM stockings ORDER BY name");
    $categories['stockings']['sizes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Shoes
    $stmt = $pdo->query("SELECT size, price_white, price_black FROM shoes ORDER BY size");
    $categories['shoes']['sizes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
        </header>

        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Selected Items Preview Modal -->
        <div class="modal" id="selectedItemsModal">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="modal-icon">✅</span>
                    <h3>Selected Items</h3>
                    <span class="close" onclick="closeSelectedItemsModal()">&times;</span>
                </div>
                <div class="selected-items-list" id="selectedItemsList">
                    <!-- Items will be populated by JavaScript -->
                </div>
                <div class="modal-actions">
                    <button class="cancel-btn" onclick="closeSelectedItemsModal()">Continue Selecting</button>
                    <button type="submit" form="itemsForm" class="proceed-btn" id="confirmProceedBtn">Proceed to Bill</button>
                </div>
            </div>
        </div>

        <form method="POST" action="bill.php" id="itemsForm">
            <input type="hidden" name="school_id" value="<?php echo $schoolId; ?>">
            <input type="hidden" name="school_name" value="<?php echo htmlspecialchars($schoolName); ?>">
            
            <?php foreach ($categories as $key => $category): ?>
                <input type="hidden" name="selected_sizes[<?php echo $key; ?>]" id="selected_<?php echo $key; ?>" value="">
            <?php endforeach; ?>
            
            <div class="categories-grid" id="categoriesGrid">
                <?php foreach ($categories as $key => $category): ?>
                    <div class="category-card" onclick="showSizeModal('<?php echo $key; ?>', '<?php echo $category['name']; ?>', '<?php echo $category['icon']; ?>')">
                        <div class="category-icon"><?php echo $category['icon']; ?></div>
                        <div class="category-name"><?php echo $category['name']; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Size Selection Modal -->
            <div class="modal" id="sizeModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <span class="modal-icon" id="modalIcon"></span>
                        <h3 id="modalTitle">Select Size</h3>
                        <span class="close" onclick="closeSizeModal()">&times;</span>
                    </div>
                    <div class="sizes-grid" id="sizesGrid">
                        <!-- Sizes will be populated by JavaScript -->
                    </div>
                    <div class="modal-actions">
                        <button class="cancel-btn" onclick="closeSizeModal()">Cancel</button>
                    </div>
                </div>
            </div>

            <div class="actions">
                <a href="dashboard.php" class="back-btn">← Back to Schools</a>
                <button type="button" class="next-btn" id="proceedBtn" onclick="showSelectedItemsModal()" disabled>Proceed to Bill</button>
            </div>
        </form>
    </div>

    <script>
        let selectedSizes = {};
        const categoriesData = <?php echo json_encode($categories); ?>;

        function showSizeModal(categoryKey, categoryName, categoryIcon) {
            const modal = document.getElementById('sizeModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalIcon = document.getElementById('modalIcon');
            const sizesGrid = document.getElementById('sizesGrid');
            
            modalTitle.textContent = `Select ${categoryName} Size`;
            modalIcon.textContent = categoryIcon;
            
            // Populate sizes
            sizesGrid.innerHTML = '';
            const sizes = categoriesData[categoryKey].sizes;
            
            sizes.forEach(sizeData => {
                const sizeBtn = document.createElement('div');
                sizeBtn.className = 'size-btn';
                const isSelected = selectedSizes[categoryKey] === sizeData.size;
                if (isSelected) sizeBtn.classList.add('selected');
                
                let price = 0;
                if (sizeData.school_prices) price = sizeData.school_prices;
                else if (sizeData.price) price = sizeData.price;
                else if (sizeData.price_white) price = sizeData.price_white;
                else if (sizeData.price_3pic) price = sizeData.price_3pic;
                
                sizeBtn.innerHTML = `
                    <div class="size-label">${sizeData.size}</div>
                    <div class="size-price">Rs. ${parseFloat(price).toLocaleString()}</div>
                `;
                
                sizeBtn.onclick = () => selectSize(categoryKey, sizeData.size, sizeBtn);
                sizesGrid.appendChild(sizeBtn);
            });
            
            modal.style.display = 'flex';
        }

        function selectSize(categoryKey, size, button) {
            // Remove previous selection
            document.querySelectorAll('#sizesGrid .size-btn').forEach(btn => btn.classList.remove('selected'));
            
            // Select new size
            button.classList.add('selected');
            selectedSizes[categoryKey] = size;
            
            // Update hidden input
            document.getElementById(`selected_${categoryKey}`).value = size;
            
            // Update proceed button
            updateProceedButton();
            
            // Close modal after selection
            setTimeout(closeSizeModal, 300);
        }

        function closeSizeModal() {
            document.getElementById('sizeModal').style.display = 'none';
        }

        function updateProceedButton() {
            const selectedCount = Object.keys(selectedSizes).length;
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

        function showSelectedItemsModal() {
            const modal = document.getElementById('selectedItemsModal');
            const itemsList = document.getElementById('selectedItemsList');
            
            itemsList.innerHTML = '';
            
            let hasItems = false;
            for (const [categoryKey, size] of Object.entries(selectedSizes)) {
                const category = categoriesData[categoryKey];
                const sizeData = category.sizes.find(s => s.size === size);
                
                if (sizeData) {
                    hasItems = true;
                    const itemDiv = document.createElement('div');
                    itemDiv.className = 'selected-item';
                    itemDiv.innerHTML = `
                        <div class="item-info">
                            <span class="item-icon">${category.icon}</span>
                            <span class="item-name">${category.name} - ${size}</span>
                        </div>
                        <div class="item-price">Rs. ${parseFloat(getPrice(sizeData)).toLocaleString()}</div>
                    `;
                    itemsList.appendChild(itemDiv);
                }
            }
            
            if (!hasItems) {
                itemsList.innerHTML = '<div class="no-items">No items selected</div>';
            }
            
            modal.style.display = 'flex';
        }

        function getPrice(sizeData) {
            if (sizeData.school_prices) return sizeData.school_prices;
            if (sizeData.price) return sizeData.price;
            if (sizeData.price_white) return sizeData.price_white;
            if (sizeData.price_3pic) return sizeData.price_3pic;
            return 0;
        }

        function closeSelectedItemsModal() {
            document.getElementById('selectedItemsModal').style.display = 'none';
        }

        // Initialize
        updateProceedButton();
    </script>
</body>
</html>