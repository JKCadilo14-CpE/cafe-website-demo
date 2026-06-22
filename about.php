<?php require_once __DIR__ . '/components/app.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>About JKC Cafe</title>
  <meta name="description" content="Learn about JKC Cafe, a warm neighborhood cafe for fresh coffee, simple pastries, and relaxed everyday moments.">
  <link rel="icon" type="image/png" href="./images/logo.svg">
  <link rel="stylesheet" href="user-css/style.css">
  <link rel="stylesheet" href="user-css/about.css">
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>

  <?php include __DIR__ . '/components/navbar.php'; ?>

  <main id="main" class="about-page">
    <section class="section about-hero" aria-labelledby="about-title">
      <div class="about-hero-copy" data-reveal>
        <p class="eyebrow">About JKC Cafe</p>
        <h1 id="about-title">A neighborhood cafe for better daily pauses.</h1>
        <p>
          We keep coffee familiar, pastries fresh, and the room calm enough for a quick stop, a slow catch-up, or a quiet table between plans.
        </p>
        <div class="hero-actions" aria-label="About page actions">
          <a class="button button-primary" href="menu.php">View Menu</a>
          <a class="button button-secondary" href="contact.php">Contact Us</a>
        </div>
        <dl class="about-hero-facts" aria-label="Cafe details">
          <div>
            <dt>Fresh</dt>
            <dd>daily coffee and pastries</dd>
          </div>
          <div>
            <dt>Warm</dt>
            <dd>space for quick or slow visits</dd>
          </div>
          <div>
            <dt>Easy</dt>
            <dd>menu browsing and ordering</dd>
          </div>
        </dl>
      </div>

      <div class="about-hero-visual" data-reveal>
        <figure class="about-hero-media">
          <img src="images/about-cafe.png" width="1774" height="887" alt="Warm modern cafe counter with coffee, pastries, and natural light." fetchpriority="high" decoding="async">
          <figcaption>Fresh coffee, simple comfort</figcaption>
        </figure>
        <article class="about-hero-note" aria-label="Cafe promise">
          <span>Made for everyday routines</span>
          <p>Focused menu, friendly service, and a table when the day asks for one.</p>
        </article>
      </div>
    </section>

    <section class="section about-values" aria-labelledby="values-title">
      <div class="section-heading section-heading-row" data-reveal>
        <div>
          <p class="eyebrow">What we care about</p>
          <h2 id="values-title">Simple things, handled with care.</h2>
        </div>
        <p class="section-kicker">The menu is intentionally focused so the details can stay consistent.</p>
      </div>

      <div class="about-card-grid">
        <article data-reveal>
          <span class="about-card-label">01 / Fresh</span>
          <h3>Fresh daily</h3>
          <p>We keep the menu focused so coffee and pastries can be prepared fresh throughout the day.</p>
        </article>
        <article data-reveal>
          <span class="about-card-label">02 / Service</span>
          <h3>Warm service</h3>
          <p>Every visit should feel easy, whether you are grabbing a cup to go or staying for a quiet pause.</p>
        </article>
        <article data-reveal>
          <span class="about-card-label">03 / Comfort</span>
          <h3>Simple comfort</h3>
          <p>Clean flavors, calm spaces, and familiar cafe favorites keep the experience relaxed and reliable.</p>
        </article>
      </div>
    </section>

    <section class="section about-expectations" aria-labelledby="expectations-title">
      <div class="section-heading" data-reveal>
        <p class="eyebrow">What to expect</p>
        <h2 id="expectations-title">An easy cafe stop from first sip to last bite.</h2>
      </div>

      <div class="expectation-list">
        <article data-reveal>
          <span aria-hidden="true">01</span>
          <h3>Choose a drink</h3>
          <p>Start with a hot coffee, iced favorite, or something light for the afternoon.</p>
        </article>
        <article data-reveal>
          <span aria-hidden="true">02</span>
          <h3>Pair a pastry</h3>
          <p>Add a simple pastry or snack made for quick breaks and easy mornings.</p>
        </article>
        <article data-reveal>
          <span aria-hidden="true">03</span>
          <h3>Stay or take away</h3>
          <p>Settle in for a quiet pause, or take your order with you when the day is moving.</p>
        </article>
      </div>
    </section>

    <section class="section about-visual-story" aria-labelledby="visual-story-title">
      <div class="about-visual-story-inner">
        <figure class="visual-story-media" data-reveal>
          <img src="images/about-story-moment.png" width="1774" height="887" alt="A quiet cafe table with a fresh coffee, pastry plate, and folded napkin in soft morning light." loading="lazy" decoding="async">
        </figure>
        <div class="visual-story-copy" data-reveal>
          <p class="eyebrow">Made for the day</p>
          <h2 id="visual-story-title">Fresh coffee, soft light, and a table when you need one.</h2>
          <p>
            The space is kept calm and practical, with warm cups, simple food, and room for quick catch-ups or a few quiet minutes between plans.
          </p>
          <ul class="visual-story-list">
            <li>Comfortable for quick solo visits</li>
            <li>Easy to pair drinks with pastries</li>
            <li>Bright enough for work, calm enough for a break</li>
          </ul>
        </div>
      </div>
    </section>

    <section class="section about-story" aria-labelledby="story-title">
      <div data-reveal>
        <p class="eyebrow">Our daily rhythm</p>
        <h2 id="story-title">Coffee, pastries, and a little room to slow down.</h2>
      </div>
      <div class="about-story-copy" data-reveal>
        <p>
          From the first morning pour to the last afternoon pastry, JKC Cafe is designed as a neighborhood place for small routines: a latte before work, a treat between errands, or a table for catching up.
        </p>
        <p>
          We are not trying to overcomplicate the cafe experience. Good coffee, fresh food, clear choices, and a warm welcome carry the day.
        </p>
      </div>
    </section>

    <section class="section about-cta" aria-labelledby="about-cta-title" data-reveal>
      <div>
        <p class="eyebrow">Come by soon</p>
        <h2 id="about-cta-title">Your next coffee break has a seat here.</h2>
      </div>
      <div class="about-cta-actions" aria-label="Next actions">
        <a class="button button-primary" href="menu.php#menu-products-title">Browse Menu</a>
        <a class="button button-secondary" href="contact.php">Visit & Hours</a>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/components/footer.php'; ?>

  <script src="user-js/script.js"></script>
</body>
</html>
