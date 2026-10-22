import { test, expect } from "@playwright/test";

test("admin grants limited operator access from the responsive form", async ({ page }) => {
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));
    await page.goto("/login");
    await page.getByLabel("Correo electrónico", { exact: true }).fill("admin@ui.test");
    await page.getByLabel("Contraseña", { exact: true }).fill("Browser123!");
    await page.getByRole("button", { name: "Entrar a UEBTV" }).click();
    await page.waitForURL(/admin\/dashboard/);
    await page.getByRole("link", { name: "Gestionar usuarios" }).click();
    await expect(page.getByRole("heading", { name: "Gestión de usuarios." })).toBeVisible();

    const create = page.locator(".users-create");
    await create.getByLabel("Rol").selectOption("operador");
    await expect(create.getByText("Permisos del operador")).toBeVisible();
    await create.getByLabel("Nombre completo").fill("Operador Solo OBS");
    await create.getByLabel("Correo electrónico").fill("solo-obs@ui.test");
    await create.getByLabel("Emitir con OBS y finalizar directos").check();
    await create.getByLabel("Contraseña", { exact: true }).fill("Browser123!");
    await create.getByLabel("Confirmar contraseña").fill("Browser123!");
    await create.getByRole("button", { name: "Crear cuenta" }).click();
    await expect(page.getByText("Usuario creado.", { exact: false })).toBeVisible();

    await page.setViewportSize({ width: 390, height: 844 });
    const row = page.locator(".users-row").filter({ hasText: "solo-obs@ui.test" });
    await expect(row).toBeVisible();
    await row.getByLabel("Editar cuenta de Operador Solo OBS").click();
    await expect(row.getByLabel("Emitir con OBS y finalizar directos")).toBeChecked();
    await expect(row.getByLabel("Crear y editar eventos")).not.toBeChecked();
    await expect(page.locator("body")).toHaveJSProperty("scrollWidth", 390);
    expect(errors).toEqual([]);
});
