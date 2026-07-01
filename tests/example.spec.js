// @ts-check
import { test, expect } from '@playwright/test';

const baseURL = process.env.BASE_URL || 'http://localhost/Project/';

test.describe.configure({ mode: 'serial' });

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
