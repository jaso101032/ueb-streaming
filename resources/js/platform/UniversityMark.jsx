import React from "react";
import { useShell } from "./shared";

export default function UniversityMark({ className = "" }) {
    const shell = useShell();

    return (
        <span className={`university-mark ${className}`.trim()}>
            <img
                className="university-mark__logo"
                src={shell.logo}
                alt="Logotipo oficial de la Universidad Estatal de Bolívar"
                width="148"
                height="32"
            />
        </span>
    );
}
