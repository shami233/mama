<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_id = $_POST['product_id'];
    $quantity = $_POST['quantity'];
    
    // Get current stock
    $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if ($product) {
        $new_quantity = $product['stock_quantity'] + $quantity;
        
        // Update stock
        $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
        $stmt->execute([$new_quantity, $product_id]);
        
        // Record in history
        $stmt = $pdo->prepare("INSERT INTO stock_history (product_id, change_type, quantity_change, new_quantity) VALUES (?, 'add_stock', ?, ?)");
        $stmt->execute([$product_id, $quantity, $new_quantity]);
        
        header("Location: add_stock.php?success=1");
        exit;
    }
}

// Get all products
$products = $pdo->query("SELECT * FROM products ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Stock</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <header>
                <h1>Add Stock</h1>
            </header>

            <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">Stock added successfully!</div>
            <?php endif; ?>

            <div class="form-container">
                <form method="POST">
                    <div class="form-group">
                        <label for="product_id">Select Product</label>
                        <select id="product_id" name="product_id" class="form-control" required>
                            <option value="">-- Choose Product --</option>
                            <?php foreach($products as $product): ?>
                            <option value="<?php echo $product['id']; ?>">
                                <?php echo htmlspecialchars($product['name']); ?> 
                                (Current: <?php echo $product['stock_quantity']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="quantity">Number of Items to Add</label>
                        <input type="number" id="quantity" name="quantity" class="form-control" required 
                               min="1" placeholder="Enter quantity">
                    </div>
                    
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fas fa-plus-circle"></i> Add Stock
                    </button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
            
            <!-- Current Stock List -->
            <div class="current-stock" style="margin-top: 30px;">
                <h3>Current Stock</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Price (FRW)</th>
                                <th>Stock Quantity</th>
                                <th>Added Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($products as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td class="rwf"><?php echo number_format($product['price_frw']); ?></td>
                                <td><?php echo $product['stock_quantity']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($product['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>