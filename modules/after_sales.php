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

$conn->exec("CREATE TABLE IF NOT EXISTS after_sales_cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    technician_id INT DEFAULT NULL,
    issue_description TEXT NOT NULL,
    assessment_status VARCHAR(100) NOT NULL DEFAULT 'For Assessment',
    warranty_status VARCHAR(100) NOT NULL DEFAULT 'Pending Assessment',
    resolution VARCHAR(100) NOT NULL DEFAULT 'Pending',
    replacement_required TINYINT(1) NOT NULL DEFAULT 0,
    notes TEXT DEFAULT NULL,
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$salesColumnsStmt = $conn->query('SHOW COLUMNS FROM sales');
$salesColumns = array_column($salesColumnsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

if (!in_array('inventory_item_id', $salesColumns, true)) {
    $conn->exec('ALTER TABLE sales ADD COLUMN inventory_item_id INT NULL DEFAULT NULL');
}
if (!in_array('customer_name', $salesColumns, true)) {
    $conn->exec("ALTER TABLE sales ADD COLUMN customer_name VARCHAR(255) NOT NULL DEFAULT ''");
}
if (!in_array('customer_contact', $salesColumns, true)) {
    $conn->exec("ALTER TABLE sales ADD COLUMN customer_contact VARCHAR(255) NOT NULL DEFAULT ''");
}
if (!in_array('installation_required', $salesColumns, true)) {
    $conn->exec('ALTER TABLE sales ADD COLUMN installation_required TINYINT(1) NOT NULL DEFAULT 0');
}
if (!in_array('installation_status', $salesColumns, true)) {
    $conn->exec("ALTER TABLE sales ADD COLUMN installation_status VARCHAR(50) NOT NULL DEFAULT 'Not Scheduled'");
}
if (!in_array('installer_id', $salesColumns, true)) {
    $conn->exec('ALTER TABLE sales ADD COLUMN installer_id INT NULL DEFAULT NULL');
}
if (!in_array('installation_date', $salesColumns, true)) {
    $conn->exec('ALTER TABLE sales ADD COLUMN installation_date DATE NULL DEFAULT NULL');
}
if (!in_array('installation_notes', $salesColumns, true)) {
    $conn->exec('ALTER TABLE sales ADD COLUMN installation_notes TEXT NULL');
}

$salesStmt = $conn->query("SELECT s.*, i.item_name AS inventory_name, e.fullname AS installer_name
    FROM sales s
    LEFT JOIN inventory i ON s.inventory_item_id = i.id
    LEFT JOIN employees e ON s.installer_id = e.id
    ORDER BY s.id DESC
    LIMIT 50");
$sales = $salesStmt->fetchAll(PDO::FETCH_ASSOC);

$installationSales = array_values(array_filter($sales, static function ($sale) {
    return (int) ($sale['installation_required'] ?? 0) === 1;
}));

$employeeStmt = $conn->query('SELECT id, fullname, position FROM employees ORDER BY fullname ASC');
$employees = $employeeStmt->fetchAll(PDO::FETCH_ASSOC);

$installers = array_values(array_filter($employees, static function ($employee) {
    $position = strtolower((string) ($employee['position'] ?? ''));
    return strpos($position, 'install') !== false || strpos($position, 'technician') !== false;
}));
if (empty($installers)) {
    $installers = $employees;
}

$technicians = array_values(array_filter($employees, static function ($employee) {
    $position = strtolower((string) ($employee['position'] ?? ''));
    return strpos($position, 'tech') !== false || strpos($position, 'repair') !== false || strpos($position, 'service') !== false;
}));
if (empty($technicians)) {
    $technicians = $employees;
}

$casesStmt = $conn->query("SELECT c.*, s.product_name, s.customer_name, s.customer_contact, t.fullname AS technician_name
    FROM after_sales_cases c
    JOIN sales s ON c.sale_id = s.id
    LEFT JOIN employees t ON c.technician_id = t.id
    ORDER BY c.id DESC
    LIMIT 50");
$cases = $casesStmt->fetchAll(PDO::FETCH_ASSOC);

$message = '';
if (!empty($_SESSION['after_sales_message'])) {
    $message = $_SESSION['after_sales_message'];
    unset($_SESSION['after_sales_message']);
}

$formPrefix = $inline ? 'modules/' : '';
$dashboardUrl = $inline ? 'dashboard.php' : '../dashboard.php';
$posUrl = $inline ? 'dashboard.php?page=pos' : 'pos.php';
$employeesUrl = $inline ? 'dashboard.php?page=employees' : 'employees.php';
$reportsUrl = $inline ? 'dashboard.php?page=reports' : 'reports.php';
?>
<?php if (!$inline): ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>After Sales</title>
        <link rel="stylesheet" href="../assets/css/style.css">
    </head>
    <body>
<?php endif; ?>

    <div class="module-header">
        <a href="<?php echo $dashboardUrl; ?>">Dashboard</a> |
        <a href="<?php echo $posUrl; ?>">POS</a> |
        <a href="<?php echo $employeesUrl; ?>">Employees</a> |
        <a href="<?php echo $reportsUrl; ?>">Reports</a>
    </div>

    <div class="module-content">
        <h1>After Sales Concerns</h1>

        <?php if ($message): ?>
            <div class="attendance-message"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="cards">
            <div class="card">
                Installation Jobs<br>
                <strong><?php echo count($installationSales); ?></strong>
            </div>
            <div class="card">
                Concern Cases<br>
                <strong><?php echo count($cases); ?></strong>
            </div>
            <div class="card">
                Technicians / Installers<br>
                <strong><?php echo count($employees); ?></strong>
            </div>
        </div>

        <div class="card">
            <h3>Assign Installation</h3>
            <?php if (empty($installationSales)): ?>
                <p class="empty-state">No sales are currently marked as requiring installation.</p>
            <?php else: ?>
                <form action="<?php echo $formPrefix; ?>save_after_sales.php" method="POST">
                    <input type="hidden" name="action" value="update_installation">
                    <div class="form-row">
                        <select name="sale_id" required>
                            <option value="">Select Sold Item</option>
                            <?php foreach ($installationSales as $sale): ?>
                                <option value="<?php echo (int) $sale['id']; ?>">
                                    Sale #<?php echo (int) $sale['id']; ?> -
                                    <?php echo htmlspecialchars($sale['product_name'], ENT_QUOTES, 'UTF-8'); ?> /
                                    <?php echo htmlspecialchars($sale['customer_name'] ?: 'Walk-in Customer', ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="installer_id" required>
                            <option value="">Assign Installer</option>
                            <?php foreach ($installers as $installer): ?>
                                <option value="<?php echo (int) $installer['id']; ?>">
                                    <?php echo htmlspecialchars($installer['fullname'], ENT_QUOTES, 'UTF-8'); ?> -
                                    <?php echo htmlspecialchars($installer['position'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <input type="date" name="installation_date">
                        <select name="installation_status" required>
                            <option value="Pending Assignment">Pending Assignment</option>
                            <option value="Scheduled">Scheduled</option>
                            <option value="Installed">Installed</option>
                            <option value="Installation Failed">Installation Failed</option>
                        </select>
                    </div>
                    <textarea name="installation_notes" placeholder="Installation notes, schedule details, site remarks"></textarea>
                    <button type="submit">Save Installation Assignment</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Log Return / Defective Concern</h3>
            <?php if (empty($sales)): ?>
                <p class="empty-state">No sales available yet.</p>
            <?php else: ?>
                <form action="<?php echo $formPrefix; ?>save_after_sales.php" method="POST">
                    <input type="hidden" name="action" value="create_case">
                    <div class="form-row">
                        <select name="sale_id" required>
                            <option value="">Select Sold Item</option>
                            <?php foreach ($sales as $sale): ?>
                                <option value="<?php echo (int) $sale['id']; ?>">
                                    Sale #<?php echo (int) $sale['id']; ?> -
                                    <?php echo htmlspecialchars($sale['product_name'], ENT_QUOTES, 'UTF-8'); ?> /
                                    <?php echo htmlspecialchars($sale['customer_name'] ?: 'Walk-in Customer', ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="technician_id" required>
                            <option value="">Assign Technician</option>
                            <?php foreach ($technicians as $technician): ?>
                                <option value="<?php echo (int) $technician['id']; ?>">
                                    <?php echo htmlspecialchars($technician['fullname'], ENT_QUOTES, 'UTF-8'); ?> -
                                    <?php echo htmlspecialchars($technician['position'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <textarea name="issue_description" placeholder="Describe the client concern or defective issue" required></textarea>
                    <div class="form-row">
                        <select name="assessment_status" required>
                            <option value="For Assessment">For Assessment</option>
                            <option value="Diagnosed">Diagnosed</option>
                            <option value="Repair In Progress">Repair In Progress</option>
                            <option value="Replacement Approved">Replacement Approved</option>
                            <option value="Closed">Closed</option>
                        </select>
                        <select name="warranty_status" required>
                            <option value="Pending Assessment">Pending Assessment</option>
                            <option value="Under Warranty">Under Warranty</option>
                            <option value="Out of Warranty">Out of Warranty</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <select name="resolution" required>
                            <option value="Pending">Pending</option>
                            <option value="Repair">Repair</option>
                            <option value="Replacement">Replacement</option>
                            <option value="Refund">Refund</option>
                            <option value="No Fault Found">No Fault Found</option>
                        </select>
                        <label class="pos-checkbox">
                            <input type="checkbox" name="replacement_required" value="1">
                            <span>Needs item replacement</span>
                        </label>
                    </div>
                    <textarea name="notes" placeholder="Assessment notes, warranty remarks, replacement decision"></textarea>
                    <button type="submit">Save Concern Case</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Installation Monitoring</h3>
            <?php if (empty($installationSales)): ?>
                <p class="empty-state">No installation records yet.</p>
            <?php else: ?>
                <table class="inventory-table">
                    <tr>
                        <th>Sale</th>
                        <th>Customer</th>
                        <th>Installer</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Notes</th>
                    </tr>
                    <?php foreach ($installationSales as $sale): ?>
                        <tr>
                            <td>
                                #<?php echo (int) $sale['id']; ?><br>
                                <strong><?php echo htmlspecialchars($sale['product_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($sale['customer_name'] ?: 'Walk-in Customer', ENT_QUOTES, 'UTF-8'); ?><br>
                                <span class="item-meta"><?php echo htmlspecialchars($sale['customer_contact'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($sale['installer_name'] ?: 'Unassigned', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($sale['installation_status'] ?: 'Pending Assignment', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($sale['installation_date'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($sale['installation_notes'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Return And Warranty Assessments</h3>
            <?php if (empty($cases)): ?>
                <p class="empty-state">No after-sales concern cases yet.</p>
            <?php else: ?>
                <table class="report-table">
                    <tr>
                        <th>Sale</th>
                        <th>Technician</th>
                        <th>Issue</th>
                        <th>Warranty</th>
                        <th>Resolution</th>
                        <th>Notes</th>
                    </tr>
                    <?php foreach ($cases as $case): ?>
                        <tr>
                            <td>
                                #<?php echo (int) $case['sale_id']; ?><br>
                                <strong><?php echo htmlspecialchars($case['product_name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                <span class="item-meta">
                                    <?php echo htmlspecialchars($case['customer_name'] ?: 'Walk-in Customer', ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($case['technician_name'] ?: 'Unassigned', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($case['issue_description'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php echo htmlspecialchars($case['assessment_status'], ENT_QUOTES, 'UTF-8'); ?><br>
                                <span class="item-meta"><?php echo htmlspecialchars($case['warranty_status'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($case['resolution'], ENT_QUOTES, 'UTF-8'); ?><br>
                                <span class="item-meta">
                                    <?php echo (int) $case['replacement_required'] === 1 ? 'Replacement Needed' : 'No Replacement'; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($case['notes'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>

<?php if (!$inline): ?>
    </body>
    </html>
<?php endif; ?>
