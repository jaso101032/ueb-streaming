"""Opt-in end-to-end check on isolated SQLite, Nginx ports and synthetic video.
Run: python3 tests/integration/recording_smoke.py
Requires nginx-rtmp, PHP SQLite and FFmpeg. Does not modify the running server.
"""
import json
import os
import pathlib
import secrets
import signal
import subprocess
import tempfile
import time
import urllib.request

project = pathlib.Path(__file__).resolve().parents[2]
children = []
with tempfile.TemporaryDirectory(prefix='uebtv-recording-smoke-') as temporary:
    root = pathlib.Path(temporary)
    (root/'raw').mkdir()
    (root/'vod').mkdir()
    (root/'database.sqlite').touch()
    token = secrets.token_hex(32)
    env = os.environ | {
        'APP_ENV': 'testing', 'APP_DEBUG': 'false', 'DB_CONNECTION': 'sqlite',
        'DB_DATABASE': str(root/'database.sqlite'), 'DB_URL': '', 'CACHE_STORE': 'array',
        'SESSION_DRIVER': 'array', 'STREAMING_CALLBACK_TOKEN': token,
        'STREAMING_RECORDINGS_ROOT': temporary, 'STREAMING_RECONNECT_GRACE': '15',
        'STREAMING_CONTROL_URL': 'http://127.0.0.1:18092/control',
    }

    def run(args, **kwargs):
        return subprocess.run(args, cwd=project, env=env, check=True, capture_output=True, text=True, **kwargs)

    def php(code):
        boot = "require 'vendor/autoload.php'; $app=require 'bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); "
        return run(['php', '-r', boot+code]).stdout.strip()

    def start(args, log, cwd=project):
        process = subprocess.Popen(args, cwd=cwd, env=env, stdout=log, stderr=log, start_new_session=True)
        children.append(process)
        return process

    def publish(log, duration):
        return start(['/usr/bin/ffmpeg', '-nostdin', '-v', 'error', '-re', '-f', 'lavfi', '-i', 'color=c=blue:s=160x90:r=10',
            '-f', 'lavfi', '-i', 'sine=frequency=440:sample_rate=44100', '-t', str(duration),
            '-c:v', 'libx264', '-threads', '1', '-preset', 'ultrafast', '-g', '10', '-c:a', 'aac',
            '-f', 'flv', 'rtmp://127.0.0.1:19350/live/ueb_smoke'], log)

    try:
        run(['php', 'artisan', 'migrate', '--force'])
        php("$u=App\\Models\\User::factory()->create(['role'=>'admin']); $c=App\\Models\\Categoria::create(['nombre'=>'Smoke','slug'=>'smoke']); $e=App\\Models\\Evento::create(['titulo'=>'Smoke','slug'=>'smoke','tipo_evento'=>'Conferencias','categoria_id'=>$c->id,'organizador_id'=>$u->id,'fecha_inicio'=>now(),'estado'=>'programado']); $e->transmision()->create(['stream_key'=>'ueb_smoke']);")
        (root/'nginx.conf').write_text(f'''
load_module /usr/lib/nginx/modules/ngx_rtmp_module.so;
daemon off;
worker_processes 1;
pid {root}/nginx.pid;
error_log {root}/nginx-error.log notice;
events {{ worker_connections 128; }}
rtmp {{ server {{ listen 127.0.0.1:19350; application live {{
    live on;
    access_log off;
    record all;
    record_path {root}/raw;
    record_append on;
    record_lock on;
    on_publish http://127.0.0.1:18091/streaming/hooks/authorize?token={token};
    on_update http://127.0.0.1:18091/streaming/hooks/authorize?token={token};
    notify_update_timeout 1s;
    notify_update_strict on;
    on_publish_done http://127.0.0.1:18091/streaming/hooks/disconnected?token={token};
}} }} }}
http {{ access_log off; client_body_temp_path {root}/body;
    proxy_temp_path {root}/proxy; fastcgi_temp_path {root}/fastcgi;
    uwsgi_temp_path {root}/uwsgi; scgi_temp_path {root}/scgi;
    server {{ listen 127.0.0.1:18092; location /control {{ rtmp_control drop; }} }}
}}
''')
        with open(root/'processes.log', 'w+') as log:
            start(['php', '-S', '127.0.0.1:18091', str(project/'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], log, project/'public')
            start(['/usr/sbin/nginx', '-p', temporary, '-c', str(root/'nginx.conf')], log)
            for _ in range(30):
                try:
                    urllib.request.urlopen('http://127.0.0.1:18091/up', timeout=1).close()
                    break
                except OSError:
                    time.sleep(.2)
            first = publish(log, 3)
            assert first.wait(timeout=15) == 0, 'First publisher failed'
            time.sleep(1)
            size = (root/'raw/ueb_smoke.flv').stat().st_size
            second = publish(log, 30)
            time.sleep(3)
            assert (root/'raw/ueb_smoke.flv').stat().st_size > size, 'Reconnect did not append'
            php("app(App\\Http\\Controllers\\TransmisionController::class)->finalizar(App\\Models\\Evento::firstOrFail(), app(App\\Services\\StreamControl::class));")
            second.wait(timeout=10)
            rejected = publish(log, 1)
            assert rejected.wait(timeout=10) != 0, 'Finalized stream accepted a reconnect'
            php("App\\Models\\Transmision::query()->update(['last_signal_at'=>now()->subSeconds(16)]);")
            run(['php', 'artisan', 'streaming:prepare-recordings'], timeout=60)
            state = json.loads(php("echo json_encode(App\\Models\\Transmision::first()->only(['recording_status','recording_file','recording_error']));"))
            assert state['recording_status'] == 'ready', state
            probe = json.loads(run(['/usr/bin/ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'json', str(root/'vod'/state['recording_file'])]).stdout)
            assert float(probe['format']['duration']) > 4, 'Missing recording segment'
            request = urllib.request.Request('http://127.0.0.1:18091/eventos/1/grabacion', headers={'Range': 'bytes=0-31'})
            with urllib.request.urlopen(request, timeout=5) as response:
                assert response.status == 206 and len(response.read()) == 32, 'Seeking failed'
            print('PASS: RTMP capture, append after reconnect, forced stop, rejected reconnect, automatic MP4 and public byte ranges.')
    except Exception:
        # Tokens can occur in nginx logs; sanitize before displaying diagnostics.
        for name in ['processes.log', 'nginx-error.log']:
            path = root/name
            if path.exists():
                print(path.read_text()[-6000:].replace(token, '[redacted]'))
        raise
    finally:
        for child in reversed(children):
            if child.poll() is None:
                os.killpg(child.pid, signal.SIGTERM)
        for child in children:
            try:
                child.wait(timeout=5)
            except subprocess.TimeoutExpired:
                os.killpg(child.pid, signal.SIGKILL)
                child.wait()
