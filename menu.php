<?php
require_once __DIR__ . '/components/app.php';

$isLoggedIn = app_is_logged_in();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'product_reminder') {
    $reminderResult = 'login_required';

    if ($isLoggedIn) {
        $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT) ?: 0;
        $reminderResult = app_save_product_reminder((int) $_SESSION['user_id'], $productId);
    }

    header('Location: menu.php?reminder=' . rawurlencode($reminderResult) . '#menu-products-title');
    exit();
}

$menuProducts = app_public_menu_products();
$pendingReminderProductIds = $isLoggedIn ? app_user_pending_product_reminder_ids((int) $_SESSION['user_id']) : [];
$menuImageDimensions = [
    'images/signature-latte.png' => [1402, 1122],
    'images/caramel-cloud-coffee.png' => [1448, 1086],
    'images/mocha-cream-latte.png' => [1402, 1122],
    'images/croissant-card.png' => [1448, 1086],
    'images/blueberry-muffin.png' => [1448, 1086],
    'images/ham-cheese-toast.png' => [1405, 1119],
    'images/americano.png' => [1402, 1122],
    'images/cappuccino.png' => [1448, 1086],
    'images/vanilla-latte.png' => [1451, 1084],
    'images/iced-americano.png' => [1448, 1086],
    'images/iced-coffee-card.png' => [1448, 1086],
    'images/cold-brew.png' => [1402, 1122],
];

$menuImageSize = static function (string $image) use ($menuImageDimensions): array {
    return $menuImageDimensions[$image] ?? [1448, 1086];
};

$menuProductCount = count($menuProducts);
$menuCategoryCounts = [];
$menuCategoryLabels = [];

foreach ($menuProducts as $product) {
    $category = trim((string) ($product['category'] ?? 'Uncategorized'));

    if ($category === '') {
        $category = 'Uncategorized';
    }

    $categoryKey = strtolower($category);

    if (!isset($menuCategoryCounts[$categoryKey])) {
        $menuCategoryCounts[$categoryKey] = 0;
        $menuCategoryLabels[$categoryKey] = $category;
    }

    $menuCategoryCounts[$categoryKey]++;
}

$menuCategoryCount = count($menuCategoryCounts);

if (isset($_GET['added'])) {
    $addedMessage = 'Item added to your cart.';
} else {
    $addedMessage = '';
}

$reminderMessage = '';
$reminderMessageType = '';

if (isset($_GET['reminder'])) {
    $reminderResult = (string) $_GET['reminder'];
    $reminderMessage = match ($reminderResult) {
        'created' => 'Reminder set. We will notify you here when that item is available.',
        'exists' => 'You already have a reminder set for that item.',
        'available' => 'That item is available now. You can add it to your cart.',
        'login_required' => 'Please log in before setting an availability reminder.',
        'invalid' => 'Unable to set a reminder for that item.',
        default => 'Unable to set that reminder right now. Please try again later.',
    };
    $reminderMessageType = in_array($reminderResult, ['created', 'exists', 'available'], true) ? 'success' : 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Menu | JKC Cafe</title>
  <meta name="description" content="Browse the JKC Cafe menu with hot coffee, iced coffee, featured drinks, pastries, and simple cafe food.">
  <link rel="icon" type="image/svg+xml" href="./images/logo.svg">
  <link rel="stylesheet" href="user-css/style.css">
  <link rel="stylesheet" href="user-css/menu.css">
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>

  <?php include __DIR__ . '/components/navbar.php'; ?>

  <main id="main" class="menu-page">
    <section class="section menu-hero" aria-labelledby="menu-page-title">
      <div class="menu-hero-content" data-reveal>
        <div class="menu-hero-copy">
          <p class="eyebrow">JKC Cafe menu</p>
          <h1 id="menu-page-title">Find your coffee break without the long wait.</h1>
          <p>
            Browse warm cups, iced favorites, pastries, and simple bites in one focused menu built for quick everyday ordering.
          </p>
          <div class="menu-hero-actions" aria-label="Menu shortcuts">
            <a class="button button-primary" href="#menu-products-title">Start Order</a>
            <a class="button button-secondary" href="#favorites-title">Quick picks</a>
          </div>
          <dl class="menu-hero-stats" aria-label="Menu overview">
            <div>
              <dt><?php echo e((string) $menuProductCount); ?></dt>
              <dd><?php echo $menuProductCount === 1 ? 'item' : 'items'; ?></dd>
            </div>
            <div>
              <dt><?php echo e((string) $menuCategoryCount); ?></dt>
              <dd><?php echo $menuCategoryCount === 1 ? 'category' : 'categories'; ?></dd>
            </div>
            <div>
              <dt>Fresh</dt>
              <dd>daily picks</dd>
            </div>
          </dl>
        </div>
      </div>

      <div class="menu-hero-visual" data-reveal>
        <figure class="menu-hero-media">
          <img src="images/menu-hero-banner.png" width="1808" height="870" alt="Warm cafe counter with coffee cups and pastries in natural light." fetchpriority="high" decoding="async">
          <figcaption>Counter favorites, ready for quick stops.</figcaption>
        </figure>
        <aside class="menu-hero-note" aria-label="Ordering note">
          <span>Order-friendly menu</span>
          <p>Pick a category, search by craving, and add favorites straight to your cart.</p>
        </aside>
      </div>
    </section>

    <section class="section menu-favorites" aria-labelledby="favorites-title">
      <div class="section-heading section-heading-row" data-reveal>
        <div>
          <p class="eyebrow">Today's favorites</p>
          <h2 id="favorites-title">Quick picks for the first scroll.</h2>
        </div>
        <p class="section-kicker">Three easy starting points when you already know the mood.</p>
      </div>

      <div class="favorite-grid">
        <article class="favorite-card" data-reveal>
          <img src="images/signature-latte.png" width="1402" height="1122" alt="Signature latte with smooth foam in a white ceramic cup." loading="lazy" decoding="async">
          <div>
            <span class="favorite-label">01 / Smooth</span>
            <span class="menu-tag">Featured Drinks</span>
            <h3>Signature Latte</h3>
            <p>Balanced, smooth, and easy to pair with breakfast.</p>
            <a href="#signature-latte">View item</a>
          </div>
        </article>
        <article class="favorite-card" data-reveal>
          <img src="images/croissant-card.png" width="1448" height="1086" alt="Golden butter croissant on a white plate." loading="lazy" decoding="async">
          <div>
            <span class="favorite-label">02 / Flaky</span>
            <span class="menu-tag">Featured Food</span>
            <h3>Butter Croissant</h3>
            <p>Flaky, simple, and best beside a hot cup.</p>
            <a href="#butter-croissant">View item</a>
          </div>
        </article>
        <article class="favorite-card" data-reveal>
          <img src="images/iced-coffee-card.png" width="1448" height="1086" alt="Iced caramel coffee in a clear glass with creamy coffee layers." loading="lazy" decoding="async">
          <div>
            <span class="favorite-label">03 / Chilled</span>
            <span class="menu-tag">Iced Coffee</span>
            <h3>Iced Caramel Coffee</h3>
            <p>Cool, creamy, and made for warm afternoons.</p>
            <a href="#iced-caramel-coffee">View item</a>
          </div>
        </article>
      </div>
    </section>

    <section class="section menu-shop" aria-labelledby="menu-products-title">
      <aside class="menu-sidebar" aria-labelledby="menu-categories-title" data-reveal>
        <div class="menu-sidebar-head">
          <p class="eyebrow">Filter</p>
          <h2 id="menu-categories-title">Categories</h2>
        </div>
        <div class="menu-filter-list" data-menu-filters>
          <button class="menu-filter is-active" type="button" data-category="all" aria-pressed="true"><span>All</span><small><?php echo e((string) $menuProductCount); ?></small></button>
          <?php foreach ($menuCategoryCounts as $categoryKey => $categoryCount): ?>
            <button class="menu-filter" type="button" data-category="<?php echo e($categoryKey); ?>" aria-pressed="false"><span><?php echo e($menuCategoryLabels[$categoryKey]); ?></span><small><?php echo e((string) $categoryCount); ?></small></button>
          <?php endforeach; ?>
        </div>
        <p class="menu-sidebar-note">Filters update the grid instantly and keep your place on the page.</p>
      </aside>

      <div class="menu-products" data-reveal>
        <div class="menu-products-head">
          <div class="menu-products-title">
            <p class="eyebrow">Browse items</p>
            <h2 id="menu-products-title">Cafe menu</h2>
            <div class="menu-products-meta">
              <p class="menu-result-count" aria-live="polite" data-menu-count>Showing <?php echo e((string) $menuProductCount); ?> <?php echo $menuProductCount === 1 ? 'item' : 'items'; ?></p>
              <p>Search by drink, pastry, or category.</p>
            </div>
          </div>
          <div class="menu-products-tools">
            <form class="menu-search" role="search" aria-label="Search menu" data-menu-search-form>
              <label for="menu-search-input">Search menu</label>
              <div class="menu-search-control">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                  <path d="m21 21-4.4-4.4"></path>
                  <circle cx="11" cy="11" r="7"></circle>
                </svg>
                <input id="menu-search-input" type="search" placeholder="Search coffee, pastry, or category" autocomplete="off" data-menu-search>
                <button class="menu-search-clear" type="button" aria-label="Clear menu search" hidden data-menu-clear>
                  <span aria-hidden="true">&times;</span>
                </button>
              </div>
            </form>
          </div>
        </div>

        <?php if ($addedMessage !== ''): ?>
          <p class="cart-alert" role="status"><?php echo e($addedMessage); ?></p>
        <?php endif; ?>
        <?php if ($reminderMessage !== ''): ?>
          <p class="cart-alert <?php echo $reminderMessageType === 'error' ? 'is-error' : ''; ?>" role="<?php echo $reminderMessageType === 'error' ? 'alert' : 'status'; ?>"><?php echo e($reminderMessage); ?></p>
        <?php endif; ?>

        <div class="menu-product-grid" data-menu-grid>
          <?php foreach ($menuProducts as $product): ?>
            <?php
              $productId = (int) ($product['id'] ?? 0);
              $slug = app_slug($product['name']);
              $categorySearch = strtolower($product['category']);
              [$imageWidth, $imageHeight] = $menuImageSize((string) $product['image']);
              $isOrderable = $productId > 0 && (bool) ($product['is_orderable'] ?? false);
              $productStatus = app_normalize_product_status((string) ($product['status'] ?? 'available'));
              $productStatusClass = str_replace('_', '-', $productStatus);
              $productStatusLabel = app_product_status_label($productStatus);
              $hasPendingReminder = isset($pendingReminderProductIds[$productId]);
            ?>
            <article class="product-card" id="<?php echo e($slug); ?>" data-product data-name="<?php echo e($product['name']); ?>" data-category="<?php echo e($categorySearch); ?>" data-description="<?php echo e($product['search']); ?>">
              <img src="<?php echo e($product['image']); ?>" width="<?php echo e((string) $imageWidth); ?>" height="<?php echo e((string) $imageHeight); ?>" alt="<?php echo e($product['alt']); ?>" loading="lazy" decoding="async">
              <div class="product-card-body">
                <span class="menu-tag"><?php echo e($product['category']); ?></span>
                <?php if ($productStatus !== 'available'): ?>
                  <span class="menu-tag product-availability <?php echo e($productStatusClass); ?>"><?php echo e($productStatusLabel); ?></span>
                <?php endif; ?>
                <h3><?php echo e($product['name']); ?></h3>
                <p><?php echo e($product['description']); ?></p>
                <div class="product-card-footer">
                  <strong><?php echo $isOrderable ? e(app_format_money((float) $product['price'])) : 'Price unavailable'; ?></strong>
                  <form action="cart.php" method="post">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?php echo e((string) $productId); ?>">
                    <input type="hidden" name="quantity" value="1">
                    <input type="hidden" name="return_to" value="menu.php?added=1#<?php echo e($slug); ?>">
                    <button type="submit" <?php echo !$isOrderable ? 'disabled' : ''; ?>>Add to cart</button>
                  </form>
                </div>
                <?php if ($productStatus !== 'available'): ?>
                  <div class="product-reminder-row">
                    <?php if ($isLoggedIn): ?>
                      <?php if ($hasPendingReminder): ?>
                        <button type="button" disabled>Reminder set</button>
                      <?php else: ?>
                        <form action="menu.php" method="post">
                          <input type="hidden" name="action" value="product_reminder">
                          <input type="hidden" name="product_id" value="<?php echo e((string) $productId); ?>">
                          <button type="submit">Notify me when available</button>
                        </form>
                      <?php endif; ?>
                    <?php else: ?>
                      <a class="product-reminder-login" href="login.php">Log in to get notified.</a>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>

        <div class="menu-empty" hidden data-menu-empty>
          <h3>No menu items found</h3>
          <p>Try a different search term or choose another category.</p>
        </div>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/components/footer.php'; ?>

  <script src="user-js/script.js"></script>
  <script src="user-js/menu.js"></script>
</body>
</html>
