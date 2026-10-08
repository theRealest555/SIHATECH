import { defineConfig, devices } from '@playwright/test';

if (process.env.SIHATECH_E2E !== '1') throw new Error('Use npm run test:e2e to prepare an isolated database.');
export default defineConfig({
    testDir: './e2e', testMatch: '**/*.spec.mjs', fullyParallel: false, workers: 1,
    forbidOnly: Boolean(process.env.CI), retries: 0, timeout: 45000,
    reporter: 'list', outputDir: 'test-results',
    use: { baseURL: 'http://localhost:4310', screenshot: 'only-on-failure', trace: 'retain-on-failure' },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
    webServer: [
        { command: 'node e2e/mailbox.mjs', url: 'http://127.0.0.1:8266/health', reuseExistingServer: false, timeout: 30000 },
        { command: 'node e2e/server.mjs', url: 'http://localhost:8310/up', reuseExistingServer: false, timeout: 30000 },
        { command: 'npm run dev -- --host localhost --port 4310 --strictPort', url: 'http://localhost:4310', reuseExistingServer: false, timeout: 30000 },
    ],
});
