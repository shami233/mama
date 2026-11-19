<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $price_frw = $_POST['price_frw'];
    
    $stmt = $pdo->prepare("INSERT INTO products (name, price_frw) VALUES (?, ?)");
    $stmt->execute([$name, $price_frw]);
    
    header("Location: add_product.php?success=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <header>
                <h1>Add New Product</h1>
                <a href="products_list.php" class="btn btn-primary">View All Products</a>
            </header>

            <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">Product added successfully!</div>
            <?php endif; ?>

            <div class="form-container">
                <form method="POST">
                    <div class="form-group">
                        <label for="name">Product Name</label>
                        <input type="text" id="name" name="name" class="form-control" required 
                               placeholder="Enter product name">
                    </div>
                    
                    <div class="form-group">
                        <label for="price_frw">Price (FRW)</label>
                        <div class="input-with-icon">
                            <i class="fas fa-money-bill"></i>
                            <input type="number" id="price_frw" name="price_frw" class="form-control" required 
                                   placeholder="Enter price in Rwandan Francs" min="0" step="100">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fas fa-save"></i> Save Product
                    </button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</body>
</html>