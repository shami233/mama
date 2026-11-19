<?php
// goods.php
require_once 'config.php';
requireLogin();

// Add new good
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_good'])) {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $quantity = $_POST['quantity'];
    $weight_kg = $_POST['weight_kg'];
    $price = $_POST['price'];
    $category = $_POST['category'];
    
    $stmt = $pdo->prepare("INSERT INTO goods (name, description, quantity, weight_kg, price, category) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $description, $quantity, $weight_kg, $price, $category]);
    
    // Add to stock history
    $good_id = $pdo->lastInsertId();
    $history_stmt = $pdo->prepare("
        INSERT INTO stock_history (good_id, change_type, quantity_change, previous_quantity, new_quantity, notes)
        VALUES (?, 'ADD', ?, 0, ?, 'Initial stock')
    ");
    $history_stmt->execute([$good_id, $quantity, $quantity]);
    
    $success = "Product added successfully!";
}

// Get all goods
$goods = $pdo->query("SELECT * FROM goods ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goods Management - Inventory System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar (same as dashboard) -->
        <?php include 'sidebar.php'; ?>

        <main class="main-content">
            <nav class="navbar">
                <div class="navbar-left">
                    <h1>Goods Management</h1>
                </div>
                <div class="navbar-right">
                    <button class="btn btn-primary" id="addGoodBtn">
                        <i class="fas fa-plus"></i> Add New Product
                    </button>
                </div>
            </nav>

            <div class="content-wrapper">
                <?php if(isset($success)): ?>
                    <div class="alert success"><?php echo $success; ?></div>
                <?php endif; ?>

                <div class="table-container">
                    <div class="table-header">
                        <h2>All Products</h2>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Weight (kg)</th>
                                <th>Price ($)</th>
                                <th>Stock Value</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($goods as $good): ?>
                            <tr>
                                <td><?php echo $good['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($good['name']); ?></strong>
                                    <?php if($good['description']): ?>
                                    <br><small><?php echo htmlspecialchars($good['description']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $good['category'] ?: 'Uncategorized'; ?></td>
                                <td>
                                    <span class="stock-badge <?php echo $good['quantity'] < 10 ? 'danger' : ($good['quantity'] < 20 ? 'warning' : 'success'); ?>">
                                        <?php echo $good['quantity']; ?>
                                    </span>
                                </td>
                                <td><?php echo $good['weight_kg'] ? number_format($good['weight_kg'], 2) . ' kg' : 'N/A'; ?></td>
                                <td>$<?php echo number_format($good['price'], 2); ?></td>
                                <td>$<?php echo number_format($good['quantity'] * $good['price'], 2); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary edit-btn" data-id="<?php echo $good['id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger delete-btn" data-id="<?php echo $good['id']; ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Product Modal -->
    <div id="addGoodModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Product</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Product Name *</label>
                            <input type="text" id="name" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select id="category" name="category" class="form-control">
                                <option value="">Select Category</option>
                                <option value="Electronics">Electronics</option>
                                <option value="Clothing">Clothing</option>
                                <option value="Food">Food</option>
                                <option value="Books">Books</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="quantity">Initial Quantity *</label>
                            <input type="number" id="quantity" name="quantity" class="form-control" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="weight_kg">Weight (kg)</label>
                            <input type="number" id="weight_kg" name="weight_kg" class="form-control" step="0.01" min="0">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="price">Price ($) *</label>
                            <input type="number" id="price" name="price" class="form-control" step="0.01" min="0" required>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_good" class="btn btn-primary">Add Product</button>
                </form>
            </div>
        </div>
    </div>

    <script src="js/main.js"></script>
</body>
</html>