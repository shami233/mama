<?php
require_once 'config.php';

// Get all clients
$clients = $pdo->query("
    SELECT c.*, 
           COUNT(s.id) as total_purchases,
           SUM(s.total_price_frw) as total_spent
    FROM clients c
    LEFT JOIN sales s ON c.id = s.client_id
    GROUP BY c.id
    ORDER BY c.name
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <header>
                <h1>Clients</h1>
            </header>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Client Name</th>
                            <th>Phone Number</th>
                            <th>Total Purchases</th>
                            <th>Total Spent (FRW)</th>
                            <th>Joined Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($clients as $index => $client): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($client['name']); ?></td>
                            <td><?php echo htmlspecialchars($client['phone']); ?></td>
                            <td><?php echo $client['total_purchases']; ?></td>
                            <td class="rwf"><?php echo number_format($client['total_spent'] ?? 0); ?></td>
                            <td><?php echo date('M d, Y', strtotime($client['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>