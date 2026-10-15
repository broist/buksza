import { defineConfig } from '@playwright/test';
export default defineConfig({
    testDir: './tests/browser', fullyParallel: false, workers: 1, timeout: 180000,
    use: { baseURL: 'http://localhost:18081', browserName: 'chromium', viewport: {width:360,height:800}, trace: 'retain-on-failure', screenshot: 'only-on-failure' },
    expect: { timeout: 30000 },
    reporter: [['list'],['html',{open:'never'}]], outputDir: '../test-results',
});
