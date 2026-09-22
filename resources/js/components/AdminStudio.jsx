import React, { useState } from 'react';

function Icon({ name, ...props }) {
    const paths = {
        calendar: <><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M16 3v4M8 3v4M3 11h18M8 15h2M14 15h2"/></>,
        signal: <><circle cx="12" cy="12" r="2"/><path d="M7 7a7 7 0 0 0 0 10M17 7a7 7 0 0 1 0 10M4 4a11 11 0 0 0 0 16M20 4a11 11 0 0 1 0 16"/></>,
        users: <><circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6M18 15a5 5 0 0 1 3 5"/></>,
        play: <><rect x="3" y="4" width="18" height="16" rx="3"/><path d="m10 8 6 4-6 4Z"/></>,
        arrow: <path d="M5 12h14m-5-5 5 5-5 5"/>,
        plus: <path d="M12 5v14M5 12h14"/>,
    };
    return <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" {...props}>{paths[name] || paths.calendar}</svg>;
}
const statuses = { en_vivo: 'En vivo', programado: 'Programado', finalizado: 'Finalizado', cancelado: 'Cancelado' };

export default function AdminStudio({ name, metrics, events, createUrl, eventsUrl, streamingUrl, catalogUrl }) {
    const [filter, setFilter] = useState('todos');
    const visible = events.filter(event => filter === 'todos' || event.status === filter);
    return <div className="studio-page">
        <div className="studio-heading"><div><p className="studio-eyebrow">TU ESPACIO DE GESTIÓN</p><h1>Hola, {name.split(' ')[0]}<span className="studio-heading-dot">.</span></h1><p>Todo listo para conectar con tu comunidad.</p></div><a className="studio-button" href={createUrl}><Icon name="plus"/>Crear evento</a></div>
        <section className="studio-feature" aria-labelledby="studio-feature-title">
            <div className="studio-feature-copy"><span className="studio-pill">UEBTV · CENTRO DE PRODUCCIÓN</span><h2 id="studio-feature-title">Grandes ideas.<br/><span>Una comunidad conectada.</span></h2><p>Convierte cada encuentro en una experiencia compartida. Organiza tu programación y prepara tu próxima transmisión.</p><a href={streamingUrl}>Ir al control de streaming <Icon name="arrow"/></a></div>
            <div className="studio-broadcast-art" aria-hidden="true"><div className="studio-orbit studio-orbit-one"/><div className="studio-orbit studio-orbit-two"/><div className="studio-art-screen"><div className="studio-art-bars">{[35,58,42,80,100,68,90,48,72,38,56,28].map((h,i)=><i key={i} style={{height:`${h}%`}}/>)}</div><span>LA VOZ DE NUESTRA UNIVERSIDAD</span><strong>UEB<span>TV</span></strong><div className="studio-art-line"><i/> UN ESPACIO PARA CONECTAR</div></div><div className="studio-art-tag"><Icon name="play"/> Historias que nos unen</div></div>
        </section>
        <section className="studio-metrics" aria-label="Resumen de la plataforma">{metrics.map((metric,i)=><article key={metric.label} className={i===1?'studio-metric studio-metric-live':'studio-metric'}><div><span>{metric.label}</span><Icon name={metric.icon}/></div><strong>{new Intl.NumberFormat('es').format(metric.value)}</strong><p>{metric.detail}</p></article>)}</section>
        <div className="studio-bottom-grid"><section className="studio-panel"><div className="studio-panel-heading"><div><p className="studio-eyebrow">PROGRAMACIÓN</p><h2>Actividad reciente</h2></div><a href={eventsUrl} className="studio-text-link">Ver todos <Icon name="arrow"/></a></div>
            <div className="studio-filter-group" role="group" aria-label="Filtrar los cinco eventos recientes">{[['todos','Todos'],['programado','Programados'],['en_vivo','En vivo']].map(([value,label])=><button key={value} type="button" aria-pressed={filter===value} onClick={()=>setFilter(value)}>{label}</button>)}</div>
            <div className="studio-event-list" aria-live="polite">{visible.length ? visible.map(event=><a className="studio-event-row" key={event.id} href={event.url}><div className="studio-event-thumb">{event.image?<img src={event.image} alt="" loading="lazy"/>:<Icon name="play"/>}</div><div className="studio-event-info"><small>{event.category}</small><h3>{event.title}</h3><p>{event.date || 'Sin fecha definida'}</p></div><span className={`studio-status studio-status-${event.status}`}>{statuses[event.status] || event.status}</span><Icon name="arrow"/></a>):<div className="studio-empty"><span className="studio-empty-icon"><Icon name="calendar" width="32" height="32"/></span><h3>{events.length?'No hay eventos en este estado':'Tu próxima historia empieza aquí'}</h3><p>{events.length?'Selecciona otro filtro para consultar la actividad reciente.':'Crea tu primer evento y empieza a dar forma a la programación de UEBTV.'}</p><a className="studio-button studio-button-soft" href={events.length?eventsUrl:createUrl}>{events.length?'Explorar eventos':'Crear mi primer evento'}<Icon name="arrow"/></a></div>}</div>
        </section><aside className="studio-rail"><section className="studio-panel studio-shortcuts"><p className="studio-eyebrow">A UN CLIC</p><h2>Tu mesa de trabajo</h2>{[{url:createUrl,icon:'calendar',title:'Programar un evento',copy:'Dale lugar a tu próximo encuentro'},{url:streamingUrl,icon:'signal',title:'Control de streaming',copy:'Prepara la señal y las credenciales'},{url:catalogUrl,icon:'play',title:'Explorar el catálogo',copy:'Mira lo que ve tu comunidad'}].map(item=><a key={item.url} href={item.url}><span className="studio-shortcut-icon"><Icon name={item.icon}/></span><span><strong>{item.title}</strong><small>{item.copy}</small></span><Icon name="arrow" width="17"/></a>)}</section><div className="studio-note"><span className="studio-eyebrow">ANTES DE TRANSMITIR</span><h3>Los detalles hacen la diferencia.</h3><p>Añade una miniatura, revisa la fecha y prueba tu señal antes de compartir el evento.</p><a href={createUrl}>Preparar un evento <span aria-hidden="true">↗</span></a></div></aside></div>
    </div>;
}
