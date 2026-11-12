#!/usr/bin/env bash
# CP-NF-12 (RNF-12): copia y restauración aislada de la base de datos y de los medios.
# Mide RPO (antigüedad de la copia) y RTO (duración de la restauración) y comprueba
# recuentos de registros y huellas SHA-256. No modifica la base ni los archivos en uso:
# restaura en una base temporal (uebtv_restauracion) y en una carpeta aparte.
#
# Uso (en el servidor, con los nombres de su despliegue):
#   DB_CONTAINER=<contenedor de postgres> DB_USER=<usuario> DB_NAME=<base> \
#   MEDIA_DIR=<carpeta con grabaciones y archivos> bash scripts/piloto/respaldo.sh 1
# Umbral (tabla 42): RPO <= 24 h y RTO <= 4 h; recuentos y huellas coincidentes.
set -euo pipefail
CICLO="${1:-1}"; : "${DB_CONTAINER:?}"; : "${DB_USER:?}"; : "${DB_NAME:?}"; : "${MEDIA_DIR:?}"
OUT="respaldo_ciclo${CICLO}"; mkdir -p "$OUT"; LOG="$OUT/registro.txt"
TABLAS="users eventos transmisiones scheduled_videos chat_messages audit_logs institutional_ads"
log(){ echo "$(date '+%F %T') $*" | tee -a "$LOG"; }
cuenta(){ docker exec "$DB_CONTAINER" psql -U "$DB_USER" -d "$1" -Atc "select '$2', count(*) from $2" 2>/dev/null || echo "$2|NA"; }

log "Ciclo $CICLO: inicio de la copia"
T_COPIA=$(date +%s)
docker exec "$DB_CONTAINER" pg_dump -U "$DB_USER" -Fc "$DB_NAME" > "$OUT/base.dump"
tar -C "$MEDIA_DIR" -cf "$OUT/medios.tar" .
(cd "$MEDIA_DIR" && find . -type f -print0 | sort -z | xargs -0 sha256sum) > "$OUT/huellas_origen.txt"
for t in $TABLAS; do cuenta "$DB_NAME" "$t"; done > "$OUT/recuentos_origen.txt"
log "Copia terminada: $(du -h "$OUT/base.dump" | cut -f1) de base y $(du -h "$OUT/medios.tar" | cut -f1) de medios"

log "Inicio de la restauración aislada"
T0=$(date +%s)
docker exec "$DB_CONTAINER" psql -U "$DB_USER" -d postgres -c "DROP DATABASE IF EXISTS uebtv_restauracion" >/dev/null
docker exec "$DB_CONTAINER" psql -U "$DB_USER" -d postgres -c "CREATE DATABASE uebtv_restauracion" >/dev/null
docker exec -i "$DB_CONTAINER" pg_restore -U "$DB_USER" -d uebtv_restauracion --no-owner < "$OUT/base.dump"
mkdir -p "$OUT/medios_restaurados" && tar -C "$OUT/medios_restaurados" -xf "$OUT/medios.tar"
T1=$(date +%s)
for t in $TABLAS; do cuenta uebtv_restauracion "$t"; done > "$OUT/recuentos_restaurados.txt"
(cd "$OUT/medios_restaurados" && find . -type f -print0 | sort -z | xargs -0 sha256sum) > "$OUT/huellas_restauradas.txt"

RTO=$((T1 - T0)); RPO=$((T0 - T_COPIA))
log "RPO (antigüedad de la copia al restaurar): ${RPO} s · RTO (restauración): ${RTO} s"
if diff -q "$OUT/recuentos_origen.txt" "$OUT/recuentos_restaurados.txt" >/dev/null; then log "Recuentos: coinciden"; else log "Recuentos: NO coinciden"; diff "$OUT/recuentos_origen.txt" "$OUT/recuentos_restaurados.txt" | tee -a "$LOG"; fi
if diff -q "$OUT/huellas_origen.txt" "$OUT/huellas_restauradas.txt" >/dev/null; then log "Huellas SHA-256: coinciden ($(wc -l < "$OUT/huellas_origen.txt") archivos)"; else log "Huellas SHA-256: NO coinciden"; fi
log "Siguiente paso manual: reproducir un MP4 de $OUT/medios_restaurados y anotarlo en el acta."
docker exec "$DB_CONTAINER" psql -U "$DB_USER" -d postgres -c "DROP DATABASE uebtv_restauracion" >/dev/null
log "Base temporal eliminada. Evidencia en $OUT/"
