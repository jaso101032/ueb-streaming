const list = document.getElementById('liveChatMessages');

if (list) {
    const endpoint = list.dataset.chatUrl;
    const canModerate = list.dataset.canModerate === 'true';
    const connection = document.getElementById('chatConnection');
    const error = document.getElementById('chatError');
    const form = document.getElementById('liveChatForm');
    const input = document.getElementById('liveChatInput');
    let etag = null;
    let timer;
    let inFlight = false;
    let delay = 5000;
    let revision = 0;

    const showError = (message = '') => {
        error.textContent = message;
        error.hidden = !message;
    };
    const request = async (url, options = {}) => {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 12000);
        try {
            return await fetch(url, { ...options, signal: controller.signal, headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                ...options.headers,
            } });
        } finally { clearTimeout(timeout); }
    };
    const failure = async (response) => {
        if (response.status === 429) return 'Demasiados intentos. Espera un minuto y vuelve a intentarlo.';
        if ([401, 419].includes(response.status)) return 'Tu sesión venció. Recarga la página e inicia sesión de nuevo.';
        if (response.status === 403) return 'No tienes permiso para realizar esta acción.';
        if (response.status === 404) return 'El mensaje o el evento ya no está disponible.';
        if (response.status === 422) {
            const body = await response.json().catch(() => ({}));
            return body.errors?.message?.[0] || 'Revisa el mensaje antes de enviarlo.';
        }
        return 'No se pudo completar la operación. Inténtalo de nuevo.';
    };
    const render = (messages) => {
        const atBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 60;
        const scrollTop = list.scrollTop;
        const fragment = document.createDocumentFragment();
        for (const message of messages) {
            const row = document.createElement('div');
            row.className = 'uebtv-chat-message';
            row.dataset.messageId = message.id;
            const avatar = document.createElement('span');
            avatar.className = 'uebtv-chat-avatar';
            avatar.textContent = Array.from(message.user)[0]?.toUpperCase() || '?';
            const text = document.createElement('p');
            const author = document.createElement('strong');
            author.textContent = message.user;
            text.append(author, document.createTextNode(message.message));
            row.append(avatar, text);
            if (canModerate) {
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'uebtv-chat-remove';
                remove.dataset.removeMessage = message.id;
                remove.textContent = 'Retirar';
                remove.setAttribute('aria-label', `Retirar mensaje de ${message.user}`);
                row.append(remove);
            }
            fragment.append(row);
        }
        if (!messages.length) {
            const empty = document.createElement('div');
            empty.className = 'uebtv-chat-empty';
            empty.textContent = 'Sé el primero en comentar durante la transmisión.';
            fragment.append(empty);
        }
        list.replaceChildren(fragment);
        list.scrollTop = atBottom ? list.scrollHeight : scrollTop;
    };
    const schedule = (wait = delay) => {
        clearTimeout(timer);
        if (!document.hidden) timer = setTimeout(refresh, wait);
    };
    const refresh = async () => {
        if (inFlight || document.hidden) return;
        inFlight = true;
        const startedAt = revision;
        try {
            const response = await request(endpoint, { headers: etag ? { 'If-None-Match': etag } : {} });
            if (response.status !== 304 && !response.ok) throw new Error(await failure(response));
            if (response.status !== 304) {
                const messages = await response.json();
                if (startedAt === revision) {
                    render(messages);
                    etag = response.headers.get('ETag');
                }
            }
            connection.textContent = '';
            delay = 5000;
        } catch {
            connection.textContent = 'No se pudo actualizar el chat. Reintentando automáticamente…';
            delay = Math.min(delay * 2, 30000);
        } finally {
            inFlight = false;
            schedule(startedAt !== revision ? 0 : delay);
        }
    };
    const changed = () => {
        revision++;
        etag = null;
        delay = 5000;
        clearTimeout(timer);
        refresh();
    };
    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const message = input.value.trim();
        if (!message) { showError('Escribe un mensaje antes de enviarlo.'); return; }
        const button = form.querySelector('button');
        if (button.disabled) return;
        button.disabled = true;
        showError();
        const draft = input.value;
        try {
            const response = await request(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ message }) });
            if (!response.ok) { showError(await failure(response)); return; }
            if (input.value === draft) input.value = '';
            changed();
        } catch { showError('No se pudo confirmar el envío. Tu texto se conserva; revisa el chat antes de reintentarlo.'); }
        finally { button.disabled = false; }
    });
    list.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-remove-message]');
        if (!canModerate || !button || button.disabled) return;
        if (!window.confirm('¿Retirar este mensaje del chat para todos los espectadores?')) return;
        button.disabled = true;
        showError();
        try {
            const response = await request(`${endpoint}/${button.dataset.removeMessage}`, { method: 'DELETE' });
            if (!response.ok) { showError(await failure(response)); return; }
            button.closest('[data-message-id]').remove();
            changed();
        } catch { showError('No se pudo confirmar la moderación. Comprueba tu conexión e inténtalo de nuevo.'); }
        finally { button.disabled = false; }
    });
    document.addEventListener('visibilitychange', () => {
        clearTimeout(timer);
        if (!document.hidden) refresh();
    });
    list.scrollTop = list.scrollHeight;
    schedule();
}
