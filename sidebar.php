<?php
// sidebar.php - Reusable sidebar component
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="logo">
        <i class="fas fa-store"></i>
        <h2>My Shop</h2>
    </div>
    <nav>
        <a href="index.php" <?php echo $current_page == 'index.php' ? 'class="active"' : ''; ?>>
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="add_product.php" <?php echo $current_page == 'add_product.php' ? 'class="active"' : ''; ?>>
            <i class="fas fa-plus-circle"></i> Add Product
        </a>
        <a href="add_stock.php" <?php echo $current_page == 'add_stock.php' ? 'class="active"' : ''; ?>>
            <i class="fas fa-boxes"></i> Add Stock
        </a>
        <a href="sell.php" <?php echo $current_page == 'sell.php' ? 'class="active"' : ''; ?>>
            <i class="fas fa-shopping-cart"></i> Sell Product
        </a>
        <a href="clients.php" <?php echo $current_page == 'clients.php' ? 'class="active"' : ''; ?>>
            <i class="fas fa-users"></i> Clients
        </a>
        <a href="report.php" <?php echo $current_page == 'report.php' ? 'class="active"' : ''; ?>>
            <i class="fas fa-chart-bar"></i> Report
        </a>
        <a href="products_list.php" <?php echo $current_page == 'products_list.php' ? 'class="active"' : ''; ?>>
            <i class="fas fa-list"></i> All Products
        </a>
            <a href="logout.php" style="margin-top: 20px; color: #dc3545;">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
    </nav>
</div>