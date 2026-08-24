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

function renderCountdown(ids, target, heroId, arrivedMsg) {
    const cd   = getCountdown(target);
    const hero = document.getElementById(heroId);

    if (!cd) {
        document.getElementById(ids[0]).closest('.counter').style.display = 'none';
        if (hero) {
            hero.innerHTML = arrivedMsg;
            hero.classList.add('arrived-msg');
        }
        return;
    }

    document.getElementById(ids[0]).textContent = pad(cd.months);
    document.getElementById(ids[1]).textContent = pad(cd.days);
    document.getElementById(ids[2]).textContent = pad(cd.hours);
    document.getElementById(ids[3]).textContent = pad(cd.minutes);
    document.getElementById(ids[4]).textContent = pad(cd.seconds);
}

function tick() {
    renderCountdown(
        ['a-months', 'a-days', 'a-hours', 'a-minutes', 'a-seconds'],
        TARGET_ARRIVAL,
        'arrival-hero',
        '<span class="heart">♥</span>¡YA ESTÁ AQUÍ!'
    );
    renderCountdown(
        ['f-months', 'f-days', 'f-hours', 'f-minutes', 'f-seconds'],
        TARGET_DEPARTURE,
        'flight-hero',
        '<span class="plane-icon">✈</span>¡EL VUELO HA DESPEGADO!'
    );
}

tick();
setInterval(tick, 1000);
