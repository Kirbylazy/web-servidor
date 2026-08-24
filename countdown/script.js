// Llegada Sevilla: 13 oct 2026 16:30 CEST (UTC+2) → 14:30 UTC
const TARGET_ARRIVAL   = new Date('2026-10-13T14:30:00Z');

// Salida SLC: 12 oct 2026 15:30 MDT (UTC-6) → 21:30 UTC
const TARGET_DEPARTURE = new Date('2026-10-12T21:30:00Z');

function pad(n) {
    return String(n).padStart(2, '0');
}

function getCountdown(target) {
    const now  = new Date();
    const diff = target - now;

    if (diff <= 0) return null;

    let months = (target.getFullYear() - now.getFullYear()) * 12
               + (target.getMonth() - now.getMonth());

    const pivot = new Date(now);
    pivot.setMonth(pivot.getMonth() + months);
    if (pivot > target) {
        months--;
        pivot.setMonth(pivot.getMonth() - 1);
    }

    const remaining = target - pivot;
    const totalSecs = Math.floor(remaining / 1000);
    const seconds   = totalSecs % 60;
    const minutes   = Math.floor(totalSecs / 60) % 60;
    const hours     = Math.floor(totalSecs / 3600) % 24;
    const days      = Math.floor(totalSecs / 86400);

    return { months, days, hours, minutes, seconds };
}

// Muestra u oculta un elemento por id ('' restaura el estilo CSS)
function show(id, visible) {
    const el = document.getElementById(id);
    if (el) el.style.display = visible ? '' : 'none';
}

function renderCountdown(p, target, heroId, arrivedMsg) {
    const cd   = getCountdown(target);
    const hero = document.getElementById(heroId);

    if (!cd) {
        show(`counter-${p}`, false);
        if (hero) {
            hero.innerHTML = arrivedMsg;
            hero.classList.add('arrived-msg');
        }
        return;
    }

    // Actualizar valores
    document.getElementById(`${p}-months`).textContent  = pad(cd.months);
    document.getElementById(`${p}-days`).textContent    = pad(cd.days);
    document.getElementById(`${p}-hours`).textContent   = pad(cd.hours);
    document.getElementById(`${p}-minutes`).textContent = pad(cd.minutes);
    document.getElementById(`${p}-seconds`).textContent = pad(cd.seconds);

    // Ocultar unidades que todavía son cero liderando (leading zeros)
    // Una unidad se oculta solo si ella Y todas las superiores son cero
    const showMonths  = cd.months > 0;
    const showDays    = cd.months > 0 || cd.days > 0;
    const showHours   = showDays       || cd.hours > 0;
    const showMinutes = showHours      || cd.minutes > 0;
    // SEG siempre visible

    // Fila 1: MESES : DÍAS
    show(`wrap-${p}-months`, showMonths);
    show(`sep-${p}-months`,  showMonths);   // ":" entre meses y días
    show(`wrap-${p}-days`,   showDays);
    show(`row1-${p}`,        showDays);     // ocultar fila entera cuando meses=0 y días=0

    // Fila 2: HORAS : MIN : SEG
    show(`wrap-${p}-hours`,   showHours);
    show(`sep-${p}-hours`,    showHours);   // ":" entre horas y min
    show(`wrap-${p}-minutes`, showMinutes);
    show(`sep-${p}-seconds`,  showMinutes); // ":" entre min y seg
    // .counter-unit de SEG no tiene id wrap porque nunca se oculta
}

function tick() {
    renderCountdown('a', TARGET_ARRIVAL,   'arrival-hero', '<span class="heart">♥</span>¡YA ESTÁ AQUÍ!');
    renderCountdown('f', TARGET_DEPARTURE, 'flight-hero',  '<span class="plane-icon">✈</span>¡EL VUELO HA DESPEGADO!');
}

tick();
setInterval(tick, 1000);
