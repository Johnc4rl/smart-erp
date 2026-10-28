<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit();
}

require '../config/db.php';

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    header('Location: ../index.php?error=' . urlencode('Username and password are required.'));
    exit();
}

$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$resolvedEmployeeId = !empty($user['employee_id']) ? (int) $user['employee_id'] : 0;
$resolvedRole = trim((string) ($user['account_role'] ?? ''));
if ($user && $resolvedEmployeeId <= 0) {
    $employeeStmt = $conn->prepare('SELECT id, fullname FROM employees WHERE employee_code = ? LIMIT 1');
    $employeeStmt->execute([$user['username']]);
    $employee = $employeeStmt->fetch(PDO::FETCH_ASSOC);

    if ($employee) {
        $resolvedEmployeeId = (int) $employee['id'];
        $user['employee_id'] = $resolvedEmployeeId;
        if (empty($user['fullname']) && !empty($employee['fullname'])) {
            $user['fullname'] = $employee['fullname'];
        }
        if ($resolvedRole === '') {
            $resolvedRole = 'employee';
        }
    }
}

if ($user && (int) ($user['is_active'] ?? 1) !== 1) {
    header('Location: ../index.php?error=' . urlencode('This employee account is inactive.'));
    exit();
}

if ($user && password_verify($password, $user['password'])) {
    if ($resolvedEmployeeId > 0 && (int) ($user['employee_id'] ?? 0) !== $resolvedEmployeeId) {
        $syncStmt = $conn->prepare('UPDATE users SET employee_id = ?, fullname = ? WHERE id = ?');
        $syncStmt->execute([
            $resolvedEmployeeId,
            $user['fullname'] ?? $user['username'],
            (int) $user['id'],
        ]);
    }

    if ($resolvedRole === '') {
        $resolvedRole = $resolvedEmployeeId > 0 ? 'employee' : 'admin';
    }
    if (!in_array($resolvedRole, ['employee', 'admin'], true)) {
        $resolvedRole = $resolvedEmployeeId > 0 ? 'employee' : 'admin';
    }

    session_regenerate_id(true);
    $_SESSION['user'] = $user['fullname'] ?? $user['username'];
    $_SESSION['user_id'] = (int) ($user['id'] ?? 0);
    $_SESSION['employee_id'] = $resolvedEmployeeId > 0 ? $resolvedEmployeeId : null;
    $_SESSION['user_role'] = $resolvedRole;
    header("Location: ../dashboard.php");
    exit();
}

header('Location: ../index.php?error=' . urlencode('Invalid login credentials.'));
exit();
