import React from "react";
import { useShell } from "./shared";

// This setting belongs only to playback, never to header or institutional logos.
export default function PlayerWatermark({ src, position }) {
    const { playerWatermark, tvLogo } = useShell();
    const fallback = tvLogo || "/images/brand/uebtv-logo-final.png";
    const source = src || playerWatermark?.url || fallback;
    const placement = position || playerWatermark?.position || "top-right";
    return <span className={`uebtv-player-mark is-${placement}`}>
        <img key={source} src={source} alt="Marca de agua del canal" onError={(event) => {
            if (event.currentTarget.getAttribute("src") !== fallback) event.currentTarget.src = fallback;
        }} />
    </span>;
}
