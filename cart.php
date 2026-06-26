<?php
require_once __DIR__ . '/components/app.php';

app_seed_menu_products();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT) ?: 0;

    if ($action === 'add') {
        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT) ?: 1;
        $addedToCart = app_add_to_cart($productId, $quantity);
        $returnTo = (string) ($_POST['return_to'] ?? 'cart.php');
        if (
            $returnTo === ''
            || preg_match('/[\r\n]/', $returnTo)
            || str_starts_with($returnTo, 'http')
            || str_contains($returnTo, '//')
        ) {
            $returnTo = 'cart.php';
        }
        if (!$addedToCart && str_contains($returnTo, 'added=1')) {
            $returnTo = 'menu.php';
        }
        header('Location: ' . ($returnTo !== '' ? $returnTo : 'cart.php'));
        exit();
    }

    if ($action === 'update') {
        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT) ?: 0;
        app_update_cart_item($productId, $quantity);
        header('Location: cart.php');
        exit();
    }

    if ($action === 'remove') {
        app_remove_cart_item($productId);
        header('Location: cart.php');
        exit();
    }

    if ($action === 'checkout') {
        if (!app_is_logged_in()) {
            $message = 'Please log in before checking out.';
            $messageType = 'error';
        } elseif (app_cart_count() < 1) {
            $message = 'Your cart is empty.';
            $messageType = 'error';
        } else {
            $paymentMethod = (string) ($_POST['payment_method'] ?? 'cash_on_delivery');
            $allowedMethods = ['card', 'gcash', 'cash_on_delivery'];
            $paymentMethod = in_array($paymentMethod, $allowedMethods, true) ? $paymentMethod : 'cash_on_delivery';
            $_SESSION['checkout_payment_method'] = $paymentMethod;

            if ($paymentMethod === 'gcash') {
                header('Location: checkout-gcash.php');
                exit();
            }

            if ($paymentMethod === 'card') {
                header('Location: checkout-card.php');
                exit();
            }

            header('Location: checkout-address.php');
            exit();
        }
    }
}

$items = app_cart_items();
$subtotal = app_cart_subtotal($items);
$deliveryFee = APP_DELIVERY_FEE;
$total = $subtotal + ($items === [] ? 0 : $deliveryFee);
$isLoggedIn = app_is_logged_in();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cart | JKC Cafe</title>
  <meta name="description" content="Review your JKC Cafe cart and choose a checkout payment method.">
  <link rel="icon" type="image/svg+xml" href="./images/logo.svg">
  <link rel="stylesheet" href="user-css/style.css">
  <link rel="stylesheet" href="user-css/cart-checkout.css">
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>

  <?php include __DIR__ . '/components/navbar.php'; ?>

  <main id="main" class="cart-page">
    <section class="section cart-hero" aria-labelledby="cart-title" data-reveal>
      <div>
        <p class="eyebrow">Order review</p>
        <h1 id="cart-title">Make sure your cafe run looks right.</h1>
        <p>Adjust quantities, pick a payment style, and keep checkout calm before the kitchen gets your order.</p>
        <div class="cart-hero-trust" aria-label="Checkout details">
          <span>Freshly prepared</span>
          <span>Delivery fee shown</span>
          <span>Secure checkout steps</span>
        </div>
      </div>

      <nav class="checkout-progress" aria-label="Checkout progress">
        <ol>
          <li class="is-active">
            <span>1</span>
            <strong>Cart</strong>
          </li>
          <li>
            <span>2</span>
            <strong>Payment</strong>
          </li>
          <li>
            <span>3</span>
            <strong>Delivery</strong>
          </li>
        </ol>
      </nav>
    </section>

    <section class="section cart-layout" aria-label="Cart details">
      <div class="cart-items">
        <?php if ($message !== ''): ?>
          <p class="cart-alert <?php echo $messageType === 'error' ? 'is-error' : ''; ?>" role="alert"><?php echo e($message); ?></p>
        <?php endif; ?>

        <?php if ($items === []): ?>
          <div class="cart-empty" data-reveal>
            <span class="cart-empty-icon" aria-hidden="true">JKC</span>
            <h2>Your cart is waiting for something good.</h2>
            <p>Start with a latte, pastry, or a warm cafe favorite and your order summary will appear here.</p>
            <a class="button button-primary" href="menu.php">Browse menu</a>
          </div>
        <?php else: ?>
          <?php foreach ($items as $item): ?>
            <article class="cart-item" data-reveal>
              <img src="<?php echo e($item['image']); ?>" alt="<?php echo e($item['name']); ?> from JKC Cafe" loading="lazy">
              <div class="cart-item-copy">
                <span class="menu-tag"><?php echo e($item['category']); ?></span>
                <h2><?php echo e($item['name']); ?></h2>
                <p><?php echo e(app_format_money((float) $item['price'])); ?> each · prepared fresh for pickup or delivery.</p>
              </div>
              <form class="cart-quantity" action="cart.php" method="post">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="product_id" value="<?php echo e((string) $item['id']); ?>">
                <label for="quantity-<?php echo e((string) $item['id']); ?>">Qty</label>
                <input id="quantity-<?php echo e((string) $item['id']); ?>" name="quantity" type="number" min="0" max="99" value="<?php echo e((string) $item['quantity']); ?>" data-input-glow>
                <button type="submit">Update</button>
              </form>
              <div class="cart-item-total">
                <strong><?php echo e(app_format_money((float) $item['line_total'])); ?></strong>
                <form action="cart.php" method="post">
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="product_id" value="<?php echo e((string) $item['id']); ?>">
                  <button type="submit">Remove</button>
                </form>
              </div>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <aside class="cart-summary" aria-labelledby="summary-title" data-reveal>
        <div class="cart-summary-head">
          <p class="eyebrow">Checkout</p>
          <h2 id="summary-title">Order summary</h2>
          <p class="cart-summary-note"><?php echo app_cart_count(); ?> item<?php echo app_cart_count() === 1 ? '' : 's'; ?> ready for the next step.</p>
        </div>
        <dl>
          <div>
            <dt>Subtotal</dt>
            <dd><?php echo e(app_format_money($subtotal)); ?></dd>
          </div>
          <div>
            <dt>Delivery fee</dt>
            <dd><?php echo $items === [] ? 'P0.00' : e(app_format_money($deliveryFee)); ?></dd>
          </div>
          <div class="cart-total">
            <dt>Total</dt>
            <dd><?php echo e(app_format_money($total)); ?></dd>
          </div>
        </dl>

        <form class="checkout-form" action="cart.php" method="post">
          <input type="hidden" name="action" value="checkout">
          <fieldset>
            <legend>Payment method</legend>
            <label class="payment-option">
              <input type="radio" name="payment_method" value="card">
              <span aria-hidden="true">Card</span>
              <strong>Card</strong>
              <small>Use the secure demo card step before delivery details.</small>
            </label>
            <label class="payment-option">
              <input type="radio" name="payment_method" value="gcash">
              <span aria-hidden="true">GCash</span>
              <strong>GCash</strong>
              <small>Scan the cafe QR, then continue with your address.</small>
            </label>
            <label class="payment-option">
              <input type="radio" name="payment_method" value="cash_on_delivery" checked>
              <span aria-hidden="true">COD</span>
              <strong>Cash on Delivery</strong>
              <small>Pay when your order arrives.</small>
            </label>
          </fieldset>

          <?php if (!$isLoggedIn): ?>
            <p class="checkout-note">Please <a href="login.php?redirect=cart">log in</a> to continue checkout.</p>
          <?php endif; ?>

          <p class="checkout-microcopy">You can review delivery details before the order is placed.</p>
          <button class="button button-primary" type="submit" <?php echo !$isLoggedIn || $items === [] ? 'disabled' : ''; ?>>Proceed to checkout</button>
        </form>
      </aside>
    </section>
  </main>

  <?php include __DIR__ . '/components/footer.php'; ?>

  <script src="user-js/script.js"></script>
</body>
</html>
