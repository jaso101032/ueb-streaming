"""Install only the control additions on the inspected local nginx configuration."""
import pathlib
import subprocess
import sys
import time

path = pathlib.Path('/etc/nginx/nginx.conf')
original = path.read_text()
snippet = pathlib.Path(sys.argv[1]).read_text()
if '# UEBTV stream authorization' in original:
    sys.exit('El control ya está configurado; no se modificó Nginx.')
if original.count('application live {') != 1 or original.count('http {') != 1:
    sys.exit('La configuración cambió. Revisa Nginx antes de aplicar estas adiciones.')
if 'on_publish ' in original or 'on_update ' in original or '8091' in original:
    sys.exit('Hay controles existentes. Requieren una revisión antes de integrarlos.')
updated = original.replace('application live {', 'application live {\n' + snippet, 1)
updated = updated.replace('http {', '''http {
    # UEBTV control: accessible only from this server.
    server {
        listen 127.0.0.1:8091;
        location /control {
            allow 127.0.0.1;
            deny all;
            rtmp_control drop;
        }
    }
''', 1)
backup = path.with_name('nginx.conf.uebtv-backup-' + str(time.time_ns()))
backup.write_text(original)
backup.chmod(0o600)
try:
    path.write_text(updated)
    path.chmod(0o600)  # Contains the private callback token; master Nginx runs as root.
    subprocess.run(['nginx', '-t'], check=True)
    subprocess.run(['systemctl', 'reload', 'nginx'], check=True)
except Exception:
    path.write_text(original)
    print('No se activó el control; se restauró la configuración anterior.', file=sys.stderr)
    raise
print('Control de cierre activado. Conserva Laravel disponible en 127.0.0.1:8000.')
