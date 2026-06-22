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
