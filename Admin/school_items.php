<?php
session_start();
require '../Common/connection.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['selected_outlet_id'])) {
    header("Location: index.php");
    exit();
}

$outlet_id   = $_SESSION['selected_outlet_id'];
$outlet_name = $_SESSION['selected_outlet_name'];

// ADD NEW ITEM (NAME ONLY - sizes added later)
if (isset($_POST['add_item'])) {
    $item_name = trim($_POST['item_name']);

    if ($item_name) {
        try {
            $stmt = $pdo->prepare("INSERT INTO items (item_name, outlet_id) VALUES (?, ?)");
            $stmt->execute([$item_name, $outlet_id]);
            $_SESSION['success'] = "Item '$item_name' added successfully!";
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {  // Duplicate entry
                $_SESSION['error'] = "Item '$item_name' already exists in this outlet!";
            } else {
                $_SESSION['error'] = "An error occurred. Please try again.";
            }
        }
    } else {
        $_SESSION['error'] = "Please fill both fields!";
    }
    header("Location: school_items.php");
    exit();
}

// EDIT ITEM NAME
if (isset($_POST['edit_item_name'])) {
    $item_id = $_POST['item_id'];
    $new_name = trim($_POST['new_name']);
    if ($new_name) {
        $pdo->prepare("UPDATE items SET item_name = ? WHERE item_id = ? AND outlet_id = ?")
            ->execute([$new_name, $item_id, $outlet_id]);
        $_SESSION['success'] = "Item name updated!";
    }
    header("Location: school_items.php");
    exit();
}

// ADD NEW SIZE
if (isset($_POST['add_size'])) {
    $item_id = $_POST['item_id'];
    $new_size = strtoupper(trim($_POST['new_size']));
    if ($new_size) {
        $pdo->prepare("INSERT IGNORE INTO sizes (item_id, size_value, outlet_id) VALUES (?, ?, ?)")
            ->execute([$item_id, $new_size, $outlet_id]);
        $_SESSION['success'] = "Size '$new_size' added!";
    }
    header("Location: school_items.php");
    exit();
}

// EDIT SINGLE SIZE
if (isset($_POST['edit_single_size'])) {
    $size_id = $_POST['size_id'];
    $new_size = strtoupper(trim($_POST['new_size_value']));
    if ($new_size) {
        $pdo->prepare("UPDATE sizes SET size_value = ? WHERE size_id = ? AND outlet_id = ?")
            ->execute([$new_size, $size_id, $outlet_id]);
        $_SESSION['success'] = "Size updated to '$new_size'!";
    }
    header("Location: school_items.php");
    exit();
}

// FETCH ALL ITEMS WITH SIZES (with size_id for editing)
$items = $pdo->prepare("
    SELECT i.item_id, i.item_name,
           GROUP_CONCAT(s.size_id ORDER BY s.size_value) as size_ids,
           GROUP_CONCAT(s.size_value ORDER BY s.size_value SEPARATOR ', ') as sizes
    FROM items i
    LEFT JOIN sizes s ON i.item_id = s.item_id
    WHERE i.outlet_id = ?
    GROUP BY i.item_id
    ORDER BY i.item_name
");
$items->execute([$outlet_id]);
$items = $items->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Items & Sizes - <?php echo htmlspecialchars($outlet_name); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Segoe UI',sans-serif;background:linear-gradient(135deg,#8e44ad,#9b59b6,#a569bd);min-height:100vh;padding:30px;color:#2c3e50;}
        .container{max-width:1200px;margin:0 auto;}
        .header{text-align:center;margin-bottom:40px;}
        .header h1{font-size:2.6rem;color:white;text-shadow:0 4px 15px rgba(0,0,0,0.3);}
        .outlet-badge{background:rgba(255,255,255,0.95);color:#8e44ad;padding:12px 40px;border-radius:50px;font-weight:700;font-size:1.3rem;display:inline-block;box-shadow:0 10px 30px rgba(0,0,0,0.2);}

        .card{background:white;border-radius:24px;padding:40px;box-shadow:0 20px 50px rgba(0,0,0,0.2);margin-bottom:30px;}
        .section-title{font-size:2rem;margin-bottom:25px;color:#2c3e50;display:flex;align-items:center;gap:12px;}

        .alert{padding:15px 20px;border-radius:12px;margin-bottom:25px;font-weight:600;text-align:center;}
        .success{background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
        .error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}

        /* Search Box */
        .search-box{margin-bottom:25px;}
        .search-box input{width:100%;padding:16px;border:2px solid #e1e8ed;border-radius:14px;font-size:1.1rem;}

        table{width:100%;border-collapse:collapse;}
        th{background:#8e44ad;color:white;padding:16px;text-align:left;}
        td{padding:16px;border-bottom:1px solid #eee;}
        tr:hover{background:#f8f9ff;}
        .sizes-badge{
            background:#27ae60;color:white;padding:8px 14px;border-radius:20px;font-size:0.95rem;margin:4px;
            display:inline-block;cursor:pointer;transition:0.3s;
        }
        .sizes-badge:hover{background:#219653;transform:scale(1.05);}
        .edit-link{color:#8e44ad;font-size:0.9rem;cursor:pointer;text-decoration:underline;}

        .add-form{display:flex;gap:15px;margin:30px 0;flex-wrap:wrap;}
        .add-form input{flex:1;min-width:280px;padding:16px;border:2px solid #e1e8ed;border-radius:14px;font-size:1.1rem;}
        .add-form button{padding:16px 40px;background:linear-gradient(135deg,#8e44ad,#9b59b6);color:white;border:none;border-radius:14px;font-weight:600;cursor:pointer;}

        .back-btn{display:inline-block;margin-top:30px;padding:12px 35px;background:#3498db;color:white;border-radius:50px;text-decoration:none;font-weight:600;}
        .back-btn:hover{background:#2980b9;}

        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);justify-content:center;align-items:center;z-index:999;}
        .modal.active{display:flex;}
        .modal-content{background:white;padding:35px;border-radius:20px;width:90%;max-width:500px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.3);}
        .modal input{width:100%;padding:14px;margin:15px 0;border:2px solid #ddd;border-radius:12px;font-size:1.1rem;}
        .modal button{padding:12px 30px;margin:10px;border:none;border-radius:12px;cursor:pointer;font-weight:600;}
        .btn-save{background:#27ae60;color:white;}
        .btn-cancel{background:#95a5a6;color:white;}
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Manage Items & Sizes</h1>
            <div class="outlet-badge"><?php echo htmlspecialchars($outlet_name); ?></div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="card">
            <h2 class="section-title">Items List</h2>

            <!-- Search Box -->
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search items by name..." onkeyup="searchTable()">
            </div>

            <?php if (empty($items)): ?>
                <p style="text-align:center;padding:50px;color:#7f8c8d;font-size:1.3rem;">No items added yet.</p>
            <?php else: ?>
                <table id="itemsTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Available Sizes</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $i => $item): ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['item_name']); ?></strong><br>
                                    <span class="edit-link" onclick="openEditName(<?php echo $item['item_id']; ?>, '<?php echo addslashes($item['item_name']); ?>')">
                                        Edit name
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    if ($item['sizes']) {
                                        $size_list = explode(', ', $item['sizes']);
                                        $size_ids = explode(',', $item['size_ids']);
                                        foreach ($size_list as $idx => $sz) {
                                            $size_id = $size_ids[$idx] ?? '';
                                            echo "<span class='sizes-badge' onclick=\"openEditSize($size_id, '$sz')\">$sz</span>";
                                        }
                                    } else {
                                        echo "<em>No sizes yet</em>";
                                    }
                                    ?>
                                </td>
                                <td>
                                    <button onclick="openAddSize(<?php echo $item['item_id']; ?>, '<?php echo addslashes($item['item_name']); ?>')" 
                                            style="padding:8px 16px;background:#2ecc71;color:white;border:none;border-radius:8px;cursor:pointer;font-size:0.9rem;">
                                        Add Size
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <!-- Add New Item (NAME ONLY - sizes added separately) -->
            <div class="add-form">
                <form method="POST">
                    <input type="text" name="item_name" placeholder="New item name (e.g. Belt)" required>
                    <button type="submit" name="add_item">Add New Item</button>
                </form>
            </div>
        </div>

        <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
    </div>

    <!-- Edit Item Name -->
    <div id="editNameModal" class="modal">
        <div class="modal-content">
            <h3>Edit Item Name</h3>
            <form method="POST">
                <input type="hidden" name="item_id" id="edit_item_id">
                <input type="text" name="new_name" id="edit_new_name" required>
                <button type="submit" name="edit_item_name" class="btn-save">Save</button>
                <button type="button" class="btn-cancel" onclick="closeModal('editNameModal')">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Add Size -->
    <div id="addSizeModal" class="modal">
        <div class="modal-content">
            <h3>Add Size to <span id="item_name_display"></span></h3>
            <form method="POST">
                <input type="hidden" name="item_id" id="add_size_item_id">
                <input type="text" name="new_size" placeholder="e.g. XXL or 44" required>
                <button type="submit" name="add_size" class="btn-save">Add Size</button>
                <button type="button" class="btn-cancel" onclick="closeModal('addSizeModal')">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Edit Single Size -->
    <div id="editSizeModal" class="modal">
        <div class="modal-content">
            <h3>Edit Size</h3>
            <form method="POST">
                <input type="hidden" name="size_id" id="edit_size_id">
                <input type="text" name="new_size_value" id="edit_size_value" required>
                <button type="submit" name="edit_single_size" class="btn-save">Update Size</button>
                <button type="button" class="btn-cancel" onclick="closeModal('editSizeModal')">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function openEditName(id, name) {
            document.getElementById('edit_item_id').value = id;
            document.getElementById('edit_new_name').value = name;
            document.getElementById('editNameModal').classList.add('active');
        }
        function openAddSize(id, name) {
            document.getElementById('add_size_item_id').value = id;
            document.getElementById('item_name_display').textContent = name;
            document.getElementById('addSizeModal').classList.add('active');
        }
        function openEditSize(id, value) {
            document.getElementById('edit_size_id').value = id;
            document.getElementById('edit_size_value').value = value;
            document.getElementById('editSizeModal').classList.add('active');
        }
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        // Live Search
        function searchTable() {
            let input = document.getElementById("searchInput").value.toLowerCase();
            let rows = document.querySelectorAll("#itemsTable tbody tr");
            rows.forEach(row => {
                let itemName = row.cells[1].textContent.toLowerCase();
                row.style.display = itemName.includes(input) ? "" : "none";
            });
        }
    </script>
</body>
</html>