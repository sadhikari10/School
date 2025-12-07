<?php
session_start();

if (isset($_POST['items'])) {
    $_SESSION['temp_items_json'] = json_encode($_POST['items']);
    echo "saved";
} else {
    echo "no_data";
}
