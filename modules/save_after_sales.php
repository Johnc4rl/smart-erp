<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit();
}
require '../config/db.php';

$redirect = '../dashboard.php?page=after_sales';

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

$action = $_POST['action'] ?? '';

if ($action === 'update_installation') {
    $saleId = $_POST['sale_id'] ?? null;
    $installerId = $_POST['installer_id'] ?? null;
    $installationDate = trim($_POST['installation_date'] ?? '');
    $installationStatus = trim($_POST['installation_status'] ?? 'Pending Assignment');
    $installationNotes = trim($_POST['installation_notes'] ?? '');

    if ($saleId === null || !is_numeric($saleId) || $installerId === null || !is_numeric($installerId)) {
        $_SESSION['after_sales_message'] = 'Please select a valid sale and installer.';
        header('Location: ' . $redirect);
        exit();
    }

    $allowedStatuses = ['Pending Assignment', 'Scheduled', 'Installed', 'Installation Failed'];
    if (!in_array($installationStatus, $allowedStatuses, true)) {
        $installationStatus = 'Pending Assignment';
    }

    $stmt = $conn->prepare('UPDATE sales SET installer_id = ?, installation_date = ?, installation_status = ?, installation_notes = ?, installation_required = 1 WHERE id = ?');
    $stmt->execute([
        (int) $installerId,
        $installationDate !== '' ? $installationDate : null,
        $installationStatus,
        $installationNotes !== '' ? $installationNotes : null,
        (int) $saleId,
    ]);

    $_SESSION['after_sales_message'] = 'Installation assignment saved successfully.';
    header('Location: ' . $redirect);
    exit();
}

if ($action === 'create_case') {
    $saleId = $_POST['sale_id'] ?? null;
    $technicianId = $_POST['technician_id'] ?? null;
    $issueDescription = trim($_POST['issue_description'] ?? '');
    $assessmentStatus = trim($_POST['assessment_status'] ?? 'For Assessment');
    $warrantyStatus = trim($_POST['warranty_status'] ?? 'Pending Assessment');
    $resolution = trim($_POST['resolution'] ?? 'Pending');
    $replacementRequired = !empty($_POST['replacement_required']) ? 1 : 0;
    $notes = trim($_POST['notes'] ?? '');

    if ($saleId === null || !is_numeric($saleId) || $technicianId === null || !is_numeric($technicianId) || $issueDescription === '') {
        $_SESSION['after_sales_message'] = 'Please complete the concern case form.';
        header('Location: ' . $redirect);
        exit();
    }

    $stmt = $conn->prepare('INSERT INTO after_sales_cases (sale_id, technician_id, issue_description, assessment_status, warranty_status, resolution, replacement_required, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        (int) $saleId,
        (int) $technicianId,
        $issueDescription,
        $assessmentStatus,
        $warrantyStatus,
        $resolution,
        $replacementRequired,
        $notes !== '' ? $notes : null,
    ]);

    $_SESSION['after_sales_message'] = 'After-sales concern recorded successfully.';
    header('Location: ' . $redirect);
    exit();
}

$_SESSION['after_sales_message'] = 'Invalid after-sales action.';
header('Location: ' . $redirect);
exit();
?>
