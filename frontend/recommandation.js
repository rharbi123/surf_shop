// =====================================================
// WAVECRAFT — recommandation.js
// =====================================================

const API_URL = 'http://localhost:8000/api';

let niveauSelectionne = 'Débutant - Intermédiaire';
let typeSelectionne   = 'achat';
let budget            = 600;
let previsions        = [];
let jourSelectionne   = 0;
let dateSelectionnee  = new Date().toISOString().split('T')[0]; // aujourd'hui par défaut
let spotActuel        = '';

// =====================================================
// INIT
// =====================================================
document.addEventListener('DOMContentLoaded', () => {
    const user = JSON.parse(localStorage.getItem('user') || 'null');
    const actions = document.getElementById('navbar-actions');
    if (user) {
        actions.innerHTML = `
            <a href="profil.html" class="btn-login">${user.prenom}</a>
            <button onclick="deconnecter()" style="background:transparent;border:2px solid var(--ocean);color:var(--ocean);padding:8px 16px;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;font-family:'DM Sans',sans-serif;">Déconnexion</button>
        `;
    }

    if (!localStorage.getItem('token')) {
        document.getElementById('btn-reco').style.display = 'none';
        document.getElementById('reco-login-hint').style.display = 'block';
    }

    document.querySelectorAll('.niveau-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.niveau-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            niveauSelectionne = btn.dataset.niveau;
        });
    });

    document.querySelectorAll('.type-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.type-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            typeSelectionne = btn.dataset.type;

            const spotField  = document.getElementById('spot-field');
            const budgetField = document.getElementById('budget-field');

            if (typeSelectionne === 'location') {
                spotField.style.display = 'block';
                if (budgetField) budgetField.style.display = 'none';
            } else {
                spotField.style.display = 'none';
                if (budgetField) budgetField.style.display = 'block';
                document.getElementById('meteo-apercu').style.display = 'none';
                document.getElementById('spot-select').value = '';
                previsions = [];
                spotActuel = '';
                dateSelectionnee = new Date().toISOString().split('T')[0];
            }

            document.getElementById('reco-results').style.display = 'none';
            document.getElementById('reco-budget-error').style.display = 'none';
        });
    });

    const slider = document.getElementById('budget-slider');
    slider.addEventListener('input', () => {
        budget = parseInt(slider.value);
        document.getElementById('budget-val').textContent = budget;
    });
});

function deconnecter() {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    window.location.href = 'login.html';
}

// =====================================================
// MÉTÉO SPOT
// =====================================================
async function chargerMeteoSpot(spot) {
    const apercu  = document.getElementById('meteo-apercu');
    const loading = document.getElementById('meteo-loading');

    if (!spot) {
        apercu.style.display = 'none';
        previsions = [];
        spotActuel = '';
        return;
    }

    spotActuel = spot;
    apercu.style.display = 'none';
    loading.style.display = 'flex';
    previsions = [];
    jourSelectionne = 0;
    dateSelectionnee = new Date().toISOString().split('T')[0];

    try {
        const [resAujourd, resPrev] = await Promise.all([
            fetch(`${API_URL}/spots/${encodeURIComponent(spot)}/meteo`),
            fetch(`${API_URL}/spots/${encodeURIComponent(spot)}/previsions`)
        ]);

        const dataAujourd = await resAujourd.json();
        const dataPrev    = await resPrev.json();

        const joursMap = {};

        if (dataAujourd.success && dataAujourd.data.length > 0) {
            const today = dataAujourd.data[0].date_heure.split(' ')[0];
            joursMap[today] = {
                date: today,
                label: "Aujourd'hui",
                creneaux: dataAujourd.data
            };
        }

        if (dataPrev.success && dataPrev.data.length > 0) {
            for (const jour of dataPrev.data) {
                if (!joursMap[jour.jour]) {
                    joursMap[jour.jour] = {
                        date: jour.jour,
                        label: formaterDateLabel(jour.jour),
                        creneaux: [],
                        resume: {
                            temp_min:   jour.temp_min,
                            temp_max:   jour.temp_max,
                            vent_moyen: jour.vent_moyen,
                            conditions: jour.conditions
                        }
                    };
                }
            }
        }

        previsions = Object.values(joursMap).sort((a, b) => a.date.localeCompare(b.date));
        loading.style.display = 'none';

        if (previsions.length === 0) return;

        afficherMeteoJour(0);
        apercu.style.display = 'block';

    } catch (err) {
        loading.style.display = 'none';
    }
}

// =====================================================
// AFFICHER MÉTÉO D'UN JOUR
// =====================================================
function afficherMeteoJour(index) {
    jourSelectionne  = index;
    const jour       = previsions[index];
    if (!jour) return;

    // Mettre à jour la date sélectionnée → utilisée pour la recommandation
    dateSelectionnee = jour.date;

    document.getElementById('meteo-spot-nom').textContent = `🌊 ${spotActuel}`;
    document.getElementById('meteo-date').textContent = jour.label;

    // Navigation jours
    document.getElementById('meteo-nav-jours').innerHTML = previsions.map((j, i) => `
        <button class="meteo-jour-btn ${i === index ? 'active' : ''}" onclick="afficherMeteoJour(${i})">
            ${j.label}
        </button>
    `).join('');

    const body = document.getElementById('meteo-apercu-body');

    if (jour.creneaux && jour.creneaux.length > 0) {
        body.innerHTML = `
            <div class="meteo-creneaux-grid">
                ${jour.creneaux.map(c => {
                    const heure = c.date_heure ? c.date_heure.split(' ')[1].slice(0, 5) : '';
                    return `
                        <div class="meteo-creneau">
                            <div class="meteo-heure">${heure}</div>
                            <div class="meteo-conditions">${c.conditions || '—'}</div>
                            <div class="meteo-details">
                                <span>🌡 ${Math.round(c.temperature)}°</span>
                                <span>💨 ${Math.round(c.vent_vitesse)} m/s</span>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    } else if (jour.resume) {
        body.innerHTML = `
            <div class="meteo-resume">
                <div class="meteo-resume-item">
                    <span class="meteo-resume-label">Conditions</span>
                    <span class="meteo-resume-val">${jour.resume.conditions || '—'}</span>
                </div>
                <div class="meteo-resume-item">
                    <span class="meteo-resume-label">Température</span>
                    <span class="meteo-resume-val">${Math.round(jour.resume.temp_min)}° / ${Math.round(jour.resume.temp_max)}°</span>
                </div>
                <div class="meteo-resume-item">
                    <span class="meteo-resume-label">Vent moyen</span>
                    <span class="meteo-resume-val">${Math.round(jour.resume.vent_moyen)} m/s</span>
                </div>
            </div>
        `;
    } else {
        body.innerHTML = `<p style="color:rgba(255,255,255,0.5);font-size:13px;">Données non disponibles</p>`;
    }
}

// =====================================================
// FORMATER DATE
// =====================================================
function formaterDateLabel(dateStr) {
    const today    = new Date().toISOString().split('T')[0];
    const tomorrow = new Date(Date.now() + 86400000).toISOString().split('T')[0];
    if (dateStr === today)    return "Aujourd'hui";
    if (dateStr === tomorrow) return 'Demain';
    const date = new Date(dateStr);
    return date.toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' });
}

// =====================================================
// LANCER LA RECOMMANDATION
// =====================================================
async function lancerRecommandation() {
    const token = localStorage.getItem('token');
    if (!token) {
        window.location.href = 'login.html?redirect=recommandation';
        return;
    }

    // Validation location : spot + météo obligatoires
    if (typeSelectionne === 'location') {
        const spotChoisi = document.getElementById('spot-select').value;
        if (!spotChoisi) {
            alert('Veuillez choisir un spot pour la location.');
            return;
        }
        if (previsions.length === 0) {
            alert('Veuillez attendre le chargement de la météo.');
            return;
        }
    }

    document.getElementById('reco-results').style.display = 'none';
    document.getElementById('reco-budget-error').style.display = 'none';

    const btn    = document.getElementById('btn-reco');
    const text   = document.getElementById('btn-reco-text');
    const loader = document.getElementById('btn-reco-loader');
    btn.disabled = true;
    text.style.display = 'none';
    loader.style.display = 'block';

    const spot = document.getElementById('spot-select')?.value || null;

    // Body selon le type
    const body = { niveau: niveauSelectionne, type: typeSelectionne };
    if (typeSelectionne === 'achat') {
        body.budget = budget;
    } else {
        body.spot              = spot;
        body.date_selectionnee = dateSelectionnee;
    }

    try {
        const recoResponse = await fetch(`${API_URL}/recommandation`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify(body)
        });

        if (recoResponse.status === 401) {
            localStorage.removeItem('token');
            localStorage.removeItem('user');
            window.location.href = 'login.html';
            return;
        }

        const recoData = await recoResponse.json();

        if (recoData.succes === false && (!recoData.planches || recoData.planches.length === 0)) {
            if (typeSelectionne === 'achat') {
                afficherErreurBudget(recoData.message, recoData.prix_min);
            } else {
                alert(recoData.message || 'Aucune planche trouvée pour ces conditions.');
            }
            return;
        }

        if (!recoData.succes) {
            alert('Erreur : ' + (recoData.erreur || 'Réponse inattendue.'));
            return;
        }

        // Enrichir avec photo + id + prix_location_jour depuis planches.php
        const planchesResponse = await fetch(`${API_URL}/planches`);
        const planchesData     = await planchesResponse.json();
        const toutesLesPlanches = planchesData.success ? planchesData.data : [];

        const planchesEnrichies = recoData.planches.map(reco => {
            const match = toutesLesPlanches.find(p =>
                p.nom.toLowerCase().trim() === reco.nom.toLowerCase().trim()
            );
            return match ? { ...reco, ...match } : reco;
        });

        afficherResultats(recoData, planchesEnrichies);

    } catch (err) {
        alert('Erreur de connexion au serveur.');
    } finally {
        btn.disabled = false;
        text.style.display = 'inline';
        loader.style.display = 'none';
    }
}

// =====================================================
// AFFICHER LES RÉSULTATS
// =====================================================
function afficherResultats(recoData, planches) {
    document.getElementById('ia-response-text').textContent =
        recoData.recommandation_ia || 'Recommandation générée.';

    document.getElementById('reco-count').textContent =
        `${planches.length} planche${planches.length > 1 ? 's' : ''} recommandée${planches.length > 1 ? 's' : ''}`;

    const grid = document.getElementById('reco-planches-grid');
    grid.innerHTML = planches.map(p => {
        const id = p.id_planche || '';
        const imgHtml = p.photo
            ? `<img src="${p.photo}" alt="${p.nom}" class="planche-img" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">`
            : '';
        const placeholderHtml = `<div class="planche-img-placeholder" style="${p.photo ? 'display:none' : ''}"><div class="planche-board-icon"></div></div>`;

        // Prix : location → prix/jour, achat → prix d'achat
        let prixHtml = '';
        if (typeSelectionne === 'location') {
            prixHtml = p.prix_location_jour
                ? `<div class="planche-prix">${parseFloat(p.prix_location_jour).toFixed(2)} <span>€/jour</span></div>`
                : '';
        } else {
            prixHtml = `<div class="planche-prix">${parseFloat(p.prix_achat || p.prix).toFixed(2)} <span>€</span></div>`;
        }

        return `
            <div class="planche-card" onclick="${id ? `window.location.href='planche.html?id=${id}'` : ''}">
                ${imgHtml}
                ${placeholderHtml}
                <div class="planche-info">
                    <div class="planche-marque">${p.marque || ''}</div>
                    <div class="planche-nom">${p.nom}</div>
                    <div class="planche-tags">
                        <span class="tag tag-shape">${p.shape || p.shape_label || ''}</span>
                        <span class="tag tag-niveau">${niveauCourt(p.niveau || p.niveau_label || '')}</span>
                        ${(p.taille_vagues || p.taille_vagues_label) ? `<span class="tag tag-vagues">${p.taille_vagues || p.taille_vagues_label}</span>` : ''}
                    </div>
                    ${prixHtml}
                    <div class="planche-btns">
                        <button class="btn-acheter" onclick="event.stopPropagation(); ${id ? `window.location.href='planche.html?id=${id}'` : ''}">Voir</button>
                    </div>
                </div>
            </div>
        `;
    }).join('');

    document.getElementById('reco-results').style.display = 'block';
    document.getElementById('reco-results').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// =====================================================
// ERREUR BUDGET (achat uniquement)
// =====================================================
function afficherErreurBudget(message, prixMin) {
    document.getElementById('budget-error-msg').textContent =
        message || `Budget insuffisant. Les planches débutent à ${prixMin}€.`;
    document.getElementById('reco-budget-error').style.display = 'block';
    document.getElementById('reco-budget-error').scrollIntoView({ behavior: 'smooth' });
}

function augmenterBudget() {
    const slider = document.getElementById('budget-slider');
    const newVal = Math.min(parseInt(slider.value) + 200, 2000);
    slider.value = newVal;
    budget = newVal;
    document.getElementById('budget-val').textContent = newVal;
    document.getElementById('reco-budget-error').style.display = 'none';
}

function niveauCourt(niveau) {
    if (!niveau) return '';
    if (niveau.includes('Débutant')) return 'Débutant';
    if (niveau.includes('Intermédiaire') && niveau.includes('Confirmé')) return 'Intermédiaire';
    if (niveau.includes('Expert')) return 'Expert';
    return niveau;
}