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

$userRole = $_SESSION['user_role'] ?? 'admin';
$sessionEmployeeId = isset($_SESSION['employee_id']) ? (int) $_SESSION['employee_id'] : 0;

$conn->exec("CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    time_in DATETIME NOT NULL,
    time_out DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

if ($userRole === 'employee' && $sessionEmployeeId > 0) {
    $employeeStmt = $conn->prepare('SELECT id, fullname FROM employees WHERE id = ? ORDER BY fullname ASC');
    $employeeStmt->execute([$sessionEmployeeId]);
    $employees = $employeeStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $employeeStmt = $conn->query('SELECT id, fullname FROM employees ORDER BY fullname ASC');
    $employees = $employeeStmt->fetchAll(PDO::FETCH_ASSOC);
}

$openStmt = $conn->query('SELECT employee_id, COUNT(*) AS open_count FROM attendance WHERE time_out IS NULL GROUP BY employee_id');
$openRows = $openStmt->fetchAll(PDO::FETCH_ASSOC);
$openCounts = [];
foreach ($openRows as $row) {
    $openCounts[(int) $row['employee_id']] = (int) $row['open_count'];
}

if ($userRole === 'employee' && $sessionEmployeeId > 0) {
    $attendanceStmt = $conn->prepare('SELECT a.*, e.fullname FROM attendance a JOIN employees e ON a.employee_id = e.id WHERE a.employee_id = ? ORDER BY a.id DESC LIMIT 50');
    $attendanceStmt->execute([$sessionEmployeeId]);
    $attendanceLogs = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $attendanceStmt = $conn->query('SELECT a.*, e.fullname FROM attendance a JOIN employees e ON a.employee_id = e.id ORDER BY a.id DESC LIMIT 50');
    $attendanceLogs = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);
}

$message = '';
if (isset($_SESSION['attendance_message'])) {
    $message = $_SESSION['attendance_message'];
    unset($_SESSION['attendance_message']);
}

$formPrefix = $inline ? 'modules/' : '';
$dashboardUrl = $inline ? 'dashboard.php' : '../dashboard.php';
$employeesUrl = $inline ? 'dashboard.php?page=employees' : 'employees.php';
$payrollUrl = $inline ? 'dashboard.php?page=payroll' : 'payroll.php';
$reportsUrl = $inline ? 'dashboard.php?page=reports' : 'reports.php';
$attendanceUrl = $inline ? 'dashboard.php?page=attendance' : 'attendance.php';
$selfEmployee = null;
if ($userRole === 'employee' && !empty($employees)) {
    $selfEmployee = $employees[0];
}

function formatDuration($timeIn, $timeOut)
{
    if (!$timeIn || !$timeOut) {
        return 'Active';
    }

    $diff = strtotime($timeOut) - strtotime($timeIn);
    $hours = floor($diff / 3600);
    $minutes = floor(($diff % 3600) / 60);
    return sprintf('%d hrs %02d mins', $hours, $minutes);
}
?>
<?php if (!$inline): ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>Attendance</title>
        <link rel="stylesheet" href="../assets/css/style.css">
    </head>

    <body>
    <?php endif; ?>

    <div class="module-header">
        <a href="<?php echo $dashboardUrl; ?>">Dashboard</a> |
        <a href="<?php echo $employeesUrl; ?>">Employees</a> |
        <a href="<?php echo $payrollUrl; ?>">Payroll</a> |
        <a href="<?php echo $reportsUrl; ?>">Reports</a>
    </div>

    <div class="module-content">
        <h1>Attendance</h1>

        <?php if ($message): ?>
            <div class="attendance-message">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h3>Time In / Time Out</h3>
            <form action="<?php echo $formPrefix; ?>save_attendance.php" method="POST">
                <?php if ($userRole === 'employee' && $selfEmployee): ?>
                    <input type="hidden" name="employee_id" value="<?php echo (int) $selfEmployee['id']; ?>">
                    <input type="text" value="<?php echo htmlspecialchars($selfEmployee['fullname'], ENT_QUOTES, 'UTF-8'); ?>" readonly>
                <?php else: ?>
                    <select name="employee_id" required>
                        <option value="">Choose Employee</option>
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?php echo (int) $employee['id']; ?>">
                                <?php echo htmlspecialchars($employee['fullname'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <select name="action" required>
                    <option value="">Select Action</option>
                    <option value="in">Time In</option>
                    <option value="out">Time Out</option>
                </select>
                <button type="submit">Save Attendance</button>
            </form>
        </div>

        <div class="card">
            <h3><?php echo $userRole === 'employee' ? 'My Attendance Status' : 'Current Employee Status'; ?></h3>
            <?php if (empty($employees)): ?>
                <p class="empty-state">No employees available yet.</p>
            <?php else: ?>
                <table>
                    <tr>
                        <th>Employee</th>
                        <th>Status</th>
                    </tr>
                    <?php foreach ($employees as $employee): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($employee['fullname'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php if (!empty($openCounts[(int) $employee['id']])): ?>
                                    <strong>Clocked In</strong>
                                <?php else: ?>
                                    <strong>Clocked Out</strong>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3><?php echo $userRole === 'employee' ? 'My Attendance Logs' : 'Recent Attendance Logs'; ?></h3>
            <?php if (empty($attendanceLogs)): ?>
                <p class="empty-state">No attendance records yet.</p>
            <?php else: ?>
                <table>
                    <tr>
                        <th>Employee</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Duration</th>
                    </tr>
                    <?php foreach ($attendanceLogs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['fullname'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($log['time_in'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo $log['time_out'] ? htmlspecialchars($log['time_out'], ENT_QUOTES, 'UTF-8') : '—'; ?>
                            </td>
                            <td><?php echo formatDuration($log['time_in'], $log['time_out']); ?></td>
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
