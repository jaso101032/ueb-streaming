import { test, expect } from "@playwright/test";

test("admin media library filters render on desktop and mobile", async ({ page }) => {
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));
    await page.goto("/login");
    await page.getByLabel("Correo electrónico", { exact: true }).fill("admin@ui.test");
    await page.getByLabel("Contraseña", { exact: true }).fill("Browser123!");
    await page.getByRole("button", { name: "Entrar a UEBTV" }).click();
    await page.waitForURL(/admin\/dashboard/);
    await page.getByRole("link", { name: "Biblioteca de medios" }).click();
    await expect(page.getByRole("heading", { name: "Biblioteca de medios" })).toBeVisible();
    // It explains it is not the public catalog and links to it.
    await expect(page.locator(".ml-public")).toHaveAttribute("href", /\/eventos$/);
    // File types are tabs that keep the other filters.
    await page.getByRole("navigation", { name: "Tipo de archivo" }).getByRole("link", { name: /Grabaciones/ }).click();
    await expect(page).toHaveURL(/kind=recording/);
    await expect(page.getByRole("link", { name: /Grabaciones/ })).toHaveAttribute("aria-current", "page");
    await page.getByLabel("Buscar por título").fill("x");
    await page.getByRole("button", { name: "Aplicar filtros" }).click();
    await expect(page).toHaveURL(/kind=recording.*q=x|q=x.*kind=recording/);

    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.getByRole("heading", { name: "Biblioteca de medios" })).toBeVisible();
    await expect(page.locator("body")).toHaveJSProperty("scrollWidth", 390);
    expect(errors).toEqual([]);
});
