<?php
session_start();
require '../Common/connection.php';

$bill = $_GET['bill'] ?? '';
if(empty($bill)){
    echo json_encode([]);
    exit();
}

$stmt = $pdo->prepare("SELECT item_name, exchange_status FROM advance_items WHERE bill_id = ? AND exchange_status != 'original'");
$stmt->execute([$bill]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($items);
?>
