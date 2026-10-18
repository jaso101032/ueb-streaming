import { test, expect } from "@playwright/test";

test("a live-room chat changes to read-only when admin disables comments", async ({ page, browser }) => {
    const visitorContext = await browser.newContext();
    const visitor = await visitorContext.newPage();
    await visitor.goto("/eventos/2");
    await expect(visitor.locator(".uebtv-chat-status")).toContainText("Activo");

    await page.goto("/login");
    await page.getByLabel("Correo electrónico", { exact: true }).fill("admin@ui.test");
    await page.getByLabel("Contraseña", { exact: true }).fill("Browser123!");
    await page.getByRole("button", { name: "Entrar a UEBTV" }).click();
    await page.waitForURL(/admin\/dashboard/);
    await page.goto("/admin/eventos/2");
    await page.getByRole("button", { name: "Desactivar comentarios" }).click();
    await expect(page.getByText("Comentarios deshabilitados.", { exact: false })).toBeVisible();
    await expect(visitor.locator(".uebtv-chat-status")).toContainText("Solo lectura", { timeout: 12000 });
    await expect(visitor.locator(".uebtv-live-chat__closed")).toContainText("Los comentarios están desactivados");

    await page.getByRole("button", { name: "Activar comentarios" }).click();
    await expect(visitor.locator(".uebtv-chat-status")).toContainText("Activo", { timeout: 12000 });
    await visitorContext.close();
});
