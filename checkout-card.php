<?php
require_once __DIR__ . '/components/app.php';

if (!app_is_logged_in()) {
    header('Location: cart.php');
    exit();
}

if (app_cart_count() < 1) {
    header('Location: cart.php');
    exit();
}

$_SESSION['checkout_payment_method'] = 'card';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    app_require_csrf();

    $_SESSION['checkout_payment_confirmed'] = true;
    header('Location: checkout-address.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Card Payment | JKC Cafe</title>
  <meta name="description" content="Enter card details for the JKC Cafe checkout demo.">
  <link rel="icon" type="image/svg+xml" href="./images/logo.svg">
  <link rel="stylesheet" href="user-css/style.css">
  <link rel="stylesheet" href="user-css/cart-checkout.css">
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>
  <?php include __DIR__ . '/components/navbar.php'; ?>

  <main id="main" class="checkout-page">
    <section class="section checkout-flow" aria-labelledby="card-title">
      <div class="checkout-card checkout-payment-card" data-reveal>
        <nav class="checkout-progress" aria-label="Checkout progress">
          <ol>
            <li class="is-complete">
              <span>1</span>
              <strong>Cart</strong>
            </li>
            <li class="is-active">
              <span>2</span>
              <strong>Payment</strong>
            </li>
            <li>
              <span>3</span>
              <strong>Delivery</strong>
            </li>
          </ol>
        </nav>

        <div class="checkout-card-head">
          <p class="eyebrow">Card payment</p>
          <h1 id="card-title">Use a card for this cafe order.</h1>
          <p>This demo step does not store card credentials. Add the details, then continue to the delivery address.</p>
          <div class="checkout-assurance-strip" aria-label="Card payment notes">
            <span>Demo only</span>
            <span>No card storage</span>
            <span>Delivery next</span>
          </div>
        </div>

        <form class="checkout-details-form" action="checkout-card.php" method="post">
          <?php echo app_csrf_field(); ?>
          <div class="form-row form-row-full">
            <label for="card-name">Name on card</label>
            <input id="card-name" name="card_name" type="text" autocomplete="cc-name" maxlength="100" required data-input-glow>
          </div>
          <div class="form-row form-row-full">
            <label for="card-number">Card number</label>
            <input id="card-number" name="card_number" type="text" inputmode="numeric" autocomplete="cc-number" placeholder="1234 5678 9012 3456" maxlength="24" required data-input-glow>
          </div>
          <div class="form-row">
            <label for="card-expiry">Expiry</label>
            <input id="card-expiry" name="card_expiry" type="text" placeholder="MM/YY" autocomplete="cc-exp" maxlength="7" required data-input-glow>
          </div>
          <div class="form-row">
            <label for="card-cvc">CVC</label>
            <input id="card-cvc" name="card_cvc" type="text" inputmode="numeric" autocomplete="cc-csc" placeholder="123" maxlength="4" required data-input-glow>
          </div>
          <p class="checkout-secure-note form-row-full">Demo checkout only. JKC Cafe does not store these card details.</p>
          <div class="form-actions form-row-full">
            <a class="button button-secondary" href="cart.php">Back to cart</a>
            <button class="button button-primary" type="submit">Continue to delivery</button>
          </div>
        </form>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/components/footer.php'; ?>
  <script src="user-js/script.js"></script>
</body>
</html>
