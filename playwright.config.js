import { defineConfig } from "@playwright/test";
export default defineConfig({
    webServer: {
        command: "bash tests/Browser/server.sh",
        url: "http://127.0.0.1:8017/login",
        reuseExistingServer: false,
        timeout: 120000,
    },
    testDir: "./tests/Browser",
    testMatch: "*.spec.js",
    fullyParallel: false,
    workers: 1,
    use: {
        channel: "chromium",
        baseURL: "http://127.0.0.1:8017",
        viewport: { width: 1440, height: 1000 },
        screenshot: "only-on-failure",
        trace: "retain-on-failure",
    },
    reporter: "list",
});
