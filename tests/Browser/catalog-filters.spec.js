import { test, expect } from "@playwright/test";
const decode = (t) => t.replace(/&quot;/g, '"').replace(/&#039;/g, "'").replace(/&lt;/g, "<").replace(/&gt;/g, ">").replace(/&amp;/g, "&");
const encode = (t) => t.replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
test("catalog filters with long faculty names never overflow on phones, tablets or desktop", async ({ page }) => {
    await page.route(/\/eventos(\?.*)?$/, async (route) => {
        const res = await route.fetch(); let body = await res.text();
        body = body.replace(/(data-react-page="Catalog" data-props=")([^"]*)"/, (m, pre, raw) => {
            const p = JSON.parse(decode(raw));
            p.faculties = p.faculties.map((f) => ({ ...f, nombre: "Facultad de Ciencias Administrativas, Gestión Empresarial e Informática" }));
            p.careers = [{ id: 1, nombre: "Administración de Empresas", facultad_id: 1 }, { id: 2, nombre: "Comunicación", facultad_id: 1 }, { id: 3, nombre: "Ingeniería en Software", facultad_id: 1 }];
            return pre + encode(JSON.stringify(p)) + '"';
        });
        await route.fulfill({ response: res, body });
    });
    for (const url of ["/eventos?facultad_id=1", "/eventos?facultad_id=1&categoria_id=1"]) for (const [w, h, name] of [[390, 844, "m390"], [360, 740, "m360"], [768, 1024, "t768"], [1440, 900, "d1440"]]) {
        await page.setViewportSize({ width: w, height: h });
        await page.goto(url);
        await expect(page.getByLabel("Carrera")).toBeVisible();
        await expect(page.locator("body")).toHaveJSProperty("scrollWidth", w);
    }
});
