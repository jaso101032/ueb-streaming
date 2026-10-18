import { test, expect } from "@playwright/test";

test("the archived chat follows recording time, including backward seeks", async ({ page }) => {
    await page.goto("/eventos/1");
    await expect(page.locator(".uebtv-chat-message")).toHaveCount(1);
    await expect(page.getByText("Comentario del minuto cinco.")).toHaveCount(0);

    await page.locator("video").evaluate((video) => {
        video.currentTime = 6;
        video.dispatchEvent(new Event("timeupdate"));
    });
    await expect(page.getByText("Comentario del minuto cinco.")).toBeVisible();
    await expect(page.getByText("Comentario del minuto cuarenta.")).toHaveCount(0);

    await page.locator("video").evaluate((video) => {
        video.currentTime = 42;
        video.dispatchEvent(new Event("seeked"));
    });
    await expect(page.getByText("Comentario del minuto cuarenta.")).toBeVisible();

    await page.locator("video").evaluate((video) => {
        video.currentTime = 0;
        video.dispatchEvent(new Event("seeked"));
    });
    await expect(page.getByText("Comentario del minuto cinco.")).toHaveCount(0);
    await expect(page.getByText("Comentario del minuto cuarenta.")).toHaveCount(0);
});

test("older archived comments remain accessible without rendering every message at once", async ({ page }) => {
    const messages = Array.from({ length: 85 }, (_, index) => ({
        id: index + 1, user: "Comunidad", message: `Comentario histórico ${index + 1}`,
        playback_seconds: 0, created_at: new Date().toISOString(),
    }));
    await page.route("**/eventos/1/chat/replay?after_id=0", (route) => route.fulfill({ json: {
        messages, next_after_id: null,
    } }));
    await page.goto("/eventos/1");
    const older = page.getByRole("button", { name: "Ver 5 comentarios anteriores" });
    await expect(older).toBeVisible();
    await expect(page.getByText("Comentario histórico 1", { exact: true })).toHaveCount(0);
    await older.click();
    await expect(page.getByText("Comentario histórico 1", { exact: true })).toBeVisible();
});

test("buffering indicator appears on stalled playback and clears when data resumes", async ({ page }) => {
    await page.goto("/eventos/1");
    const video = page.locator("video");
    await video.evaluate((element) => {
        Object.defineProperty(element, "paused", { configurable: true, get: () => false });
        Object.defineProperty(element, "readyState", { configurable: true, get: () => 2 });
        Object.defineProperty(element, "currentSrc", { configurable: true, get: () => element.src });
        element.dispatchEvent(new Event("waiting"));
    });
    await expect(page.locator(".uebtv-player-buffering")).toBeVisible();
    await video.evaluate((element) => element.dispatchEvent(new Event("playing")));
    await expect(page.locator(".uebtv-player-buffering")).toHaveCount(0);

    await video.evaluate((element) => {
        Object.defineProperty(element, "paused", { configurable: true, get: () => true });
        element.dispatchEvent(new Event("waiting"));
    });
    await page.waitForTimeout(600);
    await expect(page.locator(".uebtv-player-buffering")).toHaveCount(0);
});
