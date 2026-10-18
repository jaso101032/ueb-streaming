import { test, expect } from "@playwright/test";

test("video analytics appear below the player for admin only and fit mobile", async ({ page, browser }) => {
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));
    await page.goto("/login");
    await page.getByLabel("Correo electrónico", { exact: true }).fill("admin@ui.test");
    await page.getByLabel("Contraseña", { exact: true }).fill("Browser123!");
    await page.getByRole("button", { name: "Entrar a UEBTV" }).click();
    await page.waitForURL(/admin\/dashboard/);
    await page.goto("/eventos/1");
    const analytics = page.locator(".video-analytics-inline");
    await expect(analytics.getByRole("heading", { name: "Estadísticas del video" })).toBeVisible();
    await expect(analytics.getByRole("heading", { name: "Curva de crecimiento" })).toBeVisible();
    await analytics.getByLabel("Período").selectOption("7");
    await expect(analytics.getByText("Últimos 7 días")).toBeVisible();

    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.locator("body")).toHaveJSProperty("scrollWidth", 390);
    const darkToggle = page.getByRole("button", { name: "Activar modo oscuro" });
    if (await darkToggle.count()) await darkToggle.click();
    await expect(analytics).toHaveCSS("background-color", "rgb(16, 36, 62)");

    const anonymous = await browser.newContext({ viewport: { width: 390, height: 844 } });
    const visitor = await anonymous.newPage();
    await visitor.goto("/eventos/1");
    await expect(visitor.locator(".video-analytics-inline")).toHaveCount(0);
    await anonymous.close();
    expect(errors).toEqual([]);
});
