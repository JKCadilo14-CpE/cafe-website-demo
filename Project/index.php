<?php require_once __DIR__ . '/components/app.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>JKC Cafe</title>
  <meta name="description" content="JKC Cafe serves fresh coffee, pastries, and simple cafe favorites in a warm modern space.">
  <link rel="icon" type="image/svg+xml" href="./images/logo.svg">
  <link rel="stylesheet" href="user-css/style.css">
  <link rel="stylesheet" href="user-css/home.css">
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>

  <?php include __DIR__ . '/components/navbar.php'; ?>

  <main id="main" class="landing-page">
    <section class="hero section" aria-labelledby="hero-title">
      <div class="hero-copy" data-reveal>
        <p class="eyebrow">Fresh coffee, honest comfort</p>
        <h1 id="hero-title">Make room for a better coffee break.</h1>
        <p>
          JKC Cafe serves smooth espresso, chilled favorites, and fresh pastries for slow mornings, study breaks, and quick everyday orders.
        </p>
        <div class="hero-actions" aria-label="Cafe actions">
          <a class="button button-primary" href="menu.php">View Menu</a>
          <a class="button button-secondary" href="signup.php">Create Account</a>
        </div>
        <dl class="hero-stats" aria-label="Cafe highlights">
          <div>
            <dt>Fresh</dt>
            <dd>daily brews and pastries</dd>
          </div>
          <div>
            <dt>12</dt>
            <dd>easy menu favorites</dd>
          </div>
          <div>
            <dt>Fast</dt>
            <dd>browse, cart, and checkout</dd>
          </div>
        </dl>
      </div>

      <div class="hero-visual" data-reveal>
        <figure class="hero-media">
          <img src="images/cafe-hero.png" width="1774" height="887" alt="Coffee cups and pastries on a bright modern cafe counter." fetchpriority="high" decoding="async">
          <figcaption>Fresh from the counter</figcaption>
        </figure>
        <article class="hero-product-card" aria-label="Featured cafe pairing">
          <img src="images/latte-card.png" width="1448" height="1086" alt="Classic latte with smooth foam in a ceramic cup." decoding="async">
          <div>
            <span class="menu-tag">Best with pastry</span>
            <p class="hero-product-title">Signature Latte</p>
            <p>Balanced espresso, soft milk, calm finish.</p>
          </div>
        </article>
        <p class="hero-visit-note">Built for dine-in pauses and quick online ordering.</p>
      </div>
    </section>

    <section class="section categories" aria-labelledby="categories-title">
      <div class="section-heading compact-heading" data-reveal>
        <p class="eyebrow">Explore the menu</p>
        <h2 id="categories-title">Start with what you are craving.</h2>
      </div>

      <div class="category-track" aria-label="Menu categories">
        <a class="category-card" href="menu.php?category=featured-drinks#menu-products-title" data-reveal>
          <strong>Featured Drinks</strong>
          <span>House favorites and creamy signatures.</span>
          <small>3 picks</small>
        </a>
        <a class="category-card" href="menu.php?category=featured-food#menu-products-title" data-reveal>
          <strong>Featured Food</strong>
          <span>Pastries and easy cafe bites.</span>
          <small>3 picks</small>
        </a>
        <a class="category-card" href="menu.php?category=hot-coffee#menu-products-title" data-reveal>
          <strong>Hot Coffee</strong>
          <span>Comforting cups for slower moments.</span>
          <small>3 picks</small>
        </a>
        <a class="category-card" href="menu.php?category=iced-coffee#menu-products-title" data-reveal>
          <strong>Iced Coffee</strong>
          <span>Cool, clean, and refreshing.</span>
          <small>3 picks</small>
        </a>
      </div>
    </section>

    <section class="section menu-preview" aria-labelledby="menu-title">
      <div class="section-heading section-heading-row" data-reveal>
        <div>
          <p class="eyebrow">Featured menu</p>
          <h2 id="menu-title">Cafe favorites with a little personality.</h2>
        </div>
        <a class="section-link" href="menu.php#menu-products-title">Browse all items</a>
      </div>

      <div class="menu-grid">
        <article class="menu-card menu-card-featured" data-reveal>
          <img src="images/signature-latte.png" width="1402" height="1122" alt="Signature latte with smooth foam in a white ceramic cup." loading="lazy" decoding="async">
          <div class="menu-card-body">
            <span class="menu-tag">Coffee</span>
            <h3>Signature Latte</h3>
            <p>Balanced espresso with steamed milk and a soft finish that works for any time of day.</p>
            <div class="menu-card-footer">
              <strong>P145</strong>
              <a href="menu.php#signature-latte">View Item</a>
            </div>
          </div>
        </article>
        <article class="menu-card" data-reveal>
          <img src="images/iced-coffee-card.png" width="1448" height="1086" alt="Iced caramel coffee in a clear glass with creamy coffee layers." loading="lazy" decoding="async">
          <div class="menu-card-body">
            <span class="menu-tag">Cold</span>
            <h3>Iced Caramel Coffee</h3>
            <p>Chilled coffee with caramel notes and a clean, creamy taste.</p>
            <div class="menu-card-footer">
              <strong>P165</strong>
              <a href="menu.php#iced-caramel-coffee">View Item</a>
            </div>
          </div>
        </article>
        <article class="menu-card" data-reveal>
          <img src="images/croissant-card.png" width="1448" height="1086" alt="Golden butter croissant on a white plate." loading="lazy" decoding="async">
          <div class="menu-card-body">
            <span class="menu-tag">Pastry</span>
            <h3>Butter Croissant</h3>
            <p>Light, flaky, and baked to pair with any hot drink.</p>
            <div class="menu-card-footer">
              <strong>P95</strong>
              <a href="menu.php#butter-croissant">View Item</a>
            </div>
          </div>
        </article>
      </div>
    </section>

    <section class="section cafe-banner" aria-labelledby="banner-title">
      <div class="banner-copy" data-reveal>
        <p class="eyebrow">The JKC pause</p>
        <h2 id="banner-title">A warm counter, a quiet table, and coffee that fits the day.</h2>
        <p>Drop by for a fresh cup or order ahead when your break needs to be simple.</p>
        <ul class="banner-list">
          <li>Small-batch pastry choices</li>
          <li>Hot and iced coffee staples</li>
          <li>Simple checkout when you are ready</li>
        </ul>
      </div>
      <figure class="banner-media" data-reveal>
        <img src="images/cafe-banner.png" width="1774" height="887" alt="Cafe counter with coffee and pastries in bright natural light." loading="lazy" decoding="async">
      </figure>
    </section>

    <section class="section highlights" aria-labelledby="highlights-title">
      <div class="section-heading" data-reveal>
        <p class="eyebrow">Why visit us</p>
        <h2 id="highlights-title">Simple details, done with care.</h2>
      </div>

      <div class="highlight-list">
        <article data-reveal>
          <span class="highlight-number">01</span>
          <h3>Fresh daily</h3>
          <p>Coffee and pastries are prepared in small batches throughout the day.</p>
        </article>
        <article data-reveal>
          <span class="highlight-number">02</span>
          <h3>Easy ordering</h3>
          <p>Browse the menu, add favorites to cart, and sign in when you are ready.</p>
        </article>
        <article data-reveal>
          <span class="highlight-number">03</span>
          <h3>Warm space</h3>
          <p>A clean, relaxed cafe setting for quick stops, study sessions, or slow breaks.</p>
        </article>
      </div>
    </section>

    <section class="section landing-cta" aria-labelledby="landing-cta-title" data-reveal>
      <div>
        <p class="eyebrow">Ready when you are</p>
        <h2 id="landing-cta-title">Find your next cup before the craving cools.</h2>
      </div>
      <div class="landing-cta-actions" aria-label="Next actions">
        <a class="button button-primary" href="menu.php#menu-products-title">Order From Menu</a>
        <a class="button button-secondary" href="contact.php">Visit & Hours</a>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/components/footer.php'; ?>

  <script src="user-js/script.js"></script>
</body>
</html>
