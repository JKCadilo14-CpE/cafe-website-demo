// @ts-check
import { test, expect } from '@playwright/test';

const baseURL = process.env.BASE_URL || 'http://localhost/Project/';
const baseOrigin = new URL(baseURL).origin;
const isHttpsBaseUrl = new URL(baseURL).protocol === 'https:';

test.describe.configure({ mode: 'serial' });

function uniqueEmail(prefix) {
  return `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2)}@example.com`;
}

async function csrfValue(page, selector = 'input[name="csrf_token"]') {
  return page.locator(selector).first().inputValue();
}

async function signupTestUser(page) {
  const email = uniqueEmail('phase2');
  const password = 'TestPass123!';

  await page.goto(new URL('signup.php', baseURL).toString(), {
    waitUntil: 'domcontentloaded',
  });
  await page.getByLabel('Username').fill('Phase Two Tester');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password', { exact: true }).fill(password);
  await page.getByLabel('Confirm password').fill(password);
  await page.getByRole('button', { name: /Create account/i }).click();
  await expect(page).toHaveURL(/profile\.php|cart\.php/);

  return { email, password };
}

function expectSecurityHeaders(response) {
  const headers = response.headers();

  expect(headers['x-content-type-options']).toBe('nosniff');
  expect(headers['x-frame-options']).toBe('DENY');
  expect(headers['referrer-policy']).toBe('strict-origin-when-cross-origin');
  expect(headers['permissions-policy']).toBe('camera=(), microphone=(), geolocation=(), payment=()');

  if (isHttpsBaseUrl) {
    expect(headers['strict-transport-security']).toContain('max-age=31536000');
  } else {
    expect(headers['strict-transport-security']).toBeUndefined();
  }
}

const publicPages = [
  {
    path: 'index.php',
    title: /JKC Cafe/,
    heading: /Make room for a better coffee break\./i,
  },
  {
    path: 'menu.php',
    title: /Menu \| JKC Cafe/,
    heading: /Find your coffee break without the long wait\./i,
  },
  {
    path: 'about.php',
    title: /About JKC Cafe/,
    heading: /A neighborhood cafe for better daily pauses\./i,
  },
  {
    path: 'contact.php',
    title: /Contact \| JKC Cafe/,
    heading: /Need us\? We will make it easy\./i,
  },
  {
    path: 'login.php',
    title: /Log In \| JKC Cafe/,
    heading: /Sign in before your next cup\./i,
  },
  {
    path: 'signup.php',
    title: /Sign Up \| JKC Cafe/,
    heading: /Create your cafe account in a minute\./i,
  },
  {
    path: 'forgot-password.php',
    title: /Demo Password Recovery \| JKC Cafe/,
    heading: /Demo password recovery/i,
  },
];

for (const publicPage of publicPages) {
  test(`${publicPage.path} loads without major console errors`, async ({ page }) => {
    const consoleErrors = [];

    page.on('console', (message) => {
      if (message.type() === 'error') {
        consoleErrors.push(message.text());
      }
    });

    const response = await page.goto(new URL(publicPage.path, baseURL).toString(), {
      waitUntil: 'domcontentloaded',
    });

    expect(response, `${publicPage.path} should return a response`).not.toBeNull();
    expect(response?.ok(), `${publicPage.path} should load successfully`).toBe(true);
    expectSecurityHeaders(response);
    await expect(page).toHaveTitle(publicPage.title);
    await expect(page.getByRole('heading', { name: publicPage.heading })).toBeVisible();
    expect(consoleErrors, `${publicPage.path} console errors`).toEqual([]);
  });
}

test.describe('CSRF protection', () => {
  const csrfFormPages = [
    'login.php',
    'signup.php',
    'forgot-password.php',
    'contact.php',
  ];

  for (const path of csrfFormPages) {
    test(`${path} renders a CSRF token field`, async ({ page }) => {
      await page.goto(new URL(path, baseURL).toString(), {
        waitUntil: 'domcontentloaded',
      });

      const csrfField = page.locator('input[name="csrf_token"]');
      await expect(csrfField).toHaveCount(1);
      await expect(csrfField).toHaveAttribute('type', 'hidden');
      await expect(csrfField).not.toHaveValue('');
    });
  }

  test('missing CSRF token is rejected before login processing', async ({ request }) => {
    const response = await request.post(new URL('login.php', baseURL).toString(), {
      form: {
        email: 'csrf-test@example.com',
        password: 'not-a-real-password',
      },
    });

    expect(response.status()).toBe(403);
    expect(await response.text()).toMatch(/Security check failed/i);
  });

  test('invalid CSRF token is rejected before contact processing', async ({ request }) => {
    const response = await request.post(new URL('contact.php', baseURL).toString(), {
      form: {
        csrf_token: 'invalid-token',
        name: 'CSRF Tester',
        email: 'csrf-test@example.com',
        topic: 'support',
        message: 'This should not be accepted.',
      },
    });

    expect(response.status()).toBe(403);
    expect(await response.text()).toMatch(/Security check failed/i);
  });
});

test.describe('Session and logout hardening', () => {
  test('session cookie uses HttpOnly, SameSite=Lax, and environment-aware Secure', async ({ page }) => {
    await page.goto(new URL('login.php', baseURL).toString(), {
      waitUntil: 'domcontentloaded',
    });

    const cookies = await page.context().cookies(baseOrigin);
    const sessionCookie = cookies.find((cookie) => cookie.name === 'PHPSESSID' || cookie.name.toLowerCase().includes('sess'));

    expect(sessionCookie, 'session cookie should be set').toBeTruthy();
    expect(sessionCookie?.httpOnly).toBe(true);
    expect(sessionCookie?.sameSite).toBe('Lax');
    expect(sessionCookie?.secure).toBe(isHttpsBaseUrl);
  });

  test('logout requires POST and CSRF', async ({ page }) => {
    await signupTestUser(page);

    await page.goto(new URL('logout.php', baseURL).toString(), {
      waitUntil: 'domcontentloaded',
    });
    await page.goto(new URL('profile.php', baseURL).toString(), {
      waitUntil: 'domcontentloaded',
    });
    await expect(page.getByRole('heading', { name: /Welcome back/i })).toBeVisible();

    const missingTokenResponse = await page.request.post(new URL('logout.php', baseURL).toString(), {
      form: {},
    });
    expect(missingTokenResponse.status()).toBe(403);
    expect(await missingTokenResponse.text()).toMatch(/Security check failed/i);

    const invalidTokenResponse = await page.request.post(new URL('logout.php', baseURL).toString(), {
      form: {
        csrf_token: 'invalid-token',
      },
    });
    expect(invalidTokenResponse.status()).toBe(403);
    expect(await invalidTokenResponse.text()).toMatch(/Security check failed/i);

    await page.goto(new URL('profile.php', baseURL).toString(), {
      waitUntil: 'domcontentloaded',
    });
    const token = await csrfValue(page, 'form[action="logout.php"] input[name="csrf_token"]');
    const logoutResponse = await page.request.post(new URL('logout.php', baseURL).toString(), {
      form: {
        csrf_token: token,
      },
    });
    expect(logoutResponse.ok()).toBe(true);

    await page.goto(new URL('profile.php', baseURL).toString(), {
      waitUntil: 'domcontentloaded',
    });
    await expect(page).toHaveURL(/login\.php/);
  });

  test('customer navbar renders POST logout form with CSRF token', async ({ page }) => {
    await signupTestUser(page);
    await page.goto(new URL('profile.php', baseURL).toString(), {
      waitUntil: 'domcontentloaded',
    });

    const logoutForm = page.locator('form[action="logout.php"][method="post"]');
    await expect(logoutForm).toHaveCount(1);
    await expect(logoutForm.locator('input[name="csrf_token"]')).not.toHaveValue('');
  });
});

test.describe('Security headers on JSON endpoints', () => {
  test('order status endpoint keeps JSON response with hardening headers', async ({ request }) => {
    const response = await request.get(new URL('order-status.php?id=1', baseURL).toString(), {
      headers: {
        Accept: 'application/json',
      },
    });

    expect(response.status()).toBe(401);
    expect(response.headers()['content-type']).toContain('application/json');
    expectSecurityHeaders(response);
    expect(await response.json()).toHaveProperty('error');
  });
});
