<?php
// update_selected_items.php
session_start();

if (isset($_POST['items'])) {
    // CHANGE THIS LINE:
    $_SESSION['selected_sizes'] = $_POST['items'];  // ← Match what select_items.php uses
    echo "saved";
} else {
    echo "no_data";
}
?>