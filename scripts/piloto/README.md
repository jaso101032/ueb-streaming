# Mediciones del piloto en el servidor

Scripts para las pruebas no funcionales que solo se pueden medir en el servidor
desplegado (tabla 64 de la tesis). Ninguno modifica datos en uso ni imprime
contraseñas, cookies o valores del `.env`. Los resultados se anotan en el kit de
campo (`Kit_campo_UEBTV`) y se adjuntan como evidencia en los anexos H y M.

El despliegue de la facultad se hizo por SSH con Git y Docker Compose
(`docker-compose.production.yml`); los comandos se ejecutan en esa carpeta.

| Script | Prueba | Dónde se ejecuta |
|---|---|---|
| `inventario.sh` | Operación institucional (3.3) y CP-NF-16 | Servidor |
| `monitor.sh` | CP-NF-07 durante CP-NF-05 y CP-NF-06 | Servidor |
| `carga.py` | CP-NF-05 | Equipo externo con Python 3 |
| `acceso.py` | CP-NF-01 y CP-NF-14 | Equipo externo con Python 3 |
| `cabeceras.py` | CP-NF-02 y CP-NF-03 | Equipo externo con Python 3 |
| `respaldo.sh` | CP-NF-12 | Servidor |
| `php artisan uebtv:expediente` | Repositorio (3.2) y CP-NF-13 | Servidor (contenedor `app`) |

## Inventario sin secretos (3.3, CP-NF-16)

```bash
bash scripts/piloto/inventario.sh
```

Guarda `inventario_AAAAMMDD_HHMM.txt` con el commit desplegado, los servicios
activos, las imágenes, los recursos, los volúmenes, las migraciones y solo los
nombres de las variables del `.env`.

## CP-NF-07 · Recursos cada 5 s

Iniciar antes de cada corrida de CP-NF-05 y de cada emisión de CP-NF-06 y
terminar con Ctrl+C:

```bash
bash scripts/piloto/monitor.sh E1
```

Genera `recursos_E1.csv` (CPU, RAM, disco libre, red, procesos FFmpeg y
reinicios), `contenedores_E1.csv` (CPU y memoria por contenedor) y
`resumen_E1.txt` (disco inicial/final, reinicios y máximos).

## CP-NF-05 · Carga de la interfaz (RNF-05)

Tres corridas desde un equipo con Python 3 y acceso al sitio:

```bash
python3 scripts/piloto/carga.py https://<dominio> --evento <id de un evento público> --corrida 1
```

Cada corrida dura 12 minutos (2 de calentamiento y 10 medidos) con 20 sesiones,
guarda `carga_corridaN.csv` e imprime p95, errores 5xx y si cumple
(p95 ≤ 3 s y 5xx < 1 %).

## CP-NF-01 y CP-NF-14 · Matriz de acceso

Preparar `matriz.csv` con tres recursos por visibilidad (público, interno,
restringido) y los cuatro perfiles, incluidos manifiesto (`/directo/<id>.m3u8`),
segmento, MP4 (`/eventos/<id>/grabacion`) y calidades. Guardar en un archivo
local el valor de la cabecera `Cookie` de cada perfil con sesión.

```bash
python3 scripts/piloto/acceso.py https://<dominio> matriz.csv \
  --cookie usuario=cookie_usuario.txt --cookie operador=cookie_operador.txt \
  --cookie administrador=cookie_admin.txt --etiqueta antes
```

Repetir con `--etiqueta despues_del_retiro` tras retirar la grabación,
revocar la autorización o cambiar la visibilidad, reutilizando las mismas URL
copiadas. Umbral: cero accesos indebidos. Borrar los archivos de cookies al
terminar.

## CP-NF-02 y CP-NF-03 · Cabeceras, cookies y contenido mixto

```bash
python3 scripts/piloto/cabeceras.py https://<dominio> / /eventos /login /eventos/<id>
```

Registra la redirección HTTP → HTTPS, HSTS, CSP, atributos de cookie (sin su
valor) y referencias `http://` del HTML. La revisión de contenido mixto se
completa en la consola de dos navegadores, con la interfaz y la reproducción.

## Repositorio y CP-NF-13 · Expediente con huellas

```bash
docker compose -f docker-compose.production.yml exec app php artisan uebtv:expediente <id> [<id> ...]
```

Muestra metadatos, autorización de difusión, originales y derivados con tamaño
y SHA-256 recalculado, y si coincide con la huella registrada al procesarlos.
Con `--json` la salida se guarda como evidencia.

## CP-NF-12 · Respaldo y restauración (RNF-12)

```bash
DB_CONTAINER=<contenedor de postgres> DB_USER=<usuario> DB_NAME=<base> \
MEDIA_DIR=<carpeta de grabaciones y archivos> bash scripts/piloto/respaldo.sh 1
```

Repetir con 2 y 3. Restaura en una base temporal (`uebtv_restauracion`) que se
elimina al final, compara recuentos de tablas y huellas SHA-256 de los archivos
y registra la duración de la restauración (RTO). El RPO corresponde a la
frecuencia de las copias programadas (umbral: ≤ 24 h). La evidencia queda en
`respaldo_cicloN/registro.txt`. Después, reproducir un MP4 restaurado y anotarlo.
Comprobar antes el espacio libre: la copia ocupa aproximadamente lo mismo que
la base y los medios.
