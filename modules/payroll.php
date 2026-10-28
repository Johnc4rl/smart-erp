<?php
$inline = defined('INLINE_MODULE') && INLINE_MODULE;
if (!$inline) {
    session_start();
    if (!isset($_SESSION['user'])) {
        header("Location: ../index.php");
        exit();
    }
}
if (($_SESSION['user_role'] ?? 'admin') === 'employee') {
    header("Location: " . ($inline ? 'dashboard.php?page=employees' : '../dashboard.php?page=employees'));
    exit();
}

require __DIR__ . '/../config/db.php';

$employeeStmt = $conn->query('SELECT id, fullname FROM employees ORDER BY fullname ASC');
$employees = $employeeStmt->fetchAll(PDO::FETCH_ASSOC);

$payrollStmt = $conn->query('SELECT p.id, e.fullname, p.pay_period, p.gross_pay, p.deductions, p.net_pay, p.created_at
    FROM payroll p
    JOIN employees e ON p.employee_id = e.id
    ORDER BY p.id DESC');
$payrolls = $payrollStmt->fetchAll(PDO::FETCH_ASSOC);

$formPrefix = $inline ? 'modules/' : '';
$dashboardUrl = $inline ? 'dashboard.php' : '../dashboard.php';
$employeesUrl = $inline ? 'dashboard.php?page=employees' : 'employees.php';
$reportsUrl = $inline ? 'dashboard.php?page=reports' : 'reports.php';
?>

<?php if (!$inline): ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>Payroll</title>
        <link rel="stylesheet" href="../assets/css/style.css">
    </head>

    <body>
    <?php endif; ?>

    <div class="module-header">
        <a href="<?php echo $dashboardUrl; ?>">Dashboard</a> |
        <a href="<?php echo $employeesUrl; ?>">Employees</a> |
        <a href="<?php echo $reportsUrl; ?>">Reports</a>
    </div>

    <div class="module-content">
        <h1>Payroll</h1>

        <div class="card">
            <form action="<?php echo $formPrefix; ?>save_payroll.php" method="POST">
                <select name="employee_id" required>
                    <option value="">Select Employee</option>
                    <?php foreach ($employees as $employee): ?>
                        <option value="<?php echo (int) $employee['id']; ?>">
                            <?php echo htmlspecialchars($employee['fullname'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="pay_period" placeholder="Pay Period" required>
                <input type="number" step="0.01" name="gross_pay" placeholder="Gross Pay" required>
                <input type="number" step="0.01" name="deductions" placeholder="Deductions" required>
                <button type="submit">Add Payroll</button>
            </form>
        </div>

        <div class="card">
            <h3>Payroll Records</h3>
            <table>
                <tr>
                    <th>Employee</th>
                    <th>Pay Period</th>
                    <th>Gross Pay</th>
                    <th>Deductions</th>
                    <th>Net Pay</th>
                    <th>Date</th>
                </tr>
                <?php foreach ($payrolls as $record): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($record['fullname'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($record['pay_period'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo number_format($record['gross_pay'], 2); ?></td>
                        <td><?php echo number_format($record['deductions'], 2); ?></td>
                        <td><?php echo number_format($record['net_pay'], 2); ?></td>
                        <td><?php echo htmlspecialchars($record['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <?php if (!$inline): ?>
    </body>

    </html>
<?php endif; ?>
