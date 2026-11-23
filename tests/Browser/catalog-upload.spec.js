import { test, expect } from "@playwright/test";
test("admin uploads a video only for the catalog from the media library and from Crear evento", async ({ page }) => {
    const errors = []; page.on("pageerror", (e) => errors.push(e.message));
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.goto("/login");
    await page.getByLabel("Correo electrónico", { exact: true }).fill("admin@ui.test");
    await page.getByLabel("Contraseña", { exact: true }).fill("Browser123!");
    await page.getByRole("button", { name: "Entrar a UEBTV" }).click();
    await page.waitForURL(/admin\/dashboard/);
    // Desde Crear evento: tercera opción.
    await page.goto("/admin/eventos/create");
    await page.getByRole("link", { name: /Video para el catálogo/ }).click();
    const dialog = page.getByRole("dialog", { name: "Subir video al catálogo" });
    await expect(dialog).toBeVisible();
    await expect(page).not.toHaveURL(/subir/);
    await expect(dialog.getByText("¿Cuándo sale al aire?")).toHaveCount(0);
    await dialog.locator('input[name="video"]').setInputFiles("/tmp/uebtv-react-test.mp4");
    await expect(dialog.getByLabel("Título del video")).toHaveValue("uebtv react test");
    await dialog.getByLabel("Título del video").fill("Documental: Páramos de Bolívar");
    await dialog.getByLabel("Categoría").selectOption({ label: "Académico" });
    await expect(dialog.getByLabel(/Carrera/)).toBeDisabled();
    await dialog.getByLabel(/Facultad/).selectOption({ label: "Ingeniería" });
    await dialog.getByLabel(/Carrera/).selectOption({ label: "Software" });
    await dialog.getByRole("radio", { name: /Comunidad UEB/ }).check();
    await dialog.getByRole("button", { name: "Subir video" }).click();
    await page.waitForURL(/admin\/medios$/);
    await expect(page.getByText(/aparecerá en el catálogo en unos minutos/)).toBeVisible();
    const row = page.locator(".ml-tr", { hasText: "Documental: Páramos de Bolívar" });
    await expect(row.locator(".ml-kind")).toHaveText("Video");
    await expect(row.getByText("Aparecerá en el catálogo al terminar")).toBeVisible();
    // Se edita sin volver a subir el archivo.
    await row.getByRole("link", { name: /Gestionar/ }).click();
    await page.getByRole("button", { name: "Editar video" }).click();
    const edit = page.getByRole("dialog", { name: "Editar video" });
    await expect(edit.locator('input[name="video"]')).toHaveCount(0);
    await expect(edit.getByLabel(/Carrera/)).not.toHaveValue("");
    await edit.getByLabel("Título del video").fill("Documental: Páramos de Bolívar (2026)");
    await edit.getByRole("button", { name: "Guardar cambios" }).click();
    await expect(page.getByText("Cambios guardados.")).toBeVisible();
    await expect(page.getByRole("heading", { name: "Documental: Páramos de Bolívar (2026)" })).toBeVisible();
    // Nunca entra a la Parrilla 24/7.
    await page.goto("/admin/contenido-programado");
    await expect(page.getByText(/Documental: Páramos de Bolívar/)).toHaveCount(0);
    // Móvil: el formulario cabe en la pantalla.
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto("/admin/medios?subir=1");
    await expect(dialog).toBeVisible();
    await expect(page.locator("body")).toHaveJSProperty("scrollWidth", 390);
    expect(errors).toEqual([]);
});
