/**
 * booking.js — Lógica del flujo de reserva
 * Paso 1: Selección de servicios + barbero (booking.html)
 *
 * Conecta con el backend PHP via api.js
 */

'use strict';

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   ESTADO GLOBAL DE LA RESERVA
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
const state = {
  servicios:        [],    // catálogo completo
  barberos:         [],    // catálogo completo
  selServicios:     new Set(),  // IDs seleccionados
  selBarberoId:     null,
  totalPrecio:      0,
  totalDuracion:    0,
  cargando:         false,
};

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   BASE URL DE LA API
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
const API = '../api';

async function apiFetch(endpoint) {
  const res  = await fetch(`${API}/${endpoint}`, {
    headers: { 'Accept': 'application/json' },
  });
  const json = await res.json();
  if (!res.ok) throw new Error(json.error || `HTTP ${res.status}`);
  return json.data ?? json;
}

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   INIT — al cargar la página
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
document.addEventListener('DOMContentLoaded', async () => {
  // Saludo dinámico según la hora
  const hour = new Date().getHours();
  const greetEl = document.getElementById('greeting-time');
  if (greetEl) {
    greetEl.textContent = hour < 12 ? 'Buenos días ðŸ‘‹'
                        : hour < 19 ? 'Buenas tardes ðŸ‘‹'
                        : 'Buenas noches ðŸ‘‹';
  }

  // Cargar datos en paralelo
  await Promise.all([loadServicios(), loadBarberos()]);

  // Si viene ?back=1 en la URL, restaurar estado guardado
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('back') === '1') restoreState();
});

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   CARGAR Y RENDERIZAR SERVICIOS
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
async function loadServicios() {
  const grid = document.getElementById('services-grid');
  if (!grid) return;

  try {
    state.servicios = await apiFetch('servicios');
    renderServicios(state.servicios, grid);
  } catch (err) {
    grid.innerHTML = `
      <div style="padding:24px;text-align:center;color:var(--text-muted,#888)">
        <p style="font-size:2rem;margin-bottom:8px">âš ï¸</p>
        <p style="font-size:.9rem">No se pudieron cargar los servicios.</p>
        <p style="font-size:.78rem;margin-top:4px;color:#666">${err.message}</p>
        <button onclick="loadServicios()" style="margin-top:16px;padding:8px 20px;background:transparent;border:1px solid #555;color:#aaa;border-radius:8px;cursor:pointer">Reintentar</button>
      </div>`;
  }
}

function renderServicios(servicios, container) {
  container.innerHTML = '';

  // En desktop (â‰¥640px) mostramos en grid de 2 col usando CSS grid
  container.style.cssText = `
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 12px;
  `;

  servicios.forEach(svc => {
    const card = document.createElement('div');
    card.className     = 'service-card fade-in-up';
    card.id            = `svc-${svc.id}`;
    card.dataset.id    = svc.id;
    card.dataset.precio = svc.precio;
    card.dataset.dur    = svc.duracion_min;
    card.setAttribute('role', 'checkbox');
    card.setAttribute('aria-checked', 'false');
    card.setAttribute('tabindex', '0');
    card.setAttribute('aria-label', `${svc.nombre} — $${svc.precio} MXN, ${svc.duracion_min} min`);

    card.innerHTML = `
      <div style="display:flex;align-items:flex-start;gap:14px">
        <div style="
          width:48px;height:48px;border-radius:12px;flex-shrink:0;
          background:linear-gradient(135deg,rgba(154,123,50,.2),rgba(201,168,76,.1));
          border:1px solid rgba(201,168,76,.25);
          display:flex;align-items:center;justify-content:center;font-size:1.3rem
        " aria-hidden="true">${svc.icono || '✂️'}</div>

        <div style="flex:1;min-width:0">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
            <p style="font-family:'Playfair Display',serif;font-size:1rem;font-weight:600;color:var(--text-bright,#F5F0E8)">${svc.nombre}</p>
            <p style="
              font-size:.82rem;font-weight:700;color:var(--gold,#C9A84C);
              white-space:nowrap;flex-shrink:0
            ">$${parseFloat(svc.precio).toLocaleString('es-MX')} MXN</p>
          </div>
          <p style="font-size:.78rem;color:var(--text-muted,#888);line-height:1.5;margin-top:3px">${svc.descripcion || ''}</p>
          <div style="display:flex;align-items:center;gap:6px;margin-top:8px">
            <span style="
              display:inline-flex;align-items:center;gap:4px;
              font-size:.66rem;color:var(--text-muted,#888);
              background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);
              border-radius:999px;padding:2px 9px
            ">â± ${svc.duracion_min} min</span>
          </div>
        </div>

        <!-- Checkmark -->
        <div class="check-icon" style="
          width:22px;height:22px;border-radius:50%;flex-shrink:0;
          border:2px solid rgba(255,255,255,.2);
          display:flex;align-items:center;justify-content:center;
          transition:all .2s;
          color:transparent;font-size:.7rem;font-weight:700;
          background:transparent
        " aria-hidden="true">âœ“</div>
      </div>`;

    // Click handler
    const toggle = () => toggleServicio(card, svc);
    card.addEventListener('click', toggle);
    card.addEventListener('keydown', e => { if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); toggle(); } });

    container.appendChild(card);
  });
}

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   CARGAR Y RENDERIZAR BARBEROS
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
async function loadBarberos() {
  const grid = document.getElementById('barbers-grid');
  if (!grid) return;

  try {
    state.barberos = await apiFetch('barberos');
    renderBarberos(state.barberos, grid);
  } catch (err) {
    grid.innerHTML = `
      <div style="padding:24px;text-align:center;color:var(--text-muted,#888);grid-column:1/-1">
        <p>No se pudieron cargar los barberos.</p>
        <p style="font-size:.78rem;margin-top:4px;color:#666">${err.message}</p>
      </div>`;
  }
}

function renderBarberos(barberos, container) {
  container.innerHTML = '';

  // Grid responsivo: 3 col en móvil (tarjetas pequeñas)
  container.style.cssText = 'display:grid;grid-template-columns:repeat(3,1fr);gap:10px;';

  barberos.forEach(b => {
    const card = document.createElement('div');
    card.className  = 'barber-card fade-in-up';
    card.id         = `bar-${b.id}`;
    card.dataset.id = b.id;
    card.setAttribute('role', 'radio');
    card.setAttribute('aria-checked', 'false');
    card.setAttribute('tabindex', '0');
    card.setAttribute('aria-label', `${b.nombre} ${b.apellido} — ${b.especialidad}`);

    const foto        = b.foto ? `../${b.foto}` : 'https://via.placeholder.com/80';
    const dispBadge   = b.disponible_hoy
      ? `<span class="barber-badge"><span class="availability-dot"></span> Disponible</span>`
      : `<span class="barber-badge" style="color:var(--text-muted);border-color:var(--border);background:transparent">Sin turno hoy</span>`;

    card.innerHTML = `
      <div style="overflow:hidden;border-radius:12px 12px 0 0">
        <img src="${foto}" alt="${b.nombre} ${b.apellido}"
          style="width:100%;aspect-ratio:3/4;object-fit:cover;object-position:top;
                 filter:grayscale(20%);transition:filter .4s,transform .4s"
          loading="lazy"
          onerror="this.src='data:image/svg+xml,<svg xmlns=\\'http://www.w3.org/2000/svg\\' width=\\'80\\' height=\\'80\\'><rect fill=\\'%23333\\'/><text x=\\'50%\\' y=\\'50%\\' fill=\\'%23888\\' text-anchor=\\'middle\\' dy=\\'.3em\\' font-size=\\'24\\'>${b.nombre.charAt(0)}</text></svg>'">
      </div>
      <div style="padding:10px 10px 12px">
        <p style="font-family:'Playfair Display',serif;font-size:.9rem;font-weight:600;color:var(--text-bright,#F5F0E8);margin-bottom:2px">${b.nombre}</p>
        <p style="font-size:.65rem;color:var(--text-muted,#888);margin-bottom:6px;line-height:1.3">${b.especialidad || ''}</p>
        <p class="stars" style="font-size:.7rem;color:var(--gold,#C9A84C);margin-bottom:6px">
          ${'â˜…'.repeat(Math.round(b.rating))}${'â˜†'.repeat(5 - Math.round(b.rating))}
          <span style="color:var(--text-muted,#888);font-size:.62rem">${b.rating}</span>
        </p>
        ${dispBadge}
      </div>`;

    const toggle = () => selectBarber(b.id, card);
    card.addEventListener('click', toggle);
    card.addEventListener('keydown', e => { if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); toggle(); } });

    container.appendChild(card);
  });
}

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   TOGGLE SERVICIO
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
function toggleServicio(card, svc) {
  const id = parseInt(card.dataset.id);

  if (state.selServicios.has(id)) {
    state.selServicios.delete(id);
    card.classList.remove('selected');
    card.setAttribute('aria-checked', 'false');
  } else {
    state.selServicios.add(id);
    card.classList.add('selected');
    card.setAttribute('aria-checked', 'true');
  }

  recalcTotals();
  updateCTA();
}

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   SELECT BARBERO
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
function selectBarber(barberoId, clickedCard) {
  // Si es 'any' (sin preferencia)
  if (barberoId === 'any') {
    state.selBarberoId = 'any';
    document.querySelectorAll('.barber-card').forEach(c => {
      c.classList.remove('selected');
      c.setAttribute('aria-checked', 'false');
    });
    // Marcar el botón de "cualquier barbero"
    const btn = document.getElementById('btn-cualquier-barbero');
    if (btn) btn.style.borderColor = 'var(--gold)';
    updateCTA();
    return;
  }

  // Deseleccionar el de "cualquier barbero"
  const btn = document.getElementById('btn-cualquier-barbero');
  if (btn) btn.style.borderColor = '';

  // Selección exclusiva
  document.querySelectorAll('.barber-card').forEach(c => {
    c.classList.remove('selected');
    c.setAttribute('aria-checked', 'false');
  });

  state.selBarberoId = barberoId;
  if (clickedCard) {
    clickedCard.classList.add('selected');
    clickedCard.setAttribute('aria-checked', 'true');
  }

  updateCTA();
}

// Exponer para botón inline
window.selectBarber = (id) => {
  const card = document.getElementById(`bar-${id}`);
  selectBarber(id, card);
};

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   RECALCULAR TOTALES
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
function recalcTotals() {
  state.totalPrecio   = 0;
  state.totalDuracion = 0;

  state.selServicios.forEach(id => {
    const svc = state.servicios.find(s => s.id === id);
    if (svc) {
      state.totalPrecio   += parseFloat(svc.precio);
      state.totalDuracion += parseInt(svc.duracion_min);
    }
  });

  // Actualizar pills del resumen
  const countEl    = document.getElementById('summary-count');
  const durationEl = document.getElementById('summary-duration');
  const priceEl    = document.getElementById('summary-price');

  const n = state.selServicios.size;
  if (countEl)    countEl.textContent    = n === 0 ? '—' : `${n} servicio${n > 1 ? 's' : ''}`;
  if (durationEl) durationEl.textContent = `${state.totalDuracion} min`;
  if (priceEl)    priceEl.textContent    = `$${state.totalPrecio.toLocaleString('es-MX')} MXN`;
}

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   ACTUALIZAR BOTÃ“N CONTINUAR
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
function updateCTA() {
  const btn      = document.getElementById('btn-continuar');
  const ctaText  = btn?.querySelector('.cta-text');
  if (!btn) return;

  const hasServices = state.selServicios.size > 0;
  const hasBarber   = state.selBarberoId !== null;

  btn.disabled = !(hasServices && hasBarber);
  btn.setAttribute('aria-disabled', String(!(hasServices && hasBarber)));

  if (!hasServices && !hasBarber) {
    ctaText && (ctaText.textContent = 'Elige servicio y barbero');
  } else if (!hasServices) {
    ctaText && (ctaText.textContent = 'Elige al menos un servicio');
  } else if (!hasBarber) {
    ctaText && (ctaText.textContent = 'Elige tu barbero');
  } else {
    const dur = state.totalDuracion;
    const precio = state.totalPrecio.toLocaleString('es-MX');
    ctaText && (ctaText.textContent = `Continuar · $${precio} MXN · ${dur} min`);
  }
}

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   CONTINUAR AL PASO 2 (Calendario)
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
function handleContinue() {
  if (state.selServicios.size === 0 || state.selBarberoId === null) return;

  // Guardar selección en sessionStorage para el paso 2
  saveState();

  // Ir al paso 2
  window.location.href = 'calendar.html';
}

function saveState() {
  sessionStorage.setItem('booking_state', JSON.stringify({
    selServicios:  [...state.selServicios],
    selBarberoId:  state.selBarberoId,
    totalPrecio:   state.totalPrecio,
    totalDuracion: state.totalDuracion,
    serviciosData: state.servicios.filter(s => state.selServicios.has(s.id)),
    barberoData:   state.barberos.find(b => b.id === state.selBarberoId) || null,
  }));
}

function restoreState() {
  try {
    const saved = JSON.parse(sessionStorage.getItem('booking_state') || 'null');
    if (!saved) return;

    // Restaurar servicios seleccionados
    saved.selServicios.forEach(id => {
      const card = document.getElementById(`svc-${id}`);
      const svc  = state.servicios.find(s => s.id === id);
      if (card && svc) toggleServicio(card, svc);
    });

    // Restaurar barbero
    if (saved.selBarberoId && saved.selBarberoId !== 'any') {
      const card = document.getElementById(`bar-${saved.selBarberoId}`);
      if (card) selectBarber(saved.selBarberoId, card);
    } else if (saved.selBarberoId === 'any') {
      selectBarber('any', null);
    }
  } catch (_) { /* ignorar errores de parse */ }
}

// Exponer para llamar desde botón HTML
window.handleContinue = handleContinue;
window.loadServicios  = loadServicios;

