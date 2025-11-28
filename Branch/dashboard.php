<?php
require_once '../Common/connection.php';

try {
    $stmt = $pdo->query("SELECT id, name FROM schools ORDER BY name");
    $schools = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $schools = [];
}

if ($_POST['select_school'] ?? '') {
    $schoolId = $_POST['school_id'] ?? 0;
    $schoolName = $_POST['school_name'] ?? 'Other';
    
    if ($schoolId !== null) {
        session_start();
        $_SESSION['selected_school_id'] = $schoolId;
        $_SESSION['selected_school_name'] = $schoolName;
        header("Location: select_items.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Selection Dashboard</title>
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <div class="header">
            <h1>🏫 Select Your School</h1>
            <p>Choose your school from the list or select Other</p>
        </div>

        <div class="search-container">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="🔍 Search school..." autocomplete="off">
            </div>
        </div>

        <form method="POST" id="schoolForm">
            <input type="hidden" name="select_school" value="1">
            <input type="hidden" name="school_id" id="selectedSchoolId">
            <input type="hidden" name="school_name" id="selectedSchoolName">
            
            <div class="schools-grid" id="schoolsGrid">
                <?php foreach ($schools as $school): ?>
                    <div class="school-card" onclick="selectSchool(<?php echo $school['id']; ?>, '<?php echo htmlspecialchars($school['name']); ?>', this)">
                        <div class="school-name"><?php echo htmlspecialchars($school['name']); ?></div>
                    </div>
                <?php endforeach; ?>
                
                <div class="school-card other-card" onclick="selectSchool(0, 'Other', this)">
                    <div class="school-name">Other</div>
                </div>
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
            
            // Select new card
            selectedCard = card;
            card.classList.add('selected');
            
            // Set hidden inputs
            document.getElementById('selectedSchoolId').value = schoolId;
            document.getElementById('selectedSchoolName').value = schoolName;
            
            // Auto-submit form after 500ms
            setTimeout(() => {
                document.getElementById('schoolForm').submit();
            }, 500);
        }

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const cards = document.querySelectorAll('.school-card:not(.other-card)');
            
            cards.forEach(card => {
                const schoolName = card.querySelector('.school-name').textContent.toLowerCase();
                if (schoolName.includes(query)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Always show "Other" option
            document.querySelector('.other-card').style.display = 'flex';
        });
    </script>
</body>
</html>