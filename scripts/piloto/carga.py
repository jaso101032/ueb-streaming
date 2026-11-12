#!/usr/bin/env python3
"""CP-NF-05 (RNF-05): prueba de carga de la interfaz.

20 sesiones concurrentes, 2 minutos de calentamiento y 10 minutos medidos.
Solo usa la biblioteca estándar de Python 3. Excluye la descarga de video.

Uso:
  python3 scripts/piloto/carga.py https://uebtv.ejemplo.edu.ec --evento 12 --corrida 1
Resultado: carga_corrida1.csv (muestras) y un resumen con p95 y errores 5xx.
Umbral (tabla 42): p95 <= 3 s y 5xx < 1 % en cada corrida.
"""
import argparse, csv, random, ssl, statistics, threading, time, urllib.error, urllib.request

ap = argparse.ArgumentParser()
ap.add_argument('base'); ap.add_argument('--evento', required=True, help='id de un evento público')
ap.add_argument('--sesiones', type=int, default=20); ap.add_argument('--calentamiento', type=int, default=120)
ap.add_argument('--medicion', type=int, default=600); ap.add_argument('--corrida', default='1')
a = ap.parse_args()
base = a.base.rstrip('/')
rutas = ['/', '/eventos', f'/eventos/{a.evento}', '/eventos?q=conferencia', '/login', '/senal/estado']
ctx = ssl.create_default_context()
inicio = time.time(); fin_cal = inicio + a.calentamiento; fin = fin_cal + a.medicion
muestras, lock = [], threading.Lock()

def sesion(n):
    while time.time() < fin:
        ruta = random.choice(rutas); t0 = time.time()
        try:
            req = urllib.request.Request(base + ruta, headers={'User-Agent': f'uebtv-carga/{n}'})
            with urllib.request.urlopen(req, timeout=30, context=ctx) as r:
                r.read(); estado = r.status
        except urllib.error.HTTPError as e: estado = e.code
        except Exception: estado = 0
        dt = time.time() - t0
        if t0 >= fin_cal:
            with lock: muestras.append((round(t0 - fin_cal, 2), ruta, estado, round(dt, 4)))
        time.sleep(random.uniform(0.5, 1.5))  # tiempo de lectura de una persona

print(f'Corrida {a.corrida}: {a.sesiones} sesiones, calentamiento {a.calentamiento} s, medición {a.medicion} s contra {base}')
hilos = [threading.Thread(target=sesion, args=(i,), daemon=True) for i in range(a.sesiones)]
for h in hilos: h.start()
for h in hilos: h.join()
with open(f'carga_corrida{a.corrida}.csv', 'w', newline='') as f:
    w = csv.writer(f); w.writerow(['segundo', 'ruta', 'estado', 'tiempo_s']); w.writerows(muestras)
t = sorted(m[3] for m in muestras); n = len(t)
p95 = t[max(0, int(round(0.95 * n)) - 1)] if n else float('nan')
e5 = sum(1 for m in muestras if 500 <= m[2] <= 599); e0 = sum(1 for m in muestras if m[2] == 0)
print(f'Solicitudes medidas: {n}')
print(f'p95: {p95:.3f} s · mediana: {statistics.median(t) if t else float("nan"):.3f} s')
print(f'Errores 5xx: {e5} ({100 * e5 / n if n else 0:.2f} %) · sin respuesta: {e0}')
print('CUMPLE' if n and p95 <= 3 and (e5 / n) < 0.01 else 'NO CUMPLE', '(umbral: p95 <= 3 s y 5xx < 1 %)')
