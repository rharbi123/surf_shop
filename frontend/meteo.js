/* =====================================================
   WAVECRAFT — meteo.js
   GSAP · Canvas Waves · Chart.js · API Météo
   ===================================================== */

const API_URL = 'http://localhost:8000/api';
let spotActuel = 'Lacanau';
let chartInstance = null;
let leafletMap = null;
let markeurs = {};

gsap.registerPlugin(ScrollTrigger);

/* =====================================================
   INIT
   ===================================================== */
document.addEventListener('DOMContentLoaded', () => {
    initCanvasWaves();
    initParticles();
    animerHero();
    initCarte();
    initSpotSelector();
    chargerMeteo(spotActuel);
});

/* =====================================================
   CARTE LEAFLET INTERACTIVE
   ===================================================== */
const SPOTS_MAP = [
    { nom: 'Lacanau',     ville: 'Lacanau',          lat: 44.9305, lon: -1.2172 },
    { nom: 'Hossegor',    ville: 'Soorts-Hossegor',   lat: 43.6667, lon: -1.5833 },
    { nom: 'Capbreton',   ville: 'Capbreton',         lat: 43.6558, lon: -1.4365 },
    { nom: 'Biscarrosse', ville: 'Biscarrosse',       lat: 44.4000, lon: -1.2000 },
    { nom: 'Mimizan',     ville: 'Mimizan',           lat: 44.2167, lon: -1.3167 },
    { nom: 'Seignosse',   ville: 'Seignosse',         lat: 43.6456, lon: -1.5201 },
    { nom: 'Anglet',      ville: 'Anglet',            lat: 43.4833, lon: -1.5500 },
];

function initCarte() {
    // Initialiser la carte Leaflet
    leafletMap = L.map('surf-map', {
        center: [44.1, -1.4],
        zoom: 9,
        zoomControl: true,
        scrollWheelZoom: false,
        attributionControl: true,
    });

    // Tuiles sombres CartoDB Dark Matter
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OSM</a> · © <a href="https://carto.com/">CARTO</a>',
        subdomains: 'abcd',
        maxZoom: 19,
    }).addTo(leafletMap);

    // Créer les marqueurs pour chaque spot
    SPOTS_MAP.forEach(spot => {
        const icon = creerIconeMarqueur(spot.nom, spot.nom === spotActuel);

        const marqueur = L.marker([spot.lat, spot.lon], { icon })
            .addTo(leafletMap)
            .bindPopup(creerContenuPopup(spot), {
                maxWidth: 220,
                className: 'popup-dark',
                closeButton: true,
                offset: [0, -8],
            });

        marqueur.on('click', () => {
            selectionnerSpot(spot.nom);
            // Scroll vers les données météo
            setTimeout(() => {
                document.querySelector('.meteo-main').scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 400);
        });

        markeurs[spot.nom] = { marqueur, spot };
    });

    // Animation d'entrée de la carte
    gsap.from('#surf-map', { opacity: 0, scale: 0.98, duration: 0.8, ease: 'power3.out', delay: 0.3 });
    gsap.from('.map-header', { opacity: 0, y: 20, duration: 0.7, ease: 'power3.out' });
}

function creerIconeMarqueur(nom, actif = false) {
    return L.divIcon({
        className: '',
        html: `
            <div class="map-marker-wrap${actif ? ' active' : ''}" id="marker-${nom}">
                <div class="map-pulse"></div>
                <div class="map-pulse map-pulse-2"></div>
                <div class="map-dot"></div>
                <div class="map-marker-label">${nom}</div>
            </div>`,
        iconSize:   [80, 52],
        iconAnchor: [40, 16],
        popupAnchor: [0, -20],
    });
}

function creerContenuPopup(spot) {
    return `
        <div class="popup-content">
            <div class="popup-spot-name">${spot.nom}</div>
            <div class="popup-ville">${spot.ville} · Côte Atlantique</div>
            <div class="popup-score-row" id="popup-score-${spot.nom}">
                <span class="popup-score-emoji">🌊</span>
                <span>Chargement…</span>
            </div>
            <button class="popup-btn" onclick="selectionnerSpotEtScroll('${spot.nom}')">
                Voir les conditions →
            </button>
        </div>`;
}

function selectionnerSpotEtScroll(nom) {
    selectionnerSpot(nom);
    setTimeout(() => {
        document.querySelector('.meteo-main').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 300);
}

function mettreAJourMarqueur(nom, actif) {
    if (!markeurs[nom]) return;
    const { marqueur, spot } = markeurs[nom];
    marqueur.setIcon(creerIconeMarqueur(nom, actif));
}

function mettreAJourInfoCarte(spotNom, score) {
    document.getElementById('map-sel-spot').textContent  = spotNom;
    document.getElementById('map-sel-emoji').textContent = score.emoji;
    document.getElementById('map-sel-label').textContent = score.label;

    // Mettre à jour le popup du spot actuel
    const popupEl = document.getElementById(`popup-score-${spotNom}`);
    if (popupEl) {
        popupEl.innerHTML = `
            <span class="popup-score-emoji">${score.emoji}</span>
            <span>${score.label} · ${score.detail.split('—')[0].trim()}</span>`;
    }

    // Animation de l'info box
    gsap.from('.map-selected-info', { scale: 0.97, duration: 0.4, ease: 'back.out(2)' });
}

/* =====================================================
   CANVAS WAVES — fond animé du hero
   ===================================================== */
function initCanvasWaves() {
    const canvas = document.getElementById('wave-canvas');
    const ctx = canvas.getContext('2d');
    let time = 0;
    let raf;

    function resize() {
        canvas.width  = window.innerWidth;
        canvas.height = canvas.parentElement.clientHeight;
    }
    resize();
    window.addEventListener('resize', resize);

    const waves = [
        { amp: 35, freq: 0.008, speed: 0.012, color: 'rgba(0,196,204,0.18)', lineWidth: 2.5, yOffset: 0.6 },
        { amp: 22, freq: 0.012, speed: 0.018, color: 'rgba(0,102,204,0.22)', lineWidth: 2,   yOffset: 0.65 },
        { amp: 50, freq: 0.005, speed: 0.007, color: 'rgba(0,196,204,0.08)', lineWidth: 3,   yOffset: 0.55 },
    ];

    function draw() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        waves.forEach(w => {
            ctx.beginPath();
            for (let x = 0; x <= canvas.width; x += 2) {
                const y = canvas.height * w.yOffset
                    + Math.sin(x * w.freq + time * w.speed * 60) * w.amp
                    + Math.cos(x * w.freq * 0.5 + time * w.speed * 40) * (w.amp * 0.4);
                x === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
            }
            ctx.strokeStyle = w.color;
            ctx.lineWidth   = w.lineWidth;
            ctx.stroke();
        });

        // Remplissage sous la 1ère vague
        ctx.beginPath();
        for (let x = 0; x <= canvas.width; x += 2) {
            const y = canvas.height * waves[0].yOffset
                + Math.sin(x * waves[0].freq + time * waves[0].speed * 60) * waves[0].amp;
            x === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
        }
        ctx.lineTo(canvas.width, canvas.height);
        ctx.lineTo(0, canvas.height);
        ctx.closePath();
        ctx.fillStyle = 'rgba(0,15,35,0.5)';
        ctx.fill();

        time += 0.016;
        raf = requestAnimationFrame(draw);
    }
    draw();
}

/* =====================================================
   PARTICULES FLOTTANTES
   ===================================================== */
function initParticles() {
    const container = document.getElementById('particles');
    const N = 18;

    for (let i = 0; i < N; i++) {
        const p = document.createElement('div');
        p.className = 'particle';
        const size = Math.random() * 4 + 2;
        p.style.cssText = `
            width: ${size}px; height: ${size}px;
            left: ${Math.random() * 100}%;
            top:  ${Math.random() * 100}%;
        `;
        container.appendChild(p);

        gsap.to(p, {
            opacity: Math.random() * 0.6 + 0.1,
            y: -(Math.random() * 120 + 40),
            x: (Math.random() - 0.5) * 60,
            duration: Math.random() * 6 + 4,
            delay: Math.random() * 4,
            repeat: -1,
            yoyo: true,
            ease: 'sine.inOut'
        });
    }
}

/* =====================================================
   ANIMATIONS HERO (GSAP)
   ===================================================== */
function animerHero() {
    const tl = gsap.timeline({ defaults: { ease: 'power4.out' } });

    tl.to('#hero-label', { opacity: 1, y: 0, duration: 0.8 })
      .to('#hero-title',  { opacity: 1, y: 0, duration: 1 }, '-=0.4')
      .to('#hero-sub',    { opacity: 1, duration: 0.7 }, '-=0.5')
      .to('#spot-selector', { opacity: 1, y: 0, duration: 0.7 }, '-=0.3')
      .to('.spot-pill', {
          opacity: 1,
          stagger: 0.07,
          duration: 0.4,
          ease: 'back.out(1.7)'
      }, '-=0.4')
      .to('.hero-scroll-hint', { opacity: 0.5, duration: 0.5 }, '-=0.2');
}

/* =====================================================
   SÉLECTEUR DE SPOT (pills + carte en sync)
   ===================================================== */
function initSpotSelector() {
    document.querySelectorAll('.spot-pill').forEach(pill => {
        pill.addEventListener('click', () => {
            if (pill.dataset.spot === spotActuel) return;
            selectionnerSpot(pill.dataset.spot);
        });
    });
}

function selectionnerSpot(nom) {
    if (nom === spotActuel) return;

    // Désactiver l'ancien marqueur
    mettreAJourMarqueur(spotActuel, false);

    gsap.to('#current-section', { opacity: 0, y: 10, duration: 0.25, onComplete: () => {
        // Sync pills
        document.querySelectorAll('.spot-pill').forEach(p => {
            p.classList.toggle('active', p.dataset.spot === nom);
        });

        // Activer le nouveau marqueur
        spotActuel = nom;
        mettreAJourMarqueur(nom, true);

        // Fly vers le spot sur la carte
        if (leafletMap && markeurs[nom]) {
            const { spot } = markeurs[nom];
            leafletMap.flyTo([spot.lat, spot.lon], 11, { duration: 1.2, easeLinearity: 0.25 });
        }

        chargerMeteo(nom);
    }});
}

/* =====================================================
   CHARGEMENT DONNÉES MÉTÉO
   ===================================================== */
async function chargerMeteo(spot) {
    afficherLoader(true);

    try {
        const [meteoRes, previsionsRes, creneauxRes] = await Promise.all([
            fetch(`${API_URL}/spots/${encodeURIComponent(spot)}/meteo`),
            fetch(`${API_URL}/spots/${encodeURIComponent(spot)}/previsions`),
            fetch(`${API_URL}/spots/${encodeURIComponent(spot)}/creneaux`)
        ]);

        const meteo      = await meteoRes.json();
        const previsions = await previsionsRes.json();
        const creneaux   = await creneauxRes.json();

        if (!meteo.success) throw new Error(meteo.erreur);

        afficherLoader(false);
        afficherMeteoActuelle(meteo.actuelle, meteo.spot);
        afficherGraphique(meteo.heures);
        afficherPrevisions(previsions.data || []);
        afficherCreneaux(creneaux.data || []);
        animerSections();

    } catch (err) {
        console.error('Erreur météo:', err);
        afficherLoader(false);
        afficherErreur();
    }
}

function afficherLoader(show) {
    const loader = document.getElementById('meteo-loader');
    loader.classList.toggle('hidden', !show);
}

/* =====================================================
   MÉTÉO ACTUELLE
   ===================================================== */
function afficherMeteoActuelle(data, spot) {
    if (!data) return;

    const heureFormatee = new Date(data.date_heure).toLocaleTimeString('fr-FR', {
        hour: '2-digit', minute: '2-digit'
    });

    document.getElementById('update-time').textContent = `Mise à jour : ${heureFormatee}`;
    document.getElementById('main-spot').textContent  = spot.nom + ' · ' + spot.ville;
    document.getElementById('main-heure').textContent = heureFormatee;
    document.getElementById('main-conditions').textContent = data.conditions;

    // Icône OpenWeather
    const iconEl = document.getElementById('main-icon');
    iconEl.src = `https://openweathermap.org/img/wn/${data.icone}@2x.png`;
    iconEl.alt = data.conditions;

    // Température animée
    animerNombre('main-temp', 0, Math.round(parseFloat(data.temperature)), 1.2, '');

    // Ressenti + humidité
    animerNombre(null, 0, Math.round(parseFloat(data.ressenti)), 1, '°', 'feels-temp');
    animerNombre(null, 0, parseInt(data.humidite), 1, '%', 'feels-humid');

    // Barre humidité
    setTimeout(() => {
        document.getElementById('humidity-fill').style.width = `${data.humidite}%`;
    }, 200);

    // Vent
    const ms  = parseFloat(data.vent_vitesse);
    const kmh = Math.round(ms * 3.6);
    animerNombre('wind-kmh', 0, kmh, 1.2, '');
    document.getElementById('wind-ms').textContent = `${ms.toFixed(1)} m/s`;
    document.getElementById('wind-beaufort').textContent = beaufort(ms);
    document.getElementById('wind-dir-label').textContent = directionCardinale(data.vent_direction);

    // Boussole
    animerBoussole(data.vent_direction);

    // Surf Score
    const score = calculerScoreSurf(ms, data.conditions);
    afficherScore(score);
}

/* =====================================================
   SCORE SURF
   ===================================================== */
function calculerScoreSurf(ventMs, conditions) {
    const kmh = ventMs * 3.6;
    const condLower = (conditions || '').toLowerCase();

    let note = 5; // base
    let label, emoji, detail, classe;

    // Ajustement par vent
    if (kmh < 5)        { note = 2; }
    else if (kmh < 15)  { note = 5; }
    else if (kmh < 28)  { note = 7; }
    else if (kmh < 45)  { note = 9; }
    else                { note = 3; } // trop fort = dangereux

    // Bonus conditions claires
    if (condLower.includes('dégagé') || condLower.includes('peu nuageux')) note = Math.min(10, note + 1);

    if (note <= 2) {
        label = 'Flat'; emoji = '😴';
        detail = `Vent de ${kmh.toFixed(0)} km/h — Conditions trop calmes, pas de vagues.`;
        classe = 'badge-flat';
    } else if (note <= 4) {
        label = 'Surfable'; emoji = '🏄';
        detail = `Vent de ${kmh.toFixed(0)} km/h — Quelques vagues, bonne session pour débutants.`;
        classe = 'badge-surfable';
    } else if (note <= 6) {
        label = 'Bon'; emoji = '🤙';
        detail = `Vent de ${kmh.toFixed(0)} km/h — Bonne session garantie. Conditions idéales niveaux intermédiaires.`;
        classe = 'badge-bon';
    } else if (note <= 8) {
        label = 'Excellent'; emoji = '🔥';
        detail = `Vent de ${kmh.toFixed(0)} km/h — Conditions de compétition ! Réservé aux confirmés.`;
        classe = 'badge-excellent';
    } else if (note === 9 || note === 10) {
        label = 'Légendaire'; emoji = '🌊';
        detail = `Vent de ${kmh.toFixed(0)} km/h — Conditions exceptionnelles. Expert uniquement.`;
        classe = 'badge-excellent';
    } else {
        label = 'Dangereux'; emoji = '⚠️';
        detail = `Vent de ${kmh.toFixed(0)} km/h — Trop puissant. Ne pas surfer.`;
        classe = 'badge-danger';
    }

    return { note, label, emoji, detail, classe };
}

function afficherScore(score) {
    animerNombre('score-number', 0, score.note, 1.5, '');
    document.getElementById('score-label').textContent  = score.label;
    document.getElementById('score-emoji').textContent  = score.emoji;
    document.getElementById('score-detail').textContent = score.detail;

    // Sync avec la carte
    mettreAJourInfoCarte(spotActuel, score);

    // Couleur dynamique
    const colors = {
        'badge-flat':      '#78909c',
        'badge-surfable':  '#0066CC',
        'badge-bon':       '#00C4CC',
        'badge-excellent': '#00e676',
        'badge-danger':    '#ff5252'
    };
    const color = colors[score.classe] || '#00C4CC';

    // Gauge SVG
    const arcTotal = 251;
    const filled   = Math.round((score.note / 10) * arcTotal);
    const gaugeFill = document.getElementById('gauge-fill');
    gsap.to(gaugeFill, {
        attr: { 'stroke-dasharray': `${filled} ${arcTotal}` },
        duration: 1.5,
        ease: 'power3.out'
    });
    gsap.to(gaugeFill, { attr: { stroke: color }, duration: 0.8 });

    // Glow couleur
    const glow = document.getElementById('score-glow');
    if (glow) {
        glow.style.background = `radial-gradient(circle, ${color}33 0%, transparent 70%)`;
    }
}

/* =====================================================
   GRAPHIQUE CHART.JS
   ===================================================== */
function afficherGraphique(heures) {
    if (!heures || heures.length === 0) return;

    const labels = heures.map(h => {
        const d = new Date(h.date_heure);
        return d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    });

    const temps = heures.map(h => parseFloat(h.temperature).toFixed(1));
    const vents = heures.map(h => (parseFloat(h.vent_vitesse) * 3.6).toFixed(1));

    const ctx = document.getElementById('meteo-chart').getContext('2d');

    if (chartInstance) chartInstance.destroy();

    chartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Température (°C)',
                    data: temps,
                    borderColor: '#ff9966',
                    backgroundColor: 'rgba(255,153,102,0.08)',
                    pointBackgroundColor: '#ff9966',
                    pointRadius: 5,
                    pointHoverRadius: 8,
                    borderWidth: 2.5,
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y'
                },
                {
                    label: 'Vent (km/h)',
                    data: vents,
                    borderColor: '#00C4CC',
                    backgroundColor: 'rgba(0,196,204,0.06)',
                    pointBackgroundColor: '#00C4CC',
                    pointRadius: 5,
                    pointHoverRadius: 8,
                    borderWidth: 2.5,
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            interaction: { mode: 'index', intersect: false },
            animation: { duration: 1200, easing: 'easeInOutQuart' },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(6,13,26,0.9)',
                    borderColor: 'rgba(255,255,255,0.1)',
                    borderWidth: 1,
                    titleColor: '#fff',
                    bodyColor: 'rgba(255,255,255,0.7)',
                    padding: 12,
                    cornerRadius: 10
                }
            },
            scales: {
                x: {
                    grid:   { color: 'rgba(255,255,255,0.04)' },
                    ticks:  { color: 'rgba(255,255,255,0.4)', font: { size: 11 } }
                },
                y: {
                    type: 'linear',
                    position: 'left',
                    grid:   { color: 'rgba(255,255,255,0.04)' },
                    ticks:  { color: 'rgba(255,153,102,0.8)', font: { size: 11 }, callback: v => v + '°C' }
                },
                y1: {
                    type: 'linear',
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    ticks: { color: 'rgba(0,196,204,0.8)', font: { size: 11 }, callback: v => v + ' km/h' }
                }
            }
        }
    });
}

/* =====================================================
   PRÉVISIONS 5 JOURS
   ===================================================== */
function afficherPrevisions(data) {
    const track = document.getElementById('previsions-track');
    track.innerHTML = '';

    if (!data || data.length === 0) {
        track.innerHTML = '<p style="color:rgba(255,255,255,0.4);padding:40px;">Aucune prévision disponible</p>';
        return;
    }

    const joursNoms = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];

    data.forEach((jour, i) => {
        const date  = new Date(jour.jour);
        const isAujourd = (jour.jour === new Date().toISOString().split('T')[0]);
        const jourNom = isAujourd ? "Auj." : joursNoms[date.getDay()];
        const dateStr = date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });

        const ventKmh = (parseFloat(jour.vent_moyen) * 3.6).toFixed(0);
        const score   = calculerScoreSurf(parseFloat(jour.vent_moyen), jour.conditions);

        // Icône conditions
        const iconeCondi = iconFromConditions(jour.conditions);

        const card = document.createElement('div');
        card.className = `prev-card${isAujourd ? ' today' : ''}`;
        card.innerHTML = `
            <div class="prev-day">${jourNom}</div>
            <div class="prev-date">${dateStr}</div>
            <div class="prev-icon">${iconeCondi}</div>
            <div class="prev-temps">
                <span class="prev-temp-max">${Math.round(parseFloat(jour.temp_max))}°</span>
                <span class="prev-temp-min">${Math.round(parseFloat(jour.temp_min))}°</span>
            </div>
            <div class="prev-vent">💨 ${ventKmh} km/h</div>
            <span class="prev-score-badge ${score.classe}">${score.emoji} ${score.label}</span>
        `;
        track.appendChild(card);

        // Animation entrée
        gsap.to(card, {
            opacity: 1,
            y: 0,
            duration: 0.5,
            delay: i * 0.1,
            ease: 'power3.out'
        });
    });
}

/* =====================================================
   CRÉNEAUX IDÉAUX
   ===================================================== */
function afficherCreneaux(data) {
    const grid = document.getElementById('creneaux-grid');
    grid.innerHTML = '';

    if (!data || data.length === 0) {
        grid.innerHTML = `<div class="creneaux-empty">
            🌊 Pas de créneau idéal identifié pour ce spot cette semaine.<br>
            <small>Vent favorable entre 18 et 72 km/h</small>
        </div>`;
        return;
    }

    data.forEach((item, i) => {
        const dt    = new Date(item.date_heure);
        const date  = dt.toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' });
        const heure = dt.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
        const kmh   = (parseFloat(item.vent_vitesse) * 3.6).toFixed(0);
        const score = calculerScoreSurf(parseFloat(item.vent_vitesse), item.conditions);

        const card = document.createElement('div');
        card.className = 'creneau-card';
        card.innerHTML = `
            <div class="creneau-icon">${score.emoji}</div>
            <div class="creneau-info">
                <div class="creneau-date">${date}</div>
                <div class="creneau-heure">${heure}</div>
                <div class="creneau-conditions">${item.conditions}</div>
            </div>
            <div class="creneau-vent">
                <div class="creneau-vent-val">${kmh}</div>
                <div class="creneau-vent-unit">km/h</div>
                <div class="creneau-temp">${Math.round(parseFloat(item.temperature))}°C</div>
            </div>
        `;
        grid.appendChild(card);

        gsap.to(card, {
            opacity: 1,
            x: 0,
            duration: 0.5,
            delay: i * 0.08,
            ease: 'power3.out'
        });
    });
}

/* =====================================================
   ANIMATIONS SECTIONS (ScrollTrigger)
   ===================================================== */
function animerSections() {
    const sections = ['#current-section', '#chart-section', '#previsions-section', '#creneaux-section'];

    sections.forEach((sel, i) => {
        const el = document.querySelector(sel);
        if (!el) return;

        ScrollTrigger.create({
            trigger: el,
            start: 'top 85%',
            onEnter: () => {
                gsap.to(el, { opacity: 1, y: 0, duration: 0.7, delay: i * 0.1, ease: 'power3.out' });
            },
            once: true
        });
    });

    // Première section visible sans scroll
    gsap.to('#current-section', { opacity: 1, y: 0, duration: 0.8, delay: 0.2, ease: 'power3.out' });
}

/* =====================================================
   ANIMATION BOUSSOLE
   ===================================================== */
function animerBoussole(degres) {
    const needle = document.getElementById('compass-needle');
    if (!needle) return;

    // Convention météo : 0° = vent venant du Nord
    // On pointe dans la direction d'où vient le vent
    gsap.to(needle, {
        rotation: degres,
        duration: 1.8,
        ease: 'elastic.out(1, 0.5)',
        transformOrigin: 'bottom center'
    });
}

/* =====================================================
   COMPTEUR ANIMÉ
   ===================================================== */
function animerNombre(elId, from, to, duration = 1, suffix = '', altId = null) {
    const el = document.getElementById(altId || elId);
    if (!el) return;

    const obj = { val: from };
    gsap.to(obj, {
        val: to,
        duration,
        ease: 'power2.out',
        onUpdate: () => {
            el.textContent = Math.round(obj.val) + suffix;
        }
    });
}

/* =====================================================
   UTILITAIRES
   ===================================================== */
function beaufort(ms) {
    if (ms < 0.3)  return 'Calme · B0';
    if (ms < 1.6)  return 'Très légère brise · B1';
    if (ms < 3.4)  return 'Légère brise · B2';
    if (ms < 5.5)  return 'Petite brise · B3';
    if (ms < 8.0)  return 'Jolie brise · B4';
    if (ms < 10.8) return 'Bonne brise · B5';
    if (ms < 13.9) return 'Vent frais · B6';
    if (ms < 17.2) return 'Grand frais · B7';
    if (ms < 20.8) return 'Coup de vent · B8';
    return 'Tempête · B9+';
}

function directionCardinale(deg) {
    const dirs = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSO', 'SO', 'OSO', 'O', 'ONO', 'NO', 'NNO'];
    return dirs[Math.round(deg / 22.5) % 16];
}

function iconFromConditions(conditions) {
    const c = (conditions || '').toLowerCase();
    if (c.includes('orage'))           return '<span style="font-size:40px">⛈️</span>';
    if (c.includes('pluie') || c.includes('bruine')) return '<span style="font-size:40px">🌧️</span>';
    if (c.includes('neige'))           return '<span style="font-size:40px">❄️</span>';
    if (c.includes('brume') || c.includes('brouillard')) return '<span style="font-size:40px">🌫️</span>';
    if (c.includes('dégagé') || c.includes('clair'))   return '<span style="font-size:40px">☀️</span>';
    if (c.includes('peu nuageux'))     return '<span style="font-size:40px">🌤️</span>';
    if (c.includes('partiellement'))   return '<span style="font-size:40px">⛅</span>';
    if (c.includes('couvert'))         return '<span style="font-size:40px">☁️</span>';
    return '<span style="font-size:40px">🌊</span>';
}

function afficherErreur() {
    document.getElementById('meteo-loader').innerHTML = `
        <div style="text-align:center;padding:60px;color:rgba(255,255,255,0.4)">
            <div style="font-size:48px;margin-bottom:16px">⚠️</div>
            <p>Impossible de charger les données météo.</p>
            <p style="font-size:12px;margin-top:8px">Vérifiez que le serveur PHP est lancé sur le port 8000.</p>
        </div>`;
}
