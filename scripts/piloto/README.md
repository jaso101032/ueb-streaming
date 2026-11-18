# Mediciones del piloto en el servidor

Scripts para las pruebas no funcionales que solo se pueden medir en el servidor
desplegado (tabla 42 de la tesis). No modifican datos en uso. Los resultados se
anotan en los formatos del piloto (F4, F5 y F9).

## CP-NF-05 · Carga de la interfaz (RNF-05)

Desde un equipo con Python 3 y acceso al sitio, tres corridas:

```bash
python3 scripts/piloto/carga.py https://<dominio> --evento <id de un evento público> --corrida 1
python3 scripts/piloto/carga.py https://<dominio> --evento <id> --corrida 2
python3 scripts/piloto/carga.py https://<dominio> --evento <id> --corrida 3
```

Cada corrida dura 12 minutos (2 de calentamiento y 10 medidos) con 20 sesiones,
guarda `carga_corridaN.csv` e imprime p95, errores 5xx y si cumple
(p95 ≤ 3 s y 5xx < 1 %). Mientras corre, anotar CPU y memoria del servidor (F5).

## CP-NF-12 · Respaldo y restauración (RNF-12)

En el servidor, con los nombres del despliegue (por ejemplo, en Dokploy:
`docker ps` muestra el contenedor de PostgreSQL):

```bash
DB_CONTAINER=<contenedor de postgres> DB_USER=<usuario> DB_NAME=<base> \
MEDIA_DIR=<carpeta de grabaciones y archivos> bash scripts/piloto/respaldo.sh 1
```

Repetir con 2 y 3. Restaura en una base temporal (`uebtv_restauracion`) que se
elimina al final, compara recuentos de tablas y huellas SHA-256 de los archivos
y registra la duración de la restauración (RTO). El RPO corresponde a la
frecuencia de las copias programadas (umbral: ≤ 24 h). La evidencia queda en
`respaldo_cicloN/registro.txt`. Después, reproducir un MP4 restaurado y anotarlo.
