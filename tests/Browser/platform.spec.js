import { readFileSync } from "node:fs";
import { test, expect } from "@playwright/test";
const login = async (page, email = "admin@ui.test") => {
    await page.goto("/login");
    await page.getByLabel("Correo electrónico", { exact: true }).fill(email);
    await page.getByLabel("Contraseña", { exact: true }).fill("Browser123!");
    await page.getByRole("button", { name: "Entrar a UEBTV" }).click();
    await page.waitForURL(/admin\/dashboard|eventos/);
};
test("notification bell keeps preferences and fits a narrow mobile header", async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 720 });
    await login(page);
    await page.goto("/senal");
    for (const width of [320, 393]) {
        await page.setViewportSize({ width, height: 720 });
        const brand = await page.locator(".studio-header .studio-brand").boundingBox();
        const bell = await page.locator(".studio-notifications__toggle").boundingBox();
        const theme = await page.locator(".studio-theme-toggle").boundingBox();
        const menu = await page.locator(".studio-menu-toggle").boundingBox();
        expect(brand.x + brand.width).toBeLessThanOrEqual(bell.x + 1);
        expect(bell.x + bell.width).toBeLessThanOrEqual(theme.x + 1);
        expect(theme.x + theme.width).toBeLessThanOrEqual(menu.x + 1);
        await expect(page.locator(".studio-account .studio-signout")).toBeHidden();
        await expect(page.locator("body")).toHaveJSProperty("scrollWidth", width);
    }
    await page.setViewportSize({ width: 320, height: 720 });
    await page.locator(".studio-menu-toggle").click();
    await expect(page.getByRole("button", { name: "Cerrar sesión desde el menú" })).toBeVisible();
    await page.locator(".studio-menu-toggle").click();
    await page.getByRole("button", { name: /Notificaciones/ }).click();
    await expect(page.getByRole("heading", { name: "Notificaciones" })).toBeVisible();
    const live = page.getByRole("checkbox", { name: "Directos" });
    await expect(live).toBeChecked();
    await live.uncheck();
    await expect(live).not.toBeChecked();
    await page.reload();
    await page.getByRole("button", { name: /Notificaciones/ }).click();
    await expect(page.getByRole("checkbox", { name: "Directos" })).not.toBeChecked();
    await page.getByRole("checkbox", { name: "Directos" }).check();
    await expect(page.locator("body")).toHaveJSProperty("scrollWidth", 320);
});
test("student mobile menu keeps account actions outside the compact header", async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 720 });
    await login(page, "student@ui.test");
    await page.goto("/eventos");
    await expect(page.locator(".studio-account .studio-signout")).toBeHidden();
    await page.locator(".studio-menu-toggle").click();
    await expect(page.getByRole("link", { name: /Mi perfil/ })).toBeVisible();
    await expect(page.getByRole("button", { name: "Cerrar sesión desde el menú" })).toBeVisible();
    await expect(page.locator("body")).toHaveJSProperty("scrollWidth", 320);
});
test("Android TV channel mode shows a full-screen viewer without the website shell", async ({ page }) => {
    await page.route("**/senal/estado", (route) => route.fulfill({ json: {
        mode: "idle", event: null, playback: null, advertisement: null, next: [],
        summary: { count: 0, totalSeconds: 0, remainingSeconds: 0 },
    } }));
    await page.goto("/senal?modo=tv");
    await expect(page.locator("html")).toHaveClass(/uebtv-tv-mode/);
    await expect(page.locator(".studio-header")).toBeHidden();
    await expect(page.locator(".channel-heading")).toBeHidden();
    await expect(page.locator(".channel-next")).toBeHidden();
    await expect(page.locator(".channel-screen")).toBeVisible();
    const frame = await page.locator(".channel-screen-frame").boundingBox();
    expect(frame.width).toBeGreaterThanOrEqual(1440);
    expect(frame.height).toBeGreaterThanOrEqual(900);
});
test("channel agenda opens all upcoming programs grouped by date while the side list stays at five", async ({ page }) => {
    const agenda = Array.from({ length: 8 }, (_, index) => ({
        id: index + 1,
        title: `Programa ${index + 1}`,
        starts_at: new Date(Date.now() + (index + 1) * 86400000).toISOString(),
        duration: 3600,
    }));
    await page.route("**/senal/estado", (route) => route.fulfill({ json: {
        mode: "idle", event: null, playback: null, advertisement: null,
        next: agenda.slice(0, 5), agenda,
        summary: { count: 8, totalSeconds: 28800, remainingSeconds: 28800 },
    } }));
    await page.goto("/senal");
    await expect(page.locator(".channel-agenda-item")).toHaveCount(5);
    await page.getByRole("button", { name: "Ver programación completa" }).click();
    const dialog = page.getByRole("dialog", { name: "Programación" });
    await expect(dialog).toBeVisible();
    await expect(dialog.locator(".channel-agenda-modal-item")).toHaveCount(8);
    await expect(dialog.getByRole("heading", { name: "Programa 8" })).toBeVisible();
    await page.keyboard.press("Escape");
    await expect(dialog).toBeHidden();
});
test("Android TV mode keeps advertising non-pausable and its controls reachable", async ({ page }) => {
    await page.route("**/senal/estado", (route) => route.fulfill({ json: {
        mode: "advertisement", event: null,
        advertisement: { id: 11, title: "Aviso institucional", mediaUrl: "/tv-ad.mp4" },
        playback: { state: "playing", position: 1, duration: 60 },
        next: [], summary: { count: 0, totalSeconds: 0, remainingSeconds: 0 },
    } }));
    await page.route("**/tv-ad.mp4*", (route) => route.fulfill({
        body: readFileSync("/tmp/uebtv-react-test.mp4"), contentType: "video/mp4",
        headers: { "Accept-Ranges": "bytes" },
    }));
    await page.goto("/senal?modo=tv");
    await expect(page.locator("html")).toHaveClass(/uebtv-tv-mode/);
    const video = page.locator(".channel-screen video");
    await expect(video).toHaveJSProperty("paused", false);
    await expect(page.getByRole("button", { name: "Pausar", exact: true })).toHaveCount(0);
    await expect(page.getByRole("button", { name: "Imagen en imagen" })).toHaveCount(0);
    const firstControl = page.locator(".uebtv-player-controls button").first();
    await firstControl.focus();
    await expect(page.locator(".uebtv-player-controls")).toHaveCSS("opacity", "1");
    await page.keyboard.press("Space");
    await expect(video).toHaveJSProperty("paused", false);
});
test("channel download card keeps its QR visible on desktop and mobile without entering TV mode", async ({ page }) => {
    await page.goto("/senal");
    const card = page.locator(".channel-app-card");
    const qr = card.getByRole("img", { name: "Código QR para descargar UEBTV" });
    await expect(card).toBeVisible();
    await expect(qr).toHaveJSProperty("naturalWidth", 400);
    const art = await card.locator(".channel-app-art").boundingBox();
    const mascot = await card.locator(".channel-app-mascot").boundingBox();
    expect(mascot.x).toBeGreaterThanOrEqual(art.x);
    expect(mascot.x + mascot.width).toBeLessThanOrEqual(art.x + art.width);
    await expect(card.getByRole("link", { name: "Descargar APK" })).toHaveAttribute("href", "/descargas/uebtv.apk");
    await expect(card.getByRole("link")).toHaveCount(1);
    await expect(qr.locator("xpath=ancestor::a")).toHaveCount(0);
    await card.screenshot({ path: "/tmp/uebtv-download-card-desktop.png" });
    const apk = await page.request.get("/descargas/uebtv.apk");
    expect(apk.status()).toBe(200);
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(card).toBeVisible();
    await expect(page.locator("body")).toHaveJSProperty("scrollWidth", 390);
    await card.screenshot({ path: "/tmp/uebtv-download-card-mobile.png" });
    await page.setViewportSize({ width: 320, height: 720 });
    await expect(page.locator("body")).toHaveJSProperty("scrollWidth", 320);
    await expect(qr).toBeVisible();
    await page.goto("/senal?modo=tv");
    await expect(card).toHaveCount(0);
});
test("mobile channel places the UEBTV and UEB marks side by side", async ({ page }) => {
    await page.goto("/senal");
    for (const width of [320, 390, 600, 640]) {
        await page.setViewportSize({ width, height: 844 });
        const tvMark = page.locator(".channel-heading-title__mobile");
        const universityMark = page.locator(".channel-institution-mark");
        await expect(tvMark).toBeHidden();
        await expect(page.locator(".channel-heading-title__mark")).toBeVisible();
        const suffix = page.locator(".channel-heading-title__suffix");
        await expect(suffix).toBeVisible();
        await expect(suffix).toHaveCSS("color", /rgb\(255, 255, 255\)|rgb\(241, 247, 255\)/);
        await expect(page.locator(".channel-heading-title__mark img")).toHaveCSS("filter", "brightness(0) invert(1)");
        await expect(universityMark).toBeVisible();
        const tv = await page.locator(".channel-heading-title").boundingBox();
        const university = await universityMark.boundingBox();
        expect(tv.x + tv.width).toBeLessThanOrEqual(university.x + 1);
        expect(Math.abs(tv.y + tv.height / 2 - university.y - university.height / 2)).toBeLessThan(18);
        await expect(page.locator("body")).toHaveJSProperty("scrollWidth", width);
        if (width === 390) await page.locator(".channel-heading").screenshot({ path: "/tmp/uebtv-channel-brands-mobile.png" });
    }
    await page.setViewportSize({ width: 390, height: 844 });
    await page.evaluate(() => { document.documentElement.dataset.theme = "dark"; });
    await expect(page.locator(".channel-heading-title__mobile")).toBeHidden();
    await expect(page.locator(".channel-heading-title__mark")).toBeVisible();
    await expect(page.locator(".channel-institution-mark")).toBeVisible();
    await expect(page.locator("body")).toHaveJSProperty("scrollWidth", 390);
    await page.evaluate(() => { document.documentElement.dataset.theme = "light"; });
    await page.setViewportSize({ width: 1440, height: 900 });
    await expect(page.locator(".channel-heading-title__mobile")).toBeHidden();
    await expect(page.locator(".channel-heading-title__suffix")).toHaveCSS("color", "rgb(16, 39, 68)");
    await expect(page.locator(".channel-heading-title__mark img")).toHaveCSS("filter", "none");
});
test("mobile recorded player aligns timeline and speed and keeps details compact", async ({ page }) => {
    await page.route("**/test-video.mp4", (route) => route.fulfill({
        body: readFileSync("/tmp/uebtv-react-test.mp4"),
        contentType: "video/mp4",
        headers: { "Accept-Ranges": "bytes" },
    }));
    await page.goto("/eventos/1");
    for (const width of [320, 390, 600]) {
        await page.setViewportSize({ width, height: 844 });
        const measurements = await page.evaluate(() => {
            const timeline = document.querySelector(".uebtv-player-timeline.is-vod-timeline");
            const range = timeline.querySelector(".uebtv-player-range--progress");
            const speed = document.querySelector(".uebtv-player-speed");
            const controls = document.querySelector(".uebtv-player-controls");
            const details = document.querySelector(".uebtv-watch-details-toggle");
            return {
                timeline: timeline.getBoundingClientRect().toJSON(),
                range: range.getBoundingClientRect().toJSON(),
                speed: speed.getBoundingClientRect().toJSON(),
                controls: controls.getBoundingClientRect().toJSON(),
                detailsFont: parseFloat(getComputedStyle(details).fontSize),
                detailsHeight: details.getBoundingClientRect().height,
                rangeHeight: getComputedStyle(range).height,
            };
        });
        expect(measurements.range.x).toBeGreaterThanOrEqual(measurements.timeline.x);
        expect(measurements.range.x + measurements.range.width).toBeLessThanOrEqual(measurements.timeline.x + measurements.timeline.width + 1);
        expect(measurements.speed.x + measurements.speed.width).toBeLessThanOrEqual(measurements.controls.x + measurements.controls.width);
        expect(measurements.speed.width).toBeGreaterThanOrEqual(50);
        expect(measurements.detailsFont).toBeLessThanOrEqual(13);
        expect(measurements.detailsHeight).toBeLessThanOrEqual(42);
        expect(measurements.rangeHeight).toBe("16px");
        await expect(page.locator("body")).toHaveJSProperty("scrollWidth", width);
        await page.locator(".uebtv-player-speed").selectOption("1.5");
        await expect(page.locator("video")).toHaveJSProperty("playbackRate", 1.5);
    }
});
test("FACAGEI wordmark keeps the original proportions on desktop and mobile", async ({ page }) => {
    await page.goto("/senal");
    const logo = page.locator(".facagei-brand-logo--header");
    await expect(logo).toHaveJSProperty("naturalWidth", 1665);
    for (const width of [1440, 390, 320]) {
        await page.setViewportSize({ width, height: 900 });
        const box = await logo.boundingBox();
        expect(box.width / box.height).toBeCloseTo(1665 / 945, 2);
        await page.locator(".studio-header").screenshot({ path: `/tmp/uebtv-header-facagei-${width}.png` });
    }
});
test("video analytics exports a complete print-ready PDF report for the selected period", async ({ page }) => {
    await page.addInitScript(() => {
        window.__printRequested = false;
        window.print = () => { window.__printRequested = true; };
    });
    await page.route("**/admin/eventos/*/estadisticas*", (route) => route.fulfill({ json: {
        period: 7,
        current: { views: 3, allTime: 12, peakDay: { date: "2026-10-19", label: "19/10", views: 2 }, growthPercent: 50, previousViews: 2 },
        daily: [
            { date: "2026-10-18", label: "18/10", views: 1 },
            { date: "2026-10-19", label: "19/10", views: 2 },
        ],
        mostViewed: [{ id: 1, title: "Conferencia UEB", url: "/eventos/1", views: 3, type: "Grabación" }],
        leastViewed: [{ id: 2, title: "Himno UEB", url: "/eventos/2", views: 0, type: "Programado" }],
    } }));
    await login(page);
    await page.goto("/eventos");
    await page.getByRole("button", { name: "Ver estadísticas" }).first().click();
    await expect(page.getByRole("heading", { name: "Estadísticas de reproducción" })).toBeVisible();
    const report = page.locator(".video-analytics-report");
    await expect(report).toContainText("Conferencia UEB");
    await expect(report).toContainText("Registro diario");
    await expect(report).toContainText("Metodología.");
    await page.getByRole("button", { name: "Exportar estadísticas a PDF" }).click();
    await expect.poll(() => page.evaluate(() => window.__printRequested)).toBe(true);
});
test("public navigation menu is usable on mobile and desktop", async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto("/senal");
    await expect(page.getByRole("link", { name: "Iniciar sesión" })).toBeVisible();
    await page.getByRole("button", { name: "Abrir menú" }).click();
    const menu = page.getByRole("navigation", { name: "Menú principal" });
    await expect(menu).toBeVisible();
    await expect(menu.getByRole("link", { name: /Señal en vivo/ })).toHaveAttribute("href", /\/senal$/);
    await expect(menu.getByRole("link", { name: /Explorar catálogo/ })).toHaveAttribute("href", /\/eventos$/);
    await expect(menu.getByRole("link", { name: "Crear cuenta" })).toHaveAttribute("href", /\/register$/);
    await expect(page.locator("body")).toHaveJSProperty("scrollWidth", 390);
    await page.screenshot({ path: "/tmp/uebtv-guest-menu-mobile.png" });
    await page.setViewportSize({ width: 320, height: 720 });
    await expect(page.locator("body")).toHaveJSProperty("scrollWidth", 320);
    await expect(menu).toBeVisible();
    const logo = await page.locator('.studio-header .studio-brand').boundingBox();
    const account = await page.locator('.studio-account').boundingBox();
    expect(logo.x + logo.width).toBeLessThanOrEqual(account.x);
    await page.screenshot({ path: "/tmp/uebtv-guest-menu-320.png" });
    await page.keyboard.press("Escape");
    await expect(menu).toHaveCount(0);
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.getByRole("button", { name: "Abrir menú" }).click();
    await expect(menu).toBeVisible();
    await page.screenshot({ path: "/tmp/uebtv-guest-menu-desktop.png" });
    await page.locator("main").click({ position: { x: 20, y: 20 } });
    await expect(menu).toHaveCount(0);
    await login(page, "student@ui.test");
    await page.getByRole("button", { name: "Abrir menú" }).click();
    await expect(menu.getByRole("link", { name: "Mi perfil" })).toBeVisible();
});
test("public catalog, empty search, room and mobile layout render without React errors", async ({
    page,
}) => {
    const errors = [];
    page.on("pageerror", (e) => errors.push(e.message));
    await page.goto("/eventos");
    await expect(
        page.getByRole("heading", { name: "Eventos y transmisiones" }),
    ).toBeVisible();
    await expect(
        page.getByRole("link", { name: "Eliminar evento" }),
    ).toHaveCount(0);
    await page.getByLabel("Buscar por título").fill("inexistente");
    await page.getByRole("button", { name: "Buscar", exact: true }).click();
    await expect(
        page.getByText("No encontramos eventos.", { exact: false }),
    ).toBeVisible();
    await page.goto("/eventos/1");
    await expect(
        page.getByRole("heading", { name: "Conferencia inaugural UEB 2026" }),
    ).toBeVisible();
    await expect(page.locator("video")).toHaveAttribute(
        "src",
        "/test-video.mp4",
    );
    await expect(
        page.getByRole("button", { name: /Retirar mensaje/ }),
    ).toHaveCount(0);
    await page.screenshot({
        path: "/tmp/uebtv-watch-desktop.png",
        fullPage: true,
    });
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.locator("body")).toHaveJSProperty("scrollWidth", 390);
    await page.screenshot({
        path: "/tmp/uebtv-watch-mobile.png",
        fullPage: true,
    });
    expect(errors).toEqual([]);
});
test("archived video shows views instead of connected spectators and counts playback", async ({ page }) => {
    await page.route("**/test-video.mp4", (route) => route.fulfill({
        body: readFileSync("/tmp/uebtv-react-test.mp4"),
        contentType: "video/mp4",
        headers: { "Accept-Ranges": "bytes" },
    }));
    await page.goto("/eventos/1");
    await expect(page.getByLabel("0 visualizaciones")).toBeVisible();
    await expect(page.getByText(/espectadores? conectados?/)).toHaveCount(0);
    await page.getByRole("button", { name: "Reproducir", exact: true }).click();
    await expect(page.getByLabel("1 visualización")).toBeVisible({ timeout: 15000 });
    await page.reload();
    await expect(page.getByLabel("1 visualización")).toBeVisible();
});
test("desktop player shortcuts control recorded video without hijacking text fields", async ({ page }) => {
    await page.route("**/test-video.mp4", (route) => route.fulfill({
        body: readFileSync("/tmp/uebtv-react-test.mp4"),
        contentType: "video/mp4",
        headers: { "Accept-Ranges": "bytes" },
    }));
    await page.goto("/eventos/1");
    const video = page.locator("video");
    await page.getByRole("button", { name: "Reproducir", exact: true }).click();
    await expect.poll(() => video.evaluate((v) => v.currentTime)).toBeGreaterThan(0.5);
    await page.evaluate(() => document.activeElement.blur());
    await page.keyboard.press("Space");
    await expect(video).toHaveJSProperty("paused", true);
    const beforeSeek = await video.evaluate((v) => v.currentTime);
    await page.keyboard.press("ArrowRight");
    await expect.poll(() => video.evaluate((v) => v.currentTime)).toBeGreaterThan(beforeSeek + 4.5);
    await page.keyboard.press("ArrowLeft");
    await expect.poll(() => video.evaluate((v) => v.currentTime)).toBeLessThan(beforeSeek + 0.5);
    const beforeFocusedSeek = await video.evaluate((v) => v.currentTime);
    await page.getByLabel("Posición del video").focus();
    await page.keyboard.press("ArrowRight");
    await expect.poll(() => video.evaluate((v) => v.currentTime)).toBeGreaterThan(beforeFocusedSeek + 4.5);

    await page.evaluate(() => {
        const input = document.createElement("input");
        input.id = "shortcut-test-input";
        document.body.append(input);
        input.focus();
    });
    await page.keyboard.press("f");
    await page.keyboard.press("Space");
    await expect(page.locator("#shortcut-test-input")).toHaveValue("f ");
    await expect(video).toHaveJSProperty("paused", true);
    await page.evaluate(() => document.activeElement.blur());
    await page.keyboard.press("Space");
    await expect(video).toHaveJSProperty("paused", false);
    await page.getByRole("button", { name: "Pausar", exact: true }).focus();
    await page.keyboard.press("f");
    await expect.poll(() => page.locator(".uebtv-player-shell").evaluate((el) => document.fullscreenElement === el)).toBe(true);
    await page.keyboard.press("f");
    await expect.poll(() => page.evaluate(() => document.fullscreenElement)).toBeNull();
    await page.keyboard.press("f");
    await expect.poll(() => page.locator(".uebtv-player-shell").evaluate((el) => document.fullscreenElement === el)).toBe(true);
    await page.keyboard.press("Escape");
    await expect.poll(() => page.evaluate(() => document.fullscreenElement)).toBeNull();
});
test("fullscreen controls hide while the pointer is still and return when it moves", async ({ page }) => {
    await page.route("**/test-video.mp4", (route) => route.fulfill({
        body: readFileSync("/tmp/uebtv-react-test.mp4"),
        contentType: "video/mp4",
        headers: { "Accept-Ranges": "bytes" },
    }));
    await page.goto("/eventos/1");
    await page.getByRole("button", { name: "Reproducir", exact: true }).click();
    await expect(page.locator("video")).toHaveJSProperty("paused", false);

    const shell = page.locator(".uebtv-player-shell");
    const controls = shell.locator(".uebtv-player-controls");
    await shell.getByRole("button", { name: "Pantalla completa" }).click();
    await expect.poll(() => shell.evaluate((element) => document.fullscreenElement === element)).toBe(true);
    await expect(shell.locator(".uebtv-player-title")).toBeVisible();
    const recordedOverlay = await shell.evaluate((element) => ({
        titleRight: element.querySelector(".uebtv-player-title").getBoundingClientRect().right,
        watermarkLeft: element.querySelector(".uebtv-player-mark").getBoundingClientRect().left,
    }));
    expect(recordedOverlay.titleRight).toBeLessThan(recordedOverlay.watermarkLeft);
    await expect.poll(() => controls.evaluate((element) => getComputedStyle(element).opacity)).toBe("0");
    await expect(controls).toHaveCSS("pointer-events", "none");

    await page.mouse.move(200, 200);
    await expect(controls).toHaveCSS("opacity", "1");
    await page.keyboard.press("Escape");
    await expect.poll(() => page.evaluate(() => document.fullscreenElement)).toBeNull();
    await page.setViewportSize({ width: 390, height: 844 });
    await shell.getByRole("button", { name: "Pantalla completa" }).click();
    await expect.poll(() => shell.evaluate((element) => document.fullscreenElement === element)).toBe(true);
    await expect(shell.locator(".uebtv-player-title")).toBeVisible();
    await expect(shell.locator(".uebtv-player-mark")).toBeVisible();
    const mobileRecordingOverlay = await shell.evaluate((element) => ({
        titleRight: element.querySelector(".uebtv-player-title").getBoundingClientRect().right,
        watermarkLeft: element.querySelector(".uebtv-player-mark").getBoundingClientRect().left,
    }));
    expect(mobileRecordingOverlay.titleRight).toBeLessThan(mobileRecordingOverlay.watermarkLeft);
    await page.keyboard.press("Escape");
});
test("arrow shortcuts do not seek institutional advertising", async ({ page }) => {
    await page.route("**/senal/estado", (route) => route.fulfill({ json: {
        mode: "advertisement", event: null,
        advertisement: { id: 9, title: "Publicidad de prueba", mediaUrl: "/keyboard-ad.mp4" },
        playback: { state: "playing", position: 20, duration: 60 },
        next: [], summary: { count: 0, totalSeconds: 0, remainingSeconds: 0 },
    } }));
    await page.route("**/keyboard-ad.mp4*", (route) => route.fulfill({
        body: readFileSync("/tmp/uebtv-react-test.mp4"), contentType: "video/mp4",
        headers: { "Accept-Ranges": "bytes" },
    }));
    await page.goto("/senal");
    const video = page.locator("video");
    await expect.poll(() => video.evaluate((v) => v.currentTime)).toBeGreaterThan(19);
    await video.evaluate((v) => v.pause());
    const before = await video.evaluate((v) => v.currentTime);
    await page.keyboard.press("ArrowRight");
    await page.keyboard.press("ArrowLeft");
    expect(Math.abs((await video.evaluate((v) => v.currentTime)) - before)).toBeLessThan(0.5);
});
test("access, profile, administration, event form preview and mobile navigation", async ({
    page,
}) => {
    const errors = [];
    page.on("pageerror", (e) => errors.push(e.message));
    await page.goto("/register");
    await expect(
        page.getByText("Utiliza al menos 8 caracteres", { exact: false }),
    ).toBeVisible();
    await page
        .getByRole("button", { name: "Mostrar contraseña", exact: true })
        .click();
    await expect(page.locator("#password")).toHaveAttribute("type", "text");
    await page.screenshot({ path: "/tmp/uebtv-register.png", fullPage: true });
    await login(page);
    await expect(page.getByRole("heading", { name: /Hola,/ })).toBeVisible();
    await expect(
        page.getByRole("link", { name: "Crear evento", exact: true }),
    ).toHaveCount(1);
    await page.screenshot({ path: "/tmp/uebtv-dashboard.png", fullPage: true });
    await page.getByRole("link", { name: "Crear evento", exact: true }).click();
    await expect(
        page.getByRole("heading", { name: "Crear evento", exact: true }),
    ).toBeVisible();
    await expect(page.locator("form.uebtv-event-form")).toHaveAttribute(
        "enctype",
        "multipart/form-data",
    );
    await page
        .getByLabel("Título *", { exact: true })
        .fill("Un encuentro para imaginar");
    await expect(
        page.getByRole("heading", { name: "Un encuentro para imaginar" }),
    ).toBeVisible();
    await page
        .getByLabel("Facultad", { exact: true })
        .selectOption({ label: "Ingeniería" });
    await expect(page.getByLabel("Carrera")).toContainText("Software");
    await page.screenshot({
        path: "/tmp/uebtv-event-form.png",
        fullPage: true,
    });
    await page.goto("/admin/eventos");
    await expect(
        page.getByRole("button", { name: /^Eliminar evento / }).first(),
    ).toBeVisible();
    await page.goto("/admin/eventos/1");
    await expect(
        page.getByRole("heading", { name: "Acerca del evento" }),
    ).toBeVisible();
    await page.goto("/streaming/control");
    await expect(
        page.getByRole("heading", { name: "Emitir con OBS." }),
    ).toBeVisible();
    await page.goto("/admin/contenido-programado");
    await expect(
        page.getByRole("heading", { name: "Contenido programado" }),
    ).toBeVisible();
    await page.screenshot({ path: "/tmp/uebtv-scheduled.png", fullPage: true });
    await page.goto("/profile");
    await expect(
        page.getByRole("heading", { name: "Información del perfil" }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Eliminar mi cuenta" }).click();
    await expect(
        page.getByRole("button", { name: "Confirmar eliminación" }),
    ).toBeVisible();
    await page.setViewportSize({ width: 390, height: 844 });
    await page.getByRole("button", { name: "Abrir menú" }).click();
    await expect(
        page.getByRole("link", { name: "Crear evento", exact: true }),
    ).toBeVisible();
    await page.keyboard.press("Escape");
    await expect(
        page.getByRole("link", { name: "Crear evento", exact: true }),
    ).not.toBeVisible();
    expect(errors).toEqual([]);
});
test("chat sends and moderates safely; regular user has no destructive controls", async ({
    page,
}) => {
    const text = "Mensaje de prueba React " + Date.now();
    await login(page);
    await page.goto("/eventos/2");
    await page.getByRole("textbox", { name: "Tu mensaje" }).fill(text);
    await page.getByRole("button", { name: "Enviar mensaje" }).click();
    await expect(
        page.locator(".uebtv-chat-message").filter({ hasText: text }),
    ).toBeVisible();
    page.on("dialog", (d) => d.accept());
    await page
        .locator(".uebtv-chat-message")
        .filter({ hasText: text })
        .getByRole("button")
        .click();
    await expect(
        page.locator(".uebtv-chat-message").filter({ hasText: text }),
    ).toHaveCount(0);
    await page
        .getByRole("button", { name: "Cerrar sesión", exact: true })
        .click();
    await login(page, "student@ui.test");
    await page.goto("/eventos/2");
    await expect(
        page.getByRole("button", { name: /Retirar mensaje/ }),
    ).toHaveCount(0);
    await expect(page.locator(".studio-sidebar")).toHaveCount(0);
    await page.goto("/eventos");
    await expect(
        page.getByRole("button", { name: "Eliminar evento" }),
    ).toHaveCount(0);
});

test("mobile conversation folds below the video without losing its contents", async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.route("**/eventos/2/chat", (route) => route.request().method() === "GET"
        ? route.fulfill({ json: [{ id: 901, user: "María", message: "Qué buena transmisión de nuestra universidad." }] })
        : route.continue());
    await page.goto("/eventos/2");
    const chat = page.locator(".uebtv-live-chat");
    const toggle = chat.getByRole("button", { name: "Mostrar comentarios" });
    await expect(chat).toBeVisible();
    await expect(chat.locator(".uebtv-live-chat__content")).toBeHidden();
    await expect(chat.locator(".uebtv-chat-preview")).toBeVisible();
    await expect(chat.locator(".uebtv-chat-preview")).toContainText("María");
    const player = await page.locator(".uebtv-watch-main").boundingBox();
    const panel = await chat.boundingBox();
    expect(panel.y).toBeGreaterThanOrEqual(player.y + player.height);
    await expect(page.locator("body")).toHaveJSProperty("scrollWidth", 390);
    await chat.screenshot({ path: "/tmp/uebtv-chat-mobile-collapsed.png" });
    await toggle.click();
    await expect(chat.getByRole("button", { name: "Ocultar comentarios" })).toHaveAttribute("aria-expanded", "true");
    await expect(chat.locator(".uebtv-live-chat__messages")).toBeVisible();
    await expect(chat.locator(".uebtv-chat-message")).toContainText("Qué buena transmisión");
    await chat.screenshot({ path: "/tmp/uebtv-chat-mobile-open.png" });
    await page.locator("html").evaluate((root) => root.setAttribute("data-theme", "dark"));
    await expect(chat.locator(".uebtv-live-chat__messages")).toBeVisible();
    await chat.screenshot({ path: "/tmp/uebtv-chat-mobile-dark.png" });
    await page.setViewportSize({ width: 320, height: 720 });
    await expect(page.locator("body")).toHaveJSProperty("scrollWidth", 320);
    await page.setViewportSize({ width: 1440, height: 900 });
    await expect(chat.locator(".uebtv-live-chat__messages")).toBeVisible();
    await expect(chat.locator(".uebtv-chat-expand")).toBeHidden();
    await chat.screenshot({ path: "/tmp/uebtv-chat-desktop-dark.png" });
});

test("chat preserves drafts on rejected sends, escapes content and reconnects", async ({
    page,
}) => {
    await login(page, "student@ui.test");
    let code = 429,
        offline = false,
        polls = 0;
    await page.route("**/eventos/2/chat", async (route) => {
        if (route.request().method() === "POST") {
            if (offline) return route.abort();
            return route.fulfill({
                status: code,
                json: { errors: { message: ["Mensaje demasiado largo"] } },
            });
        }
        polls++;
        if (offline) return route.abort();
        return route.fulfill({
            json: [
                {
                    id: 999,
                    user: "Comunidad",
                    message: "<script>window.chatUnsafe=true</script>",
                },
            ],
            headers: { ETag: '"mock-feed"' },
        });
    });
    await page.goto("/eventos/2");
    await expect(
        page.locator(".uebtv-chat-message").filter({ hasText: "<script>" }),
    ).toBeVisible();
    expect(await page.evaluate(() => window.chatUnsafe)).toBeUndefined();
    const input = page.getByRole("textbox", { name: "Tu mensaje" });
    await input.fill("Un mensaje que se conserva");
    for (const [status, message] of [
        [429, "Espera un minuto"],
        [419, "sesión venció"],
        [422, "Mensaje demasiado largo"],
    ]) {
        code = status;
        await page.getByRole("button", { name: "Enviar mensaje" }).click();
        await expect(page.getByRole("alert")).toContainText(message);
        await expect(input).toHaveValue("Un mensaje que se conserva");
        await expect(
            page.getByRole("button", { name: "Enviar mensaje" }),
        ).toBeEnabled();
    }
    offline = true;
    await page.getByRole("button", { name: "Enviar mensaje" }).click();
    await expect(page.getByRole("alert")).toContainText("Conservamos tu texto");
    await expect(input).toHaveValue("Un mensaje que se conserva");
    await expect(
        page.getByRole("status").filter({ hasText: "Reconectando" }),
    ).toBeVisible({ timeout: 10000 });
    offline = false;
    await expect(
        page.getByRole("status").filter({ hasText: "Reconectando" }),
    ).toHaveCount(0, { timeout: 15000 });
    expect(polls).toBeGreaterThan(2);
});

test("scheduled playback joins the server position and becomes seekable after ending", async ({
    page,
}) => {
    let state = "upcoming";
    await page.route("**/eventos/1", async (route) => {
        const response = await route.fetch();
        let html = await response.text();
        const match = html.match(
            /data-react-page="Watch" data-props="([^"]*)"/,
        );
        const decode = (s) =>
            s
                .replaceAll("&quot;", '"')
                .replaceAll("&amp;", "&")
                .replaceAll("&#039;", "'")
                .replaceAll("&lt;", "<")
                .replaceAll("&gt;", ">");
        const props = JSON.parse(decode(match[1]));
        props.event.scheduled = {
            state: "upcoming",
            stateUrl: "/mock-clock",
            mediaUrl: "/mock-video",
        };
        const encoded = JSON.stringify(props)
            .replaceAll("&", "&amp;")
            .replaceAll('"', "&quot;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;");
        html = html.replace(match[1], encoded);
        await route.fulfill({ response, body: html });
    });
    await page.route("**/mock-clock", (route) =>
        route.fulfill({
            json: {
                state,
                position: 20,
                duration: 60,
                starts_at: new Date(Date.now() + 60000).toISOString(),
            },
        }),
    );
    await page.route("**/mock-video", (route) => {
        const data = readFileSync("/tmp/uebtv-react-test.mp4");
        const range = route
            .request()
            .headers()
            .range?.match(/bytes=(\d+)-(\d*)/);
        if (!range)
            return route.fulfill({
                body: data,
                contentType: "video/mp4",
                headers: { "Accept-Ranges": "bytes" },
            });
        const start = Number(range[1]),
            end = range[2]
                ? Math.min(Number(range[2]), data.length - 1)
                : data.length - 1;
        return route.fulfill({
            status: 206,
            body: data.subarray(start, end + 1),
            contentType: "video/mp4",
            headers: {
                "Accept-Ranges": "bytes",
                "Content-Range": `bytes ${start}-${end}/${data.length}`,
            },
        });
    });
    await page.goto("/eventos/1");
    await expect(
        page.getByText("Pregrabado · Comienza el", { exact: false }),
    ).toBeVisible();
    await expect(page.locator("video")).not.toHaveAttribute(
        "src",
        /mock-video/,
    );
    state = "playing";
    await expect(page.locator("video")).toHaveAttribute("src", "/mock-video", {
        timeout: 10000,
    });
    await page.locator("video").evaluate((v) => {
        v.muted = true;
        return v.play();
    });
    await expect
        .poll(() => page.locator("video").evaluate((v) => v.paused))
        .toBe(false);
    await expect
        .poll(() => page.locator("video").evaluate((v) => v.currentTime))
        .toBeGreaterThan(18);
    await page.locator("video").evaluate((v) => {
        v.currentTime = 0;
    });
    await expect
        .poll(() => page.locator("video").evaluate((v) => v.currentTime))
        .toBeGreaterThan(18);
    state = "ended";
    await expect(
        page.getByText("Pregrabado · Disponible bajo demanda"),
    ).toBeVisible({ timeout: 10000 });
    await page.locator("video").evaluate((v) => {
        v.pause();
        v.currentTime = 5;
    });
    await expect
        .poll(() => page.locator("video").evaluate((v) => v.currentTime))
        .toBe(5);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.locator("video").evaluate((v) => { v.currentTime = 30; });
    const progress = page.getByRole("slider", { name: "Posición del video" });
    await expect(progress).toHaveAttribute("style", /--player-progress: 50%/);
    const mobileControls = await page.evaluate(() => {
        const bar = document.querySelector('.uebtv-player-range--progress');
        const shell = document.querySelector('.uebtv-player-shell');
        const speed = document.querySelector('.uebtv-player-speed');
        return {
            barWidth: bar.getBoundingClientRect().width,
            playerWidth: shell.getBoundingClientRect().width,
            barHeight: bar.getBoundingClientRect().height,
            speedHeight: speed.getBoundingClientRect().height,
        };
    });
    expect(mobileControls.barWidth).toBeGreaterThan(mobileControls.playerWidth * 0.8);
    expect(mobileControls.barHeight).toBe(16);
    expect(mobileControls.speedHeight).toBe(32);
    await expect(page.locator('.react-player-label')).toBeHidden();
    await page.screenshot({ path: '/tmp/uebtv-vod-mobile-controls.png' });
    await page.evaluate(() => { document.documentElement.dataset.theme = 'dark'; });
    await expect(page.locator('.uebtv-watch-state').first()).toHaveCSS('color', 'rgb(228, 242, 255)');
    await page.screenshot({ path: '/tmp/uebtv-vod-mobile-controls-dark.png' });
});

test("live player reconnects after a native HLS error without reloading the page", async ({ page }) => {
    await page.addInitScript(() => {
        const original = HTMLMediaElement.prototype.canPlayType;
        HTMLMediaElement.prototype.canPlayType = function (type) {
            return type === "application/vnd.apple.mpegurl" ? "probably" : original.call(this, type);
        };
    });
    await page.route("**/eventos/1", async (route) => {
        const response = await route.fetch();
        let html = await response.text();
        const match = html.match(/data-react-page="Watch" data-props="([^"]*)"/);
        const decode = (value) => value.replaceAll("&quot;", '"').replaceAll("&amp;", "&").replaceAll("&#039;", "'").replaceAll("&lt;", "<").replaceAll("&gt;", ">");
        const props = JSON.parse(decode(match[1]));
        props.event.status = "en_vivo";
        props.event.scheduled = null;
        props.event.videoUrl = null;
        props.event.hlsUrl = "/mock-live.m3u8";
        props.event.viewersUrl = null;
        const encoded = JSON.stringify(props).replaceAll("&", "&amp;").replaceAll('"', "&quot;").replaceAll("<", "&lt;").replaceAll(">", "&gt;");
        html = html.replace(match[1], encoded);
        await route.fulfill({ response, body: html });
    });
    await page.route("**/mock-live.m3u8**", (route) => route.fulfill({ body: "#EXTM3U\n", contentType: "application/vnd.apple.mpegurl" }));
    await page.goto("/eventos/1");
    const video = page.locator("video");
    await expect(video).toHaveAttribute("src", /mock-live\.m3u8\?reconnect=0/);
    await video.evaluate((element) => element.dispatchEvent(new Event("error")));
    await expect(page.getByText("Reconectando automáticamente", { exact: false })).toBeVisible();
    await expect(video).toHaveAttribute("src", /mock-live\.m3u8\?reconnect=1/, { timeout: 5000 });
});

test("live HLS tolerates a slow first fragment without rebuilding the player", async ({ page }) => {
    test.setTimeout(45000);
    await page.addInitScript(() => {
        const original = HTMLMediaElement.prototype.canPlayType;
        HTMLMediaElement.prototype.canPlayType = function (type) {
            return type === "application/vnd.apple.mpegurl" ? "" : original.call(this, type);
        };
    });
    let manifests = 0, fragments = 0, firstFragmentDelivered = false;
    await page.route("**/senal/estado", (route) => route.fulfill({ json: {
        mode: "live", event: { id: 44, title: "Directo de prueba", status: "en_vivo", hlsUrl: "/slow-live.m3u8", url: "/eventos/44" },
        playback: null, advertisement: null, next: [], summary: { count: 0, totalSeconds: 0, remainingSeconds: 0 },
    } }));
    await page.route("**/slow-live.m3u8", (route) => {
        manifests++;
        return route.fulfill({ body: readFileSync("/tmp/uebtv-react-hls.m3u8"), contentType: "application/vnd.apple.mpegurl" });
    });
    await page.route("**/uebtv-react-hls-*.ts", async (route) => {
        fragments++;
        const first = fragments === 1;
        if (first) await new Promise((resolve) => setTimeout(resolve, 12000));
        const filename = new URL(route.request().url()).pathname.split("/").pop();
        await route.fulfill({ body: readFileSync(`/tmp/${filename}`), contentType: "video/mp2t" });
        if (first) firstFragmentDelivered = true;
    });
    await page.goto("/senal");
    await expect.poll(() => firstFragmentDelivered, { timeout: 30000 }).toBe(true);
    await expect.poll(() => page.locator("video").evaluate((v) => v.currentTime), { timeout: 30000 }).toBeGreaterThan(0);
    expect(fragments).toBeGreaterThan(0);
    expect(manifests).toBe(1);
    await expect(page.getByText("Reconectando automáticamente", { exact: false })).toHaveCount(0);
});

test("React event form creates, updates and deletes through Laravel routes", async ({
    page,
}) => {
    await login(page);
    await page.goto("/admin/eventos/create");
    await page
        .getByLabel("Título *", { exact: true })
        .fill("Evento de integración React");
    await page
        .getByLabel("Tipo de evento *")
        .selectOption({ label: "Conferencias" });
    await page.getByLabel("Categoría *").selectOption({ label: "Académico" });
    await page.getByLabel("Fecha y hora de inicio *").fill("2027-10-01T12:00");
    await page
        .getByRole("button", { name: "Guardar y generar clave OBS" })
        .click();
    await page.waitForURL(/admin\/eventos\/\d+$/);
    await expect(
        page.getByRole("heading", { name: "Evento de integración React." }),
    ).toBeVisible();
    await page.getByRole("link", { name: "Editar evento" }).click();
    await page
        .getByLabel("Título *", { exact: true })
        .fill("Evento actualizado en React");
    await page.getByRole("button", { name: "Guardar cambios" }).click();
    await page.waitForURL(/admin\/eventos\/\d+$/);
    await expect(
        page.getByRole("heading", { name: "Evento actualizado en React." }),
    ).toBeVisible();
    await page.goto("/admin/eventos");
    page.on("dialog", (d) => d.accept());
    await page
        .locator(".studio-management-row")
        .filter({ hasText: "Evento actualizado en React" })
        .getByRole("button", { name: "Eliminar evento Evento actualizado en React" })
        .click();
    await expect(
        page.getByRole("heading", { name: "Evento actualizado en React" }),
    ).toHaveCount(0);
});

test("continuous channel switches from schedule to live and resumes before the next video", async ({
    page,
}) => {
    test.setTimeout(60000);
    const errors = [];
    page.on("pageerror", (e) => errors.push(e.message));
    let mode = "scheduled",
        id = 1,
        position = 12;
    await page.route("**/senal/estado", (route) =>
        route.fulfill({
            json: {
                mode,
                event:
                    mode === "live"
                        ? {
                              id: 99,
                              title: "Encuentro en directo",
                              status: "en_vivo",
                              hlsUrl: "/mock-live.m3u8",
                              url: "/eventos/99",
                          }
                        : {
                              id,
                              title:
                                  id === 1
                                      ? "Documental institucional"
                                      : "Segundo programa",
                              status: "programado",
                              scheduled: { mediaUrl: "/channel-video.mp4" },
                              url: `/eventos/${id}`,
                          },
                playback:
                    mode === "live"
                        ? null
                        : { state: "playing", position, duration: 60 },
                next: [
                    {
                        id: 2,
                        title: "Próxima emisión",
                        starts_at: "2027-01-01T12:00:00Z",
                        duration: 60,
                    },
                ],
                summary: { remainingSeconds: 100, totalSeconds: 120 },
                resumeTitle: "Documental institucional",
            },
        }),
    );
    await page.route("**/mock-live.m3u8", (route) =>
        route.fulfill({ status: 503, body: "No real OBS source in this test" }),
    );
    const bytes = readFileSync("/tmp/uebtv-react-test.mp4");
    await page.route("**/channel-video.mp4", (route) => {
        const range = route.request().headers()["range"];
        const start = range ? Number(range.match(/bytes=(\d+)/)?.[1] || 0) : 0;
        return route.fulfill({
            status: range ? 206 : 200,
            contentType: "video/mp4",
            headers: {
                "Accept-Ranges": "bytes",
                ...(range
                    ? {
                          "Content-Range": `bytes ${start}-${bytes.length - 1}/${bytes.length}`,
                      }
                    : {}),
            },
            body: bytes.subarray(start),
        });
    });
    await page.goto("/senal");
    await expect(
        page.getByRole("heading", { name: "Documental institucional" }),
    ).toBeVisible();
    await expect
        .poll(() => page.locator("video").evaluate((v) => v.currentTime))
        .toBeGreaterThan(11);
    await page.getByRole("button", { name: "Pantalla completa" }).click();
    const scheduledOverlay = await page.locator(".uebtv-player-shell").evaluate((element) => ({
        titleRight: element.querySelector(".uebtv-player-title").getBoundingClientRect().right,
        watermarkLeft: element.querySelector(".uebtv-player-mark").getBoundingClientRect().left,
    }));
    expect(scheduledOverlay.titleRight).toBeLessThan(scheduledOverlay.watermarkLeft);
    await page.keyboard.press("Escape");
    await page.screenshot({ path: "/tmp/uebtv-channel.png", fullPage: true });
    mode = "live";
    await expect(
        page.getByRole("heading", { name: "Encuentro en directo" }),
    ).toBeVisible({ timeout: 10000 });
    await expect(page.locator(".uebtv-player-live")).toBeVisible();
    // The fixture has no real HLS source, so its reconnect notice covers the
    // controls. The player shortcut still lets us inspect the live overlays.
    await page.keyboard.press("f");
    await expect.poll(() => page.locator(".uebtv-player-shell").evaluate((element) => document.fullscreenElement === element)).toBe(true);
    const liveOverlay = await page.locator(".uebtv-player-shell").evaluate((element) => ({
        liveBottom: element.querySelector(".uebtv-player-live").getBoundingClientRect().bottom,
        titleTop: element.querySelector(".uebtv-player-title").getBoundingClientRect().top,
    }));
    expect(liveOverlay.titleTop).toBeGreaterThan(liveOverlay.liveBottom);
    await page.keyboard.press("Escape");
    await page.setViewportSize({ width: 390, height: 844 });
    await page.keyboard.press("f");
    await expect.poll(() => page.locator(".uebtv-player-shell").evaluate((element) => document.fullscreenElement === element)).toBe(true);
    const mobileLiveOverlay = await page.locator(".uebtv-player-shell").evaluate((element) => ({
        liveBottom: element.querySelector(".uebtv-player-live").getBoundingClientRect().bottom,
        titleTop: element.querySelector(".uebtv-player-title").getBoundingClientRect().top,
        titleRight: element.querySelector(".uebtv-player-title").getBoundingClientRect().right,
        watermarkLeft: element.querySelector(".uebtv-player-mark").getBoundingClientRect().left,
    }));
    expect(mobileLiveOverlay.titleTop).toBeGreaterThan(mobileLiveOverlay.liveBottom);
    expect(mobileLiveOverlay.titleRight).toBeLessThan(mobileLiveOverlay.watermarkLeft);
    await page.keyboard.press("Escape");
    await page.setViewportSize({ width: 1440, height: 1000 });
    mode = "scheduled";
    position = 18;
    await expect(
        page.getByRole("heading", { name: "Documental institucional" }),
    ).toBeVisible({ timeout: 10000 });
    await expect
        .poll(() => page.locator("video").evaluate((v) => v.currentTime))
        .toBeGreaterThan(17);
    id = 2;
    position = 0;
    await expect(
        page.getByRole("heading", { name: "Segundo programa" }),
    ).toBeVisible({ timeout: 10000 });
    await expect
        .poll(() => page.locator("video").evaluate((v) => v.currentTime))
        .toBeLessThan(6);
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.locator("body")).toHaveJSProperty("scrollWidth", 390);
    await page.keyboard.press("f");
    await expect.poll(() => page.locator(".uebtv-player-shell").evaluate((element) => document.fullscreenElement === element)).toBe(true);
    const mobileScheduledOverlay = await page.locator(".uebtv-player-shell").evaluate((element) => ({
        titleRight: element.querySelector(".uebtv-player-title").getBoundingClientRect().right,
        watermarkLeft: element.querySelector(".uebtv-player-mark").getBoundingClientRect().left,
    }));
    expect(mobileScheduledOverlay.titleRight).toBeLessThan(mobileScheduledOverlay.watermarkLeft);
    await page.keyboard.press("Escape");
    expect(errors).toEqual([]);
});

test("institutional advertising fills a channel gap with a P and no seek control", async ({ page }) => {
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));
    await page.route("**/senal/estado", (route) => route.fulfill({ json: {
        mode: "advertisement", event: null,
        advertisement: { id: 7, title: "Campaña institucional", mediaUrl: "/mock-ad.mp4" },
        playback: { state: "playing", position: 1, duration: 60 },
        next: [], summary: { count: 0, totalSeconds: 0, remainingSeconds: 0 },
    } }));
    const bytes = readFileSync("/tmp/uebtv-react-test.mp4");
    await page.route("**/mock-ad.mp4", (route) => {
        const range = route.request().headers()["range"];
        const start = range ? Number(range.match(/bytes=(\d+)/)?.[1] || 0) : 0;
        return route.fulfill({ status: range ? 206 : 200, contentType: "video/mp4",
            headers: { "Accept-Ranges": "bytes", ...(range ? { "Content-Range": `bytes ${start}-${bytes.length - 1}/${bytes.length}` } : {}) },
            body: bytes.subarray(start) });
    });
    await page.goto("/senal");
    await expect(page.getByLabel("Publicidad institucional")).toBeVisible();
    await expect(page.getByText("P · Publicidad institucional")).toBeVisible();
    await expect(page.getByRole("heading", { name: "Campaña institucional" })).toBeVisible();
    await expect(page.getByRole("slider", { name: "Posición del video" })).toHaveCount(0);
    const video = page.locator(".channel-screen video");
    await expect(video).toHaveJSProperty("paused", false);
    await expect(page.getByRole("button", { name: "Pausar", exact: true })).toHaveCount(0);
    await expect(page.getByRole("button", { name: "Imagen en imagen" })).toHaveCount(0);
    await expect(video).toHaveJSProperty("disablePictureInPicture", true);
    await page.keyboard.press("Space");
    await expect(video).toHaveJSProperty("paused", false);
    const volume = page.locator('input[aria-label="Volumen"]');
    await expect(volume).toBeVisible();
    await expect(volume).toHaveCSS("height", "6px");
    const badge = page.locator(".channel-ad-indicator");
    const title = page.locator(".uebtv-player-title");
    await page.getByRole("button", { name: "Pantalla completa" }).click();
    await expect(title).toBeVisible();
    let badgeBox = await badge.boundingBox(), titleBox = await title.boundingBox();
    expect(titleBox.x).toBeGreaterThanOrEqual(badgeBox.x + badgeBox.width + 6);
    await page.keyboard.press("Escape");
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(volume).toBeHidden();
    await expect(page.getByRole("button", { name: "Activar sonido" })).toBeVisible();
    await page.getByRole("button", { name: "Pantalla completa" }).click();
    await expect(title).toBeVisible();
    badgeBox = await badge.boundingBox(); titleBox = await title.boundingBox();
    expect(titleBox.x).toBeGreaterThanOrEqual(badgeBox.x + badgeBox.width + 6);
    await page.keyboard.press("Escape");
    expect(errors).toEqual([]);
});

test("channel keeps playing muted when the browser rejects audible autoplay on the next ad", async ({ page }) => {
    await page.addInitScript(() => {
        const original = HTMLMediaElement.prototype.play;
        HTMLMediaElement.prototype.play = function (...args) {
            if (!this.muted) return Promise.reject(new DOMException("Audio autoplay blocked", "NotAllowedError"));
            return original.apply(this, args);
        };
    });
    let id = 1;
    await page.route("**/senal/estado", (route) => route.fulfill({ json: {
        mode: "advertisement", event: null,
        advertisement: { id, title: `Anuncio ${id}`, mediaUrl: "/mock-ad.mp4" },
        playback: { state: "playing", position: 1, duration: 60 },
        next: [], summary: { count: 0, totalSeconds: 0, remainingSeconds: 0 },
    } }));
    const bytes = readFileSync("/tmp/uebtv-react-test.mp4");
    await page.route("**/mock-ad.mp4", (route) => {
        const range = route.request().headers()["range"];
        const start = range ? Number(range.match(/bytes=(\d+)/)?.[1] || 0) : 0;
        return route.fulfill({ status: range ? 206 : 200, contentType: "video/mp4",
            headers: { "Accept-Ranges": "bytes", ...(range ? { "Content-Range": `bytes ${start}-${bytes.length - 1}/${bytes.length}` } : {}) },
            body: bytes.subarray(start) });
    });
    await page.goto("/senal");
    await expect.poll(() => page.locator("video").evaluate((v) => v.currentTime)).toBeGreaterThan(1);
    await page.getByRole("button", { name: "Activar sonido" }).click();
    await expect(page.locator("video")).toHaveJSProperty("muted", false);
    id = 2;
    await expect(page.getByRole("heading", { name: "Anuncio 2" })).toBeVisible({ timeout: 10000 });
    await expect(page.locator("video")).toHaveJSProperty("muted", true);
    await expect.poll(() => page.locator("video").evaluate((v) => v.currentTime)).toBeGreaterThan(1);
    await expect(page.getByText("Pulsa reproducir para unirte a la señal.")).toHaveCount(0);
});

test("channel retries an interrupted ad without asking the viewer to reload", async ({ page }) => {
    await page.route("**/senal/estado", (route) => route.fulfill({ json: {
        mode: "advertisement", event: null,
        advertisement: { id: 7, title: "Aviso institucional", mediaUrl: "/mock-ad.mp4" },
        playback: { state: "playing", position: 1, duration: 60 },
        next: [], summary: { count: 0, totalSeconds: 0, remainingSeconds: 0 },
    } }));
    const bytes = readFileSync("/tmp/uebtv-react-test.mp4");
    let attempts = 0;
    await page.route("**/mock-ad.mp4*", (route) => {
        attempts++;
        if (attempts === 1) return route.fulfill({ status: 503, body: "Temporary video error" });
        const range = route.request().headers()["range"];
        const start = range ? Number(range.match(/bytes=(\d+)/)?.[1] || 0) : 0;
        return route.fulfill({ status: range ? 206 : 200, contentType: "video/mp4",
            headers: { "Accept-Ranges": "bytes", ...(range ? { "Content-Range": `bytes ${start}-${bytes.length - 1}/${bytes.length}` } : {}) },
            body: bytes.subarray(start) });
    });
    await page.goto("/senal");
    await expect.poll(() => page.locator("video").evaluate((v) => v.currentTime), { timeout: 15000 }).toBeGreaterThan(1);
    expect(attempts).toBeGreaterThan(1);
    await expect(page.getByText("Pulsa reproducir para unirte a la señal.")).toHaveCount(0);
});

for (const mode of ["advertisement", "scheduled"]) {
    test(`continuous ${mode} waits for a slow MP4 instead of restarting it`, async ({ page }) => {
        test.setTimeout(45000);
        const bytes = readFileSync("/tmp/uebtv-react-test.mp4");
        let requests = 0, reloads = 0;
        await page.route("**/senal/estado", (route) => route.fulfill({ json: {
            mode,
            event: mode === "scheduled" ? { id: 8, title: "Programa de prueba", status: "programado", scheduled: { mediaUrl: "/slow-channel.mp4" }, url: "/eventos/8" } : null,
            advertisement: mode === "advertisement" ? { id: 8, title: "Anuncio de prueba", mediaUrl: "/slow-channel.mp4" } : null,
            playback: { state: "playing", position: 20, duration: 60 },
            next: [], summary: { count: 0, totalSeconds: 0, remainingSeconds: 0 },
        } }));
        await page.route("**/slow-channel.mp4*", async (route) => {
            requests++;
            if (route.request().url().includes("reconnect=")) reloads++;
            if (requests === 1) await new Promise((resolve) => setTimeout(resolve, 12000));
            const range = route.request().headers().range?.match(/bytes=(\d+)-(\d*)/);
            const start = range ? Number(range[1]) : 0;
            const end = range?.[2] ? Math.min(Number(range[2]), bytes.length - 1) : bytes.length - 1;
            await route.fulfill({ status: range ? 206 : 200, contentType: "video/mp4",
                headers: { "Accept-Ranges": "bytes", ...(range ? { "Content-Range": `bytes ${start}-${end}/${bytes.length}` } : {}) },
                body: bytes.subarray(start, end + 1) });
        });
        await page.goto("/senal");
        await expect.poll(() => page.locator("video").evaluate((v) => v.currentTime), { timeout: 30000 }).toBeGreaterThan(19);
        expect(reloads).toBe(0);
        await expect(page.getByText("Reconectando automáticamente", { exact: false })).toHaveCount(0);
    });
}

test("a repeated institutional ad returns to its new cycle without reloading", async ({ page }) => {
    let position = 56;
    await page.route("**/senal/estado", (route) => route.fulfill({ json: {
        mode: "advertisement", event: null,
        advertisement: { id: 7, title: "Anuncio en ciclo", mediaUrl: "/loop-ad.mp4" },
        playback: { state: "playing", position, duration: 60 },
        next: [], summary: { count: 0, totalSeconds: 0, remainingSeconds: 0 },
    } }));
    const bytes = readFileSync("/tmp/uebtv-react-test.mp4");
    await page.route("**/loop-ad.mp4*", (route) => {
        const range = route.request().headers().range?.match(/bytes=(\d+)-(\d*)/);
        const start = range ? Number(range[1]) : 0;
        const end = range?.[2] ? Math.min(Number(range[2]), bytes.length - 1) : bytes.length - 1;
        return route.fulfill({ status: range ? 206 : 200, contentType: "video/mp4",
            headers: { "Accept-Ranges": "bytes", ...(range ? { "Content-Range": `bytes ${start}-${end}/${bytes.length}` } : {}) },
            body: bytes.subarray(start, end + 1) });
    });
    await page.goto("/senal");
    await expect.poll(() => page.locator("video").evaluate((v) => v.currentTime)).toBeGreaterThan(55);
    position = 2;
    await expect.poll(() => page.locator("video").evaluate((v) => v.currentTime), { timeout: 12000 }).toBeLessThan(10);
    await expect.poll(() => page.locator("video").evaluate((v) => v.currentTime), { timeout: 12000 }).toBeGreaterThan(2);
    await expect(page.locator("video")).toHaveAttribute("src", "/loop-ad.mp4");
});

test("admin sees separate programming and institutional advertising forms on mobile", async ({ page }) => {
    await login(page);
    await page.goto("/admin/contenido-programado");
    const quickNav = page.getByRole("navigation", { name: "Navegación rápida de la parrilla" });
    await expect(quickNav.getByRole("link", { name: /Nueva programación/ })).toBeVisible();
    await expect(quickNav.getByRole("link", { name: /Eventos programados/ })).toBeVisible();
    await quickNav.getByRole("link", { name: /Anuncios institucionales/ }).click();
    await expect(page).toHaveURL(/#schedule-ads$/);
    await expect(page.getByRole("heading", { name: "Publicidad institucional" })).toBeVisible();
    await expect(page.getByLabel("Nombre del anuncio")).toBeVisible();
    await expect(page.getByLabel("Archivo de video")).toBeVisible();
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.locator("body")).toHaveJSProperty("scrollWidth", 390);
    await page.setViewportSize({ width: 320, height: 720 });
    await expect(page.locator("body")).toHaveJSProperty("scrollWidth", 320);
});

test("advertising upload shows progress and a recoverable server error", async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page);
    await page.goto("/admin/contenido-programado");
    await page.getByLabel("Nombre del anuncio").fill("Campaña institucional");
    await page.getByLabel("Archivo de video").setInputFiles({
        name: "campana.mp4", mimeType: "video/mp4", buffer: Buffer.alloc(1024),
    });
    await page.route("**/admin/contenido-programado/publicidad", async (route) => {
        await new Promise((resolve) => setTimeout(resolve, 700));
        await route.fulfill({ status: 422, contentType: "application/json",
            body: JSON.stringify({ errors: { ad_video: ["Archivo rechazado por el servidor."] } }) });
    });
    await page.getByRole("button", { name: "Subir publicidad" }).click();
    await expect(page.getByRole("progressbar", { name: "Progreso de subida del video" })).toBeVisible();
    await expect(page.locator("body")).toHaveJSProperty("scrollWidth", 390);
    await expect(page.getByRole("alert").filter({ hasText: "Archivo rechazado por el servidor." })).toBeVisible();
    await expect(page.getByRole("button", { name: "Subir publicidad" })).toBeEnabled();
});

test("desktop header places the institutional brand and mascot near the left edge without changing mobile layout", async ({ page }) => {
    await page.setViewportSize({ width: 1863, height: 900 });
    await login(page);
    await page.goto("/eventos");
    const brand = page.locator(".studio-header .studio-brand--facagei");
    const desktop = await brand.boundingBox();
    expect(desktop).not.toBeNull();
    expect(desktop.x).toBeLessThanOrEqual(36);
    expect(desktop.x).toBeGreaterThanOrEqual(20);
    await expect(brand.locator(".studio-brand-mascot")).toBeVisible();
    await expect(brand.locator(".facagei-brand-logo--header")).toBeVisible();
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.locator("body")).toHaveJSProperty("scrollWidth", 390);
});

test("scheduled upload accepts a real MP4 larger than the old 8 MB POST limit", async ({
    page,
}) => {
    await login(page);
    await page.goto("/admin/contenido-programado");
    await expect(
        page.getByText("MP4, MOV, MKV, WebM, AVI, WMV, MPEG · Hasta 500 MB"),
    ).toBeVisible();
    await page
        .getByLabel("Título de la emisión")
        .fill("Video de prueba de subida grande");
    await page
        .getByLabel("Categoría", { exact: false })
        .selectOption({ label: "Académico" });
    await page.getByLabel("Fecha y hora de inicio").fill("2027-10-01T12:00");
    // A valid ISO BMFF free box makes the fixture exceed 8 MB without a long encoding job.
    const freeBox = Buffer.alloc(9 * 1024 * 1024);
    freeBox.writeUInt32BE(freeBox.length, 0);
    freeBox.write("free", 4);
    const buffer = Buffer.concat([
        readFileSync("/tmp/uebtv-react-test.mp4"),
        freeBox,
    ]);
    await page.locator("#video").setInputFiles({
        name: "programacion.mp4",
        mimeType: "video/mp4",
        buffer,
    });
    await page
        .getByRole("button", { name: "Subir y programar emisión" })
        .click();
    await expect(
        page.getByText(
            "Video recibido. Se está preparando para la emisión; aparecerá en la programación cuando esté listo.",
        ),
    ).toBeVisible();
    await expect(
        page
            .locator(".schedule-event")
            .filter({ hasText: "Video de prueba de subida grande" }),
    ).toBeVisible();
    await expect(
        page
            .locator(".schedule-event")
            .filter({ hasText: "Video de prueba de subida grande" })
            .getByText("En cola", { exact: true }),
    ).toBeVisible();
    await expect(page.locator(".schedule-event").filter({ hasText: "Video de prueba de subida grande" })
        .getByText("En cola para convertir")).toBeVisible();
});
