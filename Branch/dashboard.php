<?php
session_start();

// Check if user is logged in and has outlet_id
if (!isset($_SESSION['user_id']) || !isset($_SESSION['outlet_id'])) {
    header("Location: login.php");
    exit();
}

// Correct path to connection file inside Common folder
require_once '../Common/connection.php';

$outlet_id = $_SESSION['outlet_id'];  // From staff login session

try {
    // Fetch only schools that belong to this staff's outlet
    $stmt = $pdo->prepare("SELECT school_id AS id, school_name AS name 
                           FROM schools 
                           WHERE outlet_id = ? 
                           ORDER BY school_name");
    $stmt->execute([$outlet_id]);
    $schools = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $schools = [];
    error_log("Dashboard Error: " . $e->getMessage());
}

// Handle school selection
if (isset($_POST['select_school'])) {
    $school_id   = (int)($_POST['school_id'] ?? 0);
    $school_name = trim($_POST['school_name'] ?? 'Other');

    // Store in session
    $_SESSION['selected_school_id']   = $school_id;
    $_SESSION['selected_school_name'] = $school_name;

    // Redirect to select_items.php
    header("Location: select_items.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select School - Staff Dashboard</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .logout-container {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        .logout-btn {
            background: #e74c3c;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
        }
        .logout-btn:hover { background: #c0392b; }

        .selected {
            border: 4px solid #8e44ad !important;
            background: #f3e8ff !important;
            transform: scale(1.05);
            box-shadow: 0 12px 30px rgba(142, 68, 173, 0.4);
            transition: all 0.3s;
        }

        .school-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
    </style>
</head>
<body>

    <div class="dashboard-container">
        <div class="header">
            <h1>Select School</h1>
            <p>Choose the school to proceed with measurement</p>
        </div>

        <div class="search-container">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search school..." autocomplete="off">
            </div>
        </div>

        <form method="POST" id="schoolForm">
            <input type="hidden" name="select_school" value="1">
            <input type="hidden" name="school_id" id="selectedSchoolId">
            <input type="hidden" name="school_name" id="selectedSchoolName">
            
            <div class="schools-grid" id="schoolsGrid">
                <?php if (!empty($schools)): ?>
                    <?php foreach ($schools as $school): ?>
                        <div class="school-card" 
                             onclick="selectSchool(<?php echo $school['id']; ?>, '<?php echo htmlspecialchars($school['name'], ENT_QUOTES); ?>', this)">
                            <div class="school-name"><?php echo htmlspecialchars($school['name']); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="grid-column: 1/-1; text-align:center; color:#666; font-size:1.2rem; padding:40px;">
                        No schools assigned to your outlet yet.
                    </p>
                <?php endif; ?>

                <!-- Always show "Other" option -->
                <div class="school-card other-card" 
                     onclick="selectSchool(0, 'Other', this)">
                    <div class="school-name">Other</div>
                </div>
            </div>

            <div style="margin-top:40px; text-align:center;">
                <a href="advance_payment.php" class="logout-btn" style="background:#667eea; margin-right:15px; padding:12px 25px;">
                   Clothe Collection
                </a>
                <a href="measurement.php" class="logout-btn" style="background:#667eea; margin-right:15px; padding:12px 25px;">
                    Take Measurement
                </a>
                <a href="logout.php" class="logout-btn" onclick="return confirm('Are you sure you want to logout?')">
                    Logout
                </a>
            </div>
        </form>
    </div>

    <script>
        let selectedCard = null;

        function selectSchool(schoolId, schoolName, card) {
            // Remove previous selection
            if (selectedCard) {
                selectedCard.classList.remove('selected');
            }
            
            // Apply selection
            selectedCard = card;
            card.classList.add('selected');
            
            // Set values
            document.getElementById('selectedSchoolId').value = schoolId;
            document.getElementById('selectedSchoolName').value = schoolName;
            
            // Auto-submit after short delay
            setTimeout(() => {
                document.getElementById('schoolForm').submit();
            }, 600);
        }

        // Live Search
        document.getElementById('searchInput').addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const cards = document.querySelectorAll('.school-card:not(.other-card)');
            
            cards.forEach(card => {
                const name = card.querySelector('.school-name').textContent.toLowerCase();
                card.style.display = name.includes(query) ? 'flex' : 'none';
            });

            // Always show "Other"
            document.querySelector('.other-card').style.display = 'flex';
        });
    </script>
</body>
</html>