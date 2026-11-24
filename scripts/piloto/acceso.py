#!/usr/bin/env python3
"""CP-NF-01 y CP-NF-14: matriz de acceso a recursos por perfil.

Lee una matriz CSV (recurso, ruta, perfil, esperado) y solicita cada ruta con la
sesión del perfil indicado, con y sin cabecera Range. No sigue redirecciones:
un 3xx hacia el inicio de sesión cuenta como acceso denegado. Las cookies se
leen de archivos locales y nunca se imprimen; los identificadores de 32
caracteres de las rutas se enmascaran en el resultado.

Perfiles: visitante (sin cookie), usuario, operador y administrador. Para cada
perfil con sesión, crear un archivo con el valor de la cabecera Cookie copiado
del navegador (Herramientas de desarrollo → Red → solicitud → Cookie).

Uso:
  python3 scripts/piloto/acceso.py https://<dominio> matriz.csv \\
      --cookie usuario=cookie_usuario.txt --cookie operador=cookie_operador.txt \\
      --cookie administrador=cookie_admin.txt --etiqueta antes_del_retiro
Matriz de ejemplo (esperado = permitido o denegado):
  recurso,ruta,perfil,esperado
  evento interno,/eventos/15,visitante,denegado
  MP4 restringido,/eventos/16/grabacion,usuario,denegado
Resultado: acceso_<etiqueta>.csv y un resumen con accesos indebidos (umbral: 0).
"""
import argparse, csv, re, ssl, urllib.error, urllib.request

ap = argparse.ArgumentParser()
ap.add_argument('base'); ap.add_argument('matriz')
ap.add_argument('--cookie', action='append', default=[], help='perfil=archivo con el valor de la cabecera Cookie')
ap.add_argument('--etiqueta', default='corrida')
a = ap.parse_args()
base = a.base.rstrip('/')
cookies = {}
for item in a.cookie:
    perfil, archivo = item.split('=', 1)
    cookies[perfil.strip()] = open(archivo, encoding='utf-8').read().strip()


class SinRedireccion(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


abrir = urllib.request.build_opener(SinRedireccion, urllib.request.HTTPSHandler(context=ssl.create_default_context())).open
enmascarar = lambda ruta: re.sub(r'[a-z0-9]{32}', '<id>', ruta)


def pedir(ruta, perfil, rango):
    cab = {'User-Agent': 'uebtv-acceso'}
    if perfil in cookies: cab['Cookie'] = cookies[perfil]
    if rango: cab['Range'] = 'bytes=0-1023'
    try:
        with abrir(urllib.request.Request(base + ruta, headers=cab), timeout=30) as r:
            cuerpo = r.read(2048)
            return r.status, r.headers.get('Content-Type', ''), len(cuerpo), ''
    except urllib.error.HTTPError as e:
        return e.code, e.headers.get('Content-Type', ''), 0, e.headers.get('Location', '') or ''
    except Exception as e:
        return 0, '', 0, type(e).__name__


filas, indebidos, bloqueados_de_mas = [], 0, 0
with open(a.matriz, newline='', encoding='utf-8') as f:
    for fila in csv.DictReader(f):
        perfil = fila['perfil'].strip()
        if perfil != 'visitante' and perfil not in cookies:
            print(f'Falta la cookie del perfil {perfil}; se omite {fila["recurso"]}.'); continue
        for rango in (False, True):
            estado, tipo, bytes_, extra = pedir(fila['ruta'].strip(), perfil, rango)
            permitido = estado in (200, 206)
            esperado = fila['esperado'].strip().lower()
            ok = permitido == (esperado == 'permitido')
            if permitido and esperado != 'permitido': indebidos += 1
            if not permitido and esperado == 'permitido': bloqueados_de_mas += 1
            filas.append([fila['recurso'], enmascarar(fila['ruta']), perfil, 'sí' if rango else 'no', estado, tipo.split(';')[0],
                          bytes_, 'login' if 'login' in extra else enmascarar(extra), esperado, 'permitido' if permitido else 'denegado', 'sí' if ok else 'NO'])

salida = f'acceso_{a.etiqueta}.csv'
with open(salida, 'w', newline='', encoding='utf-8') as f:
    w = csv.writer(f)
    w.writerow(['recurso', 'ruta', 'perfil', 'range', 'estado_http', 'tipo', 'bytes_leidos', 'redireccion', 'esperado', 'obtenido', 'coincide'])
    w.writerows(filas)
print(f'Solicitudes: {len(filas)} · accesos indebidos: {indebidos} · bloqueos no esperados: {bloqueados_de_mas}')
print('CUMPLE' if indebidos == 0 else 'NO CUMPLE', '(umbral: cero accesos indebidos) ·', salida)
