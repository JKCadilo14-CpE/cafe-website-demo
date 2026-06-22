<?php require_once __DIR__ . '/components/app.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Rewards | JKC Cafe</title>
  <meta name="description" content="Join JKC Cafe Rewards to earn points on coffee, pastries, and everyday cafe visits.">
  <link rel="icon" type="image/png" href="./images/logo.svg">
  <link rel="stylesheet" href="user-css/style.css">
  <link rel="stylesheet" href="user-css/rewards.css">
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>

  <?php include __DIR__ . '/components/navbar.php'; ?>

  <main id="main" class="rewards-page">
    <section class="section rewards-hero" aria-labelledby="rewards-title">
      <div class="rewards-hero-copy" data-reveal>
        <p class="eyebrow">JKC Cafe Rewards</p>
        <h1 id="rewards-title">Make your usual coffee run count.</h1>
        <p>
          Join free, earn points on the drinks and pastries you already love, and trade them for small cafe treats when your day needs a lift.
        </p>
        <div class="hero-actions rewards-hero-actions" aria-label="Rewards actions">
          <a class="button button-primary" href="signup.php">Join free</a>
          <a class="button button-secondary" href="menu.php">Browse menu</a>
        </div>

        <section class="rewards-card-preview" aria-labelledby="rewards-preview-title" data-reveal>
          <div class="rewards-card-topline">
            <p id="rewards-preview-title">Member card</p>
            <strong>240 <span>pts</span></strong>
          </div>
          <p class="rewards-card-note">Only 60 points from a flaky pastry reward.</p>
          <div class="rewards-progress" aria-hidden="true">
            <span></span>
          </div>
          <ul class="rewards-card-chips" aria-label="Preview benefits">
            <li>Drink upgrades</li>
            <li>Birthday treat</li>
            <li>Member offers</li>
          </ul>
        </section>

        <div class="rewards-snapshot" aria-label="Rewards program highlights">
          <div>
            <strong>Join free</strong>
            <span>Create your cafe account once, no card needed.</span>
          </div>
          <div>
            <strong>Earn points</strong>
            <span>Collect points on coffee, pastries, and bites.</span>
          </div>
          <div>
            <strong>Use perks</strong>
            <span>Redeem points for small everyday rewards.</span>
          </div>
        </div>
      </div>

      <div class="rewards-hero-visual" data-reveal>
        <figure class="rewards-hero-media">
          <img src="images/rewards-cafe.png" width="1717" height="916" alt="Cafe table with coffee, pastry, and a simple rewards card in warm natural light." fetchpriority="high">
          <figcaption>Earn points on the coffee you already love.</figcaption>
        </figure>
        <aside class="rewards-hero-note" aria-label="Rewards note">
          <span>Start simple</span>
          <p>Sign up once, order as usual, and let your everyday visits build toward treats.</p>
        </aside>
      </div>
    </section>

    <section class="section rewards-steps" aria-labelledby="steps-title">
      <div class="section-heading section-heading-row" data-reveal>
        <div>
          <p class="eyebrow">How it works</p>
          <h2 id="steps-title">Three steps, no complicated rules.</h2>
        </div>
        <p class="section-kicker">Rewards should feel like a thank-you, not homework. Join, order, and redeem when something sounds good.</p>
      </div>

      <div class="rewards-step-grid">
        <article data-reveal>
          <span aria-hidden="true">01</span>
          <p class="rewards-card-label">Create account</p>
          <h3>Join in a minute</h3>
          <p>Create your JKC Cafe account once, then keep using it for quick coffee stops and slow pastry breaks.</p>
        </article>
        <article data-reveal>
          <span aria-hidden="true">02</span>
          <p class="rewards-card-label">Order normally</p>
          <h3>Earn as you go</h3>
          <p>Collect points on warm lattes, iced coffee, croissants, muffins, and the menu favorites you come back for.</p>
        </article>
        <article data-reveal>
          <span aria-hidden="true">03</span>
          <p class="rewards-card-label">Enjoy perks</p>
          <h3>Redeem small treats</h3>
          <p>Turn points into simple rewards like drink upgrades, pastry moments, birthday extras, and member offers.</p>
        </article>
      </div>
    </section>

    <section class="section rewards-feature" aria-labelledby="rewards-feature-title">
      <figure class="rewards-feature-media" data-reveal>
        <img src="images/rewards-moment.png" width="1774" height="887" alt="Coffee, pastry, and a rewards card arranged on a warm cafe table." loading="lazy" decoding="async">
      </figure>
      <div class="rewards-feature-copy" data-reveal>
        <p class="eyebrow">Built for regular days</p>
        <h2 id="rewards-feature-title">Rewards for the coffee you were already going to order.</h2>
        <p>
          No punch-card clutter, no guessing game. JKC Cafe Rewards keeps the promise simple: your everyday orders move you closer to a little extra on the house.
        </p>
        <ul class="rewards-feature-list" aria-label="Rewards experience highlights">
          <li>Easy signup before your first order.</li>
          <li>Member perks shaped around drinks, pastries, and quick cafe visits.</li>
          <li>Clear next-step CTAs whenever you are ready to join or browse.</li>
        </ul>
      </div>
    </section>

    <section class="section rewards-perks" aria-labelledby="perks-title">
      <div class="section-heading section-heading-row" data-reveal>
        <div>
          <p class="eyebrow">Member perks</p>
          <h2 id="perks-title">Little extras that feel useful.</h2>
        </div>
        <p class="section-kicker">Small, practical rewards for mornings, study sessions, afternoon resets, and weekend pastry plans.</p>
      </div>

      <div class="rewards-perk-grid">
        <article data-reveal>
          <span aria-hidden="true">Birthday</span>
          <h3>Birthday treat</h3>
          <p>A small cafe surprise during your birthday month, made for celebrating without overthinking it.</p>
        </article>
        <article data-reveal>
          <span aria-hidden="true">Preview</span>
          <h3>Early menu previews</h3>
          <p>Get a first look at new drinks and seasonal pastries before they settle into the full menu.</p>
        </article>
        <article data-reveal>
          <span aria-hidden="true">Points</span>
          <h3>Coffee points</h3>
          <p>Earn toward rewards when you order hot cups, iced coffee, and featured cafe favorites.</p>
        </article>
        <article data-reveal>
          <span aria-hidden="true">Offers</span>
          <h3>Member offers</h3>
          <p>Receive simple offers made for quick stops, slow mornings, and afternoon breaks.</p>
        </article>
      </div>
    </section>

    <section class="section rewards-callout" aria-labelledby="callout-title" data-reveal>
      <div>
        <p class="eyebrow">Start earning</p>
        <h2 id="callout-title">Join today, then let your next cup do the work.</h2>
        <p>Create your account before you browse, or peek at the menu first and come back when your order is calling.</p>
      </div>
      <div class="rewards-callout-actions">
        <a class="button button-primary" href="signup.php">Join rewards</a>
        <a class="button button-secondary" href="menu.php">Browse menu</a>
      </div>
    </section>
  </main>

  <?php include __DIR__ . '/components/footer.php'; ?>

  <script src="user-js/script.js"></script>
</body>
</html>
