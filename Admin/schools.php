<?php
session_start();
require '../Common/connection.php';

// Security check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['selected_outlet_id'])) {
    header("Location: index.php");
    exit();
}

$outlet_id   = $_SESSION['selected_outlet_id'];
$outlet_name = $_SESSION['selected_outlet_name'] ?? 'Unknown Outlet';

// === SELECT SCHOOL → Go to items ===
if (isset($_POST['select_school'])) {
    $school_id = (int)$_POST['selected_school_id'];
    
    $stmt = $pdo->prepare("SELECT school_id, school_name FROM schools WHERE school_id = ? AND outlet_id = ?");
    $stmt->execute([$school_id, $outlet_id]);
    $school = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($school) {
        $_SESSION['selected_school_id']   = $school['school_id'];
        $_SESSION['selected_school_name'] = $school['school_name'];
        header("Location: school_items.php");
        exit();
    }
}

// === ADD NEW SCHOOL ===
if (isset($_POST['add_school'])) {
    $school_name = trim($_POST['school_name']);
    if (empty($school_name)) {
        $_SESSION['error'] = "School name cannot be empty.";
    } else {
        $check = $pdo->prepare("SELECT 1 FROM schools WHERE school_name = ? AND outlet_id = ?");
        $check->execute([$school_name, $outlet_id]);
        if ($check->fetch()) {
            $_SESSION['error'] = "School '$school_name' already exists!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO schools (school_name, outlet_id) VALUES (?, ?)");
            $stmt->execute([$school_name, $outlet_id]);
            $_SESSION['success'] = "School added successfully!";
        }
    }
    header("Location: dashboard.php");
    exit();
}

// === EDIT SCHOOL NAME ===
if (isset($_POST['save_edit'])) {
    $school_id = (int)$_POST['edit_school_id'];
    $new_name  = trim($_POST['edit_school_name']);
    
    if (empty($new_name)) {
        $_SESSION['error'] = "School name cannot be empty.";
    } else {
        $check = $pdo->prepare("SELECT 1 FROM schools WHERE school_name = ? AND outlet_id = ? AND school_id != ?");
        $check->execute([$new_name, $outlet_id, $school_id]);
        if ($check->fetch()) {
            $_SESSION['error'] = "Another school with this name already exists!";
        } else {
            $stmt = $pdo->prepare("UPDATE schools SET school_name = ? WHERE school_id = ? AND outlet_id = ?");
            $stmt->execute([$new_name, $school_id, $outlet_id]);
            $_SESSION['success'] = "School name updated!";
        }
    }
    header("Location: dashboard.php");
    exit();
}

// Fetch all schools for this outlet
$stmt = $pdo->prepare("SELECT school_id, school_name FROM schools WHERE outlet_id = ? ORDER BY school_name");
$stmt->execute([$outlet_id]);
$schools = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo htmlspecialchars($outlet_name); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 50%, #a569bd 100%);
            min-height: 100vh; padding: 20px;
        }
        .container { max-width: 1100px; margin: 0 auto; }
        .header {
            background: rgba(255,255,255,0.95); padding: 30px; border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2); text-align: center; margin-bottom: 30px;
            backdrop-filter: blur(12px);
        }
        .header h1 { color: #2c3e50; font-size: 2.4rem; margin-bottom: 10px; }
        .outlet-badge {
            background: linear-gradient(135deg, #8e44ad, #9b59b6); color: white;
            padding: 10px 25px; border-radius: 50px; font-weight: 600; font-size: 1.1rem; display: inline-block;
        }
        .card {
            background: rgba(255,255,255,0.95); border-radius: 20px; padding: 35px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2); backdrop-filter: blur(12px);
        }
        .section-title {
            font-size: 1.7rem; color: #2c3e50; margin-bottom: 25px;
            display: flex; align-items: center; gap: 12px;
        }

        .alert {
            padding: 16px 20px; border-radius: 12px; margin-bottom: 25px;
            font-weight: 600; display: flex; align-items: center; gap: 10px;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background: #8e44ad; color: white; padding: 18px; text-align: left; font-weight: 600; }
        td { padding: 18px; border-bottom: 1px solid #eee; vertical-align: middle; }
        tr:hover { background: #f8f9ff; }

        .actions {
            display: flex; gap: 10px; justify-content: center;
        }
        .btn {
            padding: 10px 20px; border: none; border-radius: 10px; cursor: pointer;
            font-weight: 600; font-size: 0.95rem; transition: all 0.3s;
        }
        .btn-edit { background: #3498db; color: white; }
        .btn-select { background: #27ae60; color: white; }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.2); }

        .add-form {
            display: flex; gap: 15px; margin-top: 30px; flex-wrap: wrap;
        }
        .add-form input {
            flex: 1; min-width: 300px; padding: 16px; border: 2px solid #e1e8ed;
            border-radius: 14px; font-size: 1.1rem;
        }
        .add-form button {
            padding: 16px 35px; background: linear-gradient(135deg, #8e44ad, #9b59b6);
            color: white; border: none; border-radius: 14px; font-weight: 600; cursor: pointer;
        }

        .links { margin-top: 35px; text-align: center; }
        .links a { color: #8e44ad; text-decoration: none; font-weight: 600; margin: 0 15px; font-size: 1.05rem; }
        .links a:hover { text-decoration: underline; }

        /* Modal */
        .modal {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.75); z-index: 9999; justify-content: center; align-items: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white; padding: 40px; border-radius: 20px; width: 90%; max-width: 520px;
            box-shadow: 0 30px 80px rgba(0,0,0,0.4); text-align: center;
        }
        .modal-content h2 { color: #2c3e50; margin-bottom: 20px; font-size: 1.8rem; }
        .modal-input {
            width: 100%; padding: 16px; border: 2px solid #8e44ad; border-radius: 12px;
            font-size: 1.1rem; margin-bottom: 25px;
        }
        .modal-buttons {
            display: flex; gap: 15px; justify-content: center;
        }
        .btn-modal {
            padding: 14px 35px; border: none; border-radius: 12px; font-weight: 600; cursor: pointer;
        }
        .btn-save { background: #27ae60; color: white; }
        .btn-cancel { background: #95a5a6; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Admin Dashboard</h1>
            <p>Managing schools for outlet</p>
            <div class="outlet-badge"><?php echo htmlspecialchars($outlet_name); ?></div>
        </div>

        <div class="card">
            <h2 class="section-title">Schools List (<?php echo count($schools); ?>)</h2>

            <!-- Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($schools)): ?>
                <p style="text-align:center; color:#7f8c8d; font-size:1.2rem; padding:40px 0;">
                    No schools added yet for this outlet.
                </p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th width="8%">#</th>
                            <th>School Name</th>
                            <th width="35%" style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schools as $i => $school): ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td style="font-weight:600; color:#2c3e50;">
                                    <?php echo htmlspecialchars($school['school_name']); ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <button class="btn btn-edit" onclick="openEditModal(<?php echo $school['school_id']; ?>, '<?php echo addslashes(htmlspecialchars($school['school_name'])); ?>')">
                                            Edit Name
                                        </button>

                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="selected_school_id" value="<?php echo $school['school_id']; ?>">
                                            <button type="submit" name="select_school" class="btn btn-select">
                                                Select School
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <!-- Add New School -->
            <div class="add-form">
                <form method="POST">
                    <input type="text" name="school_name" placeholder="Enter new school name" required>
                    <button type="submit" name="add_school">Add New School</button>
                </form>
            </div>

            <div class="links">
                <a href="index.php">Change Outlet</a> |
                <a href="../logout.php">Logout</a>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <h2>Edit School Name</h2>
            <form method="POST">
                <input type="hidden" name="edit_school_id" id="edit_school_id">
                <input type="text" name="edit_school_name" id="edit_school_name" class="modal-input" required>
                <div class="modal-buttons">
                    <button type="submit" name="save_edit" class="btn-modal btn-save">Save Changes</button>
                    <button type="button" class="btn-modal btn-cancel" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(id, name) {
            document.getElementById('edit_school_id').value = id;
            document.getElementById('edit_school_name').value = name;
            document.getElementById('editModal').classList.add('active');
        }
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) closeEditModal();
        });
    </script>
</body>
</html>