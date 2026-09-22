import '@fontsource-variable/inter';

import Alpine from 'alpinejs';
import React from 'react';
import { createRoot } from 'react-dom/client';
import UebtvDashboard from './components/UebtvDashboard';

window.Alpine = Alpine;

Alpine.start();

const dashboard = document.getElementById('uebtv-dashboard');

if (dashboard) {
	createRoot(dashboard).render(React.createElement(
		UebtvDashboard,
		JSON.parse(dashboard.dataset.props),
	));
}

import "./chat";

import AdminStudio from './components/AdminStudio';
const studio = document.getElementById('uebtv-admin-studio');
if (studio) createRoot(studio).render(React.createElement(AdminStudio, JSON.parse(studio.dataset.props)));

import "./form-feedback";

// Password visibility is opt-in and never changes the field value.
document.querySelectorAll('[data-password-toggle]').forEach(button => {
    button.addEventListener('click', () => {
        const field = document.getElementById(button.dataset.passwordToggle);
        const reveal = field.type === 'password';
        field.type = reveal ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(reveal));
        button.setAttribute('aria-label', `${reveal ? 'Ocultar' : 'Mostrar'} ${field.id === 'password_confirmation' ? 'confirmación de contraseña' : 'contraseña'}`);
    });
});

const passwordGuide = document.querySelector('.password-guide');
const registrationPassword = document.querySelector('#password[autocomplete="new-password"]');
if (passwordGuide && registrationPassword) {
    const updateGuide = () => { passwordGuide.hidden = document.activeElement === registrationPassword ? false : true; };
    registrationPassword.addEventListener('focus', updateGuide);
    registrationPassword.addEventListener('blur', () => { passwordGuide.hidden = true; });
    window.addEventListener('pageshow', updateGuide);
    updateGuide();
}
