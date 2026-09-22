// Enhance native form validation without changing Laravel submission or CSRF handling.
function initFormFeedback() {
    document.querySelectorAll('[data-feedback-form]').forEach(form => {
        const status = form.querySelector('[data-submit-feedback]');
        const controls = [...form.querySelectorAll('input:not([type=hidden]), select, textarea')];
        const showError = field => {
            const message = document.getElementById(`${field.id}-error`);
            if (!message) return;
            const text = field.validity.valueMissing ? 'Completa este campo para continuar.' : field.validationMessage;
            message.textContent = field.validity.valid ? '' : text;
            if (field.validity.valid) field.removeAttribute('aria-invalid');
            else field.setAttribute('aria-invalid', 'true');
        };
        controls.forEach(field => {
            field.addEventListener('invalid', () => {
                showError(field);
                status.textContent = 'Revisa los campos indicados antes de guardar.';
            });
            field.addEventListener('blur', () => { if (field.value || field.hasAttribute('aria-invalid')) showError(field); });
            ['input', 'change'].forEach(event => field.addEventListener(event, () => {
                // Run after the existing date, file and dependent-select validation.
                queueMicrotask(() => {
                    if (field.hasAttribute('aria-invalid')) showError(field);
                    if (field.id === 'fecha_inicio') {
                        const end = form.querySelector('#fecha_fin');
                        if (end.value || end.hasAttribute('aria-invalid')) showError(end);
                    }
                    if (field.id === 'miniatura') showError(field);
                });
                status.textContent = 'Tienes cambios pendientes. Guarda para publicarlos.';
            }));
        });
        form.addEventListener('submit', event => {
            if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
            form.dataset.submitting = 'true';
            form.setAttribute('aria-busy', 'true');
            status.textContent = 'Guardando tu evento. Espera un momento…';
            form.querySelectorAll('button[type=submit]').forEach(button => {
                button.dataset.originalLabel = button.innerHTML;
                button.disabled = true;
                button.textContent = 'Guardando…';
            });
        });
        window.addEventListener('pageshow', () => {
            if (form.dataset.submitting !== 'true') return;
            delete form.dataset.submitting;
            form.removeAttribute('aria-busy');
            status.textContent = '';
            form.querySelectorAll('[data-original-label]').forEach(button => {
                button.innerHTML = button.dataset.originalLabel;
                button.disabled = false;
            });
        });
    });
    document.querySelector('[data-error-summary]')?.focus();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initFormFeedback);
else initFormFeedback();
