<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit();
}

require '../config/db.php';

$redirect = '../dashboard.php?page=employees';

function redirectWithMessage(string $message, string $suffix = ''): void
{
    global $redirect;
    $_SESSION['employee_message'] = $message;
    header('Location: ' . $redirect . $suffix);
    exit();
}

function ensureEmployeeColumns(PDO $conn): void
{
    $columnsStmt = $conn->query('SHOW COLUMNS FROM employees');
    $columns = array_column($columnsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

    $definitions = [
        'employee_code' => "ALTER TABLE employees ADD COLUMN employee_code VARCHAR(50) NULL DEFAULT NULL",
        'department' => "ALTER TABLE employees ADD COLUMN department VARCHAR(150) NOT NULL DEFAULT ''",
        'email' => "ALTER TABLE employees ADD COLUMN email VARCHAR(255) NOT NULL DEFAULT ''",
        'phone' => "ALTER TABLE employees ADD COLUMN phone VARCHAR(100) NOT NULL DEFAULT ''",
        'address' => "ALTER TABLE employees ADD COLUMN address TEXT NULL",
        'employment_type' => "ALTER TABLE employees ADD COLUMN employment_type VARCHAR(100) NOT NULL DEFAULT 'Regular'",
        'status' => "ALTER TABLE employees ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'Active'",
        'profile_image' => "ALTER TABLE employees ADD COLUMN profile_image VARCHAR(255) NULL DEFAULT NULL",
        'document_file' => "ALTER TABLE employees ADD COLUMN document_file VARCHAR(255) NULL DEFAULT NULL",
    ];

    foreach ($definitions as $field => $sql) {
        if (!in_array($field, $columns, true)) {
            $conn->exec($sql);
        }
    }
}

function ensureUserTable(PDO $conn): void
{
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

    $columnsStmt = $conn->query('SHOW COLUMNS FROM users');
    $columns = array_column($columnsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

    $definitions = [
        'employee_id' => 'ALTER TABLE users ADD COLUMN employee_id INT NULL DEFAULT NULL',
        'fullname' => "ALTER TABLE users ADD COLUMN fullname VARCHAR(255) NOT NULL DEFAULT ''",
        'is_active' => 'ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1',
        'account_role' => "ALTER TABLE users ADD COLUMN account_role VARCHAR(50) NOT NULL DEFAULT 'admin'",
    ];

    foreach ($definitions as $field => $sql) {
        if (!in_array($field, $columns, true)) {
            $conn->exec($sql);
        }
    }
}

function sanitizeFileName(string $fileName): string
{
    $clean = preg_replace('/[^A-Za-z0-9._-]/', '_', $fileName);
    return $clean ?: 'file';
}

function saveUpload(array $file, string $prefix, array $allowedExtensions): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed. Please try again.');
    }

    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        throw new RuntimeException('Invalid upload file type.');
    }

    $uploadDir = dirname(__DIR__) . '/uploads/employees';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Unable to create upload directory.');
    }

    $targetName = $prefix . '_' . date('YmdHis') . '_' . sanitizeFileName((string) ($file['name'] ?? 'upload'));
    $targetPath = $uploadDir . '/' . $targetName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Failed to save uploaded file.');
    }

    return 'uploads/employees/' . $targetName;
}

function generateEmployeeCode(PDO $conn): string
{
    $year = date('Y');
    $prefix = 'EMP-' . $year . '-';
    $stmt = $conn->prepare("SELECT employee_code FROM employees WHERE employee_code LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $lastCode = (string) $stmt->fetchColumn();
    $nextNumber = 1;

    if ($lastCode !== '' && preg_match('/(\d+)$/', $lastCode, $matches)) {
        $nextNumber = ((int) $matches[1]) + 1;
    }

    return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
}

ensureEmployeeColumns($conn);
ensureUserTable($conn);

$action = $_POST['action'] ?? 'save';
$id = $_POST['id'] ?? null;

if ($action === 'delete') {
    if ($id === null || !is_numeric($id)) {
        redirectWithMessage('Invalid employee selected for deletion.');
    }

    $stmt = $conn->prepare('SELECT profile_image, document_file FROM employees WHERE id = ?');
    $stmt->execute([(int) $id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    $deleteStmt = $conn->prepare('DELETE FROM employees WHERE id = ?');
    $deleteStmt->execute([(int) $id]);

    if ($employee) {
        foreach (['profile_image', 'document_file'] as $field) {
            if (!empty($employee[$field])) {
                $fullPath = dirname(__DIR__) . '/' . $employee[$field];
                if (is_file($fullPath)) {
                    @unlink($fullPath);
                }
            }
        }
    }

    redirectWithMessage('Employee deleted successfully.');
}

$fullname = trim($_POST['fullname'] ?? '');
$department = trim($_POST['department'] ?? '');
$position = trim($_POST['position'] ?? '');
$salary = $_POST['salary'] ?? null;
$hiredDate = $_POST['hired_date'] ?? '';
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');
$employmentType = trim($_POST['employment_type'] ?? 'Regular');
$status = trim($_POST['status'] ?? 'Active');
$systemPassword = $_POST['system_password'] ?? '';
$accountRole = trim($_POST['account_role'] ?? 'employee');

if (
    $fullname === '' ||
    $department === '' ||
    $position === '' ||
    $salary === null ||
    !is_numeric($salary) ||
    $hiredDate === ''
) {
    $suffix = $id !== null && is_numeric($id) ? '&edit_id=' . (int) $id : '';
    redirectWithMessage('Please complete the required employee fields.', $suffix);
}

$salary = (float) $salary;
$allowedEmploymentTypes = ['Regular', 'Probationary', 'Contractual', 'Part-Time'];
$allowedStatuses = ['Active', 'Resigned', 'On Leave'];
$allowedAccountRoles = ['employee', 'admin'];

if (!in_array($employmentType, $allowedEmploymentTypes, true)) {
    $employmentType = 'Regular';
}
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'Active';
}
if (!in_array($accountRole, $allowedAccountRoles, true)) {
    $accountRole = 'employee';
}

$accountIsActive = $status !== 'Resigned' ? 1 : 0;

$profileImagePath = null;
$documentFilePath = null;

try {
    $profileImagePath = saveUpload($_FILES['profile_image'] ?? [], 'profile', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    $documentFilePath = saveUpload($_FILES['document_file'] ?? [], 'document', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);
} catch (RuntimeException $e) {
    $suffix = $id !== null && is_numeric($id) ? '&edit_id=' . (int) $id : '';
    redirectWithMessage($e->getMessage(), $suffix);
}

if ($id !== null && is_numeric($id)) {
    $currentStmt = $conn->prepare('SELECT profile_image, document_file FROM employees WHERE id = ?');
    $currentStmt->execute([(int) $id]);
    $current = $currentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        redirectWithMessage('Employee not found.');
    }

    $finalProfileImage = $profileImagePath ?: ($current['profile_image'] ?? null);
    $finalDocumentFile = $documentFilePath ?: ($current['document_file'] ?? null);

    $stmt = $conn->prepare('UPDATE employees SET fullname = ?, department = ?, position = ?, salary = ?, hired_date = ?, email = ?, phone = ?, address = ?, employment_type = ?, status = ?, profile_image = ?, document_file = ? WHERE id = ?');
    $stmt->execute([
        $fullname,
        $department,
        $position,
        $salary,
        $hiredDate,
        $email,
        $phone,
        $address !== '' ? $address : null,
        $employmentType,
        $status,
        $finalProfileImage,
        $finalDocumentFile,
        (int) $id,
    ]);

    if ($profileImagePath && !empty($current['profile_image']) && $current['profile_image'] !== $profileImagePath) {
        $oldPath = dirname(__DIR__) . '/' . $current['profile_image'];
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }
    if ($documentFilePath && !empty($current['document_file']) && $current['document_file'] !== $documentFilePath) {
        $oldPath = dirname(__DIR__) . '/' . $current['document_file'];
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    $employeeCode = (string) ($current['employee_code'] ?? '');
    if ($employeeCode !== '') {
        $userStmt = $conn->prepare('SELECT id FROM users WHERE employee_id = ? OR username = ? LIMIT 1');
        $userStmt->execute([(int) $id, $employeeCode]);
        $userId = $userStmt->fetchColumn();

        if ($userId) {
            if ($systemPassword !== '') {
                $passwordHash = password_hash($systemPassword, PASSWORD_DEFAULT);
                $updateUser = $conn->prepare('UPDATE users SET username = ?, password = ?, fullname = ?, employee_id = ?, is_active = ?, account_role = ? WHERE id = ?');
                $updateUser->execute([$employeeCode, $passwordHash, $fullname, (int) $id, $accountIsActive, $accountRole, (int) $userId]);
            } else {
                $updateUser = $conn->prepare('UPDATE users SET username = ?, fullname = ?, employee_id = ?, is_active = ?, account_role = ? WHERE id = ?');
                $updateUser->execute([$employeeCode, $fullname, (int) $id, $accountIsActive, $accountRole, (int) $userId]);
            }
        }
    }

    redirectWithMessage('Employee updated successfully.');
}

$employeeCode = generateEmployeeCode($conn);
$suffix = '';
if ($systemPassword === '') {
    redirectWithMessage('Please set a system password for the employee account.', $suffix);
}

$stmt = $conn->prepare('INSERT INTO employees (employee_code, fullname, department, position, salary, hired_date, email, phone, address, employment_type, status, profile_image, document_file) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->execute([
    $employeeCode,
    $fullname,
    $department,
    $position,
    $salary,
    $hiredDate,
    $email,
    $phone,
    $address !== '' ? $address : null,
    $employmentType,
    $status,
    $profileImagePath,
    $documentFilePath,
]);

$employeeId = (int) $conn->lastInsertId();
$passwordHash = password_hash($systemPassword, PASSWORD_DEFAULT);
$userStmt = $conn->prepare('INSERT INTO users (employee_id, username, password, fullname, is_active, account_role) VALUES (?, ?, ?, ?, ?, ?)');
$userStmt->execute([
    $employeeId,
    $employeeCode,
    $passwordHash,
    $fullname,
    $accountIsActive,
    $accountRole,
]);

redirectWithMessage('Employee added successfully.');
?>
