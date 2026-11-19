<?php
require_once 'config.php';
include 'functions.php';

// Handle stock update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'];
    $quantity = $_POST['quantity'];
    $action = $_POST['action']; // 'add' or 'remove'
    
    updateStock($pdo, $product_id, $quantity, $action);
    header("Location: stock.php?success=1");
    exit;
}

// Get all products with stock info
$stmt = $pdo->query("SELECT * FROM products ORDER BY quantity ASC");
$products = $stmt->fetchAll();

// Get stock history
$stmt = $pdo->query("
    SELECT sh.*, p.name as product_name 
    FROM stock_history sh 
    LEFT JOIN products p ON sh.product_id = p.id 
    ORDER BY sh.change_date DESC 
    LIMIT 50
");
$history = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Management - Inventory System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <header>
                <h1>Stock Management</h1>
            </header>

            <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">Stock updated successfully!</div>
            <?php endif; ?>

            <div class="dashboard-grid">
                <?php foreach($products as $product): 
                    $percentage = ($product['quantity'] / 100) * 100; // Adjust 100 to your max stock level
                ?>
                <div class="stat-card">
                    <div class="product-info">
                        <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p>Current Stock: <?php echo $product['quantity']; ?> units</p>
                        <p>Weight: <?php echo $product['weight_kg']; ?> kg</p>
                        
                        <div class="stock-bar">
                            <div class="stock-level" 
                                 data-level="<?php echo $product['quantity']; ?>"
                                 data-max="100"
                                 style="width: <?php echo min($percentage, 100); ?>%;">
                            </div>
                        </div>
                        
                        <div class="stock-actions">
                            <button onclick="updateStock(<?php echo $product['id']; ?>, 'add')" 
                                    class="btn btn-sm btn-success">
                                <i class="fas fa-plus"></i> Add
                            </button>
                            <button onclick="updateStock(<?php echo $product['id']; ?>, 'remove')" 
                                    class="btn btn-sm btn-danger">
                                <i class="fas fa-minus"></i> Remove
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Stock History -->
            <div class="form-container">
                <h2>Stock History</h2>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Action</th>
                                <th>Change</th>
                                <th>Previous</th>
                                <th>New</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($history as $record): ?>
                            <tr>
                                <td><?php echo date('M d, Y H:i', strtotime($record['change_date'])); ?></td>
                                <td><?php echo htmlspecialchars($record['product_name']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $record['change_type']; ?>">
                                        <?php echo ucfirst($record['change_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo $record['quantity_change']; ?></td>
                                <td><?php echo $record['previous_quantity']; ?></td>
                                <td><?php echo $record['new_quantity']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Stock Modal -->
    <div id="updateStockModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Stock</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" id="stockForm">
                <input type="hidden" id="stock_product_id" name="product_id">
                <input type="hidden" id="stock_action" name="action">
                
                <div class="form-group">
                    <label for="stock_quantity">Quantity</label>
                    <input type="number" id="stock_quantity" name="quantity" class="form-control" required min="1">
                </div>
                
                <button type="submit" class="btn btn-primary">Update Stock</button>
            </form>
        </div>
    </div>

    <script>
    function updateStock(productId, action) {
        document.getElementById('stock_product_id').value = productId;
        document.getElementById('stock_action').value = action;
        document.getElementById('updateStockModal').style.display = 'block';
    }
    
    function closeModal() {
        document.getElementById('updateStockModal').style.display = 'none';
    }
    </script>
</body>
</html>