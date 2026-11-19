<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_id = $_POST['product_id'];
    $client_name = $_POST['client_name'];
    $client_phone = $_POST['client_phone'];
    $quantity = $_POST['quantity'];
    $selling_price = $_POST['selling_price']; // New field for selling price
    
    // Get product info
    $stmt = $pdo->prepare("SELECT price_frw, stock_quantity FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if ($product && $product['stock_quantity'] >= $quantity) {
        // Check if client exists or create new
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE phone = ?");
        $stmt->execute([$client_phone]);
        $client = $stmt->fetch();
        
        if (!$client) {
            // Create new client
            $stmt = $pdo->prepare("INSERT INTO clients (name, phone) VALUES (?, ?)");
            $stmt->execute([$client_name, $client_phone]);
            $client_id = $pdo->lastInsertId();
        } else {
            $client_id = $client['id'];
        }
        
        // Calculate total price based on entered selling price
        $total_price = $selling_price * $quantity;
        $new_stock = $product['stock_quantity'] - $quantity;
        
        // Update product stock
        $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
        $stmt->execute([$new_stock, $product_id]);
        
        // Record sale with selling price
        $stmt = $pdo->prepare("INSERT INTO sales (product_id, client_id, quantity_sold, selling_price, total_price_frw) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$product_id, $client_id, $quantity, $selling_price, $total_price]);
        
        // Record in stock history
        $stmt = $pdo->prepare("INSERT INTO stock_history (product_id, change_type, quantity_change, new_quantity) VALUES (?, 'sell', ?, ?)");
        $stmt->execute([$product_id, $quantity, $new_stock]);
        
        header("Location: sell.php?success=1&amount=" . $total_price);
        exit;
    } else {
        $error = "Not enough stock available!";
    }
}

// Get products with stock
$products = $pdo->query("SELECT * FROM products WHERE stock_quantity > 0 ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sell Product</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        function updatePrice() {
            const productId = document.getElementById('product_id').value;
            const quantity = document.getElementById('quantity').value;
            const sellingPrice = document.getElementById('selling_price').value;
            
            if (productId && quantity > 0) {
                // Get product price via AJAX
                fetch('get_price.php?id=' + productId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.price) {
                            document.getElementById('unit_price').textContent = data.price.toLocaleString() + ' FRW';
                            
                            // If selling price is entered, show custom total
                            if (sellingPrice > 0) {
                                const total = sellingPrice * quantity;
                                document.getElementById('total_price').textContent = total.toLocaleString() + ' FRW';
                                
                                // Show profit/loss compared to regular price
                                const regularTotal = data.price * quantity;
                                const difference = total - regularTotal;
                                if (difference != 0) {
                                    const profitLossElement = document.getElementById('profit_loss');
                                    profitLossElement.style.display = 'block';
                                    profitLossElement.className = difference > 0 ? 'profit' : 'loss';
                                    profitLossElement.innerHTML = difference > 0 ? 
                                        `<i class="fas fa-arrow-up"></i> Profit: ${difference.toLocaleString()} FRW` :
                                        `<i class="fas fa-arrow-down"></i> Loss: ${Math.abs(difference).toLocaleString()} FRW`;
                                } else {
                                    document.getElementById('profit_loss').style.display = 'none';
                                }
                            } else {
                                // Show regular price
                                const regularTotal = data.price * quantity;
                                document.getElementById('total_price').textContent = regularTotal.toLocaleString() + ' FRW';
                                document.getElementById('profit_loss').style.display = 'none';
                            }
                        }
                    });
            }
        }
        
        function updateMaxQuantity() {
            const productSelect = document.getElementById('product_id');
            const selectedOption = productSelect.options[productSelect.selectedIndex];
            const stockMatch = selectedOption.text.match(/\((\d+) available\)/);
            if (stockMatch) {
                document.getElementById('quantity').max = stockMatch[1];
            }
        }

        function validateSellingPrice(input) {
            const value = parseFloat(input.value);
            if (value <= 0) {
                input.value = '';
                document.getElementById('total_price').textContent = '0 FRW';
                document.getElementById('profit_loss').style.display = 'none';
            }
            updatePrice();
        }
    </script>
    <style>
        .price-display {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .price-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .price-item:last-child {
            border-bottom: none;
        }
        
        .price-item span {
            color: #6c757d;
        }
        
        .price-item strong {
            font-size: 1.2rem;
        }
        
        .profit {
            color: #28a745;
            font-weight: bold;
            margin-top: 10px;
            padding: 10px;
            background: #d4edda;
            border-radius: 5px;
        }
        
        .loss {
            color: #dc3545;
            font-weight: bold;
            margin-top: 10px;
            padding: 10px;
            background: #f8d7da;
            border-radius: 5px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            margin-right: 10px;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-lg {
            font-size: 1.1rem;
            padding: 14px 28px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .info-text {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <header>
                <h1>Sell Product</h1>
            </header>

            <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                Sale completed successfully! Amount: <strong><?php echo number_format($_GET['amount']); ?> FRW</strong>
            </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="form-container">
                <form method="POST">
                    <!-- Product Selection -->
                    <div class="form-group">
                        <label for="product_id">Select Product</label>
                        <select id="product_id" name="product_id" class="form-control" required 
                                onchange="updatePrice(); updateMaxQuantity();">
                            <option value="">-- Choose Product --</option>
                            <?php foreach($products as $product): ?>
                            <option value="<?php echo $product['id']; ?>">
                                <?php echo htmlspecialchars($product['name']); ?> 
                                (Cost: <?php echo number_format($product['price_frw']); ?> FRW, 
                                 <?php echo $product['stock_quantity']; ?> available)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Client Information -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="client_name">Client Name</label>
                            <input type="text" id="client_name" name="client_name" class="form-control" 
                                   placeholder="Client name" required>
                        </div>
                        <div class="form-group">
                            <label for="client_phone">Client Phone</label>
                            <input type="tel" id="client_phone" name="client_phone" class="form-control" 
                                   placeholder="078XXXXXXX" required>
                        </div>
                    </div>
                    
                    <!-- Quantity and Selling Price -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="quantity">Quantity</label>
                            <input type="number" id="quantity" name="quantity" class="form-control" required 
                                   min="1" oninput="updatePrice()" placeholder="Number of items">
                        </div>
                        <div class="form-group">
                            <label for="selling_price">Selling Price (per unit)</label>
                            <input type="number" id="selling_price" name="selling_price" class="form-control" required 
                                   min="1" oninput="validateSellingPrice(this)" placeholder="Enter selling price">
                            <div class="info-text">Enter the price you're selling this item for</div>
                        </div>
                    </div>
                    
                    <!-- Price Display -->
                    <div class="price-display">
                        <div class="price-item">
                            <span>Cost Price (per unit):</span>
                            <strong id="unit_price">0 FRW</strong>
                        </div>
                        <div class="price-item">
                            <span>Total Amount:</span>
                            <strong id="total_price" class="rwf">0 FRW</strong>
                        </div>
                        <div id="profit_loss" style="display: none;"></div>
                    </div>
                    
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fas fa-cash-register"></i> Complete Sale
                    </button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</body>
</html>