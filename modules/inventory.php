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

$conn->exec("CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->exec("CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    contact VARCHAR(255) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(100) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->exec("ALTER TABLE inventory 
    ADD COLUMN IF NOT EXISTS category_id INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS supplier_id INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS barcode VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS low_stock_threshold INT NOT NULL DEFAULT 5,
    ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS description TEXT DEFAULT NULL");

$categories = $conn->query('SELECT * FROM categories ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
$suppliers = $conn->query('SELECT * FROM suppliers ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);

$editId = isset($_GET['edit_id']) ? (int) $_GET['edit_id'] : 0;
$editItem = null;
if ($editId) {
    $stmt = $conn->prepare('SELECT * FROM inventory WHERE id = ?');
    $stmt->execute([$editId]);
    $editItem = $stmt->fetch(PDO::FETCH_ASSOC);
}

$itemsStmt = $conn->query("SELECT i.*, c.name AS category_name, s.name AS supplier_name
    FROM inventory i
    LEFT JOIN categories c ON i.category_id = c.id
    LEFT JOIN suppliers s ON i.supplier_id = s.id
    ORDER BY i.id DESC");
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

$message = '';
if (!empty($_SESSION['inventory_message'])) {
    $message = $_SESSION['inventory_message'];
    unset($_SESSION['inventory_message']);
}

$formPrefix = $inline ? 'modules/' : '';
$dashboardUrl = $inline ? 'dashboard.php' : '../dashboard.php';
$posUrl = $inline ? 'dashboard.php?page=pos' : 'pos.php';
$reportsUrl = $inline ? 'dashboard.php?page=reports' : 'reports.php';
?>
<?php if (!$inline): ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>Products</title>
        <link rel="stylesheet" href="../assets/css/style.css">
    </head>

    <body>
    <?php endif; ?>

    <div class="module-header">
        <a href="<?php echo $dashboardUrl; ?>">Dashboard</a> |
        <a href="<?php echo $posUrl; ?>">POS</a> |
        <a href="<?php echo $reportsUrl; ?>">Reports</a>
    </div>

    <div class="module-content">
        <h1>Product Management</h1>

        <?php if ($message): ?>
            <div class="attendance-message">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h3><?php echo $editItem ? 'Edit Product' : 'Add Product'; ?></h3>
            <form action="<?php echo $formPrefix; ?>save_inventory.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?php echo $editItem ? (int) $editItem['id'] : ''; ?>">
                <div class="form-row">
                    <input type="text" name="item_name" placeholder="Item Name" required
                        value="<?php echo htmlspecialchars($editItem['item_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="text" name="barcode" placeholder="Barcode"
                        value="<?php echo htmlspecialchars($editItem['barcode'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-row">
                    <select name="category_id">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo (int) $category['id']; ?>" <?php echo isset($editItem['category_id']) && $editItem['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="supplier_id">
                        <option value="">Select Supplier</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo (int) $supplier['id']; ?>" <?php echo isset($editItem['supplier_id']) && $editItem['supplier_id'] == $supplier['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($supplier['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <input type="number" name="stock" placeholder="Stock Quantity" min="0" required
                        value="<?php echo htmlspecialchars($editItem['stock'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="number" step="0.01" name="price" placeholder="Unit Price" required
                        value="<?php echo htmlspecialchars($editItem['price'] ?? '0.00', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="form-row">
                    <input type="number" name="low_stock_threshold" placeholder="Low Stock Threshold" min="0"
                        value="<?php echo htmlspecialchars($editItem['low_stock_threshold'] ?? '5', ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="file" name="image">
                </div>
                <textarea name="description"
                    placeholder="Product description"><?php echo htmlspecialchars($editItem['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                <?php if (!empty($editItem['image_path'])): ?>
                    <div class="product-preview">
                        <img src="../<?php echo htmlspecialchars($editItem['image_path'], ENT_QUOTES, 'UTF-8'); ?>"
                            alt="Product image">
                    </div>
                <?php endif; ?>
                <button type="submit"><?php echo $editItem ? 'Update Product' : 'Add Product'; ?></button>
                <?php if ($editItem): ?>
                    <a class="button secondary" href="<?php echo $dashboardUrl; ?>?page=inventory">Cancel edit</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card">
            <h3>Categories</h3>
            <form action="<?php echo $formPrefix; ?>save_inventory.php" method="POST">
                <input type="hidden" name="action" value="save_category">
                <input type="text" name="category_name" placeholder="New category name" required>
                <button type="submit">Add Category</button>
            </form>
            <?php if (empty($categories)): ?>
                <p class="empty-state">No categories added yet.</p>
            <?php else: ?>
                <ul class="tag-list">
                    <?php foreach ($categories as $category): ?>
                        <li><?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Suppliers</h3>
            <form action="<?php echo $formPrefix; ?>save_inventory.php" method="POST">
                <input type="hidden" name="action" value="save_supplier">
                <input type="text" name="supplier_name" placeholder="Supplier name" required>
                <input type="text" name="supplier_contact" placeholder="Contact person">
                <input type="text" name="supplier_phone" placeholder="Phone">
                <input type="email" name="supplier_email" placeholder="Email">
                <button type="submit">Add Supplier</button>
            </form>
            <?php if (empty($suppliers)): ?>
                <p class="empty-state">No suppliers added yet.</p>
            <?php else: ?>
                <ul class="tag-list">
                    <?php foreach ($suppliers as $supplier): ?>
                        <li><?php echo htmlspecialchars($supplier['name'], ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Product List</h3>
            <?php if (empty($items)): ?>
                <p class="empty-state">No products available.</p>
            <?php else: ?>
                <table class="inventory-table">
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Supplier</th>
                        <th>Barcode</th>
                        <th>Stock</th>
                        <th>Price</th>
                        <th>Actions</th>
                    </tr>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php if (!empty($item['category_name'])): ?>
                                    <div class="item-meta">
                                        <?php echo htmlspecialchars($item['category_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($item['category_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($item['supplier_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($item['barcode'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo (int) $item['stock']; ?></td>
                            <td><?php echo number_format($item['price'], 2); ?></td>
                            <td>
                                <a class="button small"
                                    href="<?php echo $dashboardUrl; ?>?page=inventory&edit_id=<?php echo (int) $item['id']; ?>">Edit</a>
                                <form class="inline-form" action="<?php echo $formPrefix; ?>save_inventory.php" method="POST"
                                    onsubmit="return confirm('Delete this product?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                                    <button type="submit" class="button small danger">Delete</button>
                                </form>
                            </td>
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
