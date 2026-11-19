<?php
function getDashboardStats($pdo) {
    $stats = [];
    
    // Total Products
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products");
    $stats['total_products'] = $stmt->fetch()['count'];
    
    // Total Sales
    $stmt = $pdo->query("SELECT SUM(total_price) as total FROM sales");
    $stats['total_sales'] = $stmt->fetch()['total'] ?: 0;
    
    // Total Sold Items
    $stmt = $pdo->query("SELECT SUM(quantity_sold) as total FROM sales");
    $stats['total_sold_items'] = $stmt->fetch()['total'] ?: 0;
    
    // Total Clients
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM clients");
    $stats['total_clients'] = $stmt->fetch()['count'];
    
    return $stats;
}

function getRecentSales($pdo, $limit = 5) {
    $stmt = $pdo->prepare("
        SELECT s.*, p.name as product_name, c.name as client_name 
        FROM sales s 
        LEFT JOIN products p ON s.product_id = p.id 
        LEFT JOIN clients c ON s.client_id = c.id 
        ORDER BY s.sale_date DESC 
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getLowStockProducts($pdo, $threshold = 10) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE quantity < :threshold ORDER BY quantity ASC");
    $stmt->bindValue(':threshold', (int)$threshold, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function updateStock($pdo, $product_id, $quantity_change, $type = 'add') {
    // Get current stock
    $stmt = $pdo->prepare("SELECT quantity FROM products WHERE id = :id");
    $stmt->bindValue(':id', $product_id, PDO::PARAM_INT);
    $stmt->execute();
    $current = $stmt->fetch()['quantity'];
    
    // Calculate new quantity
    if ($type === 'add') {
        $new_quantity = $current + $quantity_change;
    } else {
        $new_quantity = $current - $quantity_change;
        if ($new_quantity < 0) {
            return false; // Prevent negative stock
        }
    }
    
    // Update product
    $stmt = $pdo->prepare("UPDATE products SET quantity = :quantity WHERE id = :id");
    $stmt->bindValue(':quantity', $new_quantity, PDO::PARAM_INT);
    $stmt->bindValue(':id', $product_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Record in history
    $stmt = $pdo->prepare("INSERT INTO stock_history (product_id, change_type, quantity_change, previous_quantity, new_quantity) VALUES (:product_id, :type, :change, :prev, :new)");
    $stmt->bindValue(':product_id', $product_id, PDO::PARAM_INT);
    $stmt->bindValue(':type', $type, PDO::PARAM_STR);
    $stmt->bindValue(':change', $quantity_change, PDO::PARAM_INT);
    $stmt->bindValue(':prev', $current, PDO::PARAM_INT);
    $stmt->bindValue(':new', $new_quantity, PDO::PARAM_INT);
    $stmt->execute();
    
    return $new_quantity;
}
?>