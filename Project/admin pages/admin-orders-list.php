<?php
require_once __DIR__ . '/../components/app.php';

app_require_admin();

$orders = [];
$message = '';
$messageType = '';
$currentPage = 'orders-list';
$orderSummary = [
    'total' => 0,
    'active' => 0,
    'completed' => 0,
    'cancelled' => 0,
];

function order_status_class(string $status): string
{
    return strtolower(str_replace(' ', '-', $status));
}

function order_format_date(?string $date): string
{
    $timestamp = strtotime((string) $date);

    return $timestamp ? date('M j, Y g:i A', $timestamp) : 'Not available';
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = app_db();

    $result = $mysqli->query(
        'SELECT
            id,
            customer_name,
            status,
            total_amount,
            created_at
         FROM orders
         ORDER BY created_at DESC, id DESC'
    );

    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }

    foreach ($orders as $order) {
        $status = (string) ($order['status'] ?? 'Pending');
        $normalizedStatus = order_status_class($status);
        $orderSummary['total']++;

        if (in_array($normalizedStatus, ['pending', 'preparing', 'out-for-delivery'], true)) {
            $orderSummary['active']++;
        } elseif ($normalizedStatus === 'completed') {
            $orderSummary['completed']++;
        } elseif ($normalizedStatus === 'cancelled') {
            $orderSummary['cancelled']++;
        }
    }

    $result->free();
    $mysqli->close();
} catch (mysqli_sql_exception $exception) {
    $message = 'Unable to load orders right now. Please check the database connection.';
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Orders | JKC Cafe Admin</title>
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

                <section class="orders-list-dashboard" aria-label="Admin Orders List">
                    <div class="orders-list-panel">
                        <div class="orders-list-header">
                            <div>
                                <p class="orders-list-eyebrow">Service counter</p>
                                <h1 class="orders-list-title">
                                    <i class="fa-solid fa-receipt" aria-hidden="true"></i>
                                    Orders
                                </h1>
                                <p class="orders-list-subtitle">Track incoming orders, customer details, and fulfillment status from one clean queue.</p>
                            </div>
                            <div class="orders-summary-chips" aria-label="Order summary">
                                <span>
                                    <strong><?php echo number_format($orderSummary['total']); ?></strong>
                                    Total
                                </span>
                                <span>
                                    <strong><?php echo number_format($orderSummary['active']); ?></strong>
                                    Active
                                </span>
                                <span>
                                    <strong><?php echo number_format($orderSummary['completed']); ?></strong>
                                    Completed
                                </span>
                                <span>
                                    <strong><?php echo number_format($orderSummary['cancelled']); ?></strong>
                                    Cancelled
                                </span>
                            </div>
                        </div>

                        <?php if ($message !== ''): ?>
                            <div class="admin-message <?php echo e($messageType); ?>">
                                <?php echo e($message); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($orders)): ?>
                            <div class="orders-empty-state">
                                <i class="fa-regular fa-rectangle-list" aria-hidden="true"></i>
                                <h2>No orders yet.</h2>
                                <p>New customer orders will appear here as soon as checkout is completed.</p>
                            </div>
                        <?php else: ?>
                            <div class="orders-card-grid">
                                <?php foreach ($orders as $order): ?>
                                    <?php
                                        $orderId = (int) $order['id'];
                                        $status = (string) ($order['status'] ?? 'Pending');
                                    ?>
                                    <a class="orders-list-card" href="admin-order-details.php?id=<?php echo $orderId; ?>" aria-label="View details for order <?php echo $orderId; ?>">
                                        <div class="orders-card-top">
                                            <div>
                                                <span class="orders-card-kicker">Order ticket</span>
                                                <h2>#<?php echo $orderId; ?></h2>
                                            </div>
                                            <span class="orders-status-badge <?php echo e(order_status_class($status)); ?>">
                                                <?php echo e($status); ?>
                                            </span>
                                        </div>

                                        <div class="orders-card-body">
                                            <p>
                                                <span>Customer:</span>
                                                <?php echo e((string) ($order['customer_name'] ?? 'Not provided')); ?>
                                            </p>
                                            <p>
                                                <span>Total:</span>
                                                ₱<?php echo number_format((float) ($order['total_amount'] ?? 0), 2); ?>
                                            </p>
                                            <p>
                                                <span>Date:</span>
                                                <?php echo e(order_format_date((string) ($order['created_at'] ?? ''))); ?>
                                            </p>
                                        </div>

                                        <span class="orders-view-button">
                                            View Details
                                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </main>
        </div>

        <script src="js/admin_script.js"></script>
    </body>
</html>
