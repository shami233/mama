<?php
require_once 'config.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $quantity = $_POST['quantity'];
    $weight_kg = $_POST['weight_kg'];
    $price = $_POST['price'];
    
    $stmt = $pdo->prepare("INSERT INTO products (name, description, quantity, weight_kg, price) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $description, $quantity, $weight_kg, $price]);
    
    // Add to stock history
    $product_id = $pdo->lastInsertId();
    $stmt = $pdo->prepare("INSERT INTO stock_history (product_id, change_type, quantity_change, previous_quantity, new_quantity) VALUES (?, 'add', ?, 0, ?)");
    $stmt->execute([$product_id, $quantity, $quantity]);
    
    header("Location: products.php?success=1");
    exit;
}

// Get all products
$stmt = $pdo->query("SELECT * FROM products ORDER BY created_at DESC");
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Inventory System</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <header>
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" id="product-search" placeholder="Search products...">
                </div>
                <button onclick="openAddProductModal()" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Product
                </button>
            </header>

            <!-- Success Message -->
            <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                Product added successfully!
            </div>
            <?php endif; ?>

            <div class="form-container">
                <h2>All Products</h2>
                <div class="table-responsive">
                    <table id="products-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Quantity</th>
                                <th>Weight (kg)</th>
                                <th>Price</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($products as $product): ?>
                            <tr>
                                <td><?php echo $product['id']; ?></td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo htmlspecialchars($product['description']); ?></td>
                                <td><?php echo $product['quantity']; ?></td>
                                <td><?php echo $product['weight_kg']; ?></td>
                                <td>$<?php echo number_format($product['price'], 2); ?></td>
                                <td>
                                    <button onclick="editProduct(<?php echo $product['id']; ?>)" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteProduct(<?php echo $product['id']; ?>)" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div id="addProductModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Product</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <form action="products.php" method="POST">
                <div class="form-group">
                    <label for="name">Product Name</label>
                    <input type="text" id="name" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="quantity">Quantity</label>
                        <input type="number" id="quantity" name="quantity" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="weight_kg">Weight (kg)</label>
                        <input type="number" step="0.01" id="weight_kg" name="weight_kg" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="price">Price ($)</label>
                        <input type="number" step="0.01" id="price" name="price" class="form-control" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Add Product</button>
            </form>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
    function openAddProductModal() {
        document.getElementById('addProductModal').style.display = 'block';
    }
    
    function closeModal() {
        document.getElementById('addProductModal').style.display = 'none';
    }
    
    // Search functionality
    document.getElementById('product-search').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('#products-table tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });
    </script>
</body>
</html>