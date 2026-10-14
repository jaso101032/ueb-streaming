import { test, expect } from "@playwright/test";

test("admin watermark is validated, previewed, persisted and restored on desktop and mobile", async ({ page }) => {
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));
    await page.goto("/login");
    await page.getByLabel("Correo electrónico", { exact: true }).fill("admin@ui.test");
    await page.getByLabel("Contraseña", { exact: true }).fill("Browser123!");
    await page.getByRole("button", { name: "Entrar a UEBTV" }).click();
    await page.waitForURL(/admin\/dashboard/);
    await page.getByRole("link", { name: "Marca de agua", exact: true }).click();
    await expect(page.getByRole("heading", { name: "Marca de agua." })).toBeVisible();
    const upload = page.getByLabel("Nueva imagen PNG");
    const save = page.getByRole("button", { name: "Guardar marca de agua", exact: true });
    await expect(save).toBeDisabled();
    await upload.setInputFiles({ name: "foto.jpg", mimeType: "image/jpeg", buffer: Buffer.from("invalid") });
    await expect(page.getByRole("alert")).toContainText("PNG estático");
    await expect(save).toBeDisabled();
    await upload.setInputFiles({ name: "grande.png", mimeType: "image/png", buffer: Buffer.alloc(2097153) });
    await expect(page.getByRole("alert")).toContainText("supera los 2 MB");

    // A real opaque PNG is rejected by the server, even though the browser
    // accepts its extension and successfully shows its preview.
    const opaque = await page.evaluate(() => {
        const canvas = document.createElement("canvas");
        canvas.width = 256; canvas.height = 128;
        canvas.getContext("2d").fillRect(0, 0, 256, 128);
        return canvas.toDataURL("image/png").split(",")[1];
    });
    await upload.setInputFiles({ name: "opaco.png", mimeType: "image/png", buffer: Buffer.from(opaque, "base64") });
    await save.click();
    await expect(page.getByRole("alert")).toContainText("fondo realmente transparente");

    await upload.setInputFiles("public/images/brand/uebtv-logo-final.png");
    await expect(page.getByText(/Imagen seleccionada · todavía sin guardar/)).toBeVisible();
    await expect(page.locator(".watermark-previews img").first()).toHaveAttribute("src", /^blob:/);
    await save.click();
    await expect(page.getByText("Marca de agua actualizada.", { exact: false })).toBeVisible();
    await expect(page.getByText("marca personalizada", { exact: true })).toBeVisible();
    const image = page.locator(".watermark-previews img").first();
    await expect(image).toHaveAttribute("src", /\/storage\/player-watermarks\/[a-f0-9-]+\.png$/);
    const customUrl = await image.getAttribute("src");
    await expect.poll(() => image.evaluate((element) => element.naturalWidth)).toBe(768);

    for (const width of [1440, 390, 320]) {
        await page.setViewportSize({ width, height: 960 });
        for (const theme of ["dark", "light"]) {
            const toggle = page.getByRole("button", { name: theme === "dark" ? "Activar modo oscuro" : "Activar modo claro" });
            if (await toggle.count()) await toggle.click();
            await expect(page.locator("html")).toHaveAttribute("data-theme", theme);
            if (theme === "dark") await expect(page.locator(".studio-alert")).toHaveCSS("color", "rgb(196, 245, 223)");
            await expect(page.locator("body")).toHaveJSProperty("scrollWidth", width);
            await page.screenshot({ path: `/tmp/uebtv-watermark-${width}-${theme}.png`, fullPage: true });
        }
    }

    await page.goto("/eventos/1");
    const playerMark = page.locator(".uebtv-player-shell .uebtv-player-mark img");
    await expect(playerMark).toHaveAttribute("src", customUrl);
    await expect(page.locator(".uebtv-brand-logo--header img")).toHaveAttribute("src", /images\/brand\/uebtv-logo-final.png$/);
    await expect.poll(() => playerMark.evaluate((element) => element.naturalWidth)).toBe(768);
    const bounds = await playerMark.boundingBox();
    expect(bounds.width).toBeLessThanOrEqual(112);
    expect(bounds.height).toBeLessThanOrEqual(64);

    await page.goto("/admin/marca-de-agua");
    page.once("dialog", (dialog) => dialog.accept());
    await page.getByRole("button", { name: "Restaurar logo oficial", exact: true }).click();
    await expect(page.getByText("Se restauró la marca de agua oficial de UEBTV.")).toBeVisible();
    await expect(page.locator(".watermark-previews img").first()).toHaveAttribute("src", /images\/brand\/uebtv-logo-final.png$/);
    expect(errors).toEqual([]);
});
