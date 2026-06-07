// =====================================================
// WAVECRAFT — index.js
// =====================================================

const API_URL = 'http://localhost:8000/api';
const PAR_PAGE = 10;

let toutesLesPlanches = [];
let planchesFiltrees = [];
let pageCourante = 1;
let shapeActif = '';

// =====================================================
// CHARGEMENT DES PLANCHES
// =====================================================
async function chargerPlanches() {
    try {
        const response = await fetch(`${API_URL}/planches`);
        const data = await response.json();

        if (data.success) {
            toutesLesPlanches = data.data;
            planchesFiltrees = [...toutesLesPlanches];
            afficherPlanches();
        } else {
            afficherErreur('Impossible de charger les planches.');
        }
    } catch (error) {
        afficherErreur('Erreur de connexion au serveur.');
    }
}

// =====================================================
// AFFICHAGE DES PLANCHES
// =====================================================
function afficherPlanches() {
    const grid = document.getElementById('planches-grid');
    const debut = (pageCourante - 1) * PAR_PAGE;
    const fin = debut + PAR_PAGE;
    const planchesPage = planchesFiltrees.slice(debut, fin);

    document.getElementById('results-count').textContent =
        `${planchesFiltrees.length} planche${planchesFiltrees.length > 1 ? 's' : ''} trouvée${planchesFiltrees.length > 1 ? 's' : ''}`;

    if (planchesPage.length === 0) {
        grid.innerHTML = `
            <div class="loading">
                <p>Aucune planche ne correspond à vos critères.</p>
            </div>`;
        document.getElementById('pagination').innerHTML = '';
        return;
    }

    grid.innerHTML = planchesPage.map(p => creerCartePlanche(p)).join('');
    afficherPagination();
}

function creerCartePlanche(p) {
    const volume = Array.isArray(p.volume) ? p.volume.join(' / ') + 'L' : p.volume || '';
    const imgHtml = p.photo
        ? `<img src="${p.photo}" alt="${p.nom}" class="planche-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
           <div class="planche-img-placeholder" style="display:none"><div class="planche-board-icon"></div></div>`
        : `<div class="planche-img-placeholder"><div class="planche-board-icon"></div></div>`;

    return `
        <div class="planche-card" onclick="voirDetail(${p.id_planche})">
            ${imgHtml}
            <div class="planche-info">
                <div class="planche-marque">${p.marque}</div>
                <div class="planche-nom">${p.nom}</div>
                <div class="planche-tags">
                    <span class="tag tag-shape">${p.shape || ''}</span>
                    <span class="tag tag-niveau">${niveauCourt(p.niveau)}</span>
                    ${p.taille_vagues ? `<span class="tag tag-vagues">${p.taille_vagues}</span>` : ''}
                </div>
                <div class="planche-prix">${parseFloat(p.prix_achat).toFixed(2)} <span>€</span></div>
                <div class="planche-btns">
                    <button class="btn-acheter" onclick="event.stopPropagation(); acheter(${p.id_planche})">Acheter</button>
                    <button class="btn-louer" onclick="event.stopPropagation(); louer(${p.id_planche})">Louer</button>
                </div>
            </div>
        </div>`;
}

function niveauCourt(niveau) {
    if (!niveau) return '';
    if (niveau.includes('Débutant')) return 'Débutant';
    if (niveau.includes('Intermédiaire') && niveau.includes('Confirmé')) return 'Intermédiaire';
    if (niveau.includes('Expert')) return 'Expert';
    return niveau;
}

// =====================================================
// PAGINATION
// =====================================================
function afficherPagination() {
    const totalPages = Math.ceil(planchesFiltrees.length / PAR_PAGE);
    const pag = document.getElementById('pagination');

    if (totalPages <= 1) {
        pag.innerHTML = '';
        return;
    }

    let html = '';
    for (let i = 1; i <= totalPages; i++) {
        html += `<button class="page-btn ${i === pageCourante ? 'active' : ''}" onclick="changerPage(${i})">${i}</button>`;
    }
    pag.innerHTML = html;
}

function changerPage(page) {
    pageCourante = page;
    afficherPlanches();
    document.getElementById('catalogue').scrollIntoView({ behavior: 'smooth' });
}

// =====================================================
// FILTRES
// =====================================================
function appliquerFiltres() {
    const niveau = document.getElementById('filter-niveau').value;
    const prixMin = parseFloat(document.getElementById('filter-prix-min').value) || 0;
    const prixMax = parseFloat(document.getElementById('filter-prix-max').value) || 99999;

    planchesFiltrees = toutesLesPlanches.filter(p => {
        const prix = parseFloat(p.prix_achat);
        const matchNiveau = !niveau || p.niveau === niveau;
        const matchShape = !shapeActif || p.shape === shapeActif;
        const matchPrix = prix >= prixMin && prix <= prixMax;
        return matchNiveau && matchShape && matchPrix;
    });

    pageCourante = 1;
    afficherPlanches();
}

function resetFiltres() {
    document.getElementById('filter-niveau').value = '';
    document.getElementById('filter-prix-min').value = '';
    document.getElementById('filter-prix-max').value = '';
    shapeActif = '';
    document.querySelectorAll('.shape-card').forEach(c => c.classList.remove('active'));
    document.querySelector('.shape-card[data-shape=""]').classList.add('active');
    planchesFiltrees = [...toutesLesPlanches];
    pageCourante = 1;
    afficherPlanches();
}

// Filtres par shape
document.querySelectorAll('.shape-card').forEach(card => {
    card.addEventListener('click', () => {
        document.querySelectorAll('.shape-card').forEach(c => c.classList.remove('active'));
        card.classList.add('active');
        shapeActif = card.dataset.shape;
        appliquerFiltres();
    });
});

// =====================================================
// ACTIONS
// =====================================================
function voirDetail(id) {
    window.location.href = `planche.html?id=${id}`;
}

function acheter(id) {
    const token = localStorage.getItem('token');
    if (!token) {
        window.location.href = 'login.html?redirect=achat&id=' + id;
        return;
    }
    window.location.href = `paiement.html?type=achat&id=${id}`;
}

function louer(id) {
    const token = localStorage.getItem('token');
    if (!token) {
        window.location.href = 'login.html?redirect=location&id=' + id;
        return;
    }
    window.location.href = `location.html?id=${id}`;
}

function afficherErreur(msg) {
    document.getElementById('planches-grid').innerHTML = `
        <div class="loading"><p style="color: #CC0000;">${msg}</p></div>`;
}

// =====================================================
// INIT
// =====================================================
function deconnecter() {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    window.location.href = 'login.html';
}

document.addEventListener('DOMContentLoaded', () => {
    chargerPlanches();

    const user = JSON.parse(localStorage.getItem('user') || 'null');
    const actions = document.querySelector('.navbar-actions');

    if (user) {
        actions.innerHTML = `
            <a href="profil.html" class="btn-login">${user.prenom}</a>
            <button onclick="deconnecter()" style="background:transparent;border:2px solid var(--ocean);color:var(--ocean);padding:8px 16px;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;font-family:'DM Sans',sans-serif;">Déconnexion</button>
        `;
    } else {
        actions.innerHTML = `<a href="login.html" class="btn-login">Connexion</a>`;
    }
});