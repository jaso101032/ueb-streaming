import React from "react";
export default function UploadError({ maxMb, backUrl }) {
    return (
        <main
            className="studio-page"
            style={{ maxWidth: 720, paddingTop: "12vh" }}
        >
            <section
                className="studio-panel"
                style={{ padding: "clamp(24px, 5vw, 48px)" }}
            >
                <p className="studio-eyebrow">UEBTV · SUBIDA DE VIDEO</p>
                <h1
                    style={{
                        fontSize: "clamp(28px, 4vw, 42px)",
                        margin: "20px 0",
                    }}
                >
                    El archivo supera el límite del servidor.
                </h1>
                <p>
                    Este servidor permite videos de hasta{" "}
                    <strong>{maxMb} MB</strong>. El archivo no se guardó.
                </p>
                <p style={{ margin: "18px 0 28px" }}>
                    Selecciona un video más pequeño o solicita ampliar el límite
                    de subida. Después vuelve a seleccionar el archivo.
                </p>
                <a className="studio-button" href={backUrl}>
                    Volver al formulario ↗
                </a>
            </section>
        </main>
    );
}
