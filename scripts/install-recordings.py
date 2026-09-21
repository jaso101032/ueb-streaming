"""Enable recording and a persistent single-host processor on this Debian server."""
import grp
import json
import os
import pathlib
import pwd
import re
import shutil
import subprocess
import sys
import time

if os.geteuid() != 0:
    sys.exit('Ejecuta este instalador como root.')
config = json.loads(pathlib.Path(sys.argv[1]).read_text())
project = pathlib.Path(config['project'])
root = pathlib.Path(config['root'])
for value in [str(project), str(root), config['php'], config['ffmpeg'], config['ffprobe']]:
    if not re.fullmatch(r'/[A-Za-z0-9_./-]+', value) or '..' in pathlib.Path(value).parts:
        sys.exit('La ruta de instalación requiere revisión.')
if root == pathlib.Path('/') or len(root.parts) < 4:
    sys.exit('Usa un directorio de grabaciones dedicado, por ejemplo /var/lib/uebtv/recordings.')
if not re.fullmatch(r'[a-f0-9]{64}', config['token'] or ''):
    sys.exit('El token de streaming no está configurado.')
for executable in [config['php'], config['ffmpeg'], config['ffprobe'], '/usr/bin/python3']:
    if not os.access(executable, os.X_OK):
        sys.exit('Falta una dependencia: ' + executable)
user = pwd.getpwuid(project.stat().st_uid)
if user.pw_uid == 0:
    sys.exit('El proyecto debe pertenecer al usuario de la aplicación, no a root.')
www_gid = grp.getgrnam('www-data').gr_gid
nginx = pathlib.Path('/etc/nginx/nginx.conf')
original = nginx.read_text()
if '# UEBTV stream authorization' not in original:
    sys.exit('Primero activa el control de cierre con activate-stream-control.sh.')
if not re.search(r'^user\s+www-data\s*;', original, re.M):
    sys.exit('Este instalador requiere revisar el usuario de Nginx.')
if original.count('application live {') != 1:
    sys.exit('No se pudo identificar la aplicación RTMP live.')
start = original.index('application live {') + len('application live {')
level, end = 1, start
while level and end < len(original):
    if original[end] == '{': level += 1
    if original[end] == '}': level -= 1
    end += 1
body = original[start:end-1]
marker_start = '            # BEGIN UEBTV AUTOMATIC RECORDING'
marker_end = '            # END UEBTV AUTOMATIC RECORDING'
body = re.sub(re.escape(marker_start) + r'.*?' + re.escape(marker_end) + r'\n?', '', body, flags=re.S)
if re.search(r'\b(?:record|record_path|record_append|record_unique|record_lock|on_publish_done)\s', body):
    sys.exit('Ya existen ajustes de grabación ajenos al instalador; requieren revisión.')
# Verify the existing private token without displaying it.
for hook in ['on_publish', 'on_update']:
    expected = hook + ' http://127.0.0.1:8000/streaming/hooks/authorize?token=' + config['token'] + ';'
    if expected not in body:
        sys.exit('El token o la dirección de Laravel difiere de la configuración de cierre. Revisa antes de activar.')
snippet = f'''\n{marker_start}
            record all;
            record_path {root}/raw;
            record_suffix .flv;
            record_unique off;
            record_append on;
            record_lock on;
            on_publish_done http://127.0.0.1:8000/streaming/hooks/disconnected?token={config['token']};
{marker_end}
'''
updated = original[:start] + body + snippet + original[end-1:]

for directory, mode in [(root.parent, 0o755), (root, 0o2750), (root/'raw', 0o2770), (root/'vod', 0o2750)]:
    if directory.is_symlink(): sys.exit('No se permiten directorios de grabación enlazados.')
    directory.mkdir(parents=True, exist_ok=True)
    if directory != root.parent:
        os.chown(directory, user.pw_uid, www_gid)
        directory.chmod(mode)
service = pathlib.Path('/etc/systemd/system/uebtv-recordings.service')
timer = pathlib.Path('/etc/systemd/system/uebtv-recordings.timer')
service_content = f'''# Managed by UEBTV automatic recording installer
[Unit]
Description=UEBTV automatic recording preparation
After=network.target

[Service]
Type=oneshot
User={user.pw_name}
Group={user.pw_name}
SupplementaryGroups=www-data
WorkingDirectory={project}
ExecStart={config['php']} {project}/artisan streaming:prepare-recordings
TimeoutStartSec=infinity
KillMode=control-group
UMask=0027
Nice=10
NoNewPrivileges=true
'''
timer_content = '''# Managed by UEBTV automatic recording installer
[Unit]
Description=Check UEBTV recordings every 15 seconds

[Timer]
OnBootSec=30s
OnUnitInactiveSec=15s
Unit=uebtv-recordings.service

[Install]
WantedBy=timers.target
'''
for path in [service, timer]:
    if path.exists() and 'Managed by UEBTV automatic recording installer' not in path.read_text():
        sys.exit('Ya existe un servicio no administrado por UEBTV: ' + str(path))
backup = nginx.with_name('nginx.conf.recordings-backup-' + str(time.time_ns()))
shutil.copyfile(nginx, backup)
backup.chmod(0o600)
try:
    nginx.write_text(updated)
    nginx.chmod(0o600)
    subprocess.run(['nginx', '-t'], check=True)
    service.write_text(service_content)
    timer.write_text(timer_content)
    subprocess.run(['systemctl', 'daemon-reload'], check=True)
    subprocess.run(['systemctl', 'enable', '--now', 'uebtv-recordings.timer'], check=True)
    subprocess.run(['systemctl', 'reload', 'nginx'], check=True)
except Exception:
    nginx.write_text(original)
    print('No se completó la activación. Se restauró nginx.conf; revisa el servicio uebtv-recordings.', file=sys.stderr)
    raise
print('Grabación automática activada para las próximas conexiones de OBS.')
print('Originales: ' + str(root/'raw'))
print('Videos: ' + str(root/'vod'))
print('Laravel debe permanecer disponible en 127.0.0.1:8000.')
