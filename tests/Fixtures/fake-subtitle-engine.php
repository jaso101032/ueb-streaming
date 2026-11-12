<?php

// Motor de transcripción falso para las pruebas: imita el contrato de
// scripts/generate-subtitles.py (ENTRADA SALIDA [opciones]) según el
// contenido del video de prueba: "speech", "silence" o "crash".
[, $input, $output] = $argv;
$mode = trim((string) file_get_contents($input));
echo "PROGRESS 0\n";
if ($mode === 'silence') {
    fwrite(STDERR, "No se detectó voz en el video.\n");
    exit(3);
}
if ($mode === 'crash') {
    fwrite(STDERR, "Error al transcribir: memoria insuficiente\n");
    exit(1);
}
if ($mode === 'missing-engine') exit(4);
echo "PROGRESS 50\n";
file_put_contents($output, "WEBVTT\n\n00:00:01.000 --> 00:00:03.000\nBienvenidos a <i>UEBTV</i><script>x</script>\n");
echo "PROGRESS 100\nDONE 1\n";
