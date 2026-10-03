import React from "react";
export default class ErrorBoundary extends React.Component {
    state = { failed: false };
    static getDerivedStateFromError() {
        return { failed: true };
    }
    componentDidCatch(error) {
        console.error("No se pudo mostrar la página UEBTV.", error);
    }
    render() {
        return this.state.failed ? (
            <main className="studio-page">
                <section className="studio-panel react-panel" role="alert">
                    <p className="studio-eyebrow">UEBTV</p>
                    <h1>No se pudo mostrar esta página.</h1>
                    <p>Vuelve a cargar para recuperar tu sesión.</p>
                    <button
                        className="studio-button"
                        onClick={() => window.location.reload()}
                    >
                        Volver a cargar
                    </button>
                </section>
            </main>
        ) : (
            this.props.children
        );
    }
}
