import React from "react";
import { useShell } from "./shared";

export default function UebtvMark({ className = "" }) {
    const { tvLogo } = useShell();

    return (
        <span className={`uebtv-brand-logo ${className}`.trim()}>
            <img
                src={tvLogo}
                alt="Logotipo de UEBTV"
                width="691"
                height="691"
            />
        </span>
    );
}
