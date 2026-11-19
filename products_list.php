<?php
require_once 'config.php';

// Handle product deletion
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
    header("Location: products_list.php?deleted=1");
    exit;
}

// Get all products
$products = $pdo->query("SELECT * FROM products ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Products</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <header>
                <h1>All Products</h1>
                <a href="add_product.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add New
                </a>
            </header>

            <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">Product deleted successfully!</div>
            <?php endif; ?>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product Name</th>
                            <th>Price (FRW)</th>
                            <th>Stock Quantity</th>
                            <th>Added Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($products as $index => $product): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td class="rwf"><?php echo number_format($product['price_frw']); ?></td>
                            <td><?php echo $product['stock_quantity']; ?></td>
                            <td><?php echo date('M d, Y', strtotime($product['created_at'])); ?></td>
                            <td>
                                <a href="add_stock.php?product_id=<?php echo $product['id']; ?>" 
                                   class="btn btn-sm btn-primary" title="Add Stock">
                                    <i class="fas fa-plus"></i>
                                </a>
                                <a href="products_list.php?delete=<?php echo $product['id']; ?>" 
                                   class="btn btn-sm btn-danger" 
                                   onclick="return confirm('Delete this product?')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>