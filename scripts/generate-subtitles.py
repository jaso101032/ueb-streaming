#!/usr/bin/env python3
"""Genera subtítulos WebVTT en español a partir de un video o audio almacenado.

Uso: generate-subtitles.py ENTRADA SALIDA.vtt [--model small] [--model-dir DIR]
                           [--threads 2] [--language es]

Usa faster-whisper (Whisper, de código abierto) en la CPU: no envía el audio a
ningún servicio externo ni tiene costo por minuto. Escribe en la salida estándar
líneas "PROGRESS <0-100>" para que Laravel muestre el avance.

Códigos de salida: 0 listo · 3 no se detectó voz · 4 falta faster-whisper ·
1 cualquier otro error.
"""

import argparse
import os
import re
import subprocess
import sys
import tempfile

EXIT_NO_SPEECH = 3
EXIT_MISSING_ENGINE = 4

MAX_LINE = 42           # caracteres por línea (pauta habitual de subtitulado)
MAX_CHARS = MAX_LINE * 2  # dos líneas por cue como máximo
MAX_SECONDS = 6.0       # duración máxima de un cue
MIN_SECONDS = 1.0       # duración mínima para que alcance a leerse
PAUSE_BREAK = 0.8       # una pausa más larga inicia un nuevo cue

# Frases que Whisper "inventa" en silencios o música (vienen de sus datos de
# entrenamiento). Si un segmento es solo eso, se descarta.
HALLUCINATIONS = re.compile(
    r"amara\.org|subt[ií]tulos (realizados|por|hechos)|suscr[ií]bete|gracias por ver|"
    r"¡?no olvides suscribirte|www\.|\.com\b",
    re.IGNORECASE,
)


def timestamp(seconds):
    millis = int(round(max(0.0, seconds) * 1000))
    hours, millis = divmod(millis, 3_600_000)
    minutes, millis = divmod(millis, 60_000)
    secs, millis = divmod(millis, 1000)
    return f"{hours:02d}:{minutes:02d}:{secs:02d}.{millis:03d}"


def escape(text):
    return text.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;").replace("-->", "→")


def wrap(text):
    """Divide el texto en una o dos líneas lo más parejas posible."""
    if len(text) <= MAX_LINE:
        return text
    words = text.split(" ")
    best, best_score = text, None
    for i in range(1, len(words)):
        first, second = " ".join(words[:i]), " ".join(words[i:])
        score = max(len(first), len(second))
        if best_score is None or score < best_score:
            best, best_score = first + "\n" + second, score
    return best


def build_cues(words):
    """Agrupa palabras con tiempos en cues cortos y legibles."""
    cues, current = [], []

    def flush():
        if current:
            text = " ".join(w["text"] for w in current).strip()
            if text:
                cues.append({"start": current[0]["start"], "end": current[-1]["end"], "text": text})
            current.clear()

    for word in words:
        if current:
            text = " ".join(w["text"] for w in current)
            too_long = len(text) + 1 + len(word["text"]) > MAX_CHARS
            too_slow = word["end"] - current[0]["start"] > MAX_SECONDS
            paused = word["start"] - current[-1]["end"] > PAUSE_BREAK
            sentence_end = current[-1]["text"][-1:] in ".?!…" and len(text) >= 20
            if too_long or too_slow or paused or sentence_end:
                flush()
        current.append(word)
    flush()

    # Duración mínima sin pisar el cue siguiente.
    for i, cue in enumerate(cues):
        limit = cues[i + 1]["start"] if i + 1 < len(cues) else cue["end"] + MIN_SECONDS
        if cue["end"] - cue["start"] < MIN_SECONDS:
            cue["end"] = min(cue["start"] + MIN_SECONDS, max(limit, cue["end"]))
    return cues


def to_webvtt(cues):
    blocks = ["WEBVTT", ""]
    for cue in cues:
        blocks.append(f"{timestamp(cue['start'])} --> {timestamp(cue['end'])}")
        blocks.append(wrap(escape(cue["text"])))
        blocks.append("")
    return "\n".join(blocks)


def load_audio(path, ffmpeg):
    """Extrae el audio a 16 kHz mono con FFmpeg (el mismo que usa la plataforma)."""
    import numpy as np

    command = [ffmpeg, "-nostdin", "-hide_banner", "-loglevel", "error", "-i", path,
               "-map", "0:a:0?", "-vn", "-ac", "1", "-ar", "16000", "-f", "f32le", "-"]
    result = subprocess.run(command, stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=False)
    if result.returncode != 0 and b"does not contain any stream" in result.stderr:
        return np.zeros(0, dtype=np.float32)  # el video no tiene pista de audio
    if result.returncode != 0:
        raise RuntimeError("FFmpeg no pudo leer el audio: " + result.stderr.decode(errors="replace")[-300:])
    return np.frombuffer(result.stdout, dtype=np.float32)


def progress(value):
    print(f"PROGRESS {max(0, min(100, int(value)))}", flush=True)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("input")
    parser.add_argument("output")
    parser.add_argument("--model", default="small")
    parser.add_argument("--model-dir", default=None)
    parser.add_argument("--threads", type=int, default=2)
    parser.add_argument("--language", default="es")
    parser.add_argument("--ffmpeg", default="ffmpeg")
    args = parser.parse_args()

    if not os.path.isfile(args.input):
        print("No se encontró el video de entrada.", file=sys.stderr)
        return 1
    try:
        from faster_whisper import WhisperModel
    except ImportError:
        print("faster-whisper no está instalado.", file=sys.stderr)
        return EXIT_MISSING_ENGINE

    progress(0)
    audio = load_audio(args.input, args.ffmpeg)
    if audio.size < 16000:  # menos de un segundo de audio
        print("El video no tiene audio.", file=sys.stderr)
        return EXIT_NO_SPEECH
    model = WhisperModel(args.model, device="cpu", compute_type="int8",
                         cpu_threads=max(1, args.threads), download_root=args.model_dir or None)
    segments, info = model.transcribe(
        audio,
        language=args.language,
        word_timestamps=True,
        vad_filter=True,                     # salta silencios y música: menos texto inventado
        vad_parameters={"min_silence_duration_ms": 700},
        condition_on_previous_text=False,    # evita bucles de frases repetidas
        beam_size=5,
    )
    total = info.duration or 0.0

    words = []
    for segment in segments:
        if total:
            progress(segment.end / total * 99)
        text = segment.text.strip()
        if not text or HALLUCINATIONS.search(text):
            continue
        if segment.no_speech_prob > 0.6 and segment.avg_logprob < -1.0:
            continue
        for word in segment.words or []:
            token = word.word.strip()
            if token:
                words.append({"start": word.start, "end": word.end, "text": token})

    cues = build_cues(words)
    if not cues:
        print("No se detectó voz en el video.", file=sys.stderr)
        return EXIT_NO_SPEECH

    directory = os.path.dirname(os.path.abspath(args.output))
    fd, temporary = tempfile.mkstemp(prefix=".subtitles-", suffix=".vtt", dir=directory)
    with os.fdopen(fd, "w", encoding="utf-8") as handle:
        handle.write(to_webvtt(cues))
    os.replace(temporary, args.output)
    progress(100)
    print(f"DONE {len(cues)}", flush=True)
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except KeyboardInterrupt:
        sys.exit(1)
    except Exception as error:  # el detalle queda en el registro del trabajador
        print(f"Error al transcribir: {error}", file=sys.stderr)
        sys.exit(1)
