import { readFileSync } from "node:fs";
import { test, expect } from "@playwright/test";
const login = async (page, email = "admin@ui.test") => {
    await page.goto("/login");
    await page.getByLabel("Correo electrónico", { exact: true }).fill(email);
    await page.getByLabel("Contraseña", { exact: true }).fill("Browser123!");
    await page.getByRole("button", { name: "Entrar a UEBTV" }).click();
    await page.waitForURL(/admin\/dashboard|eventos/);
};
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
        page.getByRole("button", { name: "Eliminar", exact: true }),
    ).toBeVisible();
    await page.goto("/admin/eventos/1");
    await expect(
        page.getByRole("heading", { name: "Acerca del evento" }),
    ).toBeVisible();
    await page.goto("/streaming/control");
    await expect(
        page.getByRole("heading", { name: "Control de streaming." }),
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
    await page.getByRole("button", { name: "Abrir o cerrar menú" }).click();
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
    await page.goto("/eventos/1");
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
    await page.goto("/eventos/1");
    await expect(
        page.getByRole("button", { name: /Retirar mensaje/ }),
    ).toHaveCount(0);
    await expect(page.locator(".studio-sidebar")).toHaveCount(0);
    await page.goto("/eventos");
    await expect(
        page.getByRole("button", { name: "Eliminar evento" }),
    ).toHaveCount(0);
});

test("chat preserves drafts on rejected sends, escapes content and reconnects", async ({
    page,
}) => {
    await login(page, "student@ui.test");
    let code = 429,
        offline = false,
        polls = 0;
    await page.route("**/eventos/1/chat", async (route) => {
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
    await page.goto("/eventos/1");
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
        .getByRole("button", { name: "Eliminar", exact: true })
        .click();
    await expect(
        page.getByRole("heading", { name: "Evento actualizado en React" }),
    ).toHaveCount(0);
});

test("continuous channel switches from schedule to live and resumes before the next video", async ({
    page,
}) => {
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
    await page.screenshot({ path: "/tmp/uebtv-channel.png", fullPage: true });
    mode = "live";
    await expect(
        page.getByRole("heading", { name: "Encuentro en directo" }),
    ).toBeVisible({ timeout: 10000 });
    await expect(page.getByText("● En directo", { exact: true })).toBeVisible();
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
    expect(errors).toEqual([]);
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
            .getByText("En cola"),
    ).toBeVisible();
});
