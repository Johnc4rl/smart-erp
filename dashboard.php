<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

require 'config/db.php';
require 'includes/helpers.php';
bootstrap_db($conn);
ensure_schema($conn); // cheap check on every load, so a re-imported database is repaired automatically

$user = h($_SESSION['user'] ?? '');
$userRole = $_SESSION['user_role'] ?? 'admin';
$isAdmin = $userRole !== 'employee';
$employeeId = isset($_SESSION['employee_id']) ? (int) $_SESSION['employee_id'] : 0;

$page = $_GET['page'] ?? '';
$allowedPages = $isAdmin
    ? ['pos', 'inventory', 'employees', 'payroll', 'reports', 'attendance', 'after_sales', 'settings']
    : ['pos', 'inventory', 'reports', 'attendance', 'after_sales', 'employees'];
$activePage = in_array($page, $allowedPages, true) ? $page : '';
$pageTitles = [
    'pos' => 'Point of Sale', 'inventory' => 'Products', 'employees' => $isAdmin ? 'Employees' : 'My Account',
    'payroll' => 'Payroll', 'reports' => 'Reports', 'attendance' => 'Attendance',
    'after_sales' => 'After Sales', 'settings' => 'Settings',
];

function activeClass(string $current, string $target): string
{
    return $current === $target ? 'class="active"' : '';
}

function formatCurrency($amount)
{
    return '₱' . number_format((float) $amount, 2);
}

/* Run the module first (buffered) so it can redirect, export files, and raise flash messages. */
$moduleFile = '';
$moduleHtml = '';
if ($activePage) {
    $moduleFile = __DIR__ . '/modules/' . $activePage . '.php';
    if (!is_file($moduleFile)) {
        $activePage = '';
        $moduleFile = '';
    } else {
        define('INLINE_MODULE', true);
        ob_start();
        include $moduleFile;
        $moduleHtml = ob_get_clean();
    }
}

/* ---------- Home statistics ---------- */
$S = [];
$employeeRecord = null;
if (!$activePage) {
    if ($employeeId > 0) {
        $employeeRecord = db_row($conn, 'SELECT employee_code, fullname, department, position, status, hired_date, email, phone, profile_image FROM employees WHERE id = ?', [$employeeId]);
    }

    $ref = "COALESCE(receipt_no, CONCAT('S', id))";
    // Sales
    $S['tx'] = (int) db_val($conn, "SELECT COUNT(DISTINCT $ref) FROM sales");
    $S['units'] = (int) db_val($conn, 'SELECT COALESCE(SUM(quantity),0) FROM sales');
    $S['revenue'] = (float) db_val($conn, 'SELECT COALESCE(SUM(quantity * price),0) FROM sales');
    $S['profit'] = $S['revenue'] * 0.30; // no cost price is stored, so this is an estimate
    $S['avg'] = $S['tx'] ? $S['revenue'] / $S['tx'] : 0;
    $S['today_rev'] = (float) db_val($conn, 'SELECT COALESCE(SUM(quantity * price),0) FROM sales WHERE DATE(created_at) = CURDATE()');
    $S['today_tx'] = (int) db_val($conn, "SELECT COUNT(DISTINCT $ref) FROM sales WHERE DATE(created_at) = CURDATE()");
    $S['month_rev'] = (float) db_val($conn, 'SELECT COALESCE(SUM(quantity * price),0) FROM sales WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())');
    $S['month_tx'] = (int) db_val($conn, "SELECT COUNT(DISTINCT $ref) FROM sales WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())");

    $dayRows = db_all($conn, 'SELECT DATE(created_at) AS d, SUM(quantity * price) AS revenue FROM sales WHERE created_at >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE(created_at)');
    $byDay = [];
    foreach ($dayRows as $r) {
        $byDay[$r['d']] = (float) $r['revenue'];
    }
    $last7 = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i day"));
        $last7[] = ['label' => date('D j', strtotime($d)), 'revenue' => $byDay[$d] ?? 0];
    }

    $topProducts = db_all($conn, 'SELECT product_name, SUM(quantity) AS qty, SUM(quantity * price) AS revenue FROM sales GROUP BY product_name ORDER BY revenue DESC LIMIT 5');
    $payMethods = db_all($conn, 'SELECT payment_method, SUM(quantity * price) AS revenue FROM sales GROUP BY payment_method ORDER BY revenue DESC');
    $byCategory = db_all($conn, "SELECT COALESCE(c.name, 'Uncategorized') AS category, SUM(s.quantity * s.price) AS revenue
        FROM sales s LEFT JOIN inventory i ON i.id = s.inventory_item_id LEFT JOIN categories c ON c.id = i.category_id
        GROUP BY category ORDER BY revenue DESC LIMIT 6");
    $recentSales = db_all($conn, "SELECT $ref AS ref, MIN(created_at) AS created_at, MAX(customer_name) AS customer_name,
        SUM(quantity) AS units, SUM(quantity * price) AS total, MAX(payment_method) AS payment_method
        FROM sales GROUP BY ref ORDER BY MAX(id) DESC LIMIT 6");

    // Inventory
    $S['products'] = (int) db_val($conn, 'SELECT COUNT(*) FROM inventory');
    $S['stock_units'] = (int) db_val($conn, 'SELECT COALESCE(SUM(stock),0) FROM inventory');
    $S['stock_value'] = (float) db_val($conn, 'SELECT COALESCE(SUM(stock * price),0) FROM inventory');
    $S['low'] = (int) db_val($conn, 'SELECT COUNT(*) FROM inventory WHERE stock > 0 AND stock <= low_stock_threshold');
    $S['out'] = (int) db_val($conn, 'SELECT COUNT(*) FROM inventory WHERE stock <= 0');
    $S['categories'] = (int) db_val($conn, 'SELECT COUNT(*) FROM categories');
    $S['suppliers'] = (int) db_val($conn, 'SELECT COUNT(*) FROM suppliers');
    $lowList = db_all($conn, 'SELECT id, item_name, stock, low_stock_threshold FROM inventory WHERE stock <= low_stock_threshold ORDER BY stock ASC, item_name LIMIT 6');

    // Service
    $inst = [];
    foreach (db_all($conn, 'SELECT installation_status AS s, COUNT(*) AS n FROM sales WHERE installation_required = 1 GROUP BY installation_status') as $r) {
        $inst[$r['s']] = (int) $r['n'];
    }
    $S['cases'] = (int) db_val($conn, 'SELECT COUNT(*) FROM after_sales_cases');
    $S['cases_open'] = (int) db_val($conn, "SELECT COUNT(*) FROM after_sales_cases WHERE assessment_status <> 'Closed'");
    $S['cases_closed'] = $S['cases'] - $S['cases_open'];
    $S['cases_warranty'] = (int) db_val($conn, "SELECT COUNT(*) FROM after_sales_cases WHERE assessment_status <> 'Closed' AND warranty_status = 'Under Warranty'");
    $S['cases_replace'] = (int) db_val($conn, "SELECT COUNT(*) FROM after_sales_cases WHERE assessment_status <> 'Closed' AND replacement_required = 1");

    // People
    $S['employees'] = (int) db_val($conn, 'SELECT COUNT(*) FROM employees');
    $S['emp_active'] = (int) db_val($conn, "SELECT COUNT(*) FROM employees WHERE status = 'Active'");
    $S['present_today'] = (int) db_val($conn, 'SELECT COUNT(DISTINCT employee_id) FROM attendance WHERE DATE(time_in) = CURDATE()');
    $S['on_duty'] = (int) db_val($conn, 'SELECT COUNT(DISTINCT employee_id) FROM attendance WHERE DATE(time_in) = CURDATE() AND time_out IS NULL');
    $onDuty = [];
    if ($isAdmin) {
        $S['salary_total'] = (float) db_val($conn, "SELECT COALESCE(SUM(salary),0) FROM employees WHERE status = 'Active'");
        $S['payroll_month'] = (float) db_val($conn, 'SELECT COALESCE(SUM(net_pay),0) FROM payroll WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())');
        $S['payroll_total'] = (float) db_val($conn, 'SELECT COALESCE(SUM(net_pay),0) FROM payroll');
        $S['payroll_records'] = (int) db_val($conn, 'SELECT COUNT(*) FROM payroll');
        $onDuty = db_all($conn, 'SELECT e.fullname, e.position, a.time_in, a.time_out FROM attendance a JOIN employees e ON e.id = a.employee_id WHERE DATE(a.time_in) = CURDATE() ORDER BY a.time_in DESC LIMIT 8');
    }

    // Personal (anyone linked to an employee record)
    if ($employeeId > 0) {
        $S['my_days'] = (int) db_val($conn, 'SELECT COUNT(DISTINCT DATE(time_in)) FROM attendance WHERE employee_id = ? AND YEAR(time_in) = YEAR(CURDATE()) AND MONTH(time_in) = MONTH(CURDATE())', [$employeeId]);
        $S['my_hours'] = (float) db_val($conn, 'SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, time_in, time_out)),0) / 60 FROM attendance WHERE employee_id = ? AND time_out IS NOT NULL AND YEAR(time_in) = YEAR(CURDATE()) AND MONTH(time_in) = MONTH(CURDATE())', [$employeeId]);
        $S['my_clocked_in'] = (int) db_val($conn, 'SELECT COUNT(*) FROM attendance WHERE employee_id = ? AND DATE(time_in) = CURDATE() AND time_out IS NULL', [$employeeId]);
        $S['my_installs'] = (int) db_val($conn, "SELECT COUNT(*) FROM sales WHERE installer_id = ? AND installation_required = 1 AND installation_status IN ('Scheduled','Pending Assignment')", [$employeeId]);
        $S['my_cases'] = (int) db_val($conn, "SELECT COUNT(*) FROM after_sales_cases WHERE technician_id = ? AND assessment_status <> 'Closed'", [$employeeId]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($activePage ? $pageTitles[$activePage] . ' · ' : ''); ?>Smart ERP</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

    <button type="button" class="menu-toggle" id="menuToggle" aria-label="Open menu">Menu</button>

    <div class="sidebar" id="sidebar">
        <h2>HIGH END</h2>
        <ul>
            <li><a href="dashboard.php" <?php echo activeClass($activePage, ''); ?>>Home</a></li>
            <li><a href="dashboard.php?page=pos" <?php echo activeClass($activePage, 'pos'); ?>>Point of Sale</a></li>
            <li><a href="dashboard.php?page=inventory" <?php echo activeClass($activePage, 'inventory'); ?>>Products</a></li>
            <li><a href="dashboard.php?page=employees" <?php echo activeClass($activePage, 'employees'); ?>>
                    <?php echo $isAdmin ? 'Employees' : 'My Account'; ?>
                </a></li>
            <?php if ($isAdmin): ?>
                <li><a href="dashboard.php?page=payroll" <?php echo activeClass($activePage, 'payroll'); ?>>Payroll</a></li>
            <?php endif; ?>
            <li><a href="dashboard.php?page=reports" <?php echo activeClass($activePage, 'reports'); ?>>Reports</a></li>
            <li><a href="dashboard.php?page=attendance" <?php echo activeClass($activePage, 'attendance'); ?>>Attendance</a></li>
            <li><a href="dashboard.php?page=after_sales" <?php echo activeClass($activePage, 'after_sales'); ?>>After Sales</a></li>
            <?php if ($isAdmin): ?>
                <li><a href="dashboard.php?page=settings" <?php echo activeClass($activePage, 'settings'); ?>>Settings</a></li>
            <?php endif; ?>
            <li><a href="auth/logout.php">Logout</a></li>
        </ul>
        <p class="sidebar-user"><?php echo $user; ?><br><span><?php echo $isAdmin ? 'Administrator' : 'Employee'; ?></span></p>
    </div>

    <div class="main-content">
        <?php echo flash_render(); ?>
        <?php if ($activePage && $moduleFile): ?>
            <div class="module-container">
                <?php echo $moduleHtml; ?>
            </div>
        <?php else: ?>
            <header class="page-head">
                <div>
                    <h1>Welcome back, <?php echo $user; ?></h1>
                    <p class="muted"><?php echo date('l, F j, Y'); ?></p>
                </div>
            </header>

            <?php if ($employeeRecord): ?>
                <section class="panel profile-strip">
                    <?php if (!empty($employeeRecord['profile_image'])): ?>
                        <img class="avatar" src="<?php echo h($employeeRecord['profile_image']); ?>" alt="">
                    <?php endif; ?>
                    <div class="profile-facts">
                        <div><span>Employee Number</span><strong><?php echo h($employeeRecord['employee_code'] ?: '-'); ?></strong></div>
                        <div><span>Department</span><strong><?php echo h($employeeRecord['department'] ?: '-'); ?></strong></div>
                        <div><span>Position</span><strong><?php echo h($employeeRecord['position'] ?: '-'); ?></strong></div>
                        <div><span>Status</span><strong><?php echo h($employeeRecord['status'] ?: '-'); ?></strong></div>
                    </div>
                </section>
            <?php endif; ?>

            <h3 class="group-title">Sales</h3>
            <div class="stats">
                <?php
                echo stat_card('Revenue', formatCurrency($S['revenue']), 'All time', 'dashboard.php?page=reports', 'lead');
                echo stat_card('Today', formatCurrency($S['today_rev']), $S['today_tx'] . ' sale' . ($S['today_tx'] == 1 ? '' : 's'), 'dashboard.php?page=pos');
                echo stat_card('This month', formatCurrency($S['month_rev']), $S['month_tx'] . ' sale' . ($S['month_tx'] == 1 ? '' : 's'), 'dashboard.php?page=reports');
                echo stat_card('Total Sales', (string) $S['tx'], 'Transactions', 'dashboard.php?page=pos');
                echo stat_card('Units sold', number_format($S['units']));
                echo stat_card('Average sale', formatCurrency($S['avg']));
                echo stat_card('Profit (est. 30%)', formatCurrency($S['profit']), 'No cost price is stored');
                ?>
            </div>

            <div class="grid-2">
                <section class="panel">
                    <h3>Revenue, last 7 days</h3>
                    <?php echo column_chart($last7, 'label', 'revenue'); ?>
                </section>
                <section class="panel">
                    <h3>Top products by revenue</h3>
                    <?php echo bar_list($topProducts, 'product_name', 'revenue'); ?>
                </section>
            </div>

            <div class="grid-2">
                <section class="panel">
                    <h3>Revenue by payment method</h3>
                    <?php echo bar_list($payMethods, 'payment_method', 'revenue'); ?>
                </section>
                <section class="panel">
                    <h3>Revenue by category</h3>
                    <?php echo bar_list($byCategory, 'category', 'revenue'); ?>
                </section>
            </div>

            <section class="panel">
                <h3>Recent sales</h3>
                <?php if (!$recentSales): ?>
                    <p class="empty">No sales yet. <a href="dashboard.php?page=pos">Open the Point of Sale</a> to make one.</p>
                <?php else: ?>
                    <div class="table-wrap"><table>
                        <thead><tr><th>Receipt</th><th>Date</th><th>Customer</th><th class="num">Units</th><th class="num">Total</th><th>Payment</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentSales as $r): ?>
                            <tr>
                                <td><a href="dashboard.php?page=pos&ref=<?php echo urlencode($r['ref']); ?>"><?php echo h($r['ref']); ?></a></td>
                                <td><?php echo h(date('M j, g:i A', strtotime($r['created_at']))); ?></td>
                                <td><?php echo h($r['customer_name'] ?: 'Walk-in'); ?></td>
                                <td class="num"><?php echo (int) $r['units']; ?></td>
                                <td class="num"><?php echo formatCurrency($r['total']); ?></td>
                                <td><?php echo h($r['payment_method']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </section>

            <h3 class="group-title">Inventory</h3>
            <div class="stats">
                <?php
                echo stat_card('Products', (string) $S['products'], $S['categories'] . ' categories · ' . $S['suppliers'] . ' suppliers', 'dashboard.php?page=inventory');
                echo stat_card('Units in stock', number_format($S['stock_units']), '', 'dashboard.php?page=inventory');
                echo stat_card('Stock value', formatCurrency($S['stock_value']), 'At selling price', 'dashboard.php?page=inventory');
                echo stat_card('Low stock', (string) $S['low'], 'At or below threshold', 'dashboard.php?page=inventory&stock=low', $S['low'] ? 'warn' : '');
                echo stat_card('Out of stock', (string) $S['out'], '', 'dashboard.php?page=inventory&stock=out', $S['out'] ? 'bad' : '');
                ?>
            </div>

            <section class="panel">
                <h3>Needs restocking</h3>
                <?php if (!$lowList): ?>
                    <p class="empty">Every product is above its low-stock threshold.</p>
                <?php else: ?>
                    <div class="table-wrap"><table>
                        <thead><tr><th>Product</th><th class="num">In stock</th><th class="num">Threshold</th></tr></thead>
                        <tbody>
                        <?php foreach ($lowList as $r): ?>
                            <tr>
                                <td><?php echo h($r['item_name']); ?></td>
                                <td class="num"><span class="badge badge-<?php echo (int) $r['stock'] <= 0 ? 'bad' : 'warn'; ?>"><?php echo (int) $r['stock']; ?></span></td>
                                <td class="num"><?php echo (int) $r['low_stock_threshold']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </section>

            <h3 class="group-title">Installations and after sales</h3>
            <div class="stats">
                <?php
                echo stat_card('Pending assignment', (string) ($inst['Pending Assignment'] ?? 0), 'Installations', 'dashboard.php?page=after_sales&tab=installs', ($inst['Pending Assignment'] ?? 0) ? 'warn' : '');
                echo stat_card('Scheduled', (string) ($inst['Scheduled'] ?? 0), 'Installations', 'dashboard.php?page=after_sales&tab=installs');
                echo stat_card('Installed', (string) ($inst['Installed'] ?? 0), 'Installations', 'dashboard.php?page=after_sales&tab=installs');
                echo stat_card('Failed', (string) ($inst['Installation Failed'] ?? 0), 'Installations', 'dashboard.php?page=after_sales&tab=installs', ($inst['Installation Failed'] ?? 0) ? 'bad' : '');
                echo stat_card('Open cases', (string) $S['cases_open'], $S['cases'] . ' total', 'dashboard.php?page=after_sales', $S['cases_open'] ? 'warn' : '');
                echo stat_card('Closed cases', (string) $S['cases_closed'], '', 'dashboard.php?page=after_sales');
                echo stat_card('Open, under warranty', (string) $S['cases_warranty'], '', 'dashboard.php?page=after_sales');
                echo stat_card('Replacements needed', (string) $S['cases_replace'], 'Open cases', 'dashboard.php?page=after_sales');
                ?>
            </div>

            <h3 class="group-title">People</h3>
            <div class="stats">
                <?php
                if ($isAdmin) {
                    echo stat_card('Employees', (string) $S['employees'], $S['emp_active'] . ' active', 'dashboard.php?page=employees');
                }
                echo stat_card('Present today', (string) $S['present_today'], 'Clocked in at least once', 'dashboard.php?page=attendance');
                echo stat_card('On duty now', (string) $S['on_duty'], 'Not yet clocked out', 'dashboard.php?page=attendance');
                if ($isAdmin) {
                    echo stat_card('Monthly salaries', formatCurrency($S['salary_total']), 'Active employees', 'dashboard.php?page=payroll');
                    echo stat_card('Payroll this month', formatCurrency($S['payroll_month']), 'Net pay', 'dashboard.php?page=payroll');
                    echo stat_card('Payroll, all time', formatCurrency($S['payroll_total']), $S['payroll_records'] . ' records', 'dashboard.php?page=payroll');
                }
                ?>
            </div>

            <?php if ($isAdmin): ?>
                <section class="panel">
                    <h3>Attendance today</h3>
                    <?php if (!$onDuty): ?>
                        <p class="empty">Nobody has clocked in today.</p>
                    <?php else: ?>
                        <div class="table-wrap"><table>
                            <thead><tr><th>Employee</th><th>Position</th><th>Time in</th><th>Time out</th></tr></thead>
                            <tbody>
                            <?php foreach ($onDuty as $r): ?>
                                <tr>
                                    <td><?php echo h($r['fullname']); ?></td>
                                    <td><?php echo h($r['position']); ?></td>
                                    <td><?php echo h(date('g:i A', strtotime($r['time_in']))); ?></td>
                                    <td><?php echo $r['time_out'] ? h(date('g:i A', strtotime($r['time_out']))) : status_badge('On duty'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table></div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($employeeId > 0): ?>
                <h3 class="group-title">My work</h3>
                <div class="stats">
                    <?php
                    echo stat_card('Days present', (string) $S['my_days'], 'This month', 'dashboard.php?page=attendance');
                    echo stat_card('Hours worked', number_format($S['my_hours'], 1), 'This month', 'dashboard.php?page=attendance');
                    echo stat_card('Right now', $S['my_clocked_in'] ? 'Clocked in' : 'Clocked out', '', 'dashboard.php?page=attendance');
                    echo stat_card('My installations', (string) $S['my_installs'], 'Pending or scheduled', 'dashboard.php?page=after_sales&tab=installs');
                    echo stat_card('My open cases', (string) $S['my_cases'], 'As technician', 'dashboard.php?page=after_sales');
                    ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="assets/js/main.js"></script>
</body>

</html>
