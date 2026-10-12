import React from "react";
import { useShell } from "./shared";

export default function UebtvMark({ className = "" }) {
    const { tvLogo } = useShell();

    return (
        <span className={`uebtv-brand-logo ${className}`.trim()}>
            <img
                src={tvLogo}
                alt="Logotipo de UEBTV"
                width="1774"
                height="887"
            />
        </span>
    );
}
