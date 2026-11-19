<?php
require_once 'config.php';



// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get total money gained
$stmt = $pdo->query("SELECT SUM(total_price_frw) as total_money FROM sales");
$total_money = $stmt->fetch()['total_money'] ?: 0;
// Get total money gained
$stmt = $pdo->query("SELECT SUM(total_price_frw) as total_money FROM sales");
$total_money = $stmt->fetch()['total_money'] ?: 0;

// Get total products
$stmt = $pdo->query("SELECT COUNT(*) as total FROM products");
$total_products = $stmt->fetch()['total'];

// Get total items sold
$stmt = $pdo->query("SELECT SUM(quantity_sold) as total FROM sales");
$total_sold = $stmt->fetch()['total'] ?: 0;

// Get total stock
$stmt = $pdo->query("SELECT SUM(stock_quantity) as total FROM products");
$total_stock = $stmt->fetch()['total'] ?: 0;

// Get recent sales
$stmt = $pdo->query("
    SELECT s.*, p.name as product_name, c.name as client_name, c.phone 
    FROM sales s 
    LEFT JOIN products p ON s.product_id = p.id 
    LEFT JOIN clients c ON s.client_id = c.id 
    ORDER BY s.sale_date DESC 
    LIMIT 5
");
$recent_sales = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Stock System - Rwanda</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .currency {
            color: #10b981;
            font-weight: bold;
        }
        .rwf::after {
            content: " FRW";
            font-size: 0.9em;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <i class="fas fa-store"></i>
                <h2>My Shop</h2>
            </div>
            <nav>
                <a href="index.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
                <a href="add_product.php"><i class="fas fa-plus-circle"></i> Add Product</a>
                <a href="add_stock.php"><i class="fas fa-boxes"></i> Add Stock</a>
                <a href="sell.php"><i class="fas fa-shopping-cart"></i> Sell Product</a>
                <a href="clients.php"><i class="fas fa-users"></i> Clients</a>
                <a href="report.php"><i class="fas fa-chart-bar"></i> Report</a>
                <a href="products_list.php"><i class="fas fa-list"></i> All Products</a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <header>
                <h1>My Shop Dashboard</h1>
                <div class="date-display">
                    <i class="far fa-calendar"></i>
                    <span><?php echo date('l, F j, Y'); ?></span>
                </div>
            </header>

            <!-- Quick Stats -->
            <div class="dashboard-grid">
                <div class="stat-card bg-primary">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-info">
                        <h3 class="rwf"><?php echo number_format($total_money); ?></h3>
                        <p>Total Money Gained</p>
                    </div>
                </div>

                <div class="stat-card bg-success">
                    <div class="stat-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_products; ?></h3>
                        <p>Total Products</p>
                    </div>
                </div>

                <div class="stat-card bg-warning">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_sold; ?></h3>
                        <p>Items Sold</p>
                    </div>
                </div>

                <div class="stat-card bg-info">
                    <div class="stat-icon">
                        <i class="fas fa-warehouse"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_stock; ?></h3>
                        <p>Total in Stock</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h2>Quick Actions</h2>
                <div class="action-grid">
                    <a href="add_product.php" class="action-card">
                        <i class="fas fa-plus"></i>
                        <span>Add New Product</span>
                    </a>
                    <a href="add_stock.php" class="action-card">
                        <i class="fas fa-box"></i>
                        <span>Add Stock</span>
                    </a>
                    <a href="sell.php" class="action-card">
                        <i class="fas fa-cash-register"></i>
                        <span>Sell Product</span>
                    </a>
                    <a href="report.php" class="action-card">
                        <i class="fas fa-file-alt"></i>
                        <span>View Report</span>
                    </a>
                </div>
            </div>

            <!-- Recent Sales -->
            <div class="recent-sales">
                <h2>Recent Sales</h2>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Phone</th>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_sales)): ?>
                                <?php foreach($recent_sales as $sale): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($sale['client_name'] ?? 'Walk-in'); ?></td>
                                    <td><?php echo htmlspecialchars($sale['phone'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($sale['product_name']); ?></td>
                                    <td><?php echo $sale['quantity_sold']; ?></td>
                                    <td class="rwf"><?php echo number_format($sale['total_price_frw']); ?></td>
                                    <td><?php echo date('M d, H:i', strtotime($sale['sale_date'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center;">No sales yet. Start selling!</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh every 30 seconds
        setTimeout(function() {
            window.location.reload();
        }, 30000);
    </script>
</body>
</html>