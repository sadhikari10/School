<?php
session_start();
require '../Common/connection.php';

// Security: Must be admin + have selected outlet
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['selected_outlet_id'])) {
    header("Location: index.php");
    exit();
}

$outlet_id   = $_SESSION['selected_outlet_id'];
$outlet_name = $_SESSION['selected_outlet_name'] ?? 'Unknown Outlet';

// === ADD NEW BRAND ===
if (isset($_POST['add_brand'])) {
    $brand_name = trim($_POST['brand_name']);

    if ($brand_name) {
        $check = $pdo->prepare("SELECT 1 FROM brands WHERE LOWER(brand_name) = LOWER(?) AND outlet_id = ?");
        $check->execute([$brand_name, $outlet_id]);
        if ($check->fetch()) {
            $_SESSION['error'] = "Brand '$brand_name' already exists!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO brands (brand_name, outlet_id) VALUES (?, ?)");
            $stmt->execute([$brand_name, $outlet_id]);
            $_SESSION['success'] = "Brand '$brand_name' added!";
        }
    } else {
        $_SESSION['error'] = "Please enter a brand name.";
    }
    header("Location: brand.php");
    exit();
}

// === EDIT EXISTING BRAND ===
if (isset($_POST['edit_brand'])) {
    $brand_id = (int)$_POST['brand_id'];
    $new_name = trim($_POST['new_name']);

    if ($new_name) {
        $stmt = $pdo->prepare("UPDATE brands SET brand_name = ? WHERE brand_id = ? AND outlet_id = ?");
        $stmt->execute([$new_name, $brand_id, $outlet_id]);
        $_SESSION['success'] = "Brand updated to '$new_name'!";
    } else {
        $_SESSION['error'] = "Brand name cannot be empty.";
    }
    header("Location: brand.php");
    exit();
}

// === FETCH ALL BRANDS FOR THIS OUTLET ===
$stmt = $pdo->prepare("SELECT brand_id, brand_name FROM brands WHERE outlet_id = ? ORDER BY brand_name");
$stmt->execute([$outlet_id]);
$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Brands - <?php echo htmlspecialchars($outlet_name); ?></title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI',sans-serif;background:linear-gradient(135deg,#8e44ad,#9b59b6,#a569bd);min-height:100vh;padding:30px;color:#2c3e50;}
.container{max-width:900px;margin:0 auto;}
.header{text-align:center;margin-bottom:40px;}
.header h1{font-size:2.6rem;color:white;text-shadow:0 4px 15px rgba(0,0,0,0.3);}
.outlet-badge{background:rgba(255,255,255,0.95);color:#8e44ad;padding:12px 40px;border-radius:50px;font-weight:700;font-size:1.3rem;display:inline-block;box-shadow:0 10px 30px rgba(0,0,0,0.2);}

.card{background:white;border-radius:24px;padding:40px;box-shadow:0 20px 50px rgba(0,0,0,0.2);margin-bottom:30px;}
.section-title{font-size:2rem;margin-bottom:25px;color:#2c3e50;display:flex;align-items:center;gap:12px;}

.alert{padding:15px 20px;border-radius:12px;margin-bottom:25px;font-weight:600;text-align:center;}
.success{background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
.error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}

table{width:100%;border-collapse:collapse;}
th{background:#8e44ad;color:white;padding:16px;text-align:left;}
td{padding:16px;border-bottom:1px solid #eee;}
tr:hover{background:#f8f9ff;}

.edit-link{color:#8e44ad;font-size:0.9rem;cursor:pointer;text-decoration:underline;}

.add-form{display:flex;gap:15px;margin:30px 0;flex-wrap:wrap;}
.add-form input{flex:1;min-width:200px;padding:14px;border:2px solid #e1e8ed;border-radius:14px;font-size:1.1rem;}
.add-form button{padding:14px 35px;background:linear-gradient(135deg,#8e44ad,#9b59b6);color:white;border:none;border-radius:14px;font-weight:600;cursor:pointer;}

.back-btn{display:inline-block;margin-top:30px;padding:12px 35px;background:#3498db;color:white;border-radius:50px;text-decoration:none;font-weight:600;}
.back-btn:hover{background:#2980b9;}

.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);justify-content:center;align-items:center;z-index:999;}
.modal.active{display:flex;}
.modal-content{background:white;padding:35px;border-radius:20px;width:90%;max-width:400px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.3);}
.modal input{width:100%;padding:14px;margin:15px 0;border:2px solid #ddd;border-radius:12px;font-size:1.1rem;}
.modal button{padding:12px 30px;margin:10px;border:none;border-radius:12px;cursor:pointer;font-weight:600;}
.btn-save{background:#27ae60;color:white;}
.btn-cancel{background:#95a5a6;color:white;}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Manage Brands</h1>
        <div class="outlet-badge"><?php echo htmlspecialchars($outlet_name); ?></div>
    </div>

    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="card">
        <h2 class="section-title">Brand List</h2>
        <?php if(empty($brands)): ?>
            <p style="text-align:center;padding:50px;color:#7f8c8d;font-size:1.2rem;">No brands added yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Brand Name</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($brands as $i => $brand): ?>
                        <tr>
                            <td><?php echo $i+1; ?></td>
                            <td><?php echo htmlspecialchars($brand['brand_name']); ?></td>
                            <td>
                                <span class="edit-link" onclick="openEditModal(<?php echo $brand['brand_id']; ?>, '<?php echo addslashes($brand['brand_name']); ?>')">
                                    Edit Name
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Add New Brand -->
        <div class="add-form">
            <form method="POST">
                <input type="text" name="brand_name" placeholder="New Brand Name" required>
                <button type="submit" name="add_brand">Add Brand</button>
            </form>
        </div>
    </div>

    <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
</div>

<!-- Edit Brand Modal -->
<div id="editBrandModal" class="modal">
    <div class="modal-content">
        <h3>Edit Brand Name</h3>
        <form method="POST">
            <input type="hidden" name="brand_id" id="edit_brand_id">
            <input type="text" name="new_name" id="edit_brand_name" required>
            <button type="submit" name="edit_brand" class="btn-save">Save</button>
            <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
        </form>
    </div>
</div>

<script>
function openEditModal(id, name){
    document.getElementById('edit_brand_id').value = id;
    document.getElementById('edit_brand_name').value = name;
    document.getElementById('editBrandModal').classList.add('active');
}
function closeModal(){
    document.getElementById('editBrandModal').classList.remove('active');
}
</script>
</body>
</html>
