<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit();
}

require '../config/db.php';

$employeeId = $_POST['employee_id'] ?? null;
$action = trim($_POST['action'] ?? '');
$userRole = $_SESSION['user_role'] ?? 'admin';
$sessionEmployeeId = isset($_SESSION['employee_id']) ? (int) $_SESSION['employee_id'] : 0;

if ($employeeId === null || !is_numeric($employeeId) || !in_array($action, ['in', 'out'], true)) {
    $_SESSION['attendance_message'] = 'Invalid attendance input.';
    header('Location: ../dashboard.php?page=attendance');
    exit();
}

$employeeId = (int) $employeeId;

if ($userRole === 'employee') {
    if ($sessionEmployeeId <= 0 || $employeeId !== $sessionEmployeeId) {
        $_SESSION['attendance_message'] = 'You can only manage your own attendance.';
        header('Location: ../dashboard.php?page=attendance');
        exit();
    }
}

if ($action === 'in') {
    $checkOpen = $conn->prepare('SELECT id FROM attendance WHERE employee_id = ? AND time_out IS NULL ORDER BY id DESC LIMIT 1');
    $checkOpen->execute([$employeeId]);
    $openRecord = $checkOpen->fetch(PDO::FETCH_ASSOC);

    if ($openRecord) {
        $_SESSION['attendance_message'] = 'This employee is already clocked in.';
        header('Location: ../dashboard.php?page=attendance');
        exit();
    }

    $stmt = $conn->prepare('INSERT INTO attendance (employee_id, time_in) VALUES (?, NOW())');
    $stmt->execute([$employeeId]);
    $_SESSION['attendance_message'] = 'Time in recorded successfully.';
} else {
    $check = $conn->prepare('SELECT id FROM attendance WHERE employee_id = ? AND time_out IS NULL ORDER BY id DESC LIMIT 1');
    $check->execute([$employeeId]);
    $record = $check->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        $_SESSION['attendance_message'] = 'No open time in record found for this employee.';
        header('Location: ../dashboard.php?page=attendance');
        exit();
    }

    $update = $conn->prepare('UPDATE attendance SET time_out = NOW() WHERE id = ?');
    $update->execute([$record['id']]);
    $_SESSION['attendance_message'] = 'Time out recorded successfully.';
}

header('Location: ../dashboard.php?page=attendance');
exit();
