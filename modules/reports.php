<?php
$inline = defined('INLINE_MODULE') && INLINE_MODULE;
if (!$inline) {
    session_start();
    if (!isset($_SESSION['user'])) {
        header("Location: ../index.php");
        exit();
    }
}
require __DIR__ . '/../config/db.php';

$topProductsStmt = $conn->query("SELECT product_name, SUM(quantity) AS total_sold, SUM(quantity * price) AS total_revenue FROM sales GROUP BY product_name ORDER BY total_sold DESC LIMIT 5");
$topProducts = $topProductsStmt->fetchAll(PDO::FETCH_ASSOC);

$lowStockStmt = $conn->query('SELECT item_name, stock, price FROM inventory ORDER BY stock ASC LIMIT 5');
$lowStock = $lowStockStmt->fetchAll(PDO::FETCH_ASSOC);

$revenueTrendStmt = $conn->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, SUM(quantity * price) AS revenue FROM sales GROUP BY month ORDER BY month DESC LIMIT 6");
$revenueTrend = array_reverse($revenueTrendStmt->fetchAll(PDO::FETCH_ASSOC));
$maxRevenue = 1;
foreach ($revenueTrend as $trend) {
    $maxRevenue = max($maxRevenue, (float) $trend['revenue']);
}

$recentPayrollStmt = $conn->query('SELECT p.pay_period, e.fullname, p.gross_pay, p.deductions, p.net_pay FROM payroll p JOIN employees e ON p.employee_id = e.id ORDER BY p.created_at DESC LIMIT 5');
$recentPayroll = $recentPayrollStmt->fetchAll(PDO::FETCH_ASSOC);

$totalSales = $conn->query('SELECT COUNT(*) FROM sales')->fetchColumn();
$totalRevenue = $conn->query('SELECT SUM(quantity * price) FROM sales')->fetchColumn();
$totalItems = $conn->query('SELECT COUNT(*) FROM inventory')->fetchColumn();
$stockValue = $conn->query('SELECT SUM(stock * price) FROM inventory')->fetchColumn();
$totalEmployees = $conn->query('SELECT COUNT(*) FROM employees')->fetchColumn();
$totalPayroll = $conn->query('SELECT SUM(gross_pay) FROM payroll')->fetchColumn();

$dashboardUrl = $inline ? 'dashboard.php' : '../dashboard.php';
$employeesUrl = $inline ? 'dashboard.php?page=employees' : 'employees.php';
$payrollUrl = $inline ? 'dashboard.php?page=payroll' : 'payroll.php';
$posUrl = $inline ? 'dashboard.php?page=pos' : 'pos.php';

if (!function_exists('formatCurrency')) {
    function formatCurrency($amount)
    {
        return '₱' . number_format($amount, 2);
    }
}
?>
<?php if (!$inline): ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>Reports</title>
        <link rel="stylesheet" href="../assets/css/style.css">
    </head>

    <body>
    <?php endif; ?>

    <div class="module-header">
        <a href="<?php echo $dashboardUrl; ?>">Dashboard</a> |
        <a href="<?php echo $posUrl; ?>">POS</a> |
        <a href="<?php echo $employeesUrl; ?>">Employees</a> |
        <a href="<?php echo $payrollUrl; ?>">Payroll</a>
    </div>

    <div class="module-content content-full">
        <h1>Reports</h1>

        <div class="cards">
            <div class="card">Total Sales<br><strong><?php echo (int) $totalSales; ?></strong></div>
            <div class="card">Revenue<br><strong><?php echo formatCurrency($totalRevenue ?? 0); ?></strong></div>
            <div class="card">Inventory Items<br><strong><?php echo (int) $totalItems; ?></strong></div>
            <div class="card">Stock Value<br><strong><?php echo formatCurrency($stockValue ?? 0); ?></strong></div>
            <div class="card">Employees<br><strong><?php echo (int) $totalEmployees; ?></strong></div>
            <div class="card">Payroll Cost<br><strong><?php echo formatCurrency($totalPayroll ?? 0); ?></strong></div>
        </div>

        <div class="report-grid">
            <div class="report-card">
                <h2>Revenue Trend</h2>
                <div class="bar-chart">
                    <?php if (empty($revenueTrend)): ?>
                        <div class="empty-state">No sales data yet.</div>
                    <?php else: ?>
                        <?php foreach ($revenueTrend as $trend): ?>
                            <?php $height = $maxRevenue ? ((float) $trend['revenue'] / $maxRevenue) * 100 : 10; ?>
                            <div class="bar" style="height: <?php echo max(10, $height); ?>%;">
                                <span class="bar-value"><?php echo formatCurrency($trend['revenue'] ?? 0); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="bar-labels">
                    <?php foreach ($revenueTrend as $trend): ?>
                        <div><?php echo htmlspecialchars($trend['month'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="report-card">
                <h2>Low Stock Items</h2>
                <?php if (empty($lowStock)): ?>
                    <p class="empty-state">Inventory is healthy.</p>
                <?php else: ?>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Stock</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lowStock as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo (int) $item['stock']; ?></td>
                                    <td><?php echo formatCurrency($item['stock'] * $item['price']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="report-grid">
            <div class="report-card full-width">
                <h2>Top Selling Products</h2>
                <?php if (empty($topProducts)): ?>
                    <p class="empty-state">No products sold yet.</p>
                <?php else: ?>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Sold</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topProducts as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo (int) $product['total_sold']; ?></td>
                                    <td><?php echo formatCurrency($product['total_revenue']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="report-card full-width">
                <h2>Recent Payroll</h2>
                <?php if (empty($recentPayroll)): ?>
                    <p class="empty-state">No payroll records yet.</p>
                <?php else: ?>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Period</th>
                                <th>Net Pay</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPayroll as $record): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($record['fullname'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($record['pay_period'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo formatCurrency($record['net_pay']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h3>Summary</h3>
            <p>Use the payroll, inventory and sales reports to review current business metrics.</p>
            <p><a href="<?php echo $dashboardUrl; ?>">Back to Dashboard</a></p>
        </div>
    </div>

    <?php if (!$inline): ?>
    </body>

    </html>
<?php endif; ?>
