<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit();
}
if (($_SESSION['user_role'] ?? 'admin') === 'employee') {
    header('Location: ../dashboard.php?page=employees');
    exit();
}

require '../config/db.php';

$employeeId = $_POST['employee_id'] ?? null;
$payPeriod = trim($_POST['pay_period'] ?? '');
$grossPay = $_POST['gross_pay'] ?? null;
$deductions = $_POST['deductions'] ?? null;

if ($employeeId === null || $payPeriod === '' || $grossPay === null || $deductions === null || !is_numeric($grossPay) || !is_numeric($deductions)) {
    echo "Invalid payroll input.";
    exit();
}

$employeeId = (int) $employeeId;
$grossPay = (float) $grossPay;
$deductions = (float) $deductions;

$stmt = $conn->prepare('INSERT INTO payroll (employee_id, pay_period, gross_pay, deductions) VALUES (?, ?, ?, ?)');
$stmt->execute([$employeeId, $payPeriod, $grossPay, $deductions]);

header('Location: payroll.php');
exit();
