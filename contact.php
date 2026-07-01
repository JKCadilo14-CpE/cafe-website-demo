<?php
require_once __DIR__ . '/components/app.php';

$contactTopics = app_contact_topics();
$contactValues = [
    'name' => '',
    'email' => '',
    'topic' => '',
    'message' => '',
];
$contactErrors = [];
$contactStatus = '';
$contactStatusType = '';

if (isset($_GET['sent'])) {
    $contactStatus = 'Thanks, your message has been sent to the cafe team.';
    $contactStatusType = 'success';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    app_require_csrf();

    $contactValues = [
        'name' => trim((string) ($_POST['name'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'topic' => trim((string) ($_POST['topic'] ?? '')),
        'message' => trim((string) ($_POST['message'] ?? '')),
    ];

    if ($contactValues['name'] === '') {
        $contactErrors['name'] = 'Enter your name.';
    } elseif (strlen($contactValues['name']) > 100) {
        $contactErrors['name'] = 'Keep your name under 100 characters.';
    }

    if ($contactValues['email'] === '') {
        $contactErrors['email'] = 'Enter your email address.';
    } elseif (!filter_var($contactValues['email'], FILTER_VALIDATE_EMAIL)) {
        $contactErrors['email'] = 'Enter a valid email address.';
    } elseif (strlen($contactValues['email']) > 150) {
        $contactErrors['email'] = 'Keep your email under 150 characters.';
    }

    if ($contactValues['topic'] === '' || !array_key_exists($contactValues['topic'], $contactTopics)) {
        $contactErrors['topic'] = 'Choose a topic.';
    }

    if ($contactValues['message'] === '') {
        $contactErrors['message'] = 'Write a short message.';
    } elseif (strlen($contactValues['message']) > 2000) {
        $contactErrors['message'] = 'Keep your message under 2000 characters.';
    }

    if ($contactErrors === []) {
        try {
            app_create_contact_message(
                $contactValues['name'],
                $contactValues['email'],
                $contactValues['topic'],
                $contactValues['message']
            );

            header('Location: contact.php?sent=1#contact-form');
            exit;
        } catch (mysqli_sql_exception $exception) {
            $contactStatus = 'We could not save your message right now. Please try again in a moment.';
            $contactStatusType = 'error';
        }
    } else {
        $contactStatus = 'Please check the highlighted fields.';
        $contactStatusType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact | JKC Cafe</title>
  <meta name="description" content="Contact JKC Cafe for visits, orders, questions, and simple cafe support.">
  <link rel="icon" type="image/svg+xml" href="./images/logo.svg">
  <link rel="stylesheet" href="user-css/style.css">
  <link rel="stylesheet" href="user-css/contact.css">
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>

  <?php include __DIR__ . '/components/navbar.php'; ?>

  <main id="main" class="contact-page">
    <section class="section contact-hero" aria-labelledby="contact-title">
      <div class="contact-hero-copy" data-reveal>
        <p class="eyebrow">Contact JKC Cafe</p>
        <h1 id="contact-title">Need us? We will make it easy.</h1>
        <p>
          Ask about a visit, an order, or a small gathering. The cafe team will point you to the simplest next step.
        </p>
        <div class="hero-actions contact-hero-actions" aria-label="Contact page actions">
          <a class="button button-primary" href="#contact-form">Send a message</a>
          <a class="button button-secondary" href="menu.php">View menu</a>
        </div>

        <div class="contact-hero-facts" aria-label="Contact highlights">
          <div>
            <strong>Daily hours</strong>
            <span>Open for coffee, pastries, and quick stops.</span>
          </div>
          <div>
            <strong>Fast replies</strong>
            <span>Use the form for order questions and visit notes.</span>
          </div>
          <div>
            <strong>Easy visits</strong>
            <span>Walk-ins welcome, message ahead for small groups.</span>
          </div>
        </div>
      </div>

      <div class="contact-hero-visual" data-reveal>
        <figure class="contact-hero-media">
          <img src="images/contact-cafe.png" width="1774" height="887" alt="Warm modern cafe counter with coffee cups and pastries in natural light." fetchpriority="high">
          <figcaption>Open daily for coffee, pastries, and quick notes.</figcaption>
        </figure>
        <aside class="contact-hero-note" aria-label="Best contact option">
          <span>Best for today</span>
          <p>Use the form for order questions, group visits, feedback, and anything that needs a thoughtful reply.</p>
        </aside>
      </div>
    </section>

    <section class="section contact-info" aria-labelledby="contact-info-title">
      <div class="section-heading section-heading-row" data-reveal>
        <div>
          <p class="eyebrow">Reach us</p>
          <h2 id="contact-info-title">Choose the easiest way in.</h2>
        </div>
        <p class="section-kicker">Quick questions can go by phone or email. Anything with details is better through the form so the team has the full note.</p>
      </div>

      <div class="contact-card-grid">
        <article data-reveal>
          <span aria-hidden="true">01 / Visit</span>
          <h3>Visit us</h3>
          <p>JKC Cafe<br>Your Neighborhood, Philippines</p>
          <small>Best for coffee breaks, pastries, and relaxed cafe time.</small>
        </article>
        <article data-reveal>
          <span aria-hidden="true">02 / Call</span>
          <h3>Call us</h3>
          <p><a href="tel:+639123456789">+63 912 345 6789</a></p>
          <small>Best for same-day questions before you head over.</small>
        </article>
        <article data-reveal>
          <span aria-hidden="true">03 / Email</span>
          <h3>Email us</h3>
          <p><a href="mailto:hello@jkcafe.com">hello@jkcafe.com</a></p>
          <small>Best for longer notes, follow-ups, and cafe support.</small>
        </article>
      </div>
    </section>

    <section class="section contact-main" aria-labelledby="form-title">
      <div class="contact-panel" data-reveal>
        <p class="eyebrow">Send a message</p>
        <h2 id="form-title">Tell us what you need.</h2>
        <p>
          A few details help us reply with the right answer, whether it is about a visit, order, small gathering, or general cafe support.
        </p>

        <form class="contact-form" id="contact-form" action="contact.php#contact-form" method="post">
          <?php echo app_csrf_field(); ?>
          <div class="contact-form-intro form-row-full" aria-label="Message response details">
            <span>What happens next</span>
            <p>Messages go to the cafe team. If a field needs fixing, we will keep your note here so you do not have to start over.</p>
          </div>

          <div class="form-row">
            <label for="contact-name">Name</label>
            <input id="contact-name" name="name" type="text" autocomplete="name" value="<?php echo e($contactValues['name']); ?>" maxlength="100" required data-input-glow aria-invalid="<?php echo isset($contactErrors['name']) ? 'true' : 'false'; ?>"<?php echo isset($contactErrors['name']) ? ' aria-describedby="contact-name-error"' : ''; ?>>
            <?php if (isset($contactErrors['name'])): ?>
              <p class="form-error" id="contact-name-error"><?php echo e($contactErrors['name']); ?></p>
            <?php endif; ?>
          </div>

          <div class="form-row">
            <label for="contact-email">Email</label>
            <input id="contact-email" name="email" type="email" autocomplete="email" value="<?php echo e($contactValues['email']); ?>" maxlength="150" required data-input-glow aria-invalid="<?php echo isset($contactErrors['email']) ? 'true' : 'false'; ?>"<?php echo isset($contactErrors['email']) ? ' aria-describedby="contact-email-error"' : ''; ?>>
            <?php if (isset($contactErrors['email'])): ?>
              <p class="form-error" id="contact-email-error"><?php echo e($contactErrors['email']); ?></p>
            <?php endif; ?>
          </div>

          <div class="form-row form-row-full">
            <label for="contact-topic">Topic</label>
            <select id="contact-topic" name="topic" required data-input-glow aria-invalid="<?php echo isset($contactErrors['topic']) ? 'true' : 'false'; ?>"<?php echo isset($contactErrors['topic']) ? ' aria-describedby="contact-topic-error"' : ''; ?>>
              <option value="">Choose a topic</option>
              <?php foreach ($contactTopics as $topicValue => $topicLabel): ?>
                <option value="<?php echo e($topicValue); ?>"<?php echo $contactValues['topic'] === $topicValue ? ' selected' : ''; ?>><?php echo e($topicLabel); ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($contactErrors['topic'])): ?>
              <p class="form-error" id="contact-topic-error"><?php echo e($contactErrors['topic']); ?></p>
            <?php endif; ?>
          </div>

          <div class="form-row form-row-full">
            <label for="contact-message">Message</label>
            <textarea id="contact-message" name="message" rows="5" maxlength="2000" required data-input-glow aria-invalid="<?php echo isset($contactErrors['message']) ? 'true' : 'false'; ?>"<?php echo isset($contactErrors['message']) ? ' aria-describedby="contact-message-error"' : ''; ?>><?php echo e($contactValues['message']); ?></textarea>
            <?php if (isset($contactErrors['message'])): ?>
              <p class="form-error" id="contact-message-error"><?php echo e($contactErrors['message']); ?></p>
            <?php endif; ?>
          </div>

          <div class="form-actions form-row-full">
            <button class="button button-primary" type="submit">Send message</button>
          </div>
          <p class="contact-form-status <?php echo $contactStatusType !== '' ? 'is-' . e($contactStatusType) : ''; ?>" role="status" aria-live="polite">
            <?php echo e($contactStatus); ?>
          </p>
        </form>
      </div>

      <aside class="contact-hours" aria-labelledby="hours-title" data-reveal>
        <p class="eyebrow">Before you visit</p>
        <h2 id="hours-title">Cafe hours</h2>
        <p class="contact-hours-lede">Drop by for a drink, or send a message first if your visit has moving parts.</p>
        <dl>
          <div>
            <dt>Mon-Sat</dt>
            <dd>8:00 AM - 8:00 PM</dd>
          </div>
          <div>
            <dt>Sunday</dt>
            <dd>9:00 AM - 6:00 PM</dd>
          </div>
        </dl>
        <p class="contact-hours-note">
          For group visits or special requests, send a quick message before you come in so we can help you better.
        </p>
        <ul class="contact-note-list" aria-label="Good to know">
          <li>Walk-ins are welcome for coffee and pastries.</li>
          <li>Message ahead for small group visits.</li>
          <li>Online ordering will be added soon.</li>
        </ul>
      </aside>
    </section>
  </main>

  <?php include __DIR__ . '/components/footer.php'; ?>

  <script src="user-js/script.js"></script>
</body>
</html>
