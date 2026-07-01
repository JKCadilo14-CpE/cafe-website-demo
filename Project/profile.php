<?php
require_once __DIR__ . '/components/app.php';

if (!app_is_logged_in()) {
    header('Location: login.php');
    exit();
}

function profile_status_class(string $status): string
{
    $normalizedStatus = str_replace(' ', '-', strtolower(trim($status)));

    return match ($normalizedStatus) {
        'pending' => 'pending',
        'preparing' => 'preparing',
        'out-for-delivery' => 'out-for-delivery',
        'completed' => 'completed',
        'cancelled' => 'cancelled',
        default => 'default',
    };
}

function profile_format_date(string $date): string
{
    $timestamp = strtotime($date);

    return $timestamp ? date('M j, Y', $timestamp) : 'Recently';
}

$userId = (int) $_SESSION['user_id'];
$profile = [
    'username' => (string) ($_SESSION['username'] ?? 'Account'),
    'email' => (string) ($_SESSION['email'] ?? ''),
    'profile_image' => (string) ($_SESSION['profile_image'] ?? ''),
];
$stats = [
    'total_orders' => 0,
    'active_orders' => 0,
    'lifetime_spend' => 0.00,
];
$recentOrders = [];
$historyFavorites = [];
$userNotifications = [];
$latestDelivery = null;
$message = '';

$cartItems = app_cart_items();
$cartCount = app_cart_count();
$cartSubtotal = app_cart_subtotal($cartItems);
$cartPreview = array_slice($cartItems, 0, 3);

try {
    $mysqli = app_db();
    app_ensure_profile_image_column($mysqli);

    $profileStatement = $mysqli->prepare('SELECT username, email, profile_image FROM users WHERE id = ? LIMIT 1');
    $profileStatement->bind_param('i', $userId);
    $profileStatement->execute();
    $profileResult = $profileStatement->get_result();
    $user = $profileResult->fetch_assoc();
    $profileResult->free();
    $profileStatement->close();

    if ($user !== null) {
        $profile['username'] = (string) $user['username'];
        $profile['email'] = (string) $user['email'];
        $profile['profile_image'] = (string) ($user['profile_image'] ?? '');
        $_SESSION['username'] = $profile['username'];
        $_SESSION['email'] = $profile['email'];
        $_SESSION['profile_image'] = app_profile_image_src($profile['profile_image']);
    } else {
        $message = 'Unable to find this account right now.';
    }

    $statsStatement = $mysqli->prepare(
        "SELECT
            COUNT(*) AS total_orders,
            COALESCE(SUM(total_amount), 0) AS lifetime_spend,
            COALESCE(SUM(CASE WHEN status IN ('Pending', 'Preparing', 'Out for Delivery') THEN 1 ELSE 0 END), 0) AS active_orders
         FROM orders
         WHERE user_id = ?"
    );
    $statsStatement->bind_param('i', $userId);
    $statsStatement->execute();
    $statsResult = $statsStatement->get_result();
    $statsRow = $statsResult->fetch_assoc();
    $statsResult->free();
    $statsStatement->close();

    if ($statsRow !== null) {
        $stats['total_orders'] = (int) $statsRow['total_orders'];
        $stats['active_orders'] = (int) $statsRow['active_orders'];
        $stats['lifetime_spend'] = (float) $statsRow['lifetime_spend'];
    }

    $ordersStatement = $mysqli->prepare(
        'SELECT id, total_amount, status, created_at, payment_method, delivery_partner
         FROM orders
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 4'
    );
    $ordersStatement->bind_param('i', $userId);
    $ordersStatement->execute();
    $ordersResult = $ordersStatement->get_result();

    while ($row = $ordersResult->fetch_assoc()) {
        $recentOrders[] = $row;
    }

    $ordersResult->free();
    $ordersStatement->close();

    $deliveryStatement = $mysqli->prepare(
        'SELECT customer_name, phone_number, delivery_address, payment_method, delivery_partner
         FROM orders
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 1'
    );
    $deliveryStatement->bind_param('i', $userId);
    $deliveryStatement->execute();
    $deliveryResult = $deliveryStatement->get_result();
    $latestDelivery = $deliveryResult->fetch_assoc() ?: null;
    $deliveryResult->free();
    $deliveryStatement->close();

    $favoritesStatement = $mysqli->prepare(
        'SELECT
            p.name,
            p.category,
            p.image,
            COALESCE(SUM(oi.quantity), 0) AS ordered_quantity
         FROM order_items oi
         INNER JOIN orders o ON o.id = oi.order_id
         INNER JOIN products p ON p.id = oi.product_id
         WHERE o.user_id = ?
         GROUP BY p.id, p.name, p.category, p.image
         ORDER BY ordered_quantity DESC, p.name ASC
         LIMIT 3'
    );
    $favoritesStatement->bind_param('i', $userId);
    $favoritesStatement->execute();
    $favoritesResult = $favoritesStatement->get_result();

    while ($row = $favoritesResult->fetch_assoc()) {
        $historyFavorites[] = [
            'name' => (string) $row['name'],
            'category' => (string) $row['category'],
            'image' => (string) $row['image'],
            'ordered_quantity' => (int) $row['ordered_quantity'],
            'is_fallback' => false,
        ];
    }

    $favoritesResult->free();
    $favoritesStatement->close();
    $mysqli->close();
} catch (mysqli_sql_exception $exception) {
    $message = 'Unable to load all dashboard details right now. Please check the database connection.';
}

$userNotifications = app_user_notifications($userId, 5);

if ($historyFavorites === []) {
    foreach (array_slice(app_menu_products(), 0, 3) as $product) {
        $historyFavorites[] = [
            'name' => (string) $product['name'],
            'category' => (string) $product['category'],
            'image' => (string) $product['image'],
            'ordered_quantity' => 0,
            'is_fallback' => true,
        ];
    }
}

$displayName = trim($profile['username']) !== '' ? trim($profile['username']) : 'Account';
$displayEmail = trim($profile['email']) !== '' ? trim($profile['email']) : 'No email saved';
$initials = app_initials($displayName);
$profileImage = app_profile_image_src((string) ($_SESSION['profile_image'] ?? $profile['profile_image']));
$latestName = trim((string) ($latestDelivery['customer_name'] ?? ''));
$latestPhone = trim((string) ($latestDelivery['phone_number'] ?? ''));
$latestAddress = trim((string) ($latestDelivery['delivery_address'] ?? ''));
$latestPayment = trim((string) ($latestDelivery['payment_method'] ?? ''));
$latestOrder = $recentOrders[0] ?? null;
$latestOrderStatus = $latestOrder !== null ? (string) ($latestOrder['status'] ?? 'Pending') : 'No orders yet';
$cartSummaryText = $cartCount === 1 ? '1 item waiting' : $cartCount . ' items waiting';
$nextOrderHint = $cartCount > 0 ? 'Your cart is ready for review.' : 'Start with a signature drink or a fresh pastry.';
$orderSummaryText = $stats['total_orders'] > 0
    ? $stats['total_orders'] . ' cafe visit' . ($stats['total_orders'] === 1 ? '' : 's') . ' saved'
    : 'New cafe account';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Dashboard | JKC Cafe</title>
  <meta name="description" content="View your JKC Cafe dashboard, cart, order history, and account details.">
  <link rel="icon" type="image/svg+xml" href="./images/logo.svg">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="user-css/style.css">
  <link rel="stylesheet" href="user-css/profile.css">
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>

  <?php include __DIR__ . '/components/navbar.php'; ?>

  <main id="main" class="account-page user-dashboard-page">
    <section class="section user-dashboard" aria-labelledby="dashboard-title">
      <?php if ($message !== ''): ?>
        <p class="login-message is-error" role="alert"><?php echo e($message); ?></p>
      <?php endif; ?>

      <section class="dashboard-hero" aria-labelledby="dashboard-title" data-reveal>
        <div class="dashboard-hero-copy">
          <p class="eyebrow">Account dashboard</p>
          <div class="dashboard-profile-line">
            <span class="dashboard-avatar" aria-hidden="true">
              <?php if ($profileImage !== ''): ?>
                <img src="<?php echo e($profileImage); ?>" alt="">
              <?php else: ?>
                <?php echo e($initials); ?>
              <?php endif; ?>
            </span>
            <div>
              <h1 id="dashboard-title">Welcome back, <?php echo e($displayName); ?>.</h1>
              <p>JKC Cafe member account</p>
            </div>
          </div>
          <div class="dashboard-identity-meta" aria-label="Account details">
            <span><i class="fa-solid fa-envelope" aria-hidden="true"></i><?php echo e($displayEmail); ?></span>
            <span><i class="fa-solid fa-mug-hot" aria-hidden="true"></i><?php echo e($orderSummaryText); ?></span>
          </div>
          <p class="dashboard-hero-text">Your cart, favorite cafe picks, delivery details, and recent orders are gathered here for a smoother JKC Cafe visit.</p>
          <div class="dashboard-hero-actions">
            <a class="button button-primary" href="menu.php"><i class="fa-solid fa-mug-hot" aria-hidden="true"></i> Order from menu</a>
            <a class="button button-secondary" href="cart.php"><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i> Review cart</a>
            <a class="button button-secondary" href="settings.php"><i class="fa-solid fa-gear" aria-hidden="true"></i> Edit profile</a>
          </div>
          <div class="dashboard-quick-strip" aria-label="Account snapshot">
            <div>
              <span>Current cart</span>
              <strong><?php echo e($cartSummaryText); ?></strong>
            </div>
            <div>
              <span>Latest status</span>
              <strong><?php echo e($latestOrderStatus); ?></strong>
            </div>
            <div>
              <span>Suggested next</span>
              <strong><?php echo e($nextOrderHint); ?></strong>
            </div>
          </div>
        </div>

        <div class="dashboard-hero-media">
          <img src="images/rewards-cafe.png" width="1717" height="916" alt="Latte, croissant, and JKC Cafe rewards card on a bright cafe table.">
          <div class="dashboard-hero-note">
            <i class="fa-solid fa-star" aria-hidden="true"></i>
            <span><?php echo $stats['total_orders'] > 0 ? e((string) $stats['total_orders']) . ' orders with JKC Cafe' : 'Ready for your first JKC order'; ?></span>
          </div>
          <div class="dashboard-hero-ticket">
            <span>Today's next step</span>
            <strong><?php echo $cartCount > 0 ? 'Finish checkout' : 'Pick a favorite'; ?></strong>
            <small><?php echo e($nextOrderHint); ?></small>
          </div>
        </div>
      </section>

      <section class="dashboard-stat-grid" aria-label="Dashboard summary" data-reveal>
        <article class="dashboard-stat-card">
          <span><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i></span>
          <p>Cart ready</p>
          <strong><?php echo e((string) $cartCount); ?></strong>
          <small><?php echo $cartCount > 0 ? 'Ready to review' : 'No items yet'; ?></small>
        </article>
        <article class="dashboard-stat-card">
          <span><i class="fa-solid fa-receipt" aria-hidden="true"></i></span>
          <p>Orders placed</p>
          <strong><?php echo e((string) $stats['total_orders']); ?></strong>
          <small><?php echo $stats['total_orders'] > 0 ? 'Order history saved' : 'First order awaits'; ?></small>
        </article>
        <article class="dashboard-stat-card">
          <span><i class="fa-solid fa-truck-fast" aria-hidden="true"></i></span>
          <p>In progress</p>
          <strong><?php echo e((string) $stats['active_orders']); ?></strong>
          <small><?php echo $stats['active_orders'] > 0 ? 'In progress now' : 'Nothing in motion'; ?></small>
        </article>
        <article class="dashboard-stat-card">
          <span><i class="fa-solid fa-wallet" aria-hidden="true"></i></span>
          <p>Cafe spend</p>
          <strong><?php echo e(app_format_money($stats['lifetime_spend'])); ?></strong>
          <small>Across completed checkouts</small>
        </article>
      </section>

      <section class="dashboard-main-grid">
        <article class="dashboard-panel dashboard-cart-panel" aria-labelledby="cart-panel-title" data-reveal>
          <div class="dashboard-panel-head">
            <div>
              <p class="eyebrow">Current cart</p>
              <h2 id="cart-panel-title">Ready when you are</h2>
            </div>
            <span class="dashboard-panel-icon"><i class="fa-solid fa-basket-shopping" aria-hidden="true"></i></span>
          </div>

          <?php if ($cartItems === []): ?>
            <div class="dashboard-empty">
              <i class="fa-solid fa-mug-saucer" aria-hidden="true"></i>
              <h3>Your cart is ready for a first pick.</h3>
              <p>Add coffee, pastries, or your usual comfort order from the JKC Cafe menu.</p>
              <a class="button button-primary" href="menu.php">Start an order</a>
            </div>
          <?php else: ?>
            <div class="dashboard-cart-list">
              <?php foreach ($cartPreview as $item): ?>
                <div class="dashboard-cart-item">
                  <img src="<?php echo e($item['image']); ?>" alt="<?php echo e($item['name']); ?> in your JKC Cafe cart." loading="lazy">
                  <div>
                    <h3><?php echo e($item['name']); ?></h3>
                    <p><?php echo e((string) $item['quantity']); ?> x <?php echo e(app_format_money((float) $item['price'])); ?></p>
                  </div>
                  <strong><?php echo e(app_format_money((float) $item['line_total'])); ?></strong>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="dashboard-cart-total">
              <span>Subtotal</span>
              <strong><?php echo e(app_format_money($cartSubtotal)); ?></strong>
            </div>
            <?php if (count($cartItems) > count($cartPreview)): ?>
              <p class="dashboard-panel-note">And <?php echo e((string) (count($cartItems) - count($cartPreview))); ?> more item<?php echo count($cartItems) - count($cartPreview) === 1 ? '' : 's'; ?> in your cart.</p>
            <?php endif; ?>
            <a class="button button-primary" href="cart.php">Review checkout</a>
          <?php endif; ?>
        </article>

        <article class="dashboard-panel dashboard-orders-panel" aria-labelledby="orders-panel-title" data-reveal>
          <div class="dashboard-panel-head">
            <div>
              <p class="eyebrow">Order history</p>
              <h2 id="orders-panel-title">Recent cafe orders</h2>
            </div>
            <span class="dashboard-panel-icon"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></span>
          </div>

          <?php if ($recentOrders === []): ?>
            <div class="dashboard-empty">
              <i class="fa-regular fa-clipboard" aria-hidden="true"></i>
              <h3>No cafe orders yet.</h3>
              <p>Your receipts and delivery updates will land here after checkout.</p>
              <a class="button button-secondary" href="menu.php">Browse the menu</a>
            </div>
          <?php else: ?>
            <div class="dashboard-order-list">
              <?php foreach ($recentOrders as $order): ?>
                <?php $status = (string) ($order['status'] ?? 'Pending'); ?>
                <a class="dashboard-order-card" href="order-success.php?order_id=<?php echo e((string) $order['id']); ?>" aria-label="View details for order <?php echo e((string) $order['id']); ?>">
                  <span class="dashboard-order-marker" aria-hidden="true"></span>
                  <div>
                    <span class="dashboard-status <?php echo e(profile_status_class($status)); ?>"><?php echo e($status); ?></span>
                    <h3>Order #<?php echo e((string) $order['id']); ?></h3>
                    <p><time datetime="<?php echo e((string) $order['created_at']); ?>"><?php echo e(profile_format_date((string) $order['created_at'])); ?></time> &middot; <?php echo e((string) ($order['payment_method'] ?? 'Payment')); ?></p>
                  </div>
                  <strong><?php echo e(app_format_money((float) $order['total_amount'])); ?></strong>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </article>
      </section>

      <section class="dashboard-lower-grid">
        <article class="dashboard-panel" aria-labelledby="favorites-title" data-reveal>
          <div class="dashboard-panel-head">
            <div>
              <p class="eyebrow">Cafe picks</p>
              <h2 id="favorites-title"><?php echo $stats['total_orders'] > 0 ? 'Favorites from your visits' : 'Cafe favorites to try'; ?></h2>
            </div>
            <span class="dashboard-panel-icon"><i class="fa-solid fa-heart" aria-hidden="true"></i></span>
          </div>

          <div class="dashboard-favorite-grid">
            <?php foreach ($historyFavorites as $favorite): ?>
              <?php $favoriteCategorySlug = app_slug((string) $favorite['category']); ?>
              <a class="dashboard-favorite-card" href="menu.php?category=<?php echo e($favoriteCategorySlug); ?>#menu-products-title">
                <img src="<?php echo e($favorite['image']); ?>" alt="<?php echo e($favorite['name']); ?> from JKC Cafe." loading="lazy">
                <div>
                  <span><?php echo e($favorite['category']); ?></span>
                  <h3><?php echo e($favorite['name']); ?></h3>
                  <p>
                    <?php if ($favorite['is_fallback']): ?>
                      Fresh pick from the JKC Cafe menu.
                    <?php else: ?>
                      Ordered <?php echo e((string) $favorite['ordered_quantity']); ?> time<?php echo $favorite['ordered_quantity'] === 1 ? '' : 's'; ?>.
                    <?php endif; ?>
                  </p>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </article>

        <aside class="dashboard-panel dashboard-notifications-panel" aria-labelledby="notifications-title" data-reveal>
          <div class="dashboard-panel-head">
            <div>
              <p class="eyebrow">Notifications</p>
              <h2 id="notifications-title">Cafe updates</h2>
            </div>
            <span class="dashboard-panel-icon"><i class="fa-solid fa-bell" aria-hidden="true"></i></span>
          </div>

          <?php if ($userNotifications === []): ?>
            <div class="dashboard-empty">
              <i class="fa-regular fa-bell" aria-hidden="true"></i>
              <h3>No updates yet.</h3>
              <p>Availability reminders and account updates will appear here.</p>
            </div>
          <?php else: ?>
            <div class="dashboard-notification-list">
              <?php foreach ($userNotifications as $notification): ?>
                <?php
                  $notificationUrl = trim((string) ($notification['link_url'] ?? ''));
                  $notificationTitle = (string) ($notification['title'] ?? 'Cafe update');
                  $notificationStatus = (string) ($notification['status'] ?? 'unread');
                ?>
                <article class="dashboard-notification-card <?php echo $notificationStatus === 'unread' ? 'is-unread' : ''; ?>">
                  <span class="dashboard-order-marker" aria-hidden="true"></span>
                  <div>
                    <span class="dashboard-status <?php echo $notificationStatus === 'unread' ? 'preparing' : 'completed'; ?>"><?php echo e(ucfirst($notificationStatus)); ?></span>
                    <h3><?php echo e($notificationTitle); ?></h3>
                    <p><?php echo e((string) ($notification['message'] ?? '')); ?></p>
                    <small><?php echo e(profile_format_date((string) ($notification['created_at'] ?? ''))); ?></small>
                  </div>
                  <?php if ($notificationUrl !== ''): ?>
                    <a class="dashboard-notification-link" href="<?php echo e($notificationUrl); ?>">View</a>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </aside>

        <aside class="dashboard-panel dashboard-details-panel" aria-labelledby="details-title" data-reveal>
          <div class="dashboard-panel-head">
            <div>
              <p class="eyebrow">Account</p>
              <h2 id="details-title">Saved details</h2>
            </div>
            <span class="dashboard-panel-icon"><i class="fa-solid fa-user-check" aria-hidden="true"></i></span>
          </div>

          <dl class="dashboard-detail-list">
            <div>
              <dt>Username</dt>
              <dd><?php echo e($displayName); ?></dd>
            </div>
            <div>
              <dt>Email</dt>
              <dd><?php echo e($displayEmail); ?></dd>
            </div>
            <div>
              <dt>Latest contact</dt>
              <dd><?php echo e($latestName !== '' ? $latestName : 'Not added yet'); ?><?php echo $latestPhone !== '' ? ' &middot; ' . e($latestPhone) : ''; ?></dd>
            </div>
            <div>
              <dt>Latest address</dt>
              <dd><?php echo e($latestAddress !== '' ? $latestAddress : 'No delivery address yet'); ?></dd>
            </div>
            <div>
              <dt>Payment</dt>
              <dd><?php echo e($latestPayment !== '' ? $latestPayment : 'Choose at checkout'); ?></dd>
            </div>
          </dl>

          <a class="button button-secondary" href="settings.php"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit account</a>
        </aside>
      </section>
    </section>
  </main>

  <?php include __DIR__ . '/components/footer.php'; ?>

  <script src="user-js/script.js"></script>
</body>
</html>
