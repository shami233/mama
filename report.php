<?php
require_once 'config.php';

// Get filter parameters
$filter_type = $_GET['filter_type'] ?? 'all'; // all, date_range, month, year
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$selected_month = $_GET['month'] ?? date('Y-m');
$selected_year = $_GET['year'] ?? date('Y');

// Build query based on filter
$where_conditions = [];
$params = [];

if ($filter_type == 'date_range' && !empty($start_date) && !empty($end_date)) {
    $where_conditions[] = "DATE(s.sale_date) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
} elseif ($filter_type == 'month' && !empty($selected_month)) {
    $where_conditions[] = "DATE_FORMAT(s.sale_date, '%Y-%m') = ?";
    $params[] = $selected_month;
} elseif ($filter_type == 'year' && !empty($selected_year)) {
    $where_conditions[] = "YEAR(s.sale_date) = ?";
    $params[] = $selected_year;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get current stock status
$stock_query = "
    SELECT 
        p.*,
        COALESCE(SUM(s.quantity_sold), 0) as total_sold_all_time
    FROM products p
    LEFT JOIN sales s ON p.id = s.product_id
    GROUP BY p.id
    ORDER BY p.name
";
$stock_stmt = $pdo->query($stock_query);
$stock_status = $stock_stmt->fetchAll();

// Get daily stock movement
$daily_stock_query = "
    SELECT 
        DATE(s.sale_date) as date,
        p.id as product_id,
        p.name as product_name,
        p.stock_quantity as current_stock,
        COALESCE(SUM(s.quantity_sold), 0) as sold_today,
        p.price_frw as unit_price,
        COALESCE(SUM(s.total_price_frw), 0) as revenue_today
    FROM products p
    LEFT JOIN sales s ON p.id = s.product_id
    " . ($where_clause ? str_replace('s.sale_date', 's.sale_date', $where_clause) : "") . "
    GROUP BY p.id, DATE(s.sale_date)
    ORDER BY date DESC, product_name
";
$daily_stmt = $pdo->prepare($daily_stock_query);
$daily_stmt->execute($params);
$daily_stock_movement = $daily_stmt->fetchAll();

// Get detailed sales data with profit calculation
$query = "
    SELECT 
        s.*,
        c.name as client_name,
        c.phone as client_phone,
        p.name as product_name,
        p.price_frw as cost_price,
        p.stock_quantity as remaining_stock,
        s.selling_price,
        s.quantity_sold,
        s.total_price_frw as total_sold,
        (s.selling_price - p.price_frw) as profit_per_unit,
        ((s.selling_price - p.price_frw) * s.quantity_sold) as total_profit,
        DATE_FORMAT(s.sale_date, '%Y-%m-%d') as sale_date_only,
        DATE_FORMAT(s.sale_date, '%H:%i:%s') as sale_time
    FROM sales s
    LEFT JOIN products p ON s.product_id = p.id
    LEFT JOIN clients c ON s.client_id = c.id
    $where_clause
    ORDER BY s.sale_date DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$sales_data = $stmt->fetchAll();

// Calculate totals
$total_sales = count($sales_data);
$total_items_sold = array_sum(array_column($sales_data, 'quantity_sold'));
$total_revenue = array_sum(array_column($sales_data, 'total_sold'));
$total_cost = 0;
$total_profit = 0;

foreach ($sales_data as $sale) {
    $cost = ($sale['cost_price'] ?? 0) * $sale['quantity_sold'];
    $total_cost += $cost;
    $total_profit += $sale['total_profit'] ?? 0;
}

// Calculate profit margin
$profit_margin = $total_revenue > 0 ? ($total_profit / $total_revenue) * 100 : 0;

// Get product performance data
$product_query = "
    SELECT 
        p.id,
        p.name,
        p.stock_quantity as current_stock,
        COUNT(s.id) as times_sold,
        SUM(s.quantity_sold) as total_quantity_sold,
        SUM(s.total_price_frw) as total_revenue,
        SUM((s.selling_price - p.price_frw) * s.quantity_sold) as total_profit
    FROM products p
    LEFT JOIN sales s ON p.id = s.product_id
    $where_clause
    GROUP BY p.id
    ORDER BY total_revenue DESC
";

$stmt = $pdo->prepare($product_query);
$stmt->execute($params);
$product_performance = $stmt->fetchAll();

// Get client performance data
$client_query = "
    SELECT 
        c.id,
        c.name,
        c.phone,
        COUNT(s.id) as purchase_count,
        SUM(s.quantity_sold) as items_bought,
        SUM(s.total_price_frw) as total_spent,
        AVG(s.total_price_frw) as avg_purchase
    FROM clients c
    LEFT JOIN sales s ON c.id = s.client_id
    $where_clause
    GROUP BY c.id
    HAVING purchase_count > 0
    ORDER BY total_spent DESC
";

$stmt = $pdo->prepare($client_query);
$stmt->execute($params);
$client_performance = $stmt->fetchAll();

// Get daily summary for charts
$daily_query = "
    SELECT 
        DATE(s.sale_date) as date,
        COUNT(DISTINCT s.id) as num_sales,
        SUM(s.quantity_sold) as items_sold,
        SUM(s.total_price_frw) as revenue,
        SUM((s.selling_price - p.price_frw) * s.quantity_sold) as profit,
        COUNT(DISTINCT s.product_id) as products_sold
    FROM sales s
    LEFT JOIN products p ON s.product_id = p.id
    $where_clause
    GROUP BY DATE(s.sale_date)
    ORDER BY date DESC
";

$stmt = $pdo->prepare($daily_query);
$stmt->execute($params);
$daily_summary = $stmt->fetchAll();

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="complete_report_' . date('Y-m-d') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel
    fwrite($output, "\xEF\xBB\xBF");
    
    // Report Header
    fputcsv($output, ['COMPLETE STOCK AND SALES REPORT']);
    fputcsv($output, ['Generated: ' . date('Y-m-d H:i:s')]);
    fputcsv($output, ['Period: ' . getPeriodText($filter_type, $start_date, $end_date, $selected_month, $selected_year)]);
    fputcsv($output, []);
    
    // SECTION 1: CURRENT STOCK STATUS
    fputcsv($output, ['════════════════════════════════════════════════════════════════════════════════']);
    fputcsv($output, ['SECTION 1: CURRENT STOCK STATUS']);
    fputcsv($output, ['════════════════════════════════════════════════════════════════════════════════']);
    fputcsv($output, ['Product Name', 'Current Stock', 'Total Sold (All Time)', 'Unit Price (FRW)', 'Stock Value (FRW)', 'Status']);
    
    $total_stock_value = 0;
    foreach ($stock_status as $product) {
        $stock_value = $product['stock_quantity'] * $product['price_frw'];
        $total_stock_value += $stock_value;
        
        $status = '';
        if ($product['stock_quantity'] > 20) {
            $status = 'In Stock';
        } elseif ($product['stock_quantity'] > 5) {
            $status = 'Low Stock';
        } elseif ($product['stock_quantity'] > 0) {
            $status = 'Critical';
        } else {
            $status = 'Out of Stock';
        }
        
        fputcsv($output, [
            $product['name'],
            $product['stock_quantity'],
            $product['total_sold_all_time'],
            number_format($product['price_frw']),
            number_format($stock_value),
            $status
        ]);
    }
    
    fputcsv($output, ['TOTAL STOCK VALUE:', '', '', '', number_format($total_stock_value) . ' FRW', '']);
    fputcsv($output, []);
    
    // SECTION 2: DAILY STOCK MOVEMENT
    fputcsv($output, ['════════════════════════════════════════════════════════════════════════════════']);
    fputcsv($output, ['SECTION 2: DAILY STOCK MOVEMENT']);
    fputcsv($output, ['════════════════════════════════════════════════════════════════════════════════']);
    fputcsv($output, ['Date', 'Product', 'Sold Today', 'Remaining Stock', 'Unit Price', 'Revenue Today']);
    
    $total_daily_sold = 0;
    $total_daily_revenue = 0;
    foreach ($daily_stock_movement as $movement) {
        fputcsv($output, [
            $movement['date'] ?? 'N/A',
            $movement['product_name'],
            $movement['sold_today'],
            $movement['current_stock'],
            number_format($movement['unit_price']) . ' FRW',
            number_format($movement['revenue_today']) . ' FRW'
        ]);
        $total_daily_sold += $movement['sold_today'];
        $total_daily_revenue += $movement['revenue_today'];
    }
    
    fputcsv($output, ['TOTAL:', '', $total_daily_sold, '', '', number_format($total_daily_revenue) . ' FRW']);
    fputcsv($output, []);
    
    // SECTION 3: SALES SUMMARY
    fputcsv($output, ['════════════════════════════════════════════════════════════════════════════════']);
    fputcsv($output, ['SECTION 3: SALES SUMMARY']);
    fputcsv($output, ['════════════════════════════════════════════════════════════════════════════════']);
    fputcsv($output, ['Total Transactions', $total_sales]);
    fputcsv($output, ['Total Items Sold', $total_items_sold]);
    fputcsv($output, ['Total Revenue', number_format($total_revenue) . ' FRW']);
    fputcsv($output, ['Total Cost', number_format($total_cost) . ' FRW']);
    fputcsv($output, ['Total Profit', number_format($total_profit) . ' FRW']);
    fputcsv($output, ['Profit Margin', number_format($profit_margin, 2) . '%']);
    fputcsv($output, []);
    
    // SECTION 4: DAILY SUMMARY
    fputcsv($output, ['════════════════════════════════════════════════════════════════════════════════']);
    fputcsv($output, ['SECTION 4: DAILY SUMMARY']);
    fputcsv($output, ['════════════════════════════════════════════════════════════════════════════════']);
    fputcsv($output, ['Date', 'Transactions', 'Products Sold', 'Items Sold', 'Revenue', 'Profit']);
    
    foreach ($daily_summary as $day) {
        fputcsv($output, [
            $day['date'],
            $day['num_sales'],
            $day['products_sold'],
            $day['items_sold'],
            number_format($day['revenue']) . ' FRW',
            number_format($day['profit']) . ' FRW'
        ]);
    }
    fputcsv($output, []);
    
    // SECTION 5: DETAILED SALES
    fputcsv($output, ['════════════════════════════════════════════════════════════════════════════════']);
    fputcsv($output, ['SECTION 5: DETAILED SALES TRANSACTIONS']);
    fputcsv($output, ['════════════════════════════════════════════════════════════════════════════════']);
    fputcsv($output, ['Date', 'Time', 'Client', 'Phone', 'Product', 'Quantity', 'Selling Price', 'Cost Price', 'Profit/Unit', 'Total', 'Total Profit', 'Remaining Stock']);
    
    foreach ($sales_data as $sale) {
        fputcsv($output, [
            $sale['sale_date_only'],
            $sale['sale_time'],
            $sale['client_name'],
            $sale['client_phone'],
            $sale['product_name'],
            $sale['quantity_sold'],
            number_format($sale['selling_price']) . ' FRW',
            number_format($sale['cost_price']) . ' FRW',
            number_format($sale['profit_per_unit']) . ' FRW',
            number_format($sale['total_sold']) . ' FRW',
            number_format($sale['total_profit']) . ' FRW',
            $sale['remaining_stock']
        ]);
    }
    
    fputcsv($output, ['TOTAL:', '', '', '', '', $total_items_sold, '', '', '', number_format($total_revenue) . ' FRW', number_format($total_profit) . ' FRW', '']);
    fputcsv($output, []);
    
    // SECTION 6: PRODUCT PERFORMANCE WITH STOCK
    fputcsv($output, ['════════════════════════════════════════════════════════════════════════════════']);
    fputcsv($output, ['SECTION 6: PRODUCT PERFORMANCE']);
    fputcsv($output, ['════════════════════════════════════════════════════════════════════════════════']);
    fputcsv($output, ['Product', 'Current Stock', 'Times Sold', 'Quantity Sold', 'Revenue', 'Profit', 'Profit Margin']);
    
    foreach ($product_performance as $product) {
        $margin = $product['total_revenue'] > 0 ? ($product['total_profit'] / $product['total_revenue']) * 100 : 0;
        fputcsv($output, [
            $product['name'],
            $product['current_stock'],
            $product['times_sold'],
            $product['total_quantity_sold'],
            number_format($product['total_revenue']) . ' FRW',
            number_format($product['total_profit']) . ' FRW',
            number_format($margin, 2) . '%'
        ]);
    }
    
    fclose($output);
    exit;
}

// Helper function to get period text
function getPeriodText($filter_type, $start_date, $end_date, $selected_month, $selected_year) {
    switch($filter_type) {
        case 'date_range':
            return date('M d, Y', strtotime($start_date)) . ' to ' . date('M d, Y', strtotime($end_date));
        case 'month':
            return date('F Y', strtotime($selected_month . '-01'));
        case 'year':
            return 'Year ' . $selected_year;
        default:
            return 'All Time';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Report - My Shop</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .report-container {
            padding: 20px;
        }
        
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .filter-form {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 150px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            color: #666;
            font-weight: 500;
        }
        
        .filter-group select, .filter-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .btn-filter {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-export {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .summary-card i {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .summary-card .value {
            font-size: 1.8rem;
            font-weight: bold;
            color: #333;
        }
        
        .summary-card .label {
            color: #666;
            margin-top: 5px;
            font-size: 0.9rem;
        }
        
        .profit-positive {
            color: #28a745;
        }
        
        .profit-negative {
            color: #dc3545;
        }
        
        .stock-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-good {
            background: #d4edda;
            color: #155724;
        }
        
        .status-low {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-critical {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-out {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .charts-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .chart-container h3 {
            margin-bottom: 20px;
            color: #333;
            font-size: 1.1rem;
        }
        
        .section-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .section-title {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: #667eea;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #667eea;
            color: white;
            padding: 12px;
            text-align: left;
            font-size: 0.9rem;
        }
        
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #eee;
            font-size: 0.9rem;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-bottom: 20px;
        }
        
        .period-info {
            background: #e8f4f8;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-primary {
            background: #667eea;
            color: white;
        }
        
        .badge-success {
            background: #28a745;
            color: white;
        }
        
        .badge-warning {
            background: #ffc107;
            color: #333;
        }
        
        .badge-danger {
            background: #dc3545;
            color: white;
        }
        
        @media print {
            .filter-section, .action-buttons, .btn-filter, .btn-export {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <header>
                <h1><i class="fas fa-chart-bar"></i> Complete Stock & Sales Report</h1>
            </header>
            
            <div class="report-container">
                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" class="filter-form">
                        <div class="filter-group">
                            <label>Filter By</label>
                            <select name="filter_type" id="filter_type" onchange="toggleFilter()">
                                <option value="all" <?php echo $filter_type == 'all' ? 'selected' : ''; ?>>All Time</option>
                                <option value="date_range" <?php echo $filter_type == 'date_range' ? 'selected' : ''; ?>>Date Range</option>
                                <option value="month" <?php echo $filter_type == 'month' ? 'selected' : ''; ?>>Month</option>
                                <option value="year" <?php echo $filter_type == 'year' ? 'selected' : ''; ?>>Year</option>
                            </select>
                        </div>
                        
                        <div class="filter-group" id="date_range_group" style="display: none;">
                            <label>Start Date</label>
                            <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        
                        <div class="filter-group" id="date_range_group2" style="display: none;">
                            <label>End Date</label>
                            <input type="date" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        
                        <div class="filter-group" id="month_group" style="display: none;">
                            <label>Month</label>
                            <input type="month" name="month" value="<?php echo $selected_month; ?>">
                        </div>
                        
                        <div class="filter-group" id="year_group" style="display: none;">
                            <label>Year</label>
                            <select name="year">
                                <?php for($y = date('Y'); $y >= 2020; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo $selected_year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <button type="submit" class="btn-filter">
                                <i class="fas fa-search"></i> Apply Filter
                            </button>
                        </div>
                        
                        <div class="filter-group">
                            <a href="?export=csv&filter_type=<?php echo $filter_type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" class="btn-export">
                                <i class="fas fa-download"></i> Export Complete CSV
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Period Info -->
                <div class="period-info">
                    <span><i class="far fa-calendar-alt"></i> Period: <strong><?php echo getPeriodText($filter_type, $start_date, $end_date, $selected_month, $selected_year); ?></strong></span>
                    <span><i class="far fa-clock"></i> Generated: <?php echo date('Y-m-d H:i:s'); ?></span>
                </div>
                
                <!-- Summary Cards -->
                <div class="summary-cards">
                    <div class="summary-card">
                        <i class="fas fa-boxes"></i>
                        <div class="value"><?php echo array_sum(array_column($stock_status, 'stock_quantity')); ?></div>
                        <div class="label">Total Items in Stock</div>
                    </div>
                    
                    <div class="summary-card">
                        <i class="fas fa-dollar-sign"></i>
                        <div class="value profit-positive">
                            <?php 
                            $total_stock_value = array_sum(array_map(function($p) {
                                return $p['stock_quantity'] * $p['price_frw'];
                            }, $stock_status));
                            echo number_format($total_stock_value) . ' FRW';
                            ?>
                        </div>
                        <div class="label">Total Stock Value</div>
                    </div>
                    
                    <div class="summary-card">
                        <i class="fas fa-shopping-cart"></i>
                        <div class="value"><?php echo $total_sales; ?></div>
                        <div class="label">Transactions</div>
                    </div>
                    
                    <div class="summary-card">
                        <i class="fas fa-chart-line"></i>
                        <div class="value <?php echo $total_profit >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                            <?php echo number_format($total_profit); ?> FRW
                        </div>
                        <div class="label">Total Profit</div>
                    </div>
                </div>
                
                <!-- Charts -->
                <div class="charts-row">
                    <div class="chart-container">
                        <h3><i class="fas fa-chart-line"></i> Revenue & Profit Trend</h3>
                        <canvas id="revenueChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h3><i class="fas fa-chart-bar"></i> Daily Sales Volume</h3>
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
                
                <!-- Current Stock Status -->
                <div class="section-card">
                    <h2 class="section-title">
                        <i class="fas fa-boxes"></i> Current Stock Status
                        <span class="badge badge-primary">Total Products: <?php echo count($stock_status); ?></span>
                    </h2>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Current Stock</th>
                                    <th>Total Sold (All Time)</th>
                                    <th>Unit Price</th>
                                    <th>Stock Value</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_stock_value = 0;
                                foreach($stock_status as $product): 
                                    $stock_value = $product['stock_quantity'] * $product['price_frw'];
                                    $total_stock_value += $stock_value;
                                    
                                    $status_class = '';
                                    $status_text = '';
                                    if ($product['stock_quantity'] > 20) {
                                        $status_class = 'status-good';
                                        $status_text = 'In Stock';
                                    } elseif ($product['stock_quantity'] > 5) {
                                        $status_class = 'status-low';
                                        $status_text = 'Low Stock';
                                    } elseif ($product['stock_quantity'] > 0) {
                                        $status_class = 'status-critical';
                                        $status_text = 'Critical';
                                    } else {
                                        $status_class = 'status-out';
                                        $status_text = 'Out of Stock';
                                    }
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                                    <td><?php echo $product['stock_quantity']; ?></td>
                                    <td><?php echo $product['total_sold_all_time']; ?></td>
                                    <td><?php echo number_format($product['price_frw']); ?> FRW</td>
                                    <td class="profit-positive"><?php echo number_format($stock_value); ?> FRW</td>
                                    <td><span class="stock-status <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr style="background: #f8f9fa; font-weight: bold;">
                                    <td colspan="4" style="text-align: right;">TOTAL STOCK VALUE:</td>
                                    <td class="profit-positive"><?php echo number_format($total_stock_value); ?> FRW</td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Daily Stock Movement -->
                <div class="section-card">
                    <h2 class="section-title">
                        <i class="fas fa-calendar-day"></i> Daily Stock Movement
                        <span class="badge badge-primary">Items Sold Today: <?php echo array_sum(array_column($daily_stock_movement, 'sold_today')); ?></span>
                    </h2>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Product</th>
                                    <th>Sold Today</th>
                                    <th>Remaining Stock</th>
                                    <th>Unit Price</th>
                                    <th>Revenue Today</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_daily_sold = 0;
                                $total_daily_revenue = 0;
                                foreach($daily_stock_movement as $movement): 
                                    $total_daily_sold += $movement['sold_today'];
                                    $total_daily_revenue += $movement['revenue_today'];
                                ?>
                                <tr>
                                    <td><?php echo $movement['date'] ?? 'N/A'; ?></td>
                                    <td><?php echo htmlspecialchars($movement['product_name']); ?></td>
                                    <td><?php echo $movement['sold_today']; ?></td>
                                    <td><?php echo $movement['current_stock']; ?></td>
                                    <td><?php echo number_format($movement['unit_price']); ?> FRW</td>
                                    <td class="profit-positive"><?php echo number_format($movement['revenue_today']); ?> FRW</td>
                                </tr>
                                <?php endforeach; ?>
                                <tr style="background: #f8f9fa; font-weight: bold;">
                                    <td colspan="2" style="text-align: right;">TOTALS:</td>
                                    <td><?php echo $total_daily_sold; ?></td>
                                    <td></td>
                                    <td></td>
                                    <td class="profit-positive"><?php echo number_format($total_daily_revenue); ?> FRW</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Daily Summary -->
                <div class="section-card">
                    <h2 class="section-title">
                        <i class="fas fa-chart-simple"></i> Daily Summary
                    </h2>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Transactions</th>
                                    <th>Products Sold</th>
                                    <th>Items Sold</th>
                                    <th>Revenue</th>
                                    <th>Profit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($daily_summary as $day): ?>
                                <tr>
                                    <td><?php echo $day['date']; ?></td>
                                    <td><?php echo $day['num_sales']; ?></td>
                                    <td><?php echo $day['products_sold']; ?></td>
                                    <td><?php echo $day['items_sold']; ?></td>
                                    <td class="profit-positive"><?php echo number_format($day['revenue']); ?> FRW</td>
                                    <td class="<?php echo $day['profit'] >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                                        <?php echo number_format($day['profit']); ?> FRW
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Product Performance -->
                <div class="section-card">
                    <h2 class="section-title">
                        <i class="fas fa-chart-pie"></i> Product Performance
                    </h2>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Current Stock</th>
                                    <th>Times Sold</th>
                                    <th>Quantity Sold</th>
                                    <th>Revenue</th>
                                    <th>Profit</th>
                                    <th>Margin</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($product_performance as $product): 
                                    $margin = $product['total_revenue'] > 0 ? ($product['total_profit'] / $product['total_revenue']) * 100 : 0;
                                    $margin_class = $margin >= 30 ? 'status-good' : ($margin >= 15 ? 'status-low' : 'status-critical');
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                                    <td><?php echo $product['current_stock']; ?></td>
                                    <td><?php echo $product['times_sold']; ?></td>
                                    <td><?php echo $product['total_quantity_sold']; ?></td>
                                    <td class="profit-positive"><?php echo number_format($product['total_revenue']); ?> FRW</td>
                                    <td class="<?php echo $product['total_profit'] >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                                        <?php echo number_format($product['total_profit']); ?> FRW
                                    </td>
                                    <td><span class="stock-status <?php echo $margin_class; ?>"><?php echo number_format($margin, 1); ?>%</span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Detailed Sales -->
                <div class="section-card">
                    <h2 class="section-title">
                        <i class="fas fa-list"></i> Detailed Sales Transactions
                        <span class="badge badge-primary">Total: <?php echo $total_sales; ?> transactions</span>
                    </h2>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Client</th>
                                    <th>Product</th>
                                    <th>Qty</th>
                                    <th>Selling Price</th>
                                    <th>Cost Price</th>
                                    <th>Profit/Unit</th>
                                    <th>Total</th>
                                    <th>Total Profit</th>
                                    <th>Remaining Stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($sales_data)): ?>
                                    <?php foreach($sales_data as $sale): ?>
                                    <tr>
                                        <td><?php echo $sale['sale_date_only']; ?></td>
                                        <td><?php echo $sale['sale_time']; ?></td>
                                        <td><?php echo htmlspecialchars($sale['client_name']); ?></td>
                                        <td><?php echo htmlspecialchars($sale['product_name']); ?></td>
                                        <td><?php echo $sale['quantity_sold']; ?></td>
                                        <td><?php echo number_format($sale['selling_price']); ?> FRW</td>
                                        <td><?php echo number_format($sale['cost_price']); ?> FRW</td>
                                        <td class="<?php echo $sale['profit_per_unit'] >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                                            <?php echo number_format($sale['profit_per_unit']); ?> FRW
                                        </td>
                                        <td class="profit-positive"><?php echo number_format($sale['total_sold']); ?> FRW</td>
                                        <td class="<?php echo $sale['total_profit'] >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                                            <?php echo number_format($sale['total_profit']); ?> FRW
                                        </td>
                                        <td><?php echo $sale['remaining_stock']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr style="background: #f8f9fa; font-weight: bold;">
                                        <td colspan="4" style="text-align: right;">TOTALS:</td>
                                        <td><?php echo $total_items_sold; ?></td>
                                        <td colspan="3"></td>
                                        <td class="profit-positive"><?php echo number_format($total_revenue); ?> FRW</td>
                                        <td class="<?php echo $total_profit >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                                            <?php echo number_format($total_profit); ?> FRW
                                        </td>
                                        <td></td>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="11" style="text-align: center; padding: 40px;">
                                            <i class="fas fa-info-circle" style="font-size: 2rem; color: #ccc; margin-bottom: 10px; display: block;"></i>
                                            No sales data found for this period
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle filter fields based on selection
        function toggleFilter() {
            const filterType = document.getElementById('filter_type').value;
            
            document.getElementById('date_range_group').style.display = 'none';
            document.getElementById('date_range_group2').style.display = 'none';
            document.getElementById('month_group').style.display = 'none';
            document.getElementById('year_group').style.display = 'none';
            
            if (filterType === 'date_range') {
                document.getElementById('date_range_group').style.display = 'block';
                document.getElementById('date_range_group2').style.display = 'block';
            } else if (filterType === 'month') {
                document.getElementById('month_group').style.display = 'block';
            } else if (filterType === 'year') {
                document.getElementById('year_group').style.display = 'block';
            }
        }
        
        // Initialize filter display
        toggleFilter();
        
        // Prepare chart data
        const dates = <?php echo json_encode(array_reverse(array_column($daily_summary, 'date'))); ?>;
        const revenues = <?php echo json_encode(array_reverse(array_column($daily_summary, 'revenue'))); ?>;
        const profits = <?php echo json_encode(array_reverse(array_column($daily_summary, 'profit'))); ?>;
        const itemsSold = <?php echo json_encode(array_reverse(array_column($daily_summary, 'items_sold'))); ?>;
        
        // Revenue Chart
        const ctx1 = document.getElementById('revenueChart').getContext('2d');
        new Chart(ctx1, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    label: 'Revenue (FRW)',
                    data: revenues,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Profit (FRW)',
                    data: profits,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });
        
        // Sales Volume Chart
        const ctx2 = document.getElementById('salesChart').getContext('2d');
        new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: dates,
                datasets: [{
                    label: 'Items Sold',
                    data: itemsSold,
                    backgroundColor: '#ffc107',
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });
    </script>
</body>
</html>