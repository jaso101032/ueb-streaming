import React, { useEffect, useState } from "react";
import { Action, Feedback, Heading, Token } from "./shared";
import PlayerWatermark from "./PlayerWatermark";

const POSITIONS = [
    { id: "top-left", label: "Superior izquierda" },
    { id: "top-right", label: "Superior derecha" },
    { id: "bottom-left", label: "Inferior izquierda" },
    { id: "bottom-right", label: "Inferior derecha" },
];

export default function WatermarkSettings({ watermark, updateUrl, positionUrl, resetUrl }) {
    const [file, setFile] = useState(null), [preview, setPreview] = useState(null);
    const [position, setPosition] = useState(watermark.position || "top-right");
    const [error, setError] = useState(""), [busy, setBusy] = useState(false);
    useEffect(() => {
        if (!file) { setPreview(null); return; }
        const url = URL.createObjectURL(file);
        setPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [file]);
    useEffect(() => {
        const reset = () => setBusy(false);
        window.addEventListener("pageshow", reset);
        return () => window.removeEventListener("pageshow", reset);
    }, []);
    const choose = (event) => {
        const candidate = event.target.files[0];
        setError(""); setFile(null);
        if (!candidate) return;
        if (!/\.png$/i.test(candidate.name) || (candidate.type && candidate.type !== "image/png")) {
            setError("Selecciona un PNG estático con fondo transparente."); return;
        }
        if (candidate.size > 2 * 1024 * 1024) {
            setError("La imagen supera los 2 MB. Exporta una versión más ligera."); return;
        }
        setFile(candidate);
    };

    return <div className="studio-page watermark-settings">
        <Heading eyebrow="IDENTIDAD DEL CANAL" title="Marca de agua">
            Personaliza la imagen y elige en qué esquina aparece dentro del reproductor.
        </Heading>
        <Feedback />
        <div className="watermark-settings-grid">
            <section className="studio-panel react-panel" aria-labelledby="watermark-form-title">
                <h2 id="watermark-form-title">Imagen del reproductor</h2>
                <form action={positionUrl} method="POST" className="watermark-position-form" onSubmit={(event) => {
                    if (position === (watermark.position || "top-right")) { event.preventDefault(); return; }
                    setBusy(true);
                }}>
                    <Token method="PUT" />
                    <fieldset className="watermark-position-picker">
                        <legend>Esquina del logo</legend>
                        <div className="watermark-position-options">
                            {POSITIONS.map(({ id, label }) => <label key={id} className={`watermark-position-option ${position === id ? "is-selected" : ""}`}>
                                <input type="radio" name="position" value={id} checked={position === id} onChange={() => setPosition(id)} />
                                <span className="watermark-position-diagram" aria-hidden="true"><i className={`is-${id}`} /></span>
                                <span>{label}</span>
                            </label>)}
                        </div>
                    </fieldset>
                    <button className="studio-button" disabled={position === (watermark.position || "top-right") || busy}>{busy ? "Guardando…" : "Guardar posición"}</button>
                </form>
                <form action={updateUrl} method="POST" encType="multipart/form-data" className="watermark-form" onSubmit={(event) => {
                    if (!file || error) { event.preventDefault(); return; }
                    setBusy(true);
                }}>
                    <Token method="PUT" />
                    <label htmlFor="watermark">Nueva imagen PNG</label>
                    <input id="watermark" name="watermark" type="file" accept="image/png,.png" required onChange={choose} aria-describedby="watermark-upload-help watermark-rules" aria-invalid={!!error} />
                    <p id="watermark-upload-help">Máximo 2 MB. La transparencia se comprueba en el servidor antes de guardar.</p>
                    {error && <p className="uebtv-form-errors" role="alert">{error}</p>}
                    <button className="studio-button" disabled={!file || !!error || busy}>{busy ? "Guardando…" : "Guardar marca de agua"}</button>
                </form>
                {watermark.custom && <div className="watermark-reset">
                    <Action url={resetUrl} method="DELETE" className="studio-button-soft" confirm="¿Restaurar el logo oficial de UEBTV en los reproductores?">Restaurar logo oficial</Action>
                </div>}
                <p className="watermark-scope">Se aplica al abrir o recargar directos, grabaciones, programación y publicidad. No cambia la cabecera, el logo de la universidad ni los archivos de video.</p>
            </section>
            <aside className="studio-panel react-panel watermark-guide" aria-labelledby="watermark-guide-title">
                <h2 id="watermark-guide-title">Cómo preparar la imagen</h2>
                <ul id="watermark-rules">
                    <li>Usa únicamente el logo institucional oficial o una variante autorizada.</li>
                    <li>Exporta como <strong>PNG estático con fondo transparente</strong>. No basta con cambiar la extensión.</li>
                    <li>Ancho: <strong>128–2048 px</strong>. Alto: <strong>64–2048 px</strong>. Forma cuadrada u horizontal, entre 1:1 y 6:1.</li>
                    <li>Recomendado: <strong>768 × 384 px</strong>, sin márgenes grandes y con un pequeño espacio transparente alrededor.</li>
                    <li>Prefiere una versión blanca o con contorno que se distinga sobre escenas claras y oscuras.</li>
                </ul>
                <p>Se rechazan imágenes opacas, vacías, animadas, con bordes sin transparencia y archivos JPG, SVG, GIF o WebP. Una cuadrícula pintada no es transparencia.</p>
                <p>La validación no reconoce si el logo es oficial: revisa su autorización y acabado antes de guardarlo. No quitamos fondos ni redibujamos la marca.</p>
            </aside>
        </div>
        <section className="studio-panel react-panel watermark-previews" aria-labelledby="watermark-preview-title">
            <h2 id="watermark-preview-title">Vista previa</h2>
            <p role="status">{preview ? "Imagen seleccionada · todavía sin guardar. El servidor debe validar su transparencia." : "Así se muestra la marca actual. Comprueba su contraste sobre ambos fondos."}</p>
            <div className="watermark-preview-grid">
                {["dark", "light"].map((background) => <figure key={background}>
                    <div className={`watermark-preview-stage is-${background}`}>
                        <span className="watermark-preview-caption">Área del video</span>
                        <PlayerWatermark src={preview || watermark.url} position={position} />
                    </div>
                    <figcaption>{background === "dark" ? "Sobre fondo oscuro" : "Sobre fondo claro y cuadrícula de transparencia"}</figcaption>
                </figure>)}
            </div>
        </section>
    </div>;
}
