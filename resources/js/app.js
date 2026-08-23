
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
