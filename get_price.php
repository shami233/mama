<?php
require_once 'config.php';

if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT price_frw FROM products WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $product = $stmt->fetch();
    
    header('Content-Type: application/json');
    echo json_encode(['price' => $product['price_frw'] ?? 0]);
}
?>