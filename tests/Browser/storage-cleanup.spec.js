import { test, expect } from "@playwright/test";
test("admin frees space from one list: download first, confirmation, real deletion and audit", async ({ page }) => {
    const errors = []; page.on("pageerror", (e) => errors.push(e.message));
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto("/login");
    await page.getByLabel("Correo electrónico", { exact: true }).fill("admin@ui.test");
    await page.getByLabel("Contraseña", { exact: true }).fill("Browser123!");
    await page.getByRole("button", { name: "Entrar a UEBTV" }).click();
    await page.waitForURL(/admin\/dashboard/);
    // Desde la tarjeta de Almacenamiento del Resumen
    await page.locator(".ad-storage").getByRole("link", { name: /Liberar espacio/ }).click();
    const dialog = page.getByRole("dialog", { name: "Liberar espacio" });
    await expect(dialog).toBeVisible();
    await expect(page).not.toHaveURL(/liberar/);
    await expect(dialog.getByRole("tab")).toHaveCount(0);
    await expect(dialog.getByText("Eliminar es borrar todo")).toBeVisible();
    // Se puede guardar una copia antes de borrar.
    const download = page.waitForEvent("download");
    await dialog.getByRole("link", { name: /Descargar una copia de Programa retirado/ }).click();
    expect((await download).suggestedFilename()).toBe("uebtv-programa-retirado.mp4");
    await dialog.getByRole("checkbox", { name: /Programa retirado de prueba/ }).check();
    await dialog.getByRole("checkbox", { name: /Campaña de matrículas 2025/ }).check();
    await expect(dialog.getByText("Liberarás")).toContainText("8 MB");
    await dialog.getByRole("button", { name: /Eliminar 8 MB/ }).click();
    const confirm = page.getByRole("alertdialog");
    await expect(confirm).toContainText("No se puede deshacer");
    // Escape cierra solo la confirmación, no la ventana
    await page.keyboard.press("Escape");
    await expect(confirm).toHaveCount(0);
    await expect(dialog).toBeVisible();
    await dialog.getByRole("button", { name: /Eliminar 8 MB/ }).click();
    await page.getByRole("alertdialog").getByRole("button", { name: /Eliminar/ }).click();
    await page.waitForURL(/admin\/medios$/);
    await expect(page.getByText("Se eliminaron 2 elementos y se liberaron 8 MB.")).toBeVisible();
    await page.getByRole("button", { name: /Liberar espacio/ }).click();
    await expect(dialog.getByText("No hay nada que se pueda eliminar por ahora.")).toBeVisible();
    await page.keyboard.press("Escape");
    await expect(dialog).toHaveCount(0);
    // Auditoría
    await page.goto("/admin/auditoria");
    await expect(page.locator(".audit-op", { hasText: "Evento eliminado" }).first()).toBeVisible();
    // Móvil
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto("/admin/medios?liberar=1");
    await expect(dialog).toBeVisible();
    await expect(page.locator("body")).toHaveJSProperty("scrollWidth", 390);
    expect(errors).toEqual([]);
});
