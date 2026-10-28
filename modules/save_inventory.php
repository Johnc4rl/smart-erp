<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit();
}
require '../config/db.php';

$action = $_POST['action'] ?? 'save';
$redirect = '../dashboard.php?page=inventory';

if ($action === 'save_category') {
    $name = trim($_POST['category_name'] ?? '');
    if ($name === '') {
        $_SESSION['inventory_message'] = 'Category name cannot be empty.';
        header("Location: $redirect");
        exit();
    }

    $stmt = $conn->prepare('INSERT IGNORE INTO categories (name) VALUES (?)');
    $stmt->execute([$name]);
    $_SESSION['inventory_message'] = 'Category saved successfully.';
    header("Location: $redirect");
    exit();
}

if ($action === 'save_supplier') {
    $name = trim($_POST['supplier_name'] ?? '');
    if ($name === '') {
        $_SESSION['inventory_message'] = 'Supplier name cannot be empty.';
        header("Location: $redirect");
        exit();
    }

    $contact = trim($_POST['supplier_contact'] ?? '');
    $phone = trim($_POST['supplier_phone'] ?? '');
    $email = trim($_POST['supplier_email'] ?? '');
    $stmt = $conn->prepare('INSERT INTO suppliers (name, contact, phone, email) VALUES (?, ?, ?, ?)');
    $stmt->execute([$name, $contact, $phone, $email]);
    $_SESSION['inventory_message'] = 'Supplier saved successfully.';
    header("Location: $redirect");
    exit();
}

if ($action === 'delete') {
    $id = $_POST['id'] ?? null;
    if ($id === null || !is_numeric($id)) {
        $_SESSION['inventory_message'] = 'Invalid product selected for deletion.';
        header("Location: $redirect");
        exit();
    }

    $id = (int) $id;
    $stmt = $conn->prepare('SELECT image_path FROM inventory WHERE id = ?');
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($product && !empty($product['image_path']) && file_exists(__DIR__ . '/../' . $product['image_path'])) {
        @unlink(__DIR__ . '/../' . $product['image_path']);
    }

    $delete = $conn->prepare('DELETE FROM inventory WHERE id = ?');
    $delete->execute([$id]);
    $_SESSION['inventory_message'] = 'Product deleted successfully.';
    header("Location: $redirect");
    exit();
}

$itemName = trim($_POST['item_name'] ?? '');
$barcode = trim($_POST['barcode'] ?? '');
$categoryId = $_POST['category_id'] ?? null;
$supplierId = $_POST['supplier_id'] ?? null;
$stock = $_POST['stock'] ?? null;
$price = $_POST['price'] ?? null;
$lowThreshold = $_POST['low_stock_threshold'] ?? 5;
$description = trim($_POST['description'] ?? '');
$id = $_POST['id'] ?? null;

if ($itemName === '' || $stock === null || $price === null || !is_numeric($stock) || !is_numeric($price) || !is_numeric($lowThreshold)) {
    $_SESSION['inventory_message'] = 'Please complete all required product fields.';
    header("Location: $redirect");
    exit();
}

$stock = (int) $stock;
$price = (float) $price;
$lowThreshold = (int) $lowThreshold;
$categoryId = is_numeric($categoryId) ? (int) $categoryId : null;
$supplierId = is_numeric($supplierId) ? (int) $supplierId : null;

$imagePath = null;
if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../assets/images/products';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $filename = uniqid('prod_', true) . '.' . strtolower($ext);
    $target = $uploadDir . '/' . $filename;
    move_uploaded_file($_FILES['image']['tmp_name'], $target);
    $imagePath = 'assets/images/products/' . $filename;
}

if ($id !== null && is_numeric($id)) {
    $id = (int) $id;
    $currentStmt = $conn->prepare('SELECT image_path FROM inventory WHERE id = ?');
    $currentStmt->execute([$id]);
    $current = $currentStmt->fetch(PDO::FETCH_ASSOC);

    $fields = 'item_name = ?, barcode = ?, category_id = ?, supplier_id = ?, price = ?, stock = ?, low_stock_threshold = ?, description = ?';
    $values = [$itemName, $barcode, $categoryId, $supplierId, $price, $stock, $lowThreshold, $description];

    if ($imagePath) {
        if ($current && !empty($current['image_path']) && file_exists(__DIR__ . '/../' . $current['image_path'])) {
            @unlink(__DIR__ . '/../' . $current['image_path']);
        }
        $fields .= ', image_path = ?';
        $values[] = $imagePath;
    }

    $values[] = $id;
    $stmt = $conn->prepare("UPDATE inventory SET $fields WHERE id = ?");
    $stmt->execute($values);
    $_SESSION['inventory_message'] = 'Product updated successfully.';
} else {
    $stmt = $conn->prepare('INSERT INTO inventory (item_name, barcode, category_id, supplier_id, price, stock, low_stock_threshold, description, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$itemName, $barcode, $categoryId, $supplierId, $price, $stock, $lowThreshold, $description, $imagePath]);
    $_SESSION['inventory_message'] = 'Product added successfully.';
}

header("Location: $redirect");
exit();
?>
