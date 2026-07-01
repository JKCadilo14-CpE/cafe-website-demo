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

$_SESSION['checkout_payment_method'] = 'gcash';

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
  <title>GCash Payment | JKC Cafe</title>
  <meta name="description" content="Scan the JKC Cafe GCash QR code before entering your delivery address.">
  <link rel="icon" type="image/svg+xml" href="./images/logo.svg">
  <link rel="stylesheet" href="user-css/style.css">
  <link rel="stylesheet" href="user-css/cart-checkout.css">
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>
  <?php include __DIR__ . '/components/navbar.php'; ?>

  <main id="main" class="checkout-page">
    <section class="section checkout-flow" aria-labelledby="gcash-title">
      <div class="checkout-card checkout-payment-card checkout-gcash-card" data-reveal>
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

        <div class="checkout-payment-layout">
          <div class="checkout-card-head">
            <p class="eyebrow">GCash payment</p>
            <h1 id="gcash-title">Scan the cafe QR and keep the receipt.</h1>
            <p>Use your GCash app to scan the code, pay the cart total, then continue to delivery details once payment is done.</p>
            <ul class="checkout-help-list" aria-label="GCash payment notes">
              <li>Keep the amount equal to your cart total.</li>
              <li>Use your order name as the payment note.</li>
              <li>Open the QR full size if your camera needs a larger scan target.</li>
            </ul>
          </div>

          <figure class="checkout-qr-panel">
            <div class="checkout-qr-scan">
              <img src="own%20qr%20code/QR-Code.jpg" width="1080" height="1958" alt="Zoomed JKC Cafe GCash QR code for scanning.">
            </div>
            <img class="checkout-qr checkout-qr-full" src="own%20qr%20code/QR-Code.jpg" width="1080" height="1958" alt="Full JKC Cafe GCash payment details.">
            <figcaption>Scan the enlarged QR, then keep your GCash receipt until delivery is confirmed.</figcaption>
            <a class="checkout-qr-link" href="own%20qr%20code/QR-Code.jpg" target="_blank" rel="noopener">Open QR full size</a>
          </figure>
        </div>

        <p class="checkout-secure-note">Demo checkout only. JKC Cafe does not process real GCash payments.</p>

        <div class="checkout-qr-actions">
          <a class="button button-secondary" href="cart.php">Back to cart</a>
          <form action="checkout-gcash.php" method="post">
            <?php echo app_csrf_field(); ?>
            <button class="button button-primary" type="submit">I have paid</button>
          </form>
        </div>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/components/footer.php'; ?>
  <script src="user-js/script.js"></script>
</body>
</html>
