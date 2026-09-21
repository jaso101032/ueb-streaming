<div class="uebtv-form-page uebtv-event-editor">
    <div class="uebtv-form-container">
        <a href="{{ route('admin.eventos.index') }}" class="uebtv-form-back">← Volver a eventos</a>
        <section class="uebtv-form-heading">
            <div><p class="uebtv-kicker uebtv-kicker--navy"><span></span> Programación institucional</p>
                <h1>{{ $evento->exists ? 'Editar evento' : 'Crear evento' }}</h1>
                <p>Organiza la información, las fechas y la imagen que verá nuestra comunidad.</p>
            </div>
        </section>
        <div class="event-editor-steps" aria-label="Secciones del formulario"><span><b>01</b> Detalles del evento</span><i aria-hidden="true"></i><span><b>02</b> Imagen y video</span><i aria-hidden="true"></i><span><b>↗</b> Listo para compartir</span></div>
        @if ($errors->any())
            <div class="uebtv-form-errors" role="alert"><strong>Revisa la información ingresada</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @if ($categorias->isEmpty())
            <div class="uebtv-form-errors" role="alert">Todavía no hay categorías disponibles. Solicita al administrador que configure el catálogo antes de crear un evento.</div>
        @endif
        @if ($facultades->isEmpty())
            <p class="mb-6 text-sm text-gray-600" role="status">Aún no hay facultades ni carreras configuradas. Puedes publicar el evento para toda la institución.</p>
        @endif
        <form action="{{ $evento->exists ? route('admin.eventos.update', $evento) : route('admin.eventos.store') }}" method="POST" enctype="multipart/form-data" class="uebtv-event-form">
            @csrf
            @if ($evento->exists) @method('PUT') @endif
            <div class="uebtv-form-main">
                <section class="uebtv-form-section">
                    <div class="uebtv-form-section__title"><span>01</span><div><h2>Información principal</h2><p>Los campos con * son obligatorios.</p></div></div>
                    <div class="uebtv-form-fields">
                        <div class="uebtv-form-field uebtv-form-field--wide"><label for="titulo">Título *</label><input id="titulo" name="titulo" value="{{ old('titulo', $evento->titulo) }}" maxlength="255" placeholder="Dale un nombre a tu próximo evento" required></div>
                        <div class="uebtv-form-field"><label for="tipo_evento">Tipo de evento *</label><select id="tipo_evento" name="tipo_evento" required><option value="">Selecciona un tipo</option>@foreach ($tipos as $tipo)<option value="{{ $tipo }}" @selected(old('tipo_evento', $evento->tipo_evento) === $tipo)>{{ $tipo }}</option>@endforeach</select></div>
                        <div class="uebtv-form-field"><label for="categoria_id">Categoría *</label><select id="categoria_id" name="categoria_id" required><option value="">Selecciona una categoría</option>@foreach ($categorias as $categoria)<option value="{{ $categoria->id }}" @selected(old('categoria_id', $evento->categoria_id) == $categoria->id)>{{ $categoria->nombre }}</option>@endforeach</select></div>
                        <div class="uebtv-form-field"><label for="facultad_id">Facultad</label><select id="facultad_id" name="facultad_id"><option value="">Toda la institución</option>@foreach ($facultades as $facultad)<option value="{{ $facultad->id }}" @selected(old('facultad_id', $evento->facultad_id) == $facultad->id)>{{ $facultad->nombre }}</option>@endforeach</select></div>
                        <div class="uebtv-form-field"><label for="carrera_id">Carrera</label><select id="carrera_id" name="carrera_id" aria-describedby="carrera-ayuda"><option value="">Sin carrera específica</option>@foreach ($carreras as $carrera)<option value="{{ $carrera->id }}" data-facultad="{{ $carrera->facultad_id }}" @selected(old('carrera_id', $evento->carrera_id) == $carrera->id)>{{ $carrera->nombre }}</option>@endforeach</select><small id="carrera-ayuda">Selecciona una facultad para ver sus carreras.</small></div>
                        <div class="uebtv-form-field"><label for="fecha_inicio">Fecha y hora de inicio *</label><input id="fecha_inicio" type="datetime-local" name="fecha_inicio" value="{{ old('fecha_inicio', $evento->fecha_inicio?->format('Y-m-d\TH:i')) }}" required></div>
                        <div class="uebtv-form-field"><label for="fecha_fin">Fecha y hora de cierre</label><input id="fecha_fin" type="datetime-local" name="fecha_fin" value="{{ old('fecha_fin', $evento->fecha_fin?->format('Y-m-d\TH:i')) }}"><small>Debe ser posterior al inicio.</small></div>
                        <div class="uebtv-form-field"><label for="lugar">Lugar</label><input id="lugar" name="lugar" value="{{ old('lugar', $evento->lugar) }}" maxlength="255" placeholder="Auditorio, campus o modalidad"></div>
                        @if ($evento->exists)
                            <div class="uebtv-form-field"><label for="estado">Estado *</label><select id="estado" name="estado" required>@foreach (['programado' => 'Programado', 'en_vivo' => 'En vivo', 'finalizado' => 'Finalizado', 'cancelado' => 'Cancelado'] as $value => $label)<option value="{{ $value }}" @selected(old('estado', $evento->estado) === $value)>{{ $label }}</option>@endforeach</select></div>
                        @endif
                        <div class="uebtv-form-field uebtv-form-field--wide"><label for="descripcion">Descripción</label><textarea id="descripcion" name="descripcion" rows="5" maxlength="20000" placeholder="Cuéntale a la comunidad de qué tratará el evento…">{{ old('descripcion', $evento->descripcion) }}</textarea></div>
                    </div>
                </section>
                <section class="uebtv-form-section">
                    <div class="uebtv-form-section__title"><span>02</span><div><h2>Imagen y video</h2><p>Personaliza la presentación del evento.</p></div></div>
                    <div class="uebtv-form-fields">
                        <div class="uebtv-form-field uebtv-form-field--wide">
                            <label for="miniatura">Miniatura del evento</label>
                            <input id="miniatura" type="file" name="miniatura" accept="image/jpeg,image/png,image/webp" aria-describedby="miniatura-ayuda">
                            <small id="miniatura-ayuda">JPG, PNG o WebP. Máximo 2 MB y 4096 × 4096 píxeles. Recomendamos una imagen horizontal. Si no eliges una nueva, se conserva la actual.</small>
                            <img id="miniatura-preview" @if ($evento->miniatura) src="{{ asset('storage/'.$evento->miniatura) }}" @else hidden @endif alt="Vista previa de la miniatura" class="mt-4 rounded-lg" style="max-width:100%;width:320px;max-height:180px;object-fit:cover">
                        </div>
                        <div class="uebtv-form-field uebtv-form-field--wide"><label for="video_url">Enlace alternativo del video grabado</label><input id="video_url" type="url" name="video_url" value="{{ old('video_url', $evento->video_url) }}" maxlength="255" placeholder="https://servidor.com/videos/evento.mp4"><small>Opcional: enlace directo HTTP o HTTPS a un MP4. Se utiliza cuando no hay una grabación automática disponible.</small></div>
                    </div>
                </section>
            </div>
            <aside class="event-editor-sidebar" style="position: static;" aria-label="Vista previa del evento">
                <div class="event-preview">
                    <div class="event-preview-cover">
                        <span class="event-preview-brand">UEB<span>TV</span></span>
                        <span class="event-preview-play" aria-hidden="true">▷</span>
                        <img id="event-cover-preview" @if ($evento->miniatura) src="{{ asset('storage/'.$evento->miniatura) }}" @else hidden @endif alt="Portada del evento">
                        <span class="event-preview-label">VISTA PREVIA</span>
                    </div>
                    <div class="event-preview-body">
                        <span id="event-preview-category" class="event-preview-category">Tu categoría</span>
                        <h2 id="event-preview-title">El próximo gran momento comienza aquí</h2>
                        <p id="event-preview-date">Fecha y hora por definir</p>
                        <div class="event-preview-faculty"><span aria-hidden="true">⌂</span><span id="event-preview-faculty">Toda la institución</span></div>
                    </div>
                </div>
                <p class="event-preview-caption">Así va tomando forma tu evento.</p>
                <div class="event-editor-note">
                    <span class="event-editor-note-icon" aria-hidden="true">↗</span>
                    <h2>{{ $evento->exists ? 'Tu evento, al día' : 'Prepara tu próxima transmisión' }}</h2>
                    <p>{{ $evento->exists ? 'Los cambios se reflejarán en el catálogo y en la sala pública.' : 'Al guardar tendrás una sala pública y una clave única para conectar OBS.' }}</p>
                    <span class="event-editor-note-footer">Una comunidad. Muchas historias.</span>
                </div>
            </aside>
            <div class="uebtv-form-actions"><a href="{{ route('admin.eventos.index') }}">Cancelar</a><button type="submit" @disabled($categorias->isEmpty())>{{ $evento->exists ? 'Guardar cambios' : 'Guardar y generar clave OBS' }} <span>↗</span></button></div>
        </form>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const faculty = document.getElementById('facultad_id');
        const career = document.getElementById('carrera_id');
        const filterCareers = () => {
            Array.from(career.options).forEach(option => {
                if (!option.value) return;
                const available = option.dataset.facultad === faculty.value;
                option.hidden = !available;
                option.disabled = !available;
                if (!available && option.selected) career.value = '';
            });
        };
        faculty.addEventListener('change', filterCareers);
        filterCareers();
        const start = document.getElementById('fecha_inicio');
        const end = document.getElementById('fecha_fin');
        const updateDates = () => {
            end.min = start.value;
            end.setCustomValidity(end.value && start.value && end.value <= start.value ? 'La fecha de cierre debe ser posterior al inicio.' : '');
        };
        start.addEventListener('input', updateDates);
        end.addEventListener('input', updateDates);
        updateDates();
        const upload = document.getElementById('miniatura');
        const preview = document.getElementById('miniatura-preview');
        const original = preview.getAttribute('src');
        const cover = document.getElementById('event-cover-preview');
        const title = document.getElementById('titulo');
        const category = document.getElementById('categoria_id');
        const updateCard = () => {
            document.getElementById('event-preview-title').textContent = title.value.trim() || 'El próximo gran momento comienza aquí';
            document.getElementById('event-preview-category').textContent = category.value ? category.selectedOptions[0].textContent : 'Tu categoría';
            document.getElementById('event-preview-faculty').textContent = faculty.selectedOptions[0].textContent;
            const date = start.value ? new Date(start.value) : null;
            document.getElementById('event-preview-date').textContent = date && !Number.isNaN(date.getTime())
                ? new Intl.DateTimeFormat('es-EC', { dateStyle: 'medium', timeStyle: 'short' }).format(date)
                : 'Fecha y hora por definir';
        };
        [title, category, faculty, start].forEach(field => field.addEventListener('input', updateCard));
        updateCard();
        let previewUrl;
        upload.addEventListener('change', () => {
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            const file = upload.files[0];
            const valid = file && ['image/jpeg', 'image/png', 'image/webp'].includes(file.type) && file.size <= 2 * 1024 * 1024;
            upload.setCustomValidity(file && !valid ? 'Selecciona una imagen JPG, PNG o WebP de hasta 2 MB.' : '');
            previewUrl = valid ? URL.createObjectURL(file) : null;
            preview.hidden = !(previewUrl || original);
            if (previewUrl || original) preview.src = previewUrl || original;
            else preview.removeAttribute('src');
            cover.hidden = preview.hidden;
            if (previewUrl || original) cover.src = previewUrl || original;
            else cover.removeAttribute('src');
        });
    });
</script>
