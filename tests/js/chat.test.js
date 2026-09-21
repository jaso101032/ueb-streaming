import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/chat.js', import.meta.url), 'utf8');
function setup() {
    const element = (extra = {}) => ({
        listeners: {}, dataset: {}, value: '', textContent: '', hidden: false, disabled: false,
        scrollHeight: 100, scrollTop: 0, clientHeight: 100,
        addEventListener(type, callback) { this.listeners[type] = callback; },
        ...extra,
    });
    const list = element({ dataset: { chatUrl: '/eventos/1/chat', canModerate: 'false' } });
    const input = element({ value: 'Mi mensaje' });
    const button = element();
    const form = element({ querySelector: () => button });
    const connection = element();
    const error = element();
    const elements = { liveChatMessages: list, liveChatInput: input, liveChatForm: form, chatConnection: connection, chatError: error };
    const document = element({ getElementById: id => elements[id], querySelector: () => ({ content: 'csrf' }) });
    const timers = new Map();
    let serial = 0;
    const calls = [];
    let response = { ok: true, status: 304 };
    const context = {
        document, window: {}, AbortController, URL,
        setTimeout: (callback, delay) => { timers.set(++serial, { callback, delay }); return serial; },
        clearTimeout: id => timers.delete(id),
        fetch: async (...args) => { calls.push(args); if (response instanceof Error) throw response; return response; },
    };
    vm.runInNewContext(source, context);
    return { ...elements, button, document, timers, calls, setResponse: value => response = value };
}

test('polling waits five seconds, pauses hidden tabs and resumes without overlap', async () => {
    const env = setup();
    assert.equal([...env.timers.values()][0].delay, 5000);
    assert.equal(env.calls.length, 0);
    env.document.hidden = true;
    env.document.listeners.visibilitychange();
    assert.equal(env.timers.size, 0);
    env.document.hidden = false;
    env.document.listeners.visibilitychange();
    env.document.listeners.visibilitychange();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(env.calls.length, 1);
    assert.equal([...env.timers.values()][0].delay, 5000);
});

test('failed sends retain the draft and explain rate limits, sessions and validation', async () => {
    for (const [status, expected] of [[429, 'Espera un minuto'], [419, 'sesión venció'], [422, 'Mensaje demasiado largo']]) {
        const env = setup();
        env.setResponse({ ok: false, status, json: async () => ({ errors: { message: ['Mensaje demasiado largo'] } }) });
        await env.liveChatForm.listeners.submit({ preventDefault() {} });
        assert.equal(env.liveChatInput.value, 'Mi mensaje');
        assert.match(env.chatError.textContent, new RegExp(expected));
        assert.equal(env.chatError.hidden, false);
        assert.equal(env.button.disabled, false);
    }
});

test('network failures retain text and release the send button', async () => {
    const env = setup();
    env.setResponse(new Error('offline'));
    await env.liveChatForm.listeners.submit({ preventDefault() {} });
    assert.equal(env.liveChatInput.value, 'Mi mensaje');
    assert.match(env.chatError.textContent, /No se pudo confirmar/);
    assert.equal(env.button.disabled, false);
});

test('polling backs off after network errors', async () => {
    const env = setup();
    env.setResponse(new Error('offline'));
    await [...env.timers.values()][0].callback();
    assert.equal([...env.timers.values()][0].delay, 10000);
    assert.match(env.chatConnection.textContent, /Reintentando/);
});
