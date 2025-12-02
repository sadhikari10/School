<?php
session_start();
require '../Common/connection.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['selected_outlet_id'])) {
    header("Location: index.php");
    exit();
}

$outlet_id   = $_SESSION['selected_outlet_id'];
$outlet_name = $_SESSION['selected_outlet_name'] ?? 'Unknown Outlet';

// === ADD NEW SCHOOL (Safe from refresh duplicates) ===
if (isset($_POST['add_school'])) {
    $school_name = trim($_POST['school_name']);

    if (empty($school_name)) {
        $_SESSION['error'] = "School name cannot be empty.";
    } else {
        // Check if school already exists for this outlet
        $check = $pdo->prepare("SELECT school_id FROM schools WHERE school_name = ? AND outlet_id = ?");
        $check->execute([$school_name, $outlet_id]);
        
        if ($check->fetch()) {
            $_SESSION['error'] = "School '$school_name' already exists for this outlet!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO schools (school_name, outlet_id) VALUES (?, ?)");
            $stmt->execute([$school_name, $outlet_id]);
            $_SESSION['success'] = "School added successfully!";
        }
    }
    // Prevent refresh duplicate
    header("Location: dashboard.php");
    exit();
}

// === EDIT SCHOOL NAME (Also safe + no duplicate check on edit) ===
if (isset($_POST['save_edit'])) {
    $school_id   = (int)$_POST['edit_school_id'];
    $new_name    = trim($_POST['edit_school_name']);

    if (empty($new_name)) {
        $_SESSION['error'] = "School name cannot be empty.";
    } else {
        // Optional: prevent duplicate name (except for itself)
        $check = $pdo->prepare("SELECT school_id FROM schools WHERE school_name = ? AND outlet_id = ? AND school_id != ?");
        $check->execute([$new_name, $outlet_id, $school_id]);
        
        if ($check->fetch()) {
            $_SESSION['error'] = "Another school with name '$new_name' already exists!";
        } else {
            $stmt = $pdo->prepare("UPDATE schools SET school_name = ? WHERE school_id = ? AND outlet_id = ?");
            $stmt->execute([$new_name, $school_id, $outlet_id]);
            $_SESSION['success'] = "School name updated!";
        }
    }
    header("Location: dashboard.php");
    exit();
}

// Fetch schools
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
        .container { max-width: 1000px; margin: 0 auto; }
        .header {
            background: rgba(255,255,255,0.95); padding: 25px 30px; border-radius: 18px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.15); text-align: center; margin-bottom: 30px;
            backdrop-filter: blur(10px);
        }
        .header h1 { color: #2c3e50; font-size: 2.2rem; margin-bottom: 8px; }
        .outlet-badge {
            background: linear-gradient(135deg, #8e44ad, #9b59b6); color: white;
            padding: 8px 20px; border-radius: 50px; font-weight: 600; margin-top: 10px; display: inline-block;
        }
        .card {
            background: rgba(255,255,255,0.95); border-radius: 18px; padding: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.15); backdrop-filter: blur(10px);
        }
        .section-title { font-size: 1.6rem; color: #2c3e50; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }

        /* Messages */
        .alert {
            padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; font-weight: 600;
            display: flex; align-items: center; gap: 10px;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background: #8e44ad; color: white; padding: 15px; text-align: left; }
        td { padding: 15px; border-bottom: 1px solid #eee; }
        tr:hover { background: #f9f9ff; }
        .school-name { font-weight: 600; color: #2c3e50; }
        .btn-edit {
            background: #3498db; color: white; border: none; padding: 10px 20px;
            border-radius: 10px; cursor: pointer; font-weight: 600; transition: all 0.3s;
        }
        .btn-edit:hover { background: #2980b9; transform: translateY(-2px); }

        .add-form {
            display: flex; gap: 15px; margin-top: 25px; flex-wrap: wrap;
        }
        .add-form input {
            flex: 1; min-width: 280px; padding: 14px; border: 2px solid #e1e8ed;
            border-radius: 12px; font-size: 1rem;
        }
        .add-form button {
            padding: 14px 32px; background: linear-gradient(135deg, #8e44ad, #9b59b6);
            color: white; border: none; border-radius: 12px; font-weight: 600; cursor: pointer;
        }

        /* Modal */
        .modal {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.7); z-index: 9999; justify-content: center; align-items: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white; padding: 40px; border-radius: 20px; width: 90%; max-width: 520px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3); text-align: center;
        }
        .modal-content h2 { color: #2c3e50; margin-bottom: 20px; }
        .modal-input {
            width: 100%; padding: 16px; border: 2px solid #8e44ad; border-radius: 12px;
            font-size: 1.1rem; margin-bottom: 20px;
        }
        .modal-buttons {
            display: flex; gap: 15px; justify-content: center;
        }
        .btn-modal {
            padding: 12px 32px; border: none; border-radius: 12px; font-weight: 600; cursor: pointer;
        }
        .btn-save { background: #27ae60; color: white; }
        .btn-cancel { background: #95a5a6; color: white; }

        .links { margin-top: 30px; }
        .links a { color: #8e44ad; text-decoration: none; font-weight: 600; margin: 0 15px; }
        .links a:hover { text-decoration: underline; }
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

            <!-- Success/Error Messages -->
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
                <p style="text-align:center; color:#7f8c8d; font-size:1.1rem; padding:30px 0;">
                    No schools added yet for this outlet.
                </p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>School Name</th>
                            <th width="120" style="text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schools as $i => $school): ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td class="school-name"><?php echo htmlspecialchars($school['school_name']); ?></td>
                                <td style="text-align:center;">
                                    <button class="btn-edit" onclick="openEditModal(<?php echo $school['school_id']; ?>, '<?php echo addslashes(htmlspecialchars($school['school_name'])); ?>')">
                                        Edit
                                    </button>
                                    <form method="POST" style="display:inline; margin-left:8px;">
                                        <input type="hidden" name="selected_school_id" value="<?php echo $school['school_id']; ?>">
                                        <button type="submit" name="select_school" class="btn-edit" style="background:#27ae60;">
                                            Select
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

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