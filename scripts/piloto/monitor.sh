#!/usr/bin/env bash
# CP-NF-07 (RNF-07): muestreo de recursos del servidor cada 5 s durante CP-NF-05 y CP-NF-06.
# Registra CPU, RAM, disco, red, procesos FFmpeg y reinicios de contenedores; por
# contenedor, CPU y memoria con docker stats. Solo lee; no muestra secretos.
#
# Uso (en el servidor, en la carpeta del despliegue):
#   bash scripts/piloto/monitor.sh E1          # Ctrl+C para terminar
# Variables opcionales: INTERVALO=5  DISCO=/  IFACE=eth0
# Resultado: recursos_E1.csv, contenedores_E1.csv y resumen_E1.txt
set -u
ET="${1:-corrida}"; INTERVALO="${INTERVALO:-5}"; DISCO="${DISCO:-/}"
IFACE="${IFACE:-$(ip route show default 2>/dev/null | awk '{print $5; exit}')}"
R="recursos_${ET}.csv"; C="contenedores_${ET}.csv"; S="resumen_${ET}.txt"
echo "hora,cpu_pct,ram_usada_mb,ram_total_mb,disco_libre_gb,red_rx_kbps,red_tx_kbps,procesos_ffmpeg,reinicios" > "$R"
echo "hora,contenedor,cpu_pct,memoria" > "$C"

cpu(){ awk '/^cpu /{t=0; for(i=2;i<=NF;i++) t+=$i; print t, $5+$6}' /proc/stat; }
net(){ cat "/sys/class/net/$IFACE/statistics/$1_bytes" 2>/dev/null || echo 0; }
libre(){ df -BG --output=avail "$DISCO" | tail -1 | tr -dc '0-9'; }
reinicios(){ docker ps -q | xargs -r docker inspect -f '{{.RestartCount}}' | awk '{s+=$1} END{print s+0}'; }

INICIO=$(date '+%F %T'); LIBRE0=$(libre); REIN0=$(reinicios)
{ echo "Corrida: $ET"; echo "Inicio: $INICIO"; echo "Disco libre inicial: ${LIBRE0} GB ($DISCO)"; echo "Reinicios iniciales: $REIN0"
  echo "Interfaz: $IFACE · intervalo: ${INTERVALO} s"; } > "$S"

terminar(){
  { echo "Fin: $(date '+%F %T')"; echo "Disco libre final: $(libre) GB"; echo "Reinicios finales: $(reinicios)"
    awk -F, 'NR>1{n++; c+=$2; if($2>cm)cm=$2; if($3>rm)rm=$3; if($8>fm)fm=$8} END{printf "Muestras: %d · CPU media: %.1f %% · CPU máxima: %.1f %% · RAM máxima: %d MB · FFmpeg simultáneos (máx.): %d\n", n, (n?c/n:0), cm, rm, fm}' "$R"
  } >> "$S"; cat "$S"; exit 0
}
trap terminar INT TERM

read -r T0 I0 < <(cpu); RX0=$(net rx); TX0=$(net tx)
while true; do
  sleep "$INTERVALO"
  read -r T1 I1 < <(cpu); RX1=$(net rx); TX1=$(net tx); H=$(date '+%T')
  CPU=$(awk -v a="$T0" -v b="$T1" -v i="$I0" -v j="$I1" 'BEGIN{d=b-a; printf "%.1f", (d>0 ? 100*(1-(j-i)/d) : 0)}')
  read -r USADA TOTAL < <(free -m | awk '/^Mem:/{print $3, $2}')
  RX=$(( (RX1 - RX0) * 8 / 1000 / INTERVALO )); TX=$(( (TX1 - TX0) * 8 / 1000 / INTERVALO ))
  FF=$(pgrep -c -x ffmpeg 2>/dev/null); FF=${FF:-0}
  echo "$H,$CPU,$USADA,$TOTAL,$(libre),$RX,$TX,$FF,$(reinicios)" >> "$R"
  docker stats --no-stream --format '{{.Name}},{{.CPUPerc}},{{.MemUsage}}' 2>/dev/null | sed "s/^/$H,/; s/%//" >> "$C"
  T0=$T1; I0=$I1; RX0=$RX1; TX0=$TX1
done
