import React from "react";
const paths = [
    "M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z",
    "M5 5h14v16H5z M8 3v4 M16 3v4 M5 10h14 M8 14h3 M8 17h7",
    "M12 10v4 M9 7a7 7 0 0 0 0 10 M15 7a7 7 0 0 1 0 10 M6 4a11 11 0 0 0 0 16 M18 4a11 11 0 0 1 0 16",
    "M9 7l8 5-8 5z M3 3h18v18H3z",
    "M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18 M12 7v5l4 2",
    "M3 4h18v16H3z M3 16l5-5 4 4 3-3 6 6 M15 8h2",
    "M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2 M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M20 8v6 M23 11h-6",
    // 7 biblioteca (caja de archivos)
    "M3.5 7.5 12 3l8.5 4.5v9L12 21l-8.5-4.5z M3.5 7.5 12 12l8.5-4.5 M12 12v9",
    // 8 auditoría (escudo con check)
    "M12 3l7.5 3v5.5c0 4.5-3.2 8.2-7.5 9.5-4.3-1.3-7.5-5-7.5-9.5V6z M8.5 12l2.5 2.5 4.5-5",
    // 9 perfil (persona en círculo)
    "M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z M12 12.5a3 3 0 1 0 0-6 3 3 0 0 0 0 6z M6.5 18.5a6 6 0 0 1 11 0",
    // 10 sitio público (ventana con flecha)
    "M14 4h6v6 M20 4l-9 9 M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5",
];
export default function NavIcon({ index }) {
    return (
        <svg
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.5"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            <path d={paths[index]} />
        </svg>
    );
}
