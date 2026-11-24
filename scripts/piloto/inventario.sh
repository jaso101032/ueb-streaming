#!/usr/bin/env bash
# Apartado 3.3 y CP-NF-16: inventario de servicios y configuración SIN secretos.
# Registra versión desplegada, servicios, imágenes, recursos, volúmenes,
# migraciones y los NOMBRES (no los valores) de las variables del .env.
#
# Uso (en el servidor, en la carpeta del despliegue):
#   bash scripts/piloto/inventario.sh
# Resultado: inventario_AAAAMMDD_HHMM.txt
set -u
F="${COMPOSE_FILE:-docker-compose.production.yml}"
OUT="inventario_$(date +%Y%m%d_%H%M).txt"
dc(){ docker compose -f "$F" "$@"; }
s(){ echo; echo "== $*"; }
{
  echo "Inventario UEBTV · $(date '+%F %T %Z') · $(hostname)"
  s "Versión del código"; git log -1 --format='commit %H%nfecha %ci%nmensaje %s' 2>/dev/null
  git status --porcelain 2>/dev/null | grep -v '^??' | sed 's/^/modificado sin confirmar: /'
  s "Sistema"; (. /etc/os-release && echo "$PRETTY_NAME"); uname -r
  echo "CPU: $(nproc) núcleos · $(grep -m1 'model name' /proc/cpuinfo | cut -d: -f2 | xargs)"
  free -h | sed -n 1,2p; df -h / | tail -1
  s "Docker"; docker --version; docker compose version
  s "Servicios"; dc ps --format 'table {{.Service}}\t{{.State}}\t{{.Status}}'
  s "Imágenes"; dc images 2>/dev/null
  s "Uso de recursos por contenedor"; docker stats --no-stream --format 'table {{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}'
  s "Volúmenes"; docker system df -v 2>/dev/null | sed -n '/VOLUME NAME/,/^$/p'
  s "Migraciones"; dc exec -T app php artisan migrate:status 2>/dev/null | grep -cE 'Ran' | sed 's/^/aplicadas: /'
  dc exec -T app php artisan migrate:status 2>/dev/null | grep -E 'Pending' | sed 's/^/pendiente: /'
  s "Configuración de streaming relevante"
  dc exec -T app php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); foreach (["streaming.keep_sources","streaming.auto_subtitles.enabled","app.env","app.debug","session.secure"] as $k) { $v=config($k); echo $k," = ",var_export($v,true),PHP_EOL; }' 2>/dev/null
  s "Variables definidas en .env (solo nombres)"; [ -f .env ] && grep -E '^[A-Z_]+=' .env | cut -d= -f1 | sort | tr '\n' ' '; echo
} > "$OUT" 2>&1
echo "Inventario guardado en $OUT (revisar antes de compartir; no contiene valores del .env)."
