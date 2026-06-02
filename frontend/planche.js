// =====================================================
// WAVECRAFT — planche.js
// =====================================================

const API_URL = 'http://localhost:8000/api';

let planche = null;
let prixBase = 0;
let personnalisationsSelectionnees = new Set();

// =====================================================
// INIT
// =====================================================
document.addEventListener('DOMContentLoaded', () => {
    // Navbar : connecté ou non
    const user = JSON.parse(localStorage.getItem('user') || 'null');
    const actions = document.getElementById('navbar-actions');
    if (user) {
        actions.innerHTML = `
            <a href="profil.html" class="btn-login">${user.prenom}</a>
            <button onclick="deconnecter()" style="background:transparent;border:2px solid var(--ocean);color:var(--ocean);padding:8px 16px;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;font-family:'DM Sans',sans-serif;">Déconnexion</button>
        `;
    }

    // Récupérer l'id dans l'URL
    const params = new URLSearchParams(window.location.search);
    const id = params.get('id');

    if (!id) {
        afficherErreur('Aucune planche sélectionnée.');
        return;
    }

    chargerPlanche(id);
});

function deconnecter() {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    window.location.href = 'login.html';
}

// =====================================================
// CHARGEMENT PLANCHE
// =====================================================
async function chargerPlanche(id) {
    try {
        const response = await fetch(`${API_URL}/planches.php?id=${id}`);
        const data = await response.json();

        if (data.success) {
            planche = data.data;
            prixBase = parseFloat(planche.prix_achat);
            afficherPlanche();
            chargerSimilaires(planche.shape, planche.id_planche);
        } else {
            afficherErreur('Planche introuvable.');
        }
    } catch (err) {
        afficherErreur('Erreur de connexion au serveur.');
    }
}

// =====================================================
// AFFICHAGE PLANCHE
// =====================================================
function afficherPlanche() {
    // Breadcrumb
    document.title = `WaveCraft — ${planche.nom}`;
    document.getElementById('breadcrumb-nom').textContent = planche.nom;

    // Volume
    const volume = Array.isArray(planche.volume)
        ? planche.volume.join(' / ') + 'L'
        : (planche.volume || '—');

    // Stock
    const stock = parseInt(planche.stock) || 0;
    let stockHtml = '';
    if (stock === 0)       stockHtml = `<span class="stock-badge stock-none">Rupture de stock</span>`;
    else if (stock <= 3)   stockHtml = `<span class="stock-badge stock-low">Plus que ${stock} en stock</span>`;
    else                   stockHtml = `<span class="stock-badge stock-ok">En stock (${stock})</span>`;

    // Image
    const imgHtml = planche.photo
        ? `<img src="${planche.photo}" alt="${planche.nom}" class="planche-main-img" onerror="this.outerHTML='<div class=\\'planche-img-placeholder-lg\\'><div class=\\'board-icon-lg\\'></div></div>'">`
        : `<div class="planche-img-placeholder-lg"><div class="board-icon-lg"></div></div>`;

    // Prix location
    const prixLocation = planche.prix_location_jour
        ? `<div class="prix-location-ligne">
               <span class="prix-label">Location / jour</span>
               <span class="prix-location-val">${parseFloat(planche.prix_location_jour).toFixed(2)} €</span>
           </div>`
        : '';

    // Bouton louer désactivé si pas de prix location
    const louerDisabled = !planche.prix_location_jour || stock === 0 ? 'disabled' : '';
    const acheterDisabled = stock === 0 ? 'disabled' : '';

    document.getElementById('planche-page').innerHTML = `
        <!-- COLONNE IMAGE -->
        <div class="planche-galerie">
            ${imgHtml}
            ${stockHtml}
        </div>

        <!-- COLONNE INFOS -->
        <div class="planche-details">
            <div>
                <div class="planche-marque-detail">${planche.marque}</div>
                <div class="planche-nom-detail">${planche.nom}</div>
            </div>

            <div class="planche-tags-detail">
                ${planche.shape      ? `<span class="tag tag-lg tag-shape">${planche.shape}</span>` : ''}
                ${planche.niveau     ? `<span class="tag tag-lg tag-niveau">${niveauCourt(planche.niveau)}</span>` : ''}
                ${planche.taille_vagues ? `<span class="tag tag-lg tag-vagues">${planche.taille_vagues}</span>` : ''}
            </div>

            <div class="specs-grid">
                <div class="spec-item">
                    <div class="spec-label">Volume</div>
                    <div class="spec-value">${volume}</div>
                </div>
                <div class="spec-item">
                    <div class="spec-label">Dérives</div>
                    <div class="spec-value">${planche.nb_derives || '—'}</div>
                </div>
                <div class="spec-item">
                    <div class="spec-label">Boîtiers</div>
                    <div class="spec-value">${planche.boitiers || '—'}</div>
                </div>
                <div class="spec-item">
                    <div class="spec-label">Conception</div>
                    <div class="spec-value">${planche.conception || '—'}</div>
                </div>
            </div>

            ${planche.description ? `<p class="planche-description">${planche.description}</p>` : ''}

            <div class="prix-section">
                <div class="prix-achat-ligne">
                    <span class="prix-label">Prix d'achat</span>
                    <span class="prix-valeur">${prixBase.toFixed(2)} <small>€</small></span>
                </div>
                ${prixLocation}
            </div>

            <div id="perso-section"></div>

            <div class="prix-total-bar">
                <span class="prix-total-label">Total avec options</span>
                <span class="prix-total-valeur" id="prix-total">${prixBase.toFixed(2)} €</span>
            </div>

            <div class="action-btns">
                <button class="btn-acheter-lg" onclick="acheter()" ${acheterDisabled}>
                    🛒 Acheter
                </button>
                <button class="btn-louer-lg" onclick="louer()" ${louerDisabled}>
                    📅 Louer
                </button>
            </div>
        </div>
    `;

    // Charger les personnalisations
    chargerPersonnalisations(planche.id_planche);
}

// =====================================================
// PERSONNALISATIONS
// =====================================================
async function chargerPersonnalisations(id_planche) {
    // L'API personnalisations n'est pas exposée directement,
    // on les affiche si elles existent dans les données
    // Pour l'instant on affiche une section vide propre
    const section = document.getElementById('perso-section');
    if (!section) return;

    // Personnalisations fictives pour démo (à remplacer par appel API si endpoint dispo)
    const persos = [
        { id: 1, nom: 'Dérive FCS II', prix_supplement: 45 },
        { id: 2, nom: 'Leash 6\'', prix_supplement: 25 },
        { id: 3, nom: 'Grip pad', prix_supplement: 20 },
        { id: 4, nom: 'Housse de transport', prix_supplement: 60 },
    ];

    if (persos.length === 0) return;

    section.innerHTML = `
        <div class="perso-section">
            <div class="perso-header">✨ Options & Personnalisations</div>
            <div class="perso-list" id="perso-list">
                ${persos.map(p => `
                    <label class="perso-item" id="perso-${p.id}">
                        <input type="checkbox" value="${p.prix_supplement}" data-id="${p.id}"
                            onchange="togglePerso(${p.id}, ${p.prix_supplement}, this)">
                        <span class="perso-nom">${p.nom}</span>
                        <span class="perso-prix">+${p.prix_supplement} €</span>
                    </label>
                `).join('')}
            </div>
        </div>
    `;
}

function togglePerso(id, supplement, checkbox) {
    const item = document.getElementById(`perso-${id}`);
    if (checkbox.checked) {
        personnalisationsSelectionnees.add({ id, supplement });
        item.classList.add('selected');
    } else {
        personnalisationsSelectionnees.forEach(p => { if (p.id === id) personnalisationsSelectionnees.delete(p); });
        item.classList.remove('selected');
    }
    majPrixTotal();
}

function majPrixTotal() {
    let total = prixBase;
    document.querySelectorAll('#perso-list input[type="checkbox"]:checked').forEach(cb => {
        total += parseFloat(cb.value);
    });
    document.getElementById('prix-total').textContent = total.toFixed(2) + ' €';
}

// =====================================================
// ACTIONS
// =====================================================
function acheter() {
    const token = localStorage.getItem('token');
    if (!token) {
        window.location.href = `login.html?redirect=achat&id=${planche.id_planche}`;
        return;
    }
    window.location.href = `paiement.html?type=achat&id=${planche.id_planche}`;
}

function louer() {
    const token = localStorage.getItem('token');
    if (!token) {
        window.location.href = `login.html?redirect=location&id=${planche.id_planche}`;
        return;
    }
    window.location.href = `location.html?id=${planche.id_planche}`;
}

// =====================================================
// PLANCHES SIMILAIRES
// =====================================================
async function chargerSimilaires(shape, id_actuel) {
    try {
        const response = await fetch(`${API_URL}/planches.php`);
        const data = await response.json();

        if (!data.success) return;

        const similaires = data.data
            .filter(p => p.shape === shape && p.id_planche !== id_actuel)
            .slice(0, 4);

        if (similaires.length === 0) return;

        document.getElementById('similaires-section').style.display = 'block';
        document.getElementById('similaires-grid').innerHTML = similaires.map(p => `
            <div class="planche-card" onclick="window.location.href='planche.html?id=${p.id_planche}'">
                ${p.photo
                    ? `<img src="${p.photo}" alt="${p.nom}" class="planche-img" onerror="this.style.display='none'">`
                    : `<div class="planche-img-placeholder"><div class="planche-board-icon"></div></div>`
                }
                <div class="planche-info">
                    <div class="planche-marque">${p.marque}</div>
                    <div class="planche-nom">${p.nom}</div>
                    <div class="planche-prix">${parseFloat(p.prix_achat).toFixed(2)} <span>€</span></div>
                </div>
            </div>
        `).join('');
    } catch (err) {
        // Silencieux
    }
}

// =====================================================
// UTILITAIRES
// =====================================================
function niveauCourt(niveau) {
    if (!niveau) return '';
    if (niveau.includes('Débutant')) return 'Débutant';
    if (niveau.includes('Intermédiaire') && niveau.includes('Confirmé')) return 'Intermédiaire';
    if (niveau.includes('Expert')) return 'Expert';
    return niveau;
}

function afficherErreur(msg) {
    document.getElementById('planche-page').innerHTML = `
        <div class="loading" style="grid-column:1/-1">
            <p style="color:var(--coral)">${msg}</p>
            <a href="index.html" class="btn-primary" style="margin-top:16px;">Retour au catalogue</a>
        </div>`;
}