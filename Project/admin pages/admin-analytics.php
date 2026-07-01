<?php
require_once __DIR__ . '/../components/app.php';

app_require_admin();

$currentPage = 'analytics';
$message = '';
$messageType = '';
$analyticsStats = [
    'total_revenue' => 0.00,
    'total_orders' => 0,
    'total_products' => 0,
    'total_users' => 0,
    'pending_orders' => 0,
    'completed_orders' => 0,
    'cancelled_orders' => 0,
];
$recentOrders = [];
$topSellingProducts = [];

function analytics_status_class(string $status): string
{
    return strtolower(str_replace(' ', '-', $status));
}

function analytics_percent(int $value, int $total): int
{
    if ($total <= 0) {
        return 0;
    }

    return (int) round(($value / $total) * 100);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = app_db();

    $statsResult = $mysqli->query(
        "SELECT
            COALESCE(SUM(total_amount), 0) AS total_revenue,
            COUNT(id) AS total_orders,
            COALESCE(SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END), 0) AS pending_orders,
            COALESCE(SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END), 0) AS completed_orders,
            COALESCE(SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_orders
         FROM `orders`"
    );
    $orderStats = $statsResult->fetch_assoc() ?: [];
    $statsResult->free();

    $analyticsStats['total_revenue'] = (float) ($orderStats['total_revenue'] ?? 0);
    $analyticsStats['total_orders'] = (int) ($orderStats['total_orders'] ?? 0);
    $analyticsStats['pending_orders'] = (int) ($orderStats['pending_orders'] ?? 0);
    $analyticsStats['completed_orders'] = (int) ($orderStats['completed_orders'] ?? 0);
    $analyticsStats['cancelled_orders'] = (int) ($orderStats['cancelled_orders'] ?? 0);
    $analyticsStats['total_products'] = (int) ($mysqli->query('SELECT COUNT(id) FROM products')->fetch_row()[0] ?? 0);
    $analyticsStats['total_users'] = (int) ($mysqli->query('SELECT COUNT(id) FROM users')->fetch_row()[0] ?? 0);

    $recentLimit = 8;
    $recentStatement = $mysqli->prepare(
        'SELECT
            id,
            customer_name,
            total_amount,
            status,
            created_at
         FROM `orders`
         ORDER BY created_at DESC, id DESC
         LIMIT ?'
    );
    $recentStatement->bind_param('i', $recentLimit);
    $recentStatement->execute();
    $recentResult = $recentStatement->get_result();

    while ($row = $recentResult->fetch_assoc()) {
        $recentOrders[] = $row;
    }

    $recentResult->free();
    $recentStatement->close();

    $topProductsLimit = 8;
    $topProductsStatement = $mysqli->prepare(
        'SELECT
            p.name AS product_name,
            COALESCE(SUM(oi.quantity), 0) AS total_quantity_sold,
            COALESCE(SUM(oi.quantity * oi.price), 0) AS total_sales
         FROM order_items oi
         INNER JOIN products p ON oi.product_id = p.id
         GROUP BY p.id, p.name
         ORDER BY total_quantity_sold DESC, total_sales DESC
         LIMIT ?'
    );
    $topProductsStatement->bind_param('i', $topProductsLimit);
    $topProductsStatement->execute();
    $topProductsResult = $topProductsStatement->get_result();

    while ($row = $topProductsResult->fetch_assoc()) {
        $topSellingProducts[] = $row;
    }

    $topProductsResult->free();
    $topProductsStatement->close();
    $mysqli->close();
} catch (mysqli_sql_exception $exception) {
    $message = 'Unable to load analytics right now. Please check the database connection.';
    $messageType = 'error';
}

$totalOrders = max(0, (int) $analyticsStats['total_orders']);
$averageOrderValue = $totalOrders > 0 ? $analyticsStats['total_revenue'] / $totalOrders : 0;
$completedPercent = analytics_percent((int) $analyticsStats['completed_orders'], $totalOrders);
$pendingPercent = analytics_percent((int) $analyticsStats['pending_orders'], $totalOrders);
$cancelledPercent = analytics_percent((int) $analyticsStats['cancelled_orders'], $totalOrders);
$latestOrderDate = $recentOrders !== [] ? (string) ($recentOrders[0]['created_at'] ?? 'No orders yet') : 'No orders yet';
$topProductName = $topSellingProducts !== [] ? (string) ($topSellingProducts[0]['product_name'] ?? 'Top product') : 'No products yet';
$orderHealthItems = [
    [
        'label' => 'Completed',
        'value' => (int) $analyticsStats['completed_orders'],
        'percent' => $completedPercent,
        'class' => 'completed',
        'note' => 'Orders finished for guests',
    ],
    [
        'label' => 'Pending',
        'value' => (int) $analyticsStats['pending_orders'],
        'percent' => $pendingPercent,
        'class' => 'pending',
        'note' => 'Orders waiting for action',
    ],
    [
        'label' => 'Cancelled',
        'value' => (int) $analyticsStats['cancelled_orders'],
        'percent' => $cancelledPercent,
        'class' => 'cancelled',
        'note' => 'Orders that did not continue',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Analytics | JKC Cafe Admin</title>
        <link rel="icon" href="../images/logo.svg" type="image/svg+xml">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
        <link rel="stylesheet" href="css/admin-shell.css">
        <link rel="stylesheet" href="css/admin-style.css">
        <link rel="stylesheet" href="css/admin-analytics.css">
    </head>
    <body class="admin-analytics-page">
        <div class="admin-layout">
            <?php require __DIR__ . '/components/admin-sidebar.php'; ?>

            <main class="admin-main">
                <?php require __DIR__ . '/components/admin-topbar.php'; ?>

                <section class="analytics-dashboard" aria-labelledby="analytics-title">
                    <div class="analytics-header">
                        <div>
                            <p class="analytics-eyebrow">Cafe performance</p>
                            <h1 class="analytics-title" id="analytics-title">
                                <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                                Analytics dashboard
                            </h1>
                            <p class="analytics-subtitle">Track revenue, order flow, and best-selling menu items with a quick view built for daily cafe decisions.</p>
                            <div class="analytics-hero-meta" aria-label="Analytics snapshot">
                                <span>
                                    <strong><?php echo number_format($completedPercent); ?>%</strong>
                                    completion rate
                                </span>
                                <span>
                                    <strong>P<?php echo number_format($averageOrderValue, 2); ?></strong>
                                    average order
                                </span>
                                <span>
                                    <strong><?php echo e($latestOrderDate); ?></strong>
                                    latest order
                                </span>
                            </div>
                        </div>
                        <a class="analytics-header-link" href="admin-orders-list.php">
                            <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                            View orders
                        </a>
                    </div>

                    <?php if ($message !== ''): ?>
                        <div class="admin-message <?php echo e($messageType); ?>" role="<?php echo $messageType === 'error' ? 'alert' : 'status'; ?>">
                            <?php echo e($message); ?>
                        </div>
                    <?php endif; ?>

                    <div class="analytics-summary-grid" aria-label="Analytics Summary">
                        <article class="analytics-stat-card is-revenue">
                            <span class="analytics-stat-icon revenue"><i class="fa-solid fa-peso-sign" aria-hidden="true"></i></span>
                            <div>
                                <h2>Total Revenue</h2>
                                <p>P<?php echo number_format($analyticsStats['total_revenue'], 2); ?></p>
                                <small>All recorded order sales</small>
                            </div>
                        </article>
                        <article class="analytics-stat-card">
                            <span class="analytics-stat-icon orders"><i class="fa-solid fa-receipt" aria-hidden="true"></i></span>
                            <div>
                                <h2>Total Orders</h2>
                                <p><?php echo number_format($analyticsStats['total_orders']); ?></p>
                                <small><?php echo number_format($pendingPercent); ?>% pending right now</small>
                            </div>
                        </article>
                        <article class="analytics-stat-card">
                            <span class="analytics-stat-icon products"><i class="fa-solid fa-box-open" aria-hidden="true"></i></span>
                            <div>
                                <h2>Total Products</h2>
                                <p><?php echo number_format($analyticsStats['total_products']); ?></p>
                                <small>Menu items available in admin</small>
                            </div>
                        </article>
                        <article class="analytics-stat-card">
                            <span class="analytics-stat-icon users"><i class="fa-solid fa-users" aria-hidden="true"></i></span>
                            <div>
                                <h2>Total Users</h2>
                                <p><?php echo number_format($analyticsStats['total_users']); ?></p>
                                <small>Registered customer accounts</small>
                            </div>
                        </article>
                        <article class="analytics-stat-card">
                            <span class="analytics-stat-icon pending"><i class="fa-regular fa-clock" aria-hidden="true"></i></span>
                            <div>
                                <h2>Pending Orders</h2>
                                <p><?php echo number_format($analyticsStats['pending_orders']); ?></p>
                                <small>Needs team attention</small>
                            </div>
                        </article>
                        <article class="analytics-stat-card">
                            <span class="analytics-stat-icon completed"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
                            <div>
                                <h2>Completed Orders</h2>
                                <p><?php echo number_format($analyticsStats['completed_orders']); ?></p>
                                <small><?php echo number_format($completedPercent); ?>% of all orders</small>
                            </div>
                        </article>
                        <article class="analytics-stat-card">
                            <span class="analytics-stat-icon cancelled"><i class="fa-solid fa-ban" aria-hidden="true"></i></span>
                            <div>
                                <h2>Cancelled Orders</h2>
                                <p><?php echo number_format($analyticsStats['cancelled_orders']); ?></p>
                                <small><?php echo number_format($cancelledPercent); ?>% cancellation share</small>
                            </div>
                        </article>
                    </div>

                    <section class="analytics-health-panel" aria-labelledby="analytics-health-title">
                        <div class="analytics-health-copy">
                            <p class="analytics-eyebrow">Order health</p>
                            <h2 id="analytics-health-title">A quick read on cafe flow</h2>
                            <p>Use this mix to spot whether orders are moving cleanly or need a follow-up before the rush builds.</p>
                        </div>
                        <div class="analytics-health-list">
                            <?php foreach ($orderHealthItems as $healthItem): ?>
                                <article class="analytics-health-item <?php echo e($healthItem['class']); ?>">
                                    <div>
                                        <strong><?php echo e($healthItem['label']); ?></strong>
                                        <span><?php echo number_format((int) $healthItem['value']); ?> orders · <?php echo number_format((int) $healthItem['percent']); ?>%</span>
                                    </div>
                                    <div class="analytics-health-track" role="progressbar" aria-label="<?php echo e($healthItem['label']); ?> order share" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int) $healthItem['percent']; ?>">
                                        <span style="--value: <?php echo (int) $healthItem['percent']; ?>%;"></span>
                                    </div>
                                    <small><?php echo e($healthItem['note']); ?></small>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <div class="analytics-section-grid">
                        <section class="analytics-panel">
                            <div class="analytics-panel-header">
                                <div>
                                    <p class="analytics-eyebrow">Latest activity</p>
                                    <h2>
                                        <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                                        Recent Orders
                                    </h2>
                                </div>
                                <span><?php echo count($recentOrders); ?> shown</span>
                            </div>

                            <div class="analytics-table-wrap">
                                <table class="analytics-table">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Customer/User</th>
                                            <th>Total Amount</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recentOrders)): ?>
                                            <tr>
                                                <td colspan="6" class="analytics-empty">
                                                    <i class="fa-regular fa-clipboard" aria-hidden="true"></i>
                                                    <strong>No orders yet</strong>
                                                    <span>Recent orders will appear here once customers check out.</span>
                                                </td>
                                            </tr>
                                        <?php endif; ?>

                                        <?php foreach ($recentOrders as $order): ?>
                                            <?php
                                                $orderId = (int) ($order['id'] ?? 0);
                                                $status = (string) ($order['status'] ?? 'Pending');
                                            ?>
                                            <tr>
                                                <td data-label="Order ID"><strong>#<?php echo $orderId; ?></strong></td>
                                                <td data-label="Customer"><?php echo e((string) ($order['customer_name'] ?? 'Not provided')); ?></td>
                                                <td data-label="Total Amount">P<?php echo number_format((float) ($order['total_amount'] ?? 0), 2); ?></td>
                                                <td data-label="Status">
                                                    <span class="analytics-status-badge <?php echo e(analytics_status_class($status)); ?>">
                                                        <?php echo e($status); ?>
                                                    </span>
                                                </td>
                                                <td data-label="Date"><?php echo e((string) ($order['created_at'] ?? 'Not available')); ?></td>
                                                <td data-label="Action">
                                                    <a class="analytics-view-button" href="admin-order-details.php?id=<?php echo $orderId; ?>">
                                                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                                        View Details
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="analytics-panel">
                            <div class="analytics-panel-header">
                                <div>
                                    <p class="analytics-eyebrow">Menu movement</p>
                                    <h2>
                                        <i class="fa-solid fa-ranking-star" aria-hidden="true"></i>
                                        Top Selling Products
                                    </h2>
                                    <small class="analytics-panel-note">Current leader: <?php echo e($topProductName); ?></small>
                                </div>
                                <span><?php echo count($topSellingProducts); ?> shown</span>
                            </div>

                            <div class="analytics-table-wrap">
                                <table class="analytics-table top-products-table">
                                    <thead>
                                        <tr>
                                            <th>Product Name</th>
                                            <th>Total Quantity Sold</th>
                                            <th>Total Sales</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($topSellingProducts)): ?>
                                            <tr>
                                                <td colspan="3" class="analytics-empty">
                                                    <i class="fa-regular fa-star" aria-hidden="true"></i>
                                                    <strong>No sold products yet</strong>
                                                    <span>Best sellers will rank here after orders include menu items.</span>
                                                </td>
                                            </tr>
                                        <?php endif; ?>

                                        <?php foreach ($topSellingProducts as $index => $product): ?>
                                            <tr>
                                                <td data-label="Product Name">
                                                    <span class="analytics-rank">#<?php echo $index + 1; ?></span>
                                                    <?php echo e((string) ($product['product_name'] ?? 'Deleted product')); ?>
                                                </td>
                                                <td data-label="Quantity Sold"><?php echo number_format((int) ($product['total_quantity_sold'] ?? 0)); ?></td>
                                                <td data-label="Total Sales">P<?php echo number_format((float) ($product['total_sales'] ?? 0), 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                </section>
            </main>
        </div>

        <script src="js/admin_script.js"></script>
    </body>
</html>
