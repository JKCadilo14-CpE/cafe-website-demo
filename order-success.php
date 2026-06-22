<?php
require_once __DIR__ . '/components/app.php';

if (!app_is_logged_in()) {
    header('Location: login.php');
    exit();
}

function order_success_format_datetime(?string $value): string
{
    $timestamp = strtotime((string) $value);

    return $timestamp ? date('M j, Y g:i A', $timestamp) : 'Just now';
}

$userId = (int) $_SESSION['user_id'];
$hasRequestedOrderId = array_key_exists('order_id', $_GET);
$hasLegacyOrderId = array_key_exists('id', $_GET);
$requestedOrderId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
$legacyOrderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$sessionOrderId = (int) ($_SESSION['last_order_id'] ?? 0);
$orderId = $sessionOrderId;
$hasInvalidOrderId = false;

if ($hasRequestedOrderId) {
    $hasInvalidOrderId = $requestedOrderId === false || $requestedOrderId === null || $requestedOrderId < 1;
    $orderId = $hasInvalidOrderId ? 0 : (int) $requestedOrderId;
} elseif ($hasLegacyOrderId) {
    $hasInvalidOrderId = $legacyOrderId === false || $legacyOrderId === null || $legacyOrderId < 1;
    $orderId = $hasInvalidOrderId ? 0 : (int) $legacyOrderId;
}

$order = null;
$orderItems = [];
$message = '';
$messageType = '';

try {
    $mysqli = app_db();

    if ($hasInvalidOrderId) {
        $message = 'We could not find that order for your account.';
        $messageType = 'error';
    } elseif ($orderId > 0) {
        $statement = $mysqli->prepare(
            'SELECT id, user_id, total_amount, status, created_at, updated_at, customer_name, phone_number, delivery_address, payment_method, delivery_fee, delivery_partner, cancel_reason
             FROM orders
             WHERE id = ? AND user_id = ?
             LIMIT 1'
        );
        $statement->bind_param('ii', $orderId, $userId);
    } else {
        $statement = $mysqli->prepare(
            'SELECT id, user_id, total_amount, status, created_at, updated_at, customer_name, phone_number, delivery_address, payment_method, delivery_fee, delivery_partner, cancel_reason
             FROM orders
             WHERE user_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT 1'
        );
        $statement->bind_param('i', $userId);
    }

    if (isset($statement)) {
        $statement->execute();
        $result = $statement->get_result();
        $order = $result->fetch_assoc() ?: null;
        $result->free();
        $statement->close();
    }

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
        $loadedOrderId = (int) $order['id'];
        $itemsStatement->bind_param('i', $loadedOrderId);
        $itemsStatement->execute();
        $itemsResult = $itemsStatement->get_result();

        while ($item = $itemsResult->fetch_assoc()) {
            $quantity = max(0, (int) ($item['quantity'] ?? 0));
            $unitPrice = (float) ($item['price'] ?? 0);
            $orderItems[] = [
                'name' => trim((string) ($item['product_name'] ?? '')) !== '' ? (string) $item['product_name'] : 'Product unavailable',
                'image' => (string) ($item['image'] ?? ''),
                'quantity' => $quantity,
                'price' => $unitPrice,
                'line_total' => $quantity * $unitPrice,
            ];
        }

        $itemsResult->free();
        $itemsStatement->close();
    }

    $mysqli->close();

    if ($order !== null) {
        $_SESSION['last_order_id'] = (int) $order['id'];
    } else {
        $message = 'We could not find that order for your account.';
        $messageType = 'error';
    }
} catch (mysqli_sql_exception $exception) {
    $message = 'Unable to load your order right now. Please check the database connection.';
    $messageType = 'error';
}

$currentStatus = app_normalize_order_status((string) ($order['status'] ?? 'Pending'));
$statusClass = app_order_status_class($currentStatus);
$progressSteps = app_order_progress_steps_for_status($currentStatus);
$canCancel = $order !== null && app_order_can_cancel($currentStatus);
$placedAt = (string) ($order['created_at'] ?? '');
$lastUpdated = (string) (($order['updated_at'] ?? '') ?: ($order['created_at'] ?? ''));
$orderTotal = app_format_money((float) ($order['total_amount'] ?? 0));
$statusCopy = match ($currentStatus) {
    'Preparing' => 'The cafe team is preparing your order now.',
    'Out for Delivery' => 'Your order has left the cafe and is on its way.',
    'Completed' => 'Your order has been completed. Thank you for ordering with JKC Cafe.',
    'Cancelled' => 'This order has been cancelled.',
    default => 'Your order is in the cafe queue and will move soon.',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Status | JKC Cafe</title>
  <meta name="description" content="Track the live progress of your JKC Cafe order.">
  <link rel="icon" type="image/png" href="./images/logo.svg">
  <link rel="stylesheet" href="user-css/style.css">
  <link rel="stylesheet" href="user-css/cart-checkout.css">
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>
  <?php include __DIR__ . '/components/navbar.php'; ?>

  <main id="main" class="checkout-page order-status-page">
    <section class="section order-success-layout" aria-labelledby="success-title">
      <?php if ($order === null): ?>
        <article class="checkout-card order-success order-success-empty">
          <p class="eyebrow">Order status</p>
          <h1 id="success-title">We could not find that order.</h1>
          <p class="<?php echo $messageType === 'error' ? 'cart-alert is-error' : ''; ?>"><?php echo e($message); ?></p>
          <div class="hero-actions">
            <a class="button button-primary" href="profile.php">View profile</a>
            <a class="button button-secondary" href="menu.php">Browse menu</a>
          </div>
        </article>
      <?php else: ?>
        <article
          class="checkout-card order-success"
          aria-live="polite"
          data-order-tracker
          data-order-id="<?php echo e((string) $order['id']); ?>"
          data-status-url="order-status.php?id=<?php echo e((string) $order['id']); ?>"
          data-current-status="<?php echo e($currentStatus); ?>"
        >
          <div class="order-success-head">
            <div>
              <p class="eyebrow">Live order status</p>
              <h1 id="success-title">Order #<?php echo e((string) $order['id']); ?></h1>
              <p data-order-status-message><?php echo e($statusCopy); ?></p>
            </div>
            <span class="order-live-badge <?php echo e($statusClass); ?>" data-order-status-badge><?php echo e($currentStatus); ?></span>
          </div>

          <div class="order-live-panel <?php echo $currentStatus === 'Cancelled' ? 'is-cancelled' : ''; ?>" data-order-progress-panel>
            <ol class="order-progress-tracker" aria-label="Order progress">
              <?php foreach ($progressSteps as $index => $step): ?>
                <?php
                    $stepClasses = trim($step['class'] . ' is-' . $step['state']);
                    $stepNumber = (string) ($index + 1);
                ?>
                <li
                  class="<?php echo e($stepClasses); ?>"
                  data-order-step="<?php echo e($step['status']); ?>"
                >
                  <span class="order-progress-dot" aria-hidden="true"><?php echo e($stepNumber); ?></span>
                  <div>
                    <strong><?php echo e($step['label']); ?></strong>
                    <small><?php echo e($step['description']); ?></small>
                  </div>
                </li>
              <?php endforeach; ?>
            </ol>

            <div class="order-cancelled-note" <?php echo $currentStatus === 'Cancelled' ? '' : 'hidden'; ?> data-order-cancelled-note>
              <strong>Order cancelled</strong>
              <span><?php echo e((string) (($order['cancel_reason'] ?? '') ?: 'This order will not continue through fulfillment.')); ?></span>
            </div>
          </div>

          <div class="order-info-grid">
            <section class="order-info-tile">
              <span>Total</span>
              <strong data-order-total><?php echo e($orderTotal); ?></strong>
            </section>
            <section class="order-info-tile">
              <span>Placed</span>
              <strong><?php echo e(order_success_format_datetime($placedAt)); ?></strong>
            </section>
            <section class="order-info-tile">
              <span>Payment</span>
              <strong><?php echo e((string) ($order['payment_method'] ?? 'Not provided')); ?></strong>
            </section>
            <section class="order-info-tile">
              <span>Delivery partner</span>
              <strong><?php echo e((string) ($order['delivery_partner'] ?? 'JKC Cafe Delivery')); ?></strong>
            </section>
            <section class="order-info-tile">
              <span>Last updated</span>
              <strong data-order-updated><?php echo e(order_success_format_datetime($lastUpdated)); ?></strong>
            </section>
          </div>

          <div class="order-delivery-card">
            <div>
              <span>Delivering to</span>
              <strong><?php echo e((string) ($order['customer_name'] ?? 'Customer')); ?></strong>
            </div>
            <p><?php echo e((string) ($order['delivery_address'] ?? 'Delivery address not provided.')); ?></p>
            <small><?php echo e((string) ($order['phone_number'] ?? 'No phone number saved.')); ?></small>
          </div>

          <section class="order-items-card" aria-labelledby="order-items-title">
            <div class="order-items-head">
              <div>
                <span>Ordered items</span>
                <h2 id="order-items-title">What you ordered</h2>
              </div>
              <strong><?php echo e((string) count($orderItems)); ?> item<?php echo count($orderItems) === 1 ? '' : 's'; ?></strong>
            </div>

            <?php if ($orderItems === []): ?>
              <p class="order-items-empty">No item details are available for this order.</p>
            <?php else: ?>
              <div class="order-items-list">
                <?php foreach ($orderItems as $item): ?>
                  <article class="order-item-row">
                    <?php if (trim($item['image']) !== ''): ?>
                      <img src="<?php echo e($item['image']); ?>" alt="<?php echo e($item['name']); ?>" loading="lazy">
                    <?php else: ?>
                      <span class="order-item-placeholder" aria-hidden="true">JKC</span>
                    <?php endif; ?>
                    <div class="order-item-main">
                      <h3><?php echo e($item['name']); ?></h3>
                      <p>
                        Qty <?php echo e((string) $item['quantity']); ?>
                        &middot;
                        <?php echo e(app_format_money((float) $item['price'])); ?> each
                      </p>
                    </div>
                    <strong><?php echo e(app_format_money((float) $item['line_total'])); ?></strong>
                  </article>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>

          <div class="order-success-actions">
            <form
              action="cancel-order.php"
              method="post"
              data-order-cancel-form
              <?php echo $canCancel ? '' : 'hidden'; ?>
            >
              <input type="hidden" name="order_id" value="<?php echo e((string) $order['id']); ?>">
              <button class="button button-secondary order-cancel-button" type="submit" data-order-cancel-button>
                Cancel order
              </button>
            </form>
            <a class="button button-primary" href="menu.php">Browse more</a>
            <a class="button button-secondary" href="profile.php">View profile</a>
          </div>

          <p class="order-polling-note" data-order-polling-note>
            This page checks for status updates automatically while it is open.
          </p>
        </article>
      <?php endif; ?>
    </section>
  </main>

  <div class="order-toast" role="status" aria-live="polite" aria-atomic="true" hidden data-order-toast>
    <strong>Order update</strong>
    <span data-order-toast-message>Your order status changed.</span>
  </div>

  <?php include __DIR__ . '/components/footer.php'; ?>
  <script src="user-js/script.js"></script>
</body>
</html>
