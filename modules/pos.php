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

$itemsStmt = $conn->query('SELECT * FROM inventory ORDER BY item_name ASC');
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
$cart = $_SESSION['pos_cart'] ?? [];
$cartCount = 0;
$cartTotal = 0.0;
$cartIndex = [];

foreach ($cart as $cartItem) {
    $itemId = (int) ($cartItem['item_id'] ?? 0);
    $quantity = (int) ($cartItem['quantity'] ?? 0);
    $price = (float) ($cartItem['price'] ?? 0);

    $cartCount += $quantity;
    $cartTotal += $quantity * $price;
    $cartIndex[$itemId] = $quantity;
}

$cartTotal = round($cartTotal, 2);
$message = '';
$receipt = null;
if (!empty($_SESSION['pos_message'])) {
    $message = $_SESSION['pos_message'];
    unset($_SESSION['pos_message']);
}
if (!empty($_SESSION['pos_receipt'])) {
    $receipt = $_SESSION['pos_receipt'];
    unset($_SESSION['pos_receipt']);
}

$scriptDir = str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? ''));
$scriptDir = $scriptDir === '/' ? '' : rtrim($scriptDir, '/');
$saleAction = $inline ? $scriptDir . '/modules/save_sale.php' : $scriptDir . '/save_sale.php';

$formPrefix = $inline ? 'modules/' : '';
$dashboardUrl = $inline ? 'dashboard.php' : '../dashboard.php';
$inventoryUrl = $inline ? 'dashboard.php?page=inventory' : 'inventory.php';
$reportsUrl = $inline ? 'dashboard.php?page=reports' : 'reports.php';
?>
<?php if (!$inline): ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>Point of Sale</title>
        <link rel="stylesheet" href="../assets/css/style.css">
    </head>

    <body>
    <?php endif; ?>

    <div class="module-header">
        <a href="<?php echo $dashboardUrl; ?>">Dashboard</a> |
        <a href="<?php echo $inventoryUrl; ?>">Inventory</a> |
        <a href="<?php echo $reportsUrl; ?>">Reports</a>
    </div>

    <div class="module-content">
        <h1>Point of Sale</h1>

        <?php if ($message): ?>
            <div id="pos-message" class="attendance-message">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php else: ?>
            <div id="pos-message" class="attendance-message pos-hidden"></div>
        <?php endif; ?>

        <?php if ($receipt): ?>
            <div class="card receipt-card">
                <div class="receipt-header">
                    <h3>HIGH END</h3>
                    <p class="receipt-subtitle">Sales Receipt</p>
                    <p class="receipt-address">123 High End Ave, Makati City</p>
                </div>
                <div class="receipt-details">
                    <p><span>Date:</span> <?php echo htmlspecialchars($receipt['date'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php if (!empty($receipt['customer_name'])): ?>
                        <p><span>Customer:</span> <?php echo htmlspecialchars($receipt['customer_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($receipt['customer_contact'])): ?>
                        <p><span>Contact:</span> <?php echo htmlspecialchars($receipt['customer_contact'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($receipt['items']) && is_array($receipt['items'])): ?>
                        <div class="receipt-items">
                            <?php foreach ($receipt['items'] as $receiptItem): ?>
                                <div class="receipt-item-row">
                                    <span>
                                        <?php echo (int) $receiptItem['quantity']; ?> x
                                        <?php echo htmlspecialchars($receiptItem['product'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                    <strong>PHP <?php echo number_format((float) $receiptItem['line_total'], 2); ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p><span>Product:</span> <?php echo htmlspecialchars($receipt['product'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p><span>Qty:</span> <?php echo (int) $receipt['quantity']; ?></p>
                        <p><span>Unit Price:</span> PHP <?php echo number_format((float) $receipt['unit_price'], 2); ?></p>
                    <?php endif; ?>
                    <p class="receipt-total"><span>Total:</span> PHP <?php echo number_format((float) $receipt['total'], 2); ?></p>
                    <p><span>Installation:</span>
                        <?php echo !empty($receipt['installation_required']) ? 'Required' : 'Not Required'; ?></p>
                    <p><span>Payment:</span> <?php echo htmlspecialchars($receipt['payment_method'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><span>Paid:</span> PHP <?php echo number_format((float) $receipt['amount_paid'], 2); ?></p>
                    <p><span>Change:</span> PHP <?php echo number_format((float) $receipt['change_due'], 2); ?></p>
                </div>
                <p class="receipt-footer">Thank you for your purchase! Visit again.</p>
                <button type="button" id="print-receipt-btn">Print Receipt</button>
            </div>
        <?php endif; ?>

        <div class="card">
            <h3>Add Item to Cart</h3>
            <?php if (empty($items)): ?>
                <p class="empty-state">No inventory items available. Add stock first.</p>
            <?php else: ?>
                <form id="pos-add-form" action="<?php echo htmlspecialchars($saleAction, ENT_QUOTES, 'UTF-8'); ?>" method="POST">
                    <input type="hidden" name="action" value="add_to_cart">
                    <input type="hidden" name="response_format" value="json">
                    <select id="pos-item" name="item_id" required>
                        <option value="" data-price="0">Select Item</option>
                        <?php foreach ($items as $item): ?>
                            <option value="<?php echo (int) $item['id']; ?>"
                                data-price="<?php echo htmlspecialchars($item['price'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8'); ?>
                                (<?php echo (int) $item['stock']; ?> available)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input id="pos-quantity" type="number" name="quantity" placeholder="Quantity" min="1" value="1"
                        required>
                    <div class="pos-summary">
                        <p>Item Total: <strong id="pos-total">PHP 0.00</strong></p>
                        <p>Items in Cart: <strong id="pos-cart-preview"><?php echo (int) $cartCount; ?></strong></p>
                    </div>
                    <button type="submit" id="pos-add-button">Add to Cart</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="card" id="pos-cart-card">
            <div class="pos-cart-header">
                <h3>Cart</h3>
                <form class="inline-form <?php echo empty($cart) ? 'pos-hidden' : ''; ?>" id="pos-clear-cart-form"
                    action="<?php echo htmlspecialchars($saleAction, ENT_QUOTES, 'UTF-8'); ?>" method="POST">
                    <input type="hidden" name="response_format" value="json">
                    <input type="hidden" name="action" value="clear_cart">
                    <button type="submit" class="button small secondary">Clear Cart</button>
                </form>
            </div>

            <p id="pos-cart-empty" class="empty-state <?php echo empty($cart) ? '' : 'pos-hidden'; ?>">Your cart is empty.
                Add items for one multi-item checkout.</p>

            <div id="pos-cart-content" class="<?php echo empty($cart) ? 'pos-hidden' : ''; ?>">
                <table class="inventory-table">
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>Line Total</th>
                        <th>Action</th>
                    </tr>
                    <?php foreach ($cart as $cartItem): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($cartItem['product'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <form class="cart-qty-form" action="<?php echo htmlspecialchars($saleAction, ENT_QUOTES, 'UTF-8'); ?>" method="POST">
                                    <input type="hidden" name="response_format" value="json">
                                    <input type="hidden" name="action" value="update_cart_quantity">
                                    <input type="hidden" name="item_id" value="<?php echo (int) $cartItem['item_id']; ?>">
                                    <button type="submit" class="button small secondary cart-qty-btn" name="quantity"
                                        value="<?php echo max(0, (int) $cartItem['quantity'] - 1); ?>">-</button>
                                    <input class="cart-qty-input" type="number" name="quantity"
                                        value="<?php echo (int) $cartItem['quantity']; ?>" min="1">
                                    <button type="submit" class="button small secondary cart-qty-btn" name="quantity"
                                        value="<?php echo (int) $cartItem['quantity'] + 1; ?>">+</button>
                                    <button type="submit" class="button small" title="Update quantity">Update</button>
                                </form>
                            </td>
                            <td>PHP <?php echo number_format((float) $cartItem['price'], 2); ?></td>
                            <td>PHP <?php echo number_format((float) $cartItem['price'] * (int) $cartItem['quantity'], 2); ?>
                            </td>
                            <td>
                                <form class="inline-form" action="<?php echo htmlspecialchars($saleAction, ENT_QUOTES, 'UTF-8'); ?>" method="POST">
                                    <input type="hidden" name="response_format" value="json">
                                    <input type="hidden" name="action" value="remove_from_cart">
                                    <input type="hidden" name="item_id" value="<?php echo (int) $cartItem['item_id']; ?>">
                                    <button type="submit" class="button small danger">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <form id="pos-sale-form" action="<?php echo htmlspecialchars($saleAction, ENT_QUOTES, 'UTF-8'); ?>" method="POST">
                    <input type="hidden" name="action" value="checkout">
                    <div class="form-row">
                        <input type="text" name="customer_name" placeholder="Customer Name" required>
                        <input type="text" name="customer_contact" placeholder="Customer Contact">
                    </div>
                    <div class="form-row">
                        <select name="payment_method" required>
                            <option value="">Select Payment Method</option>
                            <option value="Cash">Cash</option>
                            <option value="Card">Card</option>
                            <option value="GCash">GCash</option>
                            <option value="Mobile Payment">Mobile Payment</option>
                        </select>
                        <input id="pos-amount-paid" type="number" name="amount_paid" placeholder="Amount Paid" min="0.01"
                            step="0.01" required>
                    </div>
                    <label class="pos-checkbox">
                        <input type="checkbox" name="installation_required" value="1">
                        <span>This sale requires installer scheduling</span>
                    </label>
                    <div class="pos-summary">
                        <p>Cart Total: <strong id="pos-cart-total">PHP <?php echo number_format($cartTotal, 2); ?></strong></p>
                        <p>Change: <strong id="pos-change">PHP 0.00</strong></p>
                    </div>
                    <button type="submit" id="pos-checkout-button">Checkout Cart</button>
                </form>
            </div>
        </div>

        <div class="card">
            <h3>Inventory</h3>
            <?php if (empty($items)): ?>
                <p class="empty-state">No inventory items available.</p>
            <?php else: ?>
                <table>
                    <tr>
                        <th>Item</th>
                        <th>Stock</th>
                        <th>Price</th>
                        <th>Cart Qty</th>
                    </tr>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo (int) $item['stock']; ?></td>
                            <td>PHP <?php echo number_format((float) $item['price'], 2); ?></td>
                            <td class="inventory-cart-qty" data-item-id="<?php echo (int) $item['id']; ?>">
                                <?php echo (int) ($cartIndex[(int) $item['id']] ?? 0); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .receipt-card {
            max-width: 520px;
            margin-bottom: 1rem;
            padding: 1rem;
            border: 1px dashed #999;
            border-radius: 8px;
            background: #fafafa;
            font-family: "Courier New", Courier, monospace;
            line-height: 1.4;
        }

        .receipt-header {
            text-align: center;
            margin-bottom: 0.75rem;
        }

        .receipt-header h3 {
            margin: 0;
            letter-spacing: 1px;
            font-size: 1.2rem;
        }

        .receipt-subtitle {
            margin: 0.25rem 0 0;
            font-size: 0.9rem;
            color: #555;
        }

        .receipt-address {
            margin: 0.25rem 0 0;
            font-size: 0.85rem;
            color: #666;
        }

        .receipt-details p {
            display: flex;
            justify-content: space-between;
            margin: 0.3rem 0;
        }

        .receipt-details p span {
            color: #222;
        }

        .receipt-items {
            border-top: 1px dashed #999;
            border-bottom: 1px dashed #999;
            margin: 0.6rem 0;
            padding: 0.5rem 0;
        }

        .receipt-item-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            margin: 0.35rem 0;
        }

        .receipt-total {
            font-weight: bold;
            border-top: 1px dashed #999;
            padding-top: 0.4rem;
            margin-top: 0.5rem;
        }

        .receipt-footer {
            margin-top: 1rem;
            font-size: 0.95rem;
            color: #333;
            text-align: center;
        }

        .receipt-card button {
            display: block;
            width: 100%;
            margin-top: 0.8rem;
            padding: 0.75rem;
            background: #2c3e50;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .receipt-card button:hover {
            background: #1a242f;
        }

        .pos-cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .pos-summary {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .pos-summary p {
            margin: 0;
        }

        .pos-checkbox {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 1rem;
            color: #333;
        }

        .pos-checkbox input {
            width: auto;
            margin: 0;
        }

        .pos-hidden {
            display: none !important;
        }

        .cart-qty-form {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            flex-wrap: wrap;
        }

        .cart-qty-input {
            width: 72px;
            margin: 0;
            padding: 0.5rem 0.65rem;
            text-align: center;
        }

        .cart-qty-btn {
            min-width: 38px;
            padding: 0.55rem 0.7rem;
        }

        .pos-button-busy {
            opacity: 0.7;
            pointer-events: none;
        }

        @media print {
            html,
            body {
                background: #fff;
                margin: 0;
                padding: 0;
            }

            body * {
                visibility: hidden;
            }

            .receipt-card,
            .receipt-card * {
                visibility: visible;
            }

            .receipt-card {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 1rem;
                box-shadow: none;
                border: none;
                font-size: 12px;
                background: #fff;
            }

            .receipt-card button {
                display: none;
            }
        }
    </style>
    <script>
        (function () {
            const addForm = document.getElementById('pos-add-form');
            const clearCartForm = document.getElementById('pos-clear-cart-form');
            const itemSelect = document.getElementById('pos-item');
            const quantityInput = document.getElementById('pos-quantity');
            const amountPaidInput = document.getElementById('pos-amount-paid');
            const totalLabel = document.getElementById('pos-total');
            const messageBox = document.getElementById('pos-message');
            const cartPreviewLabel = document.getElementById('pos-cart-preview');
            const cartCard = document.getElementById('pos-cart-card');
            const cartContent = document.getElementById('pos-cart-content');
            const cartEmpty = document.getElementById('pos-cart-empty');
            const cartTotalLabel = document.getElementById('pos-cart-total');
            const changeLabel = document.getElementById('pos-change');
            const printButton = document.getElementById('print-receipt-btn');
            const saleFormAction = <?php echo json_encode($saleAction); ?>;
            let currentCartTotal = <?php echo json_encode($cartTotal); ?>;
            let currentCartCount = <?php echo json_encode($cartCount); ?>;

            function formatCurrency(value) {
                return 'PHP ' + Number(value).toFixed(2);
            }

            function escapeHtml(value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function showMessage(message, isError) {
                if (!messageBox) {
                    return;
                }

                messageBox.textContent = message || '';
                messageBox.classList.toggle('pos-hidden', !message);
                messageBox.style.background = isError ? '#fff0f0' : '';
                messageBox.style.borderColor = isError ? '#f1c7c7' : '';
                messageBox.style.color = isError ? '#8a2424' : '';
            }

            function setButtonBusy(button, busyText) {
                if (!button) {
                    return function () { };
                }

                const originalText = button.textContent;
                button.disabled = true;
                button.classList.add('pos-button-busy');
                button.textContent = busyText;

                return function () {
                    button.disabled = false;
                    button.classList.remove('pos-button-busy');
                    button.textContent = originalText;
                };
            }

            function buildQtyForm(item) {
                const previousQty = Math.max(0, Number(item.quantity) - 1);
                const nextQty = Number(item.quantity) + 1;

                return `
                    <form class="cart-qty-form" action="${escapeHtml(saleFormAction)}" method="POST">
                        <input type="hidden" name="response_format" value="json">
                        <input type="hidden" name="action" value="update_cart_quantity">
                        <input type="hidden" name="item_id" value="${Number(item.item_id)}">
                        <button type="submit" class="button small secondary cart-qty-btn" name="quantity" value="${previousQty}">-</button>
                        <input class="cart-qty-input" type="number" name="quantity" value="${Number(item.quantity)}" min="1">
                        <button type="submit" class="button small secondary cart-qty-btn" name="quantity" value="${nextQty}">+</button>
                        <button type="submit" class="button small" title="Update quantity">Update</button>
                    </form>
                `;
            }

            function buildRemoveForm(item) {
                return `
                    <form class="inline-form" action="${escapeHtml(saleFormAction)}" method="POST">
                        <input type="hidden" name="response_format" value="json">
                        <input type="hidden" name="action" value="remove_from_cart">
                        <input type="hidden" name="item_id" value="${Number(item.item_id)}">
                        <button type="submit" class="button small danger">Remove</button>
                    </form>
                `;
            }

            function renderCart(cartState) {
                currentCartTotal = Number(cartState.cart_total || 0);
                currentCartCount = Number(cartState.cart_count || 0);

                if (cartPreviewLabel) {
                    cartPreviewLabel.textContent = String(currentCartCount);
                }

                document.querySelectorAll('.inventory-cart-qty').forEach(function (cell) {
                    cell.textContent = '0';
                });

                (cartState.items || []).forEach(function (item) {
                    const stockCell = document.querySelector('.inventory-cart-qty[data-item-id="' + Number(item.item_id) + '"]');
                    if (stockCell) {
                        stockCell.textContent = String(Number(item.quantity));
                    }
                });

                if (!cartContent || !cartEmpty || !clearCartForm) {
                    updateCheckoutSummary();
                    return;
                }

                if (!cartState.items || cartState.items.length === 0) {
                    cartContent.classList.add('pos-hidden');
                    cartEmpty.classList.remove('pos-hidden');
                    clearCartForm.classList.add('pos-hidden');
                    updateCheckoutSummary();
                    return;
                }

                cartEmpty.classList.add('pos-hidden');
                cartContent.classList.remove('pos-hidden');
                clearCartForm.classList.remove('pos-hidden');

                const rows = cartState.items.map(function (item) {
                    return `
                        <tr>
                            <td>${escapeHtml(item.product)}</td>
                            <td>${buildQtyForm(item)}</td>
                            <td>${formatCurrency(item.price)}</td>
                            <td>${formatCurrency(item.line_total)}</td>
                            <td>${buildRemoveForm(item)}</td>
                        </tr>
                    `;
                }).join('');

                cartContent.innerHTML = `
                    <table class="inventory-table">
                        <tr>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Line Total</th>
                            <th>Action</th>
                        </tr>
                        ${rows}
                    </table>
                    <form id="pos-sale-form" action="${escapeHtml(saleFormAction)}" method="POST">
                        <input type="hidden" name="action" value="checkout">
                        <div class="form-row">
                            <input type="text" name="customer_name" placeholder="Customer Name" required>
                            <input type="text" name="customer_contact" placeholder="Customer Contact">
                        </div>
                        <div class="form-row">
                            <select name="payment_method" required>
                                <option value="">Select Payment Method</option>
                                <option value="Cash">Cash</option>
                                <option value="Card">Card</option>
                                <option value="GCash">GCash</option>
                                <option value="Mobile Payment">Mobile Payment</option>
                            </select>
                            <input id="pos-amount-paid" type="number" name="amount_paid" placeholder="Amount Paid" min="0.01" step="0.01" required>
                        </div>
                        <label class="pos-checkbox">
                            <input type="checkbox" name="installation_required" value="1">
                            <span>This sale requires installer scheduling</span>
                        </label>
                        <div class="pos-summary">
                            <p>Cart Total: <strong id="pos-cart-total">${formatCurrency(currentCartTotal)}</strong></p>
                            <p>Change: <strong id="pos-change">PHP 0.00</strong></p>
                        </div>
                        <button type="submit">Checkout Cart</button>
                    </form>
                `;
                updateCheckoutSummary();
            }

            async function submitPosForm(form, submitter) {
                const formData = new FormData(form);
                const formAction = form.getAttribute('action') || form.action;
                if (submitter && submitter.name) {
                    formData.set(submitter.name, submitter.value);
                }
                formData.set('response_format', 'json');

                const response = await fetch(formAction, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                const contentType = response.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    const responseText = await response.text();
                    const compactText = responseText.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                    const fallbackMessage = compactText
                        ? 'POS server error: ' + compactText.slice(0, 180)
                        : 'The POS request returned an unexpected response. Please refresh and try again.';
                    showMessage(fallbackMessage, true);
                    throw new Error('Unexpected response type.');
                }

                const data = await response.json();
                showMessage(data.message || 'Request completed.', !response.ok || !data.ok);

                if (data.cart) {
                    renderCart(data.cart);
                }

                if (!response.ok || !data.ok) {
                    throw new Error(data.message || 'Request failed.');
                }

                return data;
            }

            function getSelectedPrice() {
                if (!itemSelect) {
                    return 0;
                }

                const option = itemSelect.selectedOptions[0];
                return option ? parseFloat(option.dataset.price || '0') : 0;
            }

            function updateAddSummary() {
                if (!itemSelect || !quantityInput || !totalLabel) {
                    return;
                }

                const price = getSelectedPrice();
                const quantity = Math.max(1, Number(quantityInput.value) || 0);
                totalLabel.textContent = formatCurrency(price * quantity);

                if (cartPreviewLabel) {
                    cartPreviewLabel.textContent = String(currentCartCount);
                }
            }

            function updateCheckoutSummary() {
                const liveCartTotalLabel = document.getElementById('pos-cart-total');
                if (liveCartTotalLabel) {
                    liveCartTotalLabel.textContent = formatCurrency(currentCartTotal);
                }

                const liveAmountPaidInput = document.getElementById('pos-amount-paid');
                const liveChangeLabel = document.getElementById('pos-change');
                if (!liveAmountPaidInput || !liveChangeLabel) {
                    return;
                }

                const amountPaid = Number(liveAmountPaidInput.value) || 0;
                const change = amountPaid - currentCartTotal;
                liveChangeLabel.textContent = formatCurrency(Math.max(change, 0));
            }

            if (itemSelect) {
                itemSelect.addEventListener('change', updateAddSummary);
            }

            if (quantityInput) {
                quantityInput.addEventListener('input', updateAddSummary);
            }

            document.addEventListener('input', function (event) {
                if (event.target && event.target.id === 'pos-amount-paid') {
                    updateCheckoutSummary();
                }
            });

            if (addForm) {
                addForm.addEventListener('submit', async function (event) {
                    event.preventDefault();
                    const submitter = event.submitter || null;
                    const releaseButton = setButtonBusy(submitter || document.getElementById('pos-add-button'), 'Adding...');

                    try {
                        await submitPosForm(addForm, submitter);
                        addForm.reset();
                        if (quantityInput) {
                            quantityInput.value = '1';
                        }
                        updateAddSummary();
                    } catch (error) {
                    } finally {
                        releaseButton();
                    }
                });
            }

            if (clearCartForm) {
                clearCartForm.addEventListener('submit', async function (event) {
                    event.preventDefault();
                    try {
                        await submitPosForm(clearCartForm, event.submitter || null);
                    } catch (error) {
                    }
                });
            }

            if (cartCard) {
                cartCard.addEventListener('submit', async function (event) {
                    const form = event.target;
                    if (!(form instanceof HTMLFormElement)) {
                        return;
                    }

                    if (form.id === 'pos-sale-form') {
                        return;
                    }

                    event.preventDefault();
                    try {
                        await submitPosForm(form, event.submitter || null);
                    } catch (error) {
                    }
                });
            }

            document.addEventListener('submit', function (event) {
                const form = event.target;
                if (!(form instanceof HTMLFormElement) || form.id !== 'pos-sale-form') {
                    return;
                }

                const checkoutButton = event.submitter || form.querySelector('button[type="submit"]');
                const customerNameField = form.querySelector('input[name="customer_name"]');
                const amountPaidField = form.querySelector('input[name="amount_paid"]');

                if (!customerNameField || !customerNameField.value.trim()) {
                    event.preventDefault();
                    showMessage('Please enter the customer name before checkout.', true);
                    if (customerNameField) {
                        customerNameField.focus();
                    }
                    return;
                }

                if (!amountPaidField || !amountPaidField.value) {
                    event.preventDefault();
                    showMessage('Please enter the payment amount before checkout.', true);
                    if (amountPaidField) {
                        amountPaidField.focus();
                    }
                    return;
                }

                setButtonBusy(checkoutButton, 'Processing...');
            });

            if (printButton) {
                printButton.addEventListener('click', function () {
                    window.print();
                });
            }

            updateAddSummary();
            updateCheckoutSummary();
        }());
    </script>
    <?php if (!$inline): ?>
    </body>

    </html>
<?php endif; ?>
