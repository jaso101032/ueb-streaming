#!/usr/bin/env python3
"""CP-NF-02 y CP-NF-03: cabeceras de seguridad, atributos de cookie y contenido mixto.

Para cada ruta: estado, redirección HTTP → HTTPS, HSTS, Content-Security-Policy,
atributos de las cookies (Secure, HttpOnly, SameSite) sin mostrar su valor y
referencias http:// en etiquetas que cargan recursos (script, link, img,
video, source, iframe, audio) del HTML servido. Complementa, no reemplaza, la
revisión de la consola en dos navegadores con la interfaz y la reproducción.

Uso:
  python3 scripts/piloto/cabeceras.py https://<dominio> / /eventos /login /eventos/<id>
Resultado: cabeceras.txt
"""
import re, ssl, sys, urllib.error, urllib.request
from urllib.parse import urlsplit

if len(sys.argv) < 3: sys.exit(__doc__)
base = sys.argv[1].rstrip('/'); rutas = sys.argv[2:]
ctx = ssl.create_default_context()


class SinRedireccion(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs): return None


abrir = urllib.request.build_opener(SinRedireccion, urllib.request.HTTPSHandler(context=ctx)).open
lineas = []
def log(t=''): print(t); lineas.append(t)


def pedir(url):
    try:
        r = abrir(urllib.request.Request(url, headers={'User-Agent': 'uebtv-cabeceras'}), timeout=30)
        return r.status, r.headers, r.read(2_000_000).decode('utf-8', 'replace')
    except urllib.error.HTTPError as e:
        return e.code, e.headers, ''


host = urlsplit(base).netloc
estado, cab, _ = pedir(f'http://{host}/')
log(f'HTTP → HTTPS: http://{host}/ responde {estado} hacia {cab.get("Location", "—")}')
mixto_total = 0
for ruta in rutas:
    estado, cab, html = pedir(base + ruta)
    log(f'\n{ruta} · estado {estado}')
    log(f'  Strict-Transport-Security: {cab.get("Strict-Transport-Security", "AUSENTE")}')
    csp = cab.get('Content-Security-Policy', '')
    log(f'  Content-Security-Policy: {"presente" if csp else "AUSENTE"}' + (' (upgrade-insecure-requests)' if 'upgrade-insecure-requests' in csp else ''))
    for c in cab.get_all('Set-Cookie') or []:
        nombre = c.split('=', 1)[0]
        attrs = [p.strip() for p in c.split(';')[1:]]
        flags = [p for p in attrs if p.lower() in ('secure', 'httponly') or p.lower().startswith('samesite')]
        log(f'  Cookie {nombre}: valor oculto · {", ".join(flags) or "sin atributos de seguridad"}')
    mixto = sorted(set(re.findall(r'<(?:script|link|img|video|source|iframe|audio)\b[^>]*?\b(?:src|href|poster)=["\'](http://[^"\']+)', html, re.I)))
    mixto_total += len(mixto)
    log(f'  Recursos http:// en el HTML: {len(mixto)}' + ''.join(f'\n    {u}' for u in mixto))
log(f'\nTotal de recursos http:// encontrados: {mixto_total}')
open('cabeceras.txt', 'w', encoding='utf-8').write('\n'.join(lineas) + '\n')
