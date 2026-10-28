<?php
/*
 * One-time setup. Creates the database and tables if they are missing, adds the
 * columns this version needs, and creates the default admin account.
 * Delete or protect this file after you have run it.
 */
require __DIR__ . '/includes/helpers.php';
$cfg = require __DIR__ . '/config/config.php';

$message = '';
$error = '';

try {
    $server = new PDO('mysql:host=' . $cfg['host'] . ';charset=utf8mb4', $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $server->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $cfg['name']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
    $server = null;

    require __DIR__ . '/config/db.php';

    $queries = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            fullname VARCHAR(150) NOT NULL,
            role VARCHAR(50) NOT NULL DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            employee_id INT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            account_role VARCHAR(50) NOT NULL DEFAULT 'admin'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS suppliers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            contact VARCHAR(255) DEFAULT NULL,
            email VARCHAR(255) DEFAULT NULL,
            phone VARCHAR(100) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS inventory (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_name VARCHAR(255) NOT NULL,
            stock INT NOT NULL DEFAULT 0,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            category_id INT DEFAULT NULL,
            supplier_id INT DEFAULT NULL,
            barcode VARCHAR(100) DEFAULT NULL,
            low_stock_threshold INT NOT NULL DEFAULT 5,
            image_path VARCHAR(255) DEFAULT NULL,
            description TEXT DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS employees (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fullname VARCHAR(255) NOT NULL,
            position VARCHAR(150) DEFAULT NULL,
            salary DECIMAL(12,2) DEFAULT 0.00,
            hired_date DATE DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            employee_code VARCHAR(50) DEFAULT NULL,
            department VARCHAR(150) NOT NULL DEFAULT '',
            email VARCHAR(255) NOT NULL DEFAULT '',
            phone VARCHAR(100) NOT NULL DEFAULT '',
            address TEXT DEFAULT NULL,
            employment_type VARCHAR(100) NOT NULL DEFAULT 'Regular',
            status VARCHAR(50) NOT NULL DEFAULT 'Active',
            profile_image VARCHAR(255) DEFAULT NULL,
            document_file VARCHAR(255) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS sales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_name VARCHAR(255) NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total DECIMAL(10,2) GENERATED ALWAYS AS (quantity * price) VIRTUAL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            payment_method VARCHAR(50) NOT NULL DEFAULT 'Cash',
            amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            change_due DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            inventory_item_id INT DEFAULT NULL,
            customer_name VARCHAR(255) NOT NULL DEFAULT '',
            customer_contact VARCHAR(255) NOT NULL DEFAULT '',
            installation_required TINYINT(1) NOT NULL DEFAULT 0,
            installation_status VARCHAR(50) NOT NULL DEFAULT 'Not Scheduled',
            installer_id INT DEFAULT NULL,
            installation_date DATE DEFAULT NULL,
            installation_notes TEXT DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS payroll (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            pay_period VARCHAR(100) NOT NULL,
            gross_pay DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            deductions DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            net_pay DECIMAL(12,2) GENERATED ALWAYS AS (gross_pay - deductions) VIRTUAL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            time_in DATETIME NOT NULL,
            time_out DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    foreach ($queries as $sql) {
        $conn->exec($sql);
    }

    ensure_schema($conn); // after_sales_cases + sales.receipt_no

    $exists = db_val($conn, 'SELECT COUNT(*) FROM users WHERE username = ?', ['admin']);
    if (!$exists) {
        $conn->prepare('INSERT INTO users (username, password, fullname, role, account_role) VALUES (?, ?, ?, ?, ?)')
            ->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'Administrator', 'admin', 'admin']);
        $message = 'Database ready. Default admin account created: admin / admin123. Change the password after signing in.';
    } else {
        $message = 'Database ready. The admin account already exists, so it was left unchanged.';
    }

    foreach (['products', 'employees', 'documents'] as $dir) {
        if (!is_dir(__DIR__ . '/uploads/' . $dir)) {
            @mkdir(__DIR__ . '/uploads/' . $dir, 0755, true);
        }
    }
} catch (Throwable $e) {
    $error = 'Setup failed: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <div class="setup-message">
        <?php if ($error): ?>
            <h1>Setup did not finish</h1>
            <p><?php echo h($error); ?></p>
            <p>Check the database settings in <code>config/config.php</code> and that MySQL is running.</p>
        <?php else: ?>
            <h1>Setup finished</h1>
            <p><?php echo h($message); ?></p>
            <p><a href="index.php">Go to login</a></p>
        <?php endif; ?>
    </div>
</body>

</html>
