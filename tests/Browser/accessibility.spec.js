import { test, expect } from "@playwright/test";
import AxeBuilder from "@axe-core/playwright";

/*
 * Auditoría automática de accesibilidad (WCAG 2.1 nivel A y AA) con axe-core:
 * páginas públicas y del panel, en modo claro y oscuro, en computadora y
 * teléfono, y también con las ventanas abiertas. Debe quedar en cero.
 */
const WCAG = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa"];

async function audit(page, label, found) {
    for (const theme of ["light", "dark"]) {
        await page.evaluate((t) => document.documentElement.setAttribute("data-theme", t), theme);
        await page.waitForTimeout(450);
        const result = await new AxeBuilder({ page }).withTags(WCAG).analyze();
        for (const v of result.violations) found.push(`${label} [${theme}] ${v.id}: ${v.nodes.slice(0, 3).map((n) => n.target.join(" ")).join(" · ")}`);
    }
}

const visit = async (page, url) => { await page.goto(url); await page.waitForTimeout(600); };

test("public and admin pages, dialogs and phone layout meet WCAG 2.1 AA", async ({ page }) => {
    test.setTimeout(600000);
    const found = [];
    for (const [url, label] of [["/", "Inicio"], ["/senal", "Señal 24/7"], ["/eventos", "Catálogo"], ["/eventos/1", "Ver video"], ["/login", "Login"], ["/register", "Registro"], ["/forgot-password", "Recuperar contraseña"]]) {
        await visit(page, url); await audit(page, label, found);
    }
    await page.goto("/login");
    await page.getByLabel("Correo electrónico", { exact: true }).fill("admin@ui.test");
    await page.getByLabel("Contraseña", { exact: true }).fill("Browser123!");
    await page.getByRole("button", { name: "Entrar a UEBTV" }).click();
    await page.waitForURL(/admin\/dashboard/);
    for (const [url, label] of [["/admin/dashboard", "Resumen"], ["/admin/eventos", "Eventos"], ["/admin/eventos/1", "Detalle del evento"], ["/admin/eventos/create", "Crear evento"], ["/streaming/control", "Emitir con OBS"], ["/admin/contenido-programado", "Parrilla 24/7"], ["/admin/medios", "Biblioteca"], ["/admin/marca-de-agua", "Marca de agua"], ["/admin/usuarios", "Usuarios"], ["/admin/auditoria", "Auditoría"], ["/profile", "Perfil"], ["/eventos/1", "Ver video con estadísticas (administrador)"], ["/eventos", "Catálogo (administrador)"]]) {
        await visit(page, url); await audit(page, label, found);
    }
    // Ventanas abiertas.
    await visit(page, "/admin/medios?subir=1");
    await expect(page.getByRole("dialog", { name: "Subir video al catálogo" })).toBeVisible();
    await audit(page, "Ventana Subir video", found);
    await visit(page, "/admin/medios?liberar=1");
    await expect(page.getByRole("dialog", { name: "Liberar espacio" })).toBeVisible();
    await page.waitForTimeout(800);
    await audit(page, "Ventana Liberar espacio", found);
    await visit(page, "/admin/contenido-programado#programar");
    await expect(page.getByRole("dialog", { name: "Programar video" })).toBeVisible();
    await audit(page, "Ventana Programar video", found);
    await visit(page, "/admin/eventos");
    await page.getByRole("button", { name: /^Eliminar evento / }).first().click();
    await expect(page.getByRole("alertdialog")).toBeVisible();
    await audit(page, "Confirmación ¿Estás seguro?", found);
    // Teléfono.
    await page.setViewportSize({ width: 390, height: 844 });
    for (const [url, label] of [["/admin/dashboard", "Resumen (teléfono)"], ["/eventos", "Catálogo (teléfono)"], ["/eventos/1", "Ver video (teléfono)"]]) {
        await visit(page, url); await audit(page, label, found);
    }
    await page.getByRole("button", { name: "Abrir menú" }).click();
    await audit(page, "Menú (teléfono)", found);
    expect(found).toEqual([]);
});
