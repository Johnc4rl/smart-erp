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

$conn->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS employee_code VARCHAR(50) NULL DEFAULT NULL");
$conn->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS department VARCHAR(150) NOT NULL DEFAULT ''");
$conn->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS email VARCHAR(255) NOT NULL DEFAULT ''");
$conn->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS phone VARCHAR(100) NOT NULL DEFAULT ''");
$conn->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS address TEXT NULL");
$conn->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS employment_type VARCHAR(100) NOT NULL DEFAULT 'Regular'");
$conn->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS status VARCHAR(50) NOT NULL DEFAULT 'Active'");
$conn->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS profile_image VARCHAR(255) NULL DEFAULT NULL");
$conn->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS document_file VARCHAR(255) NULL DEFAULT NULL");
$conn->exec("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NULL DEFAULT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    fullname VARCHAR(255) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    account_role VARCHAR(50) NOT NULL DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS account_role VARCHAR(50) NOT NULL DEFAULT 'admin'");

$editId = isset($_GET['edit_id']) ? (int) $_GET['edit_id'] : 0;
$viewOnly = $userRole === 'employee';
if ($viewOnly) {
    $editId = $sessionEmployeeId;
}
$editEmployee = null;
if ($editId > 0) {
    $editStmt = $conn->prepare('SELECT e.*, COALESCE(u.account_role, CASE WHEN u.employee_id IS NOT NULL THEN \'employee\' ELSE \'admin\' END, \'employee\') AS account_role
        FROM employees e
        LEFT JOIN users u ON u.employee_id = e.id
        WHERE e.id = ?');
    $editStmt->execute([$editId]);
    $editEmployee = $editStmt->fetch(PDO::FETCH_ASSOC);
}

if ($viewOnly && $sessionEmployeeId > 0) {
    $stmt = $conn->prepare('SELECT e.*, COALESCE(u.account_role, CASE WHEN u.employee_id IS NOT NULL THEN \'employee\' ELSE \'admin\' END, \'employee\') AS account_role
        FROM employees e
        LEFT JOIN users u ON u.employee_id = e.id
        WHERE e.id = ?
        ORDER BY e.id DESC');
    $stmt->execute([$sessionEmployeeId]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $conn->query("SELECT e.*, COALESCE(u.account_role, CASE WHEN u.employee_id IS NOT NULL THEN 'employee' ELSE 'admin' END, 'employee') AS account_role
        FROM employees e
        LEFT JOIN users u ON u.employee_id = e.id
        ORDER BY e.id DESC");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$message = '';
if (!empty($_SESSION['employee_message'])) {
    $message = $_SESSION['employee_message'];
    unset($_SESSION['employee_message']);
}

$formPrefix = $inline ? 'modules/' : '';
$dashboardUrl = $inline ? 'dashboard.php' : '../dashboard.php';
$payrollUrl = $inline ? 'dashboard.php?page=payroll' : 'payroll.php';
$reportsUrl = $inline ? 'dashboard.php?page=reports' : 'reports.php';

function employeeStatusClass(string $status): string
{
    $normalized = strtolower($status);
    if ($normalized === 'active') {
        return 'status-pill status-active';
    }
    if ($normalized === 'on leave') {
        return 'status-pill status-leave';
    }
    return 'status-pill status-resigned';
}
?>

<?php if (!$inline): ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>Employees</title>
        <link rel="stylesheet" href="../assets/css/style.css">
    </head>

    <body>
    <?php endif; ?>

    <div class="module-header">
        <a href="<?php echo $dashboardUrl; ?>">Dashboard</a> |
        <a href="<?php echo $payrollUrl; ?>">Payroll</a> |
        <a href="<?php echo $reportsUrl; ?>">Reports</a>
    </div>

    <div class="module-content">
        <h1><?php echo $viewOnly ? 'My Account' : 'Employee Management'; ?></h1>

        <?php if ($message): ?>
            <div class="attendance-message">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h3><?php echo $viewOnly ? 'Employee Profile' : ($editEmployee ? 'Edit Employee' : 'Add Employee'); ?></h3>
            <?php if ($viewOnly && $editEmployee): ?>
                <div class="employee-profile-grid">
                    <div>
                        <p><strong>Employee Number:</strong>
                            <?php echo htmlspecialchars($editEmployee['employee_code'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></p>
                        <p><strong>Full Name:</strong>
                            <?php echo htmlspecialchars($editEmployee['fullname'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></p>
                        <p><strong>Department:</strong>
                            <?php echo htmlspecialchars($editEmployee['department'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></p>
                        <p><strong>Position:</strong>
                            <?php echo htmlspecialchars($editEmployee['position'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></p>
                        <p><strong>Status:</strong>
                            <?php echo htmlspecialchars($editEmployee['status'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <div>
                        <p><strong>Employment Type:</strong>
                            <?php echo htmlspecialchars($editEmployee['employment_type'] ?: '-', ENT_QUOTES, 'UTF-8'); ?>
                        </p>
                        <p><strong>Hire Date:</strong>
                            <?php echo htmlspecialchars($editEmployee['hired_date'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></p>
                        <p><strong>Email:</strong>
                            <?php echo htmlspecialchars($editEmployee['email'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></p>
                        <p><strong>Phone:</strong>
                            <?php echo htmlspecialchars($editEmployee['phone'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></p>
                        <p><strong>Address:</strong>
                            <?php echo htmlspecialchars($editEmployee['address'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>

                <?php if (!empty($editEmployee['profile_image']) || !empty($editEmployee['document_file'])): ?>
                    <div class="employee-assets">
                        <?php if (!empty($editEmployee['profile_image'])): ?>
                            <div class="employee-asset-card">
                                <p>Profile Picture</p>
                                <img src="<?php echo htmlspecialchars($inline ? $editEmployee['profile_image'] : '../' . $editEmployee['profile_image'], ENT_QUOTES, 'UTF-8'); ?>"
                                    alt="Employee profile picture">
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($editEmployee['document_file'])): ?>
                            <div class="employee-asset-card">
                                <p>Uploaded Document</p>
                                <a class="button small secondary"
                                    href="<?php echo htmlspecialchars($inline ? $editEmployee['document_file'] : '../' . $editEmployee['document_file'], ENT_QUOTES, 'UTF-8'); ?>"
                                    target="_blank">View Document</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <form action="<?php echo $formPrefix; ?>save_employee.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?php echo $editEmployee ? (int) $editEmployee['id'] : ''; ?>">

                    <div class="form-row">
                        <input type="text" name="fullname" placeholder="Full Name" required
                            value="<?php echo htmlspecialchars($editEmployee['fullname'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="text" name="department" placeholder="Department" required
                            value="<?php echo htmlspecialchars($editEmployee['department'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-row">
                        <input type="text" name="position" placeholder="Position" required
                            value="<?php echo htmlspecialchars($editEmployee['position'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="number" step="0.01" name="salary" placeholder="Salary" required
                            value="<?php echo htmlspecialchars($editEmployee['salary'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-row">
                        <input type="date" name="hired_date" placeholder="Hired Date" required
                            value="<?php echo htmlspecialchars($editEmployee['hired_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <select name="employment_type" required>
                            <?php foreach (['Regular', 'Probationary', 'Contractual', 'Part-Time'] as $employmentType): ?>
                                <option value="<?php echo htmlspecialchars($employmentType, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($editEmployee['employment_type'] ?? 'Regular') === $employmentType ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($employmentType, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <input type="email" name="email" placeholder="Email Address"
                            value="<?php echo htmlspecialchars($editEmployee['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="text" name="phone" placeholder="Phone Number"
                            value="<?php echo htmlspecialchars($editEmployee['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="form-row">
                        <select name="status" required>
                            <?php foreach (['Active', 'Resigned', 'On Leave'] as $status): ?>
                                <option value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($editEmployee['status'] ?? 'Active') === $status ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text"
                            value="<?php echo htmlspecialchars($editEmployee['employee_code'] ?? 'Auto-generated upon save', ENT_QUOTES, 'UTF-8'); ?>"
                            readonly>
                    </div>

                    <div class="form-row">
                        <select name="account_role" required>
                            <option value="employee" <?php echo ($editEmployee['account_role'] ?? 'employee') === 'employee' ? 'selected' : ''; ?>>Regular User</option>
                            <option value="admin" <?php echo ($editEmployee['account_role'] ?? 'employee') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                        <input type="text"
                            value="<?php echo htmlspecialchars((($editEmployee['account_role'] ?? 'employee') === 'admin' ? 'Admin access' : 'Employee self-service access'), ENT_QUOTES, 'UTF-8'); ?>"
                            readonly>
                    </div>

                    <div class="form-row">
                        <input type="password" name="system_password"
                            placeholder="<?php echo $editEmployee ? 'New System Password (leave blank to keep current)' : 'System Password'; ?>"
                            <?php echo $editEmployee ? '' : 'required'; ?>>
                        <input type="text"
                            value="<?php echo htmlspecialchars(($editEmployee['employee_code'] ?? 'Auto-generated employee number') . ' will be used as the login ID', ENT_QUOTES, 'UTF-8'); ?>"
                            readonly>
                    </div>

                    <textarea name="address"
                        placeholder="Address"><?php echo htmlspecialchars($editEmployee['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>

                    <div class="form-row">
                        <div>
                            <label>Profile Picture</label>
                            <input type="file" name="profile_image" accept=".jpg,.jpeg,.png,.gif,.webp">
                        </div>
                        <div>
                            <label>Employee Document</label>
                            <input type="file" name="document_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                        </div>
                    </div>

                    <?php if (!empty($editEmployee['profile_image']) || !empty($editEmployee['document_file'])): ?>
                        <div class="employee-assets">
                            <?php if (!empty($editEmployee['profile_image'])): ?>
                                <div class="employee-asset-card">
                                    <p>Current Profile</p>
                                    <img src="<?php echo htmlspecialchars($inline ? $editEmployee['profile_image'] : '../' . $editEmployee['profile_image'], ENT_QUOTES, 'UTF-8'); ?>"
                                        alt="Employee profile picture">
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($editEmployee['document_file'])): ?>
                                <div class="employee-asset-card">
                                    <p>Current Document</p>
                                    <a class="button small secondary"
                                        href="<?php echo htmlspecialchars($inline ? $editEmployee['document_file'] : '../' . $editEmployee['document_file'], ENT_QUOTES, 'UTF-8'); ?>"
                                        target="_blank">View Document</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <button type="submit"><?php echo $editEmployee ? 'Update Employee' : 'Add Employee'; ?></button>
                    <?php if ($editEmployee): ?>
                        <a class="button secondary" href="<?php echo $dashboardUrl; ?>?page=employees">Cancel edit</a>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3><?php echo $viewOnly ? 'Account Summary' : 'Employee List'; ?></h3>
            <?php if (empty($employees)): ?>
                <p class="empty-state">No employee records available.</p>
            <?php else: ?>
                <table class="inventory-table">
                    <tr>
                        <th>Employee</th>
                        <th>Department / Position</th>
                        <th>Employment</th>
                        <th>Status</th>
                        <th>Documents</th>
                        <th>Actions</th>
                    </tr>
                    <?php foreach ($employees as $employee): ?>
                        <tr>
                            <td>
                                <div class="employee-cell">
                                    <?php if (!empty($employee['profile_image'])): ?>
                                        <img class="employee-avatar"
                                            src="<?php echo htmlspecialchars($inline ? $employee['profile_image'] : '../' . $employee['profile_image'], ENT_QUOTES, 'UTF-8'); ?>"
                                            alt="Profile picture">
                                    <?php else: ?>
                                        <div class="employee-avatar employee-avatar-fallback">
                                            <?php echo htmlspecialchars(strtoupper(substr($employee['fullname'], 0, 1)), ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?php echo htmlspecialchars($employee['fullname'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                        <span class="item-meta">
                                            <?php echo htmlspecialchars($employee['employee_code'] ?: 'Pending ID', ENT_QUOTES, 'UTF-8'); ?>
                                        </span><br>
                                        <span class="item-meta">
                                            Login ID:
                                            <?php echo htmlspecialchars($employee['employee_code'] ?: 'Pending ID', ENT_QUOTES, 'UTF-8'); ?>
                                        </span><br>
                                        <span class="item-meta">
                                            <?php echo htmlspecialchars($employee['email'] ?: 'No email', ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($employee['department'] ?: '-', ENT_QUOTES, 'UTF-8'); ?><br>
                                <span
                                    class="item-meta"><?php echo htmlspecialchars($employee['position'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($employee['employment_type'] ?: '-', ENT_QUOTES, 'UTF-8'); ?><br>
                                <span class="item-meta">Hired:
                                    <?php echo htmlspecialchars($employee['hired_date'], ENT_QUOTES, 'UTF-8'); ?></span><br>
                                <span class="item-meta">Salary: PHP
                                    <?php echo number_format((float) $employee['salary'], 2); ?></span>
                            </td>
                            <td>
                                <span class="<?php echo employeeStatusClass((string) ($employee['status'] ?? 'Active')); ?>">
                                    <?php echo htmlspecialchars($employee['status'] ?: 'Active', ENT_QUOTES, 'UTF-8'); ?>
                                </span><br>
                                <span class="item-meta">
                                    Role:
                                    <?php echo htmlspecialchars(($employee['account_role'] ?? 'employee') === 'admin' ? 'Admin' : 'Regular User', ENT_QUOTES, 'UTF-8'); ?>
                                </span><br>
                                <span
                                    class="item-meta"><?php echo htmlspecialchars($employee['phone'] ?: 'No phone', ENT_QUOTES, 'UTF-8'); ?></span>
                            </td>
                            <td>
                                <?php if (!empty($employee['document_file'])): ?>
                                    <a class="button small secondary"
                                        href="<?php echo htmlspecialchars($inline ? $employee['document_file'] : '../' . $employee['document_file'], ENT_QUOTES, 'UTF-8'); ?>"
                                        target="_blank">Open File</a>
                                <?php else: ?>
                                    <span class="item-meta">No document</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$viewOnly): ?>
                                    <a class="button small"
                                        href="<?php echo $dashboardUrl; ?>?page=employees&edit_id=<?php echo (int) $employee['id']; ?>">Edit</a>
                                    <form class="inline-form" action="<?php echo $formPrefix; ?>save_employee.php" method="POST"
                                        onsubmit="return confirm('Delete this employee record?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int) $employee['id']; ?>">
                                        <button type="submit" class="button small danger">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span class="item-meta">View only</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .employee-cell {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .employee-avatar {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .employee-avatar-fallback {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #1f7a53;
            color: #fff;
            font-weight: bold;
        }

        .employee-assets {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .employee-profile-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }

        .employee-asset-card {
            padding: 1rem;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.5);
        }

        .employee-asset-card img {
            width: 96px;
            height: 96px;
            object-fit: cover;
            border-radius: 12px;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-active {
            background: rgba(31, 122, 83, 0.14);
            color: #17613f;
        }

        .status-leave {
            background: rgba(180, 135, 60, 0.16);
            color: #8b6423;
        }

        .status-resigned {
            background: rgba(181, 65, 65, 0.14);
            color: #923030;
        }

        @media (max-width: 700px) {
            .employee-profile-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <?php if (!$inline): ?>
    </body>

    </html>
<?php endif; ?>