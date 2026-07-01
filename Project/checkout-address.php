<?php
require_once __DIR__ . '/components/app.php';

if (!app_is_logged_in()) {
    header('Location: cart.php');
    exit();
}

$items = app_cart_items();

if ($items === []) {
    header('Location: cart.php');
    exit();
}

$paymentMethod = (string) ($_SESSION['checkout_payment_method'] ?? 'cash_on_delivery');
$allowedMethods = ['card', 'gcash', 'cash_on_delivery'];
$paymentMethod = in_array($paymentMethod, $allowedMethods, true) ? $paymentMethod : 'cash_on_delivery';
$message = '';
$checkoutValues = [
    'customer_name' => '',
    'phone_number' => '',
    'delivery_address' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $checkoutValues = [
        'customer_name' => trim((string) ($_POST['customer_name'] ?? '')),
        'phone_number' => trim((string) ($_POST['phone_number'] ?? '')),
        'delivery_address' => trim((string) ($_POST['delivery_address'] ?? '')),
    ];
    $customerName = $checkoutValues['customer_name'];
    $phoneNumber = $checkoutValues['phone_number'];
    $deliveryAddress = $checkoutValues['delivery_address'];

    if ($customerName === '' || $phoneNumber === '' || $deliveryAddress === '') {
        $message = 'Please complete your delivery details.';
    } else {
        $subtotal = app_cart_subtotal($items);
        $deliveryFee = APP_DELIVERY_FEE;
        $total = $subtotal + $deliveryFee;
        $userId = (int) $_SESSION['user_id'];
        $paymentLabel = match ($paymentMethod) {
            'card' => 'Card',
            'gcash' => 'GCash',
            default => 'Cash on Delivery',
        };

        try {
            $mysqli = app_db();
            $mysqli->begin_transaction();

            $status = 'Pending';
            $deliveryPartner = 'JKC Cafe Delivery';
            $orderStatement = $mysqli->prepare(
                'INSERT INTO orders (user_id, total_amount, status, customer_name, phone_number, delivery_address, payment_method, delivery_fee, delivery_partner, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $orderStatement->bind_param(
                'idsssssds',
                $userId,
                $total,
                $status,
                $customerName,
                $phoneNumber,
                $deliveryAddress,
                $paymentLabel,
                $deliveryFee,
                $deliveryPartner
            );
            $orderStatement->execute();
            $orderId = (int) $mysqli->insert_id;
            $orderStatement->close();

            $itemStatement = $mysqli->prepare('INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)');

            foreach ($items as $item) {
                $productId = (int) $item['id'];
                $quantity = (int) $item['quantity'];
                $price = (float) $item['price'];
                $itemStatement->bind_param('iiid', $orderId, $productId, $quantity, $price);
                $itemStatement->execute();
            }

            $itemStatement->close();
            $mysqli->commit();
            $mysqli->close();

            app_clear_cart();
            unset($_SESSION['checkout_payment_method'], $_SESSION['checkout_payment_confirmed']);
            $_SESSION['last_order_id'] = $orderId;

            header('Location: order-success.php?id=' . $orderId);
            exit();
        } catch (mysqli_sql_exception $exception) {
            if (isset($mysqli) && $mysqli instanceof mysqli) {
                $mysqli->rollback();
                $mysqli->close();
            }

            $message = 'Unable to place your order right now. Please check the database connection.';
        }
    }
}

$subtotal = app_cart_subtotal($items);
$total = $subtotal + APP_DELIVERY_FEE;
$paymentLabel = match ($paymentMethod) {
    'card' => 'Card',
    'gcash' => 'GCash',
    default => 'Cash on Delivery',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Delivery Address | JKC Cafe</title>
  <meta name="description" content="Enter your delivery address to complete your JKC Cafe order.">
  <link rel="icon" type="image/svg+xml" href="./images/logo.svg">
  <link rel="stylesheet" href="user-css/style.css">
  <link rel="stylesheet" href="user-css/cart-checkout.css">
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>
  <?php include __DIR__ . '/components/navbar.php'; ?>

  <main id="main" class="checkout-page">
    <section class="section checkout-layout" aria-labelledby="address-title">
      <div class="checkout-card" data-reveal>
        <nav class="checkout-progress" aria-label="Checkout progress">
          <ol>
            <li class="is-complete">
              <span>1</span>
              <strong>Cart</strong>
            </li>
            <li class="is-complete">
              <span>2</span>
              <strong>Payment</strong>
            </li>
            <li class="is-active">
              <span>3</span>
              <strong>Delivery</strong>
            </li>
          </ol>
        </nav>

        <div class="checkout-card-head">
          <p class="eyebrow">Delivery details</p>
          <h1 id="address-title">Where should we bring your cafe order?</h1>
          <p>Payment method: <strong><?php echo e($paymentLabel); ?></strong>. Add a reachable phone number and a delivery address with enough detail for the rider.</p>
          <div class="checkout-assurance-strip" aria-label="Delivery notes">
            <span>JKC Cafe Delivery</span>
            <span>Fresh handoff</span>
            <span>Final review</span>
          </div>
        </div>

        <?php if ($message !== ''): ?>
          <p class="cart-alert is-error checkout-error" role="alert"><?php echo e($message); ?> Please check each required field below.</p>
        <?php endif; ?>

        <form class="checkout-details-form" action="checkout-address.php" method="post">
          <div class="form-row">
            <label for="customer-name">Name</label>
            <input id="customer-name" name="customer_name" type="text" autocomplete="name" maxlength="100" value="<?php echo e($checkoutValues['customer_name']); ?>" required data-input-glow>
          </div>
          <div class="form-row">
            <label for="phone-number">Phone number</label>
            <input id="phone-number" name="phone_number" type="tel" autocomplete="tel" maxlength="30" value="<?php echo e($checkoutValues['phone_number']); ?>" required data-input-glow>
          </div>
          <div class="form-row form-row-full">
            <label for="delivery-address">Delivery address</label>
            <textarea id="delivery-address" name="delivery_address" rows="5" autocomplete="street-address" maxlength="500" required data-input-glow><?php echo e($checkoutValues['delivery_address']); ?></textarea>
            <p class="checkout-field-note">Include building, street, barangay, and any rider notes that make the drop-off easier.</p>
          </div>
          <div class="form-actions form-row-full">
            <a class="button button-secondary" href="cart.php">Back to cart</a>
            <button class="button button-primary" type="submit">Place order</button>
          </div>
        </form>
      </div>

      <aside class="cart-summary checkout-side-summary" data-reveal>
        <div class="cart-summary-head">
          <p class="eyebrow">Final check</p>
          <h2>Order total</h2>
          <p class="cart-summary-note">The kitchen receives your order after this step.</p>
        </div>
        <dl>
          <div>
            <dt>Subtotal</dt>
            <dd><?php echo e(app_format_money($subtotal)); ?></dd>
          </div>
          <div>
            <dt>Delivery fee</dt>
            <dd><?php echo e(app_format_money(APP_DELIVERY_FEE)); ?></dd>
          </div>
          <div class="cart-total">
            <dt>Total</dt>
            <dd><?php echo e(app_format_money($total)); ?></dd>
          </div>
        </dl>
      </aside>
    </section>
  </main>

  <?php include __DIR__ . '/components/footer.php'; ?>
  <script src="user-js/script.js"></script>
</body>
</html>
