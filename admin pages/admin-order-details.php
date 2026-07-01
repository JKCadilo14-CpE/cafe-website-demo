<?php
require_once __DIR__ . '/../components/app.php';

app_require_admin();

$statusOptions = ['Pending', 'Preparing', 'Out for Delivery', 'Completed', 'Cancelled'];
$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$order = null;
$items = [];
$subtotal = 0.00;
$message = '';
$messageType = '';
$currentPage = 'orders-details';

function status_class(string $status): string
{
    return strtolower(str_replace(' ', '-', $status));
}

function order_detail_format_date(?string $date): string
{
    $timestamp = strtotime((string) $date);

    return $timestamp ? date('M j, Y g:i A', $timestamp) : 'Not available';
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($orderId === false || $orderId === null || $orderId < 1) {
    $message = 'Please choose a valid order to view.';
    $messageType = 'error';
} else {
    try {
        $mysqli = app_db();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            app_require_csrf();

            $action = (string) ($_POST['action'] ?? '');
            $newStatus = $action === 'cancel' ? 'Cancelled' : trim((string) ($_POST['status'] ?? ''));

            if (in_array($newStatus, $statusOptions, true)) {
                $updateStatement = $mysqli->prepare(
                    'UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?'
                );
                $updateStatement->bind_param('si', $newStatus, $orderId);
                $updateStatement->execute();
                $updateStatement->close();

                header('Location: admin-order-details.php?id=' . $orderId . '&updated=1');
                exit();
            }

            $message = 'Please select a valid order status.';
            $messageType = 'error';
        }

        if (isset($_GET['updated'])) {
            $message = 'Order status updated successfully.';
            $messageType = 'success';
        }

        $orderStatement = $mysqli->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
        $orderStatement->bind_param('i', $orderId);
        $orderStatement->execute();
        $orderResult = $orderStatement->get_result();
        $order = $orderResult->fetch_assoc();
        $orderResult->free();
        $orderStatement->close();

        if ($order !== null) {
            $itemsStatement = $mysqli->prepare(
                'SELECT
                    oi.quantity,
                    oi.price,
                    p.name AS product_name,
                    p.image
                 FROM order_items oi
                 LEFT JOIN products p ON p.id = oi.product_id
                 WHERE oi.order_id = ?
                 ORDER BY oi.id ASC'
            );
            $itemsStatement->bind_param('i', $orderId);
            $itemsStatement->execute();
            $itemsResult = $itemsStatement->get_result();

            while ($row = $itemsResult->fetch_assoc()) {
                $row['quantity'] = (int) ($row['quantity'] ?? 0);
                $row['price'] = (float) ($row['price'] ?? 0);
                $subtotal += $row['quantity'] * $row['price'];
                $items[] = $row;
            }

            $itemsResult->free();
            $itemsStatement->close();
        } elseif ($message === '') {
            $message = 'Order not found.';
            $messageType = 'error';
        }

        $mysqli->close();
    } catch (mysqli_sql_exception $exception) {
        $message = 'Unable to load this order right now. Please check the database connection.';
        $messageType = 'error';
    }
}

$currentStatus = (string) ($order['status'] ?? 'Pending');
$deliveryFee = (float) ($order['delivery_fee'] ?? 0);
$totalAmount = (float) ($order['total_amount'] ?? ($subtotal + $deliveryFee));
$currentStatusIndex = array_search($currentStatus, $statusOptions, true);
$currentStatusIndex = $currentStatusIndex === false ? 0 : (int) $currentStatusIndex;
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Order Details | JKC Cafe Admin</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
        <link rel="stylesheet" href="css/admin-shell.css">
        <link rel="stylesheet" href="css/admin-style.css">
        <link rel="stylesheet" href="css/admin-orders.css">
    </head>
    <body>
        <div class="admin-layout">
            <?php require __DIR__ . '/components/admin-sidebar.php'; ?>

            <main class="admin-main">
                <?php require __DIR__ . '/components/admin-topbar.php'; ?>

                <section class="order-details-dashboard" aria-label="Admin Order Details">
                    <?php if ($message !== ''): ?>
                        <div class="admin-message <?php echo e($messageType); ?>">
                            <?php echo e($message); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($order !== null): ?>
                        <article class="order-details-card">
                            <div class="order-details-top">
                                <div class="order-details-heading">
                                    <p class="order-details-kicker">Order Details</p>
                                    <h1>Order #<?php echo (int) $order['id']; ?></h1>
                                    <p>Review the customer, fulfillment, items, and payment summary for this order.</p>
                                </div>
                                <div class="order-details-overview">
                                    <span class="order-status-badge <?php echo e(status_class($currentStatus)); ?>">
                                        <?php echo e($currentStatus); ?>
                                    </span>
                                    <strong>P<?php echo number_format($totalAmount, 2); ?></strong>
                                    <small><?php echo e(order_detail_format_date((string) ($order['created_at'] ?? ''))); ?></small>
                                    <a href="admin-orders-list.php" class="order-back-link">
                                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                                        Back to list
                                    </a>
                                </div>
                            </div>

                            <ol class="order-status-flow" aria-label="Order status progress">
                                <?php foreach ($statusOptions as $statusIndex => $statusOption): ?>
                                    <?php
                                        $statusClassName = status_class($statusOption);
                                        $stepState = $statusIndex < $currentStatusIndex ? 'is-complete' : '';
                                        $stepState = $statusIndex === $currentStatusIndex ? 'is-current' : $stepState;
                                    ?>
                                    <li class="<?php echo e(trim($stepState . ' ' . $statusClassName)); ?>">
                                        <span aria-hidden="true">
                                            <i class="fa-solid <?php echo $statusIndex <= $currentStatusIndex ? 'fa-check' : 'fa-circle'; ?>"></i>
                                        </span>
                                        <strong><?php echo e($statusOption); ?></strong>
                                    </li>
                                <?php endforeach; ?>
                            </ol>

                            <div class="order-info-grid">
                                <section class="order-info-card">
                                    <div class="order-section-title">
                                        <i class="fa-regular fa-circle-user" aria-hidden="true"></i>
                                        <h2>Customer Information</h2>
                                    </div>
                                    <dl class="order-detail-list">
                                        <div>
                                            <dt>Customer name</dt>
                                            <dd><?php echo e((string) ($order['customer_name'] ?? 'Not provided')); ?></dd>
                                        </div>
                                        <div>
                                            <dt>Phone number</dt>
                                            <dd><?php echo e((string) ($order['phone_number'] ?? 'Not provided')); ?></dd>
                                        </div>
                                    </dl>
                                </section>

                                <section class="order-info-card">
                                    <div class="order-section-title">
                                        <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                                        <h2>Delivery Information</h2>
                                    </div>
                                    <dl class="order-detail-list">
                                        <div>
                                            <dt>Delivery address</dt>
                                            <dd><?php echo e((string) ($order['delivery_address'] ?? 'Not provided')); ?></dd>
                                        </div>
                                        <div>
                                            <dt>Delivery partner</dt>
                                            <dd><?php echo e((string) ($order['delivery_partner'] ?? 'Not provided')); ?></dd>
                                        </div>
                                        <div>
                                            <dt>Payment method</dt>
                                            <dd><?php echo e((string) ($order['payment_method'] ?? 'Not provided')); ?></dd>
                                        </div>
                                        <div>
                                            <dt>Order date</dt>
                                            <dd><?php echo e(order_detail_format_date((string) ($order['created_at'] ?? ''))); ?></dd>
                                        </div>
                                    </dl>
                                </section>
                            </div>

                            <section class="order-section-card">
                                <div class="order-section-title">
                                    <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i>
                                    <h2>Ordered Items</h2>
                                </div>
                                <div class="order-items-wrap">
                                    <table class="order-items-table">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Quantity</th>
                                                <th>Price</th>
                                                <th>Line Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($items)): ?>
                                                <tr>
                                                    <td colspan="4" class="empty-state">No items found for this order.</td>
                                                </tr>
                                            <?php endif; ?>

                                            <?php foreach ($items as $item): ?>
                                                <?php
                                                    $quantity = (int) $item['quantity'];
                                                    $price = (float) $item['price'];
                                                    $lineTotal = $quantity * $price;
                                                    $image = trim((string) ($item['image'] ?? ''));
                                                    $imageSrc = app_admin_asset_src($image);
                                                ?>
                                                <tr>
                                                    <td>
                                                        <div class="order-product-cell">
                                                            <div class="order-product-thumb">
                                                                <?php if ($imageSrc !== ''): ?>
                                                                    <img src="<?php echo e($imageSrc); ?>" alt="<?php echo e((string) ($item['product_name'] ?? 'Product')); ?>">
                                                                <?php else: ?>
                                                                    <i class="fa-solid fa-mug-hot" aria-hidden="true"></i>
                                                                <?php endif; ?>
                                                            </div>
                                                            <span><?php echo e((string) ($item['product_name'] ?? 'Deleted product')); ?></span>
                                                        </div>
                                                    </td>
                                                    <td><?php echo $quantity; ?></td>
                                                    <td>P<?php echo number_format($price, 2); ?></td>
                                                    <td>P<?php echo number_format($lineTotal, 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <section class="order-summary-card">
                                <div class="order-section-title">
                                    <i class="fa-solid fa-receipt" aria-hidden="true"></i>
                                    <h2>Order Summary</h2>
                                </div>
                                <dl class="order-summary-list">
                                    <div>
                                        <dt>Subtotal</dt>
                                        <dd>P<?php echo number_format($subtotal, 2); ?></dd>
                                    </div>
                                    <div>
                                        <dt>Delivery fee</dt>
                                        <dd>P<?php echo number_format($deliveryFee, 2); ?></dd>
                                    </div>
                                    <div class="order-summary-total">
                                        <dt>Total amount</dt>
                                        <dd>P<?php echo number_format($totalAmount, 2); ?></dd>
                                    </div>
                                </dl>
                            </section>

                            <section class="order-actions-card">
                                <div class="order-section-title">
                                    <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                                    <h2>Actions</h2>
                                </div>
                                <form class="order-actions-form" action="admin-order-details.php?id=<?php echo (int) $order['id']; ?>" method="POST">
                                    <?php echo app_csrf_field(); ?>
                                    <select class="order-status-select" name="status" aria-label="Update order status">
                                        <?php foreach ($statusOptions as $statusOption): ?>
                                            <option value="<?php echo e($statusOption); ?>" <?php echo $currentStatus === $statusOption ? 'selected' : ''; ?>>
                                                <?php echo e($statusOption); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="action" value="update" class="order-action-button primary">
                                        <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                                        Update Status
                                    </button>
                                    <button type="submit" name="action" value="cancel" class="order-action-button danger" onclick="return confirm('Cancel order #<?php echo (int) $order['id']; ?>? This will update the customer order status to Cancelled.');">
                                        <i class="fa-solid fa-ban" aria-hidden="true"></i>
                                        Cancel Order
                                    </button>
                                    <a href="admin-orders-list.php" class="order-action-button secondary">
                                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                                        Back to Orders List
                                    </a>
                                </form>
                            </section>
                        </article>
                    <?php elseif ($orderId !== false && $orderId !== null): ?>
                        <div class="order-empty-card">
                            <i class="fa-regular fa-rectangle-list" aria-hidden="true"></i>
                            <h1>Order not found</h1>
                            <p>The order may have been removed or the link may be incorrect.</p>
                            <a href="admin-orders-list.php" class="order-action-button primary">Back to Orders List</a>
                        </div>
                    <?php else: ?>
                        <div class="order-empty-card">
                            <i class="fa-regular fa-rectangle-list" aria-hidden="true"></i>
                            <h1>Choose an order</h1>
                            <p>Select an order from the list to review customer details, items, and fulfillment status.</p>
                            <a href="admin-orders-list.php" class="order-action-button primary">Open Orders List</a>
                        </div>
                    <?php endif; ?>
                </section>
            </main>
        </div>

        <script src="js/admin_script.js"></script>
    </body>
</html>
