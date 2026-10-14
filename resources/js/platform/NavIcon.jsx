import React from "react";
const paths = [
    "M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z",
    "M5 5h14v16H5z M8 3v4 M16 3v4 M5 10h14 M8 14h3 M8 17h7",
    "M12 10v4 M9 7a7 7 0 0 0 0 10 M15 7a7 7 0 0 1 0 10 M6 4a11 11 0 0 0 0 16 M18 4a11 11 0 0 1 0 16",
    "M9 7l8 5-8 5z M3 3h18v18H3z",
    "M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18 M12 7v5l4 2",
    "M3 4h18v16H3z M3 16l5-5 4 4 3-3 6 6 M15 8h2",
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
