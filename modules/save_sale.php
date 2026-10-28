<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit();
}
require '../config/db.php';

if (!isset($_SESSION['pos_cart']) || !is_array($_SESSION['pos_cart'])) {
    $_SESSION['pos_cart'] = [];
}

function isAjaxRequest(): bool
{
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $format = $_POST['response_format'] ?? $_GET['response_format'] ?? '';

    return strtolower($requestedWith) === 'xmlhttprequest'
        || stripos($accept, 'application/json') !== false
        || $format === 'json';
}

function redirectToPos(): void
{
    header('Location: ../dashboard.php?page=pos');
    exit();
}

function setPosMessage(string $message): void
{
    $_SESSION['pos_message'] = $message;
}

function loadItem(PDO $conn, int $itemId): ?array
{
    $stmt = $conn->prepare('SELECT id, item_name, stock, price FROM inventory WHERE id = ?');
    $stmt->execute([$itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    return $item ?: null;
}

function ensureSalesColumns(PDO $conn): void
{
    $columnsStmt = $conn->query('SHOW COLUMNS FROM sales');
    $columns = array_column($columnsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

    if (!in_array('payment_method', $columns, true)) {
        $conn->exec("ALTER TABLE sales ADD COLUMN payment_method VARCHAR(50) NOT NULL DEFAULT 'Cash'");
    }
    if (!in_array('amount_paid', $columns, true)) {
        $conn->exec('ALTER TABLE sales ADD COLUMN amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00');
    }
    if (!in_array('change_due', $columns, true)) {
        $conn->exec('ALTER TABLE sales ADD COLUMN change_due DECIMAL(10,2) NOT NULL DEFAULT 0.00');
    }
    if (!in_array('inventory_item_id', $columns, true)) {
        $conn->exec('ALTER TABLE sales ADD COLUMN inventory_item_id INT NULL DEFAULT NULL');
    }
    if (!in_array('customer_name', $columns, true)) {
        $conn->exec("ALTER TABLE sales ADD COLUMN customer_name VARCHAR(255) NOT NULL DEFAULT ''");
    }
    if (!in_array('customer_contact', $columns, true)) {
        $conn->exec("ALTER TABLE sales ADD COLUMN customer_contact VARCHAR(255) NOT NULL DEFAULT ''");
    }
    if (!in_array('installation_required', $columns, true)) {
        $conn->exec('ALTER TABLE sales ADD COLUMN installation_required TINYINT(1) NOT NULL DEFAULT 0');
    }
    if (!in_array('installation_status', $columns, true)) {
        $conn->exec("ALTER TABLE sales ADD COLUMN installation_status VARCHAR(50) NOT NULL DEFAULT 'Not Scheduled'");
    }
    if (!in_array('installer_id', $columns, true)) {
        $conn->exec('ALTER TABLE sales ADD COLUMN installer_id INT NULL DEFAULT NULL');
    }
    if (!in_array('installation_date', $columns, true)) {
        $conn->exec('ALTER TABLE sales ADD COLUMN installation_date DATE NULL DEFAULT NULL');
    }
    if (!in_array('installation_notes', $columns, true)) {
        $conn->exec('ALTER TABLE sales ADD COLUMN installation_notes TEXT NULL');
    }
}

function buildCartState(): array
{
    $cart = $_SESSION['pos_cart'] ?? [];
    $items = [];
    $cartCount = 0;
    $cartTotal = 0.0;

    foreach ($cart as $cartItem) {
        $quantity = (int) ($cartItem['quantity'] ?? 0);
        $price = (float) ($cartItem['price'] ?? 0);
        $lineTotal = round($quantity * $price, 2);

        $cartCount += $quantity;
        $cartTotal += $lineTotal;

        $items[] = [
            'item_id' => (int) ($cartItem['item_id'] ?? 0),
            'product' => (string) ($cartItem['product'] ?? ''),
            'quantity' => $quantity,
            'price' => $price,
            'line_total' => $lineTotal,
        ];
    }

    return [
        'items' => $items,
        'cart_count' => $cartCount,
        'cart_total' => round($cartTotal, 2),
    ];
}

function respond(bool $ok, string $message, int $statusCode = 200, array $extra = []): void
{
    if (isAjaxRequest()) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode(array_merge([
            'ok' => $ok,
            'message' => $message,
            'cart' => buildCartState(),
        ], $extra));
        exit();
    }

    setPosMessage($message);
    redirectToPos();
}

try {
    $action = $_POST['action'] ?? 'checkout';

    if ($action === 'add_to_cart') {
    $itemId = $_POST['item_id'] ?? null;
    $qty = $_POST['quantity'] ?? null;

    if ($itemId === null || !is_numeric($itemId) || $qty === null || !is_numeric($qty)) {
        respond(false, 'Invalid item or quantity.', 422);
    }

    $itemId = (int) $itemId;
    $qty = (int) $qty;

    $item = loadItem($conn, $itemId);
    if (!$item) {
        respond(false, 'Selected item not found.', 404);
    }

    if ($qty < 1) {
        respond(false, 'Quantity must be at least 1.', 422);
    }

    $existingQty = (int) ($_SESSION['pos_cart'][$itemId]['quantity'] ?? 0);
    $newQty = $existingQty + $qty;
    if ($newQty > (int) $item['stock']) {
        respond(false, 'Cart quantity cannot exceed available stock.', 422);
    }

    $_SESSION['pos_cart'][$itemId] = [
        'item_id' => $itemId,
        'product' => $item['item_name'],
        'price' => (float) $item['price'],
        'quantity' => $newQty,
    ];

        respond(true, sprintf('Added %dx %s to cart.', $qty, $item['item_name']));
    }

    if ($action === 'remove_from_cart') {
    $itemId = $_POST['item_id'] ?? null;
    if ($itemId !== null && is_numeric($itemId)) {
        unset($_SESSION['pos_cart'][(int) $itemId]);
        respond(true, 'Item removed from cart.');
    }

        respond(false, 'Item not found in cart.', 404);
    }

    if ($action === 'update_cart_quantity') {
    $itemId = $_POST['item_id'] ?? null;
    $quantity = $_POST['quantity'] ?? null;

    if ($itemId === null || !is_numeric($itemId) || $quantity === null || !is_numeric($quantity)) {
        respond(false, 'Invalid cart update.', 422);
    }

    $itemId = (int) $itemId;
    $quantity = (int) $quantity;

    if (!isset($_SESSION['pos_cart'][$itemId])) {
        respond(false, 'Cart item not found.', 404);
    }

    if ($quantity <= 0) {
        unset($_SESSION['pos_cart'][$itemId]);
        respond(true, 'Item removed from cart.');
    }

    $item = loadItem($conn, $itemId);
    if (!$item) {
        unset($_SESSION['pos_cart'][$itemId]);
        respond(false, 'Selected item no longer exists.', 404);
    }

    if ($quantity > (int) $item['stock']) {
        respond(false, 'Cart quantity cannot exceed available stock.', 422);
    }

    $_SESSION['pos_cart'][$itemId]['quantity'] = $quantity;
    $_SESSION['pos_cart'][$itemId]['price'] = (float) $item['price'];
    $_SESSION['pos_cart'][$itemId]['product'] = $item['item_name'];

        respond(true, 'Cart quantity updated.');
    }

    if ($action === 'clear_cart') {
        $_SESSION['pos_cart'] = [];
        respond(true, 'Cart cleared.');
    }

    $paymentMethod = trim($_POST['payment_method'] ?? '');
    $amountPaid = $_POST['amount_paid'] ?? null;
    $customerName = trim($_POST['customer_name'] ?? '');
    $customerContact = trim($_POST['customer_contact'] ?? '');
    $installationRequired = !empty($_POST['installation_required']) ? 1 : 0;
    $allowedMethods = ['Cash', 'Card', 'GCash', 'Mobile Payment'];

    if (empty($_SESSION['pos_cart'])) {
        respond(false, 'Your cart is empty.', 422);
    }

    if (!in_array($paymentMethod, $allowedMethods, true)) {
        respond(false, 'Please select a valid payment method.', 422);
    }

    if ($amountPaid === null || !is_numeric($amountPaid)) {
        respond(false, 'Please enter a valid amount paid.', 422);
    }

    if ($customerName === '') {
        respond(false, 'Please enter the customer name.', 422);
    }

    $amountPaid = (float) $amountPaid;
    $cart = $_SESSION['pos_cart'];
    $cartItems = [];
    $grandTotal = 0.0;

    foreach ($cart as $cartItem) {
        $itemId = (int) ($cartItem['item_id'] ?? 0);
        $qty = (int) ($cartItem['quantity'] ?? 0);
        $item = loadItem($conn, $itemId);

        if (!$item) {
            respond(false, 'One of the cart items no longer exists.', 404);
        }

        if ($qty < 1 || $qty > (int) $item['stock']) {
            respond(false, 'One of the cart items has invalid quantity or insufficient stock.', 422);
        }

        $price = (float) $item['price'];
        $lineTotal = round($price * $qty, 2);
        $grandTotal += $lineTotal;

        $cartItems[] = [
            'item_id' => $itemId,
            'product' => $item['item_name'],
            'quantity' => $qty,
            'unit_price' => $price,
            'line_total' => $lineTotal,
        ];
    }

    $grandTotal = round($grandTotal, 2);
    if ($amountPaid < $grandTotal) {
        respond(false, 'Amount paid must be at least the cart total.', 422);
    }

    $changeDue = round($amountPaid - $grandTotal, 2);

    ensureSalesColumns($conn);

    $conn->beginTransaction();
    try {
        $saleStmt = $conn->prepare("INSERT INTO sales (
        product_name,
        quantity,
        price,
        payment_method,
        amount_paid,
        change_due,
        inventory_item_id,
        customer_name,
        customer_contact,
        installation_required,
        installation_status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $updateStmt = $conn->prepare('UPDATE inventory SET stock = stock - ? WHERE id = ?');

        foreach ($cartItems as $index => $cartItem) {
            $recordAmountPaid = $index === 0 ? $amountPaid : 0.00;
            $recordChangeDue = $index === 0 ? $changeDue : 0.00;
            $installationStatus = $installationRequired ? 'Pending Assignment' : 'Not Required';

            $saleStmt->execute([
                $cartItem['product'],
                $cartItem['quantity'],
                $cartItem['unit_price'],
                $paymentMethod,
                $recordAmountPaid,
                $recordChangeDue,
                $cartItem['item_id'],
                $customerName,
                $customerContact,
                $installationRequired,
                $installationStatus,
            ]);

            $updateStmt->execute([
                $cartItem['quantity'],
                $cartItem['item_id'],
            ]);
        }

        $conn->commit();
        $_SESSION['pos_cart'] = [];
        $_SESSION['pos_message'] = sprintf(
            'Checked out %d item(s) for %.2f. Paid %.2f, change %.2f.',
            count($cartItems),
            $grandTotal,
            $amountPaid,
            $changeDue
        );
        $_SESSION['pos_receipt'] = [
            'date' => date('Y-m-d H:i:s'),
            'items' => $cartItems,
            'customer_name' => $customerName,
            'customer_contact' => $customerContact,
            'installation_required' => $installationRequired,
            'total' => $grandTotal,
            'payment_method' => $paymentMethod,
            'amount_paid' => $amountPaid,
            'change_due' => $changeDue,
        ];
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }

    redirectToPos();
} catch (Throwable $e) {
    error_log('POS save_sale error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    respond(false, 'POS server error: ' . $e->getMessage(), 500);
}
?>
