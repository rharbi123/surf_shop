// =====================================================
// WAVECRAFT — login.js
// =====================================================

const API_URL = 'http://localhost:8000/api';

// =====================================================
// ONGLETS
// =====================================================
function switchTab(tab) {
    document.getElementById('form-connexion').style.display   = tab === 'connexion'  ? 'block' : 'none';
    document.getElementById('form-inscription').style.display = tab === 'inscription' ? 'block' : 'none';
    document.getElementById('tab-connexion').classList.toggle('active',  tab === 'connexion');
    document.getElementById('tab-inscription').classList.toggle('active', tab === 'inscription');
    hideAlert();
}

// =====================================================
// ALERTE
// =====================================================
function showAlert(msg, type = 'error') {
    const el = document.getElementById('login-alert');
    el.textContent = msg;
    el.className = `login-alert ${type}`;
    el.style.display = 'block';
}

function hideAlert() {
    document.getElementById('login-alert').style.display = 'none';
}

// =====================================================
// TOGGLE MOT DE PASSE
// =====================================================
function togglePw(inputId, btn) {
    const input = document.getElementById(inputId);
    input.type = input.type === 'password' ? 'text' : 'password';
}

// =====================================================
// REDIRECTION APRÈS CONNEXION
// =====================================================
function getRedirectUrl() {
    const params = new URLSearchParams(window.location.search);
    const redirect = params.get('redirect');
    const id = params.get('id');
    if (redirect === 'achat' && id)    return `paiement.html?type=achat&id=${id}`;
    if (redirect === 'location' && id) return `location.html?id=${id}`;
    if (redirect === 'cours')          return 'cours.html';
    return 'index.html';
}

// =====================================================
// CONNEXION
// =====================================================
async function handleConnexion(event) {
    event.preventDefault();
    hideAlert();

    const btn    = document.getElementById('btn-cx');
    const span   = btn.querySelector('span');
    const loader = btn.querySelector('.form-spinner');

    btn.disabled = true;
    span.style.display = 'none';
    loader.style.display = 'block';

    try {
        const response = await fetch(`${API_URL}/auth/login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                email:        document.getElementById('cx-email').value.trim(),
                mot_de_passe: document.getElementById('cx-password').value
            })
        });

        const data = await response.json();

        if (data.success) {
            localStorage.setItem('token', data.token);
            localStorage.setItem('user', JSON.stringify(data.data));
            showAlert('Connexion réussie ! Redirection...', 'success');
            setTimeout(() => { window.location.href = getRedirectUrl(); }, 800);
        } else {
            showAlert(data.erreur || 'Email ou mot de passe incorrect.');
        }
    } catch (err) {
        showAlert('Erreur de connexion au serveur.');
    } finally {
        btn.disabled = false;
        span.style.display = 'inline';
        loader.style.display = 'none';
    }
}

// =====================================================
// INSCRIPTION — pas d'auto-login
// =====================================================
async function handleInscription(event) {
    event.preventDefault();
    hideAlert();

    const btn    = document.getElementById('btn-in');
    const span   = btn.querySelector('span');
    const loader = btn.querySelector('.form-spinner');

    const password = document.getElementById('in-password').value;
    if (password.length < 8) {
        showAlert('Le mot de passe doit contenir au moins 8 caractères.');
        return;
    }

    btn.disabled = true;
    span.style.display = 'none';
    loader.style.display = 'block';

    const email = document.getElementById('in-email').value.trim();

    try {
        const response = await fetch(`${API_URL}/utilisateurs`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nom:               document.getElementById('in-nom').value.trim(),
                prenom:            document.getElementById('in-prenom').value.trim(),
                email:             email,
                mot_de_passe:      password,
                niveau:            document.getElementById('in-niveau').value || null,
                consentement_rgpd: document.getElementById('in-rgpd').checked
            })
        });

        const data = await response.json();

        if (data.success) {
            // Pas de token stocké — l'utilisateur doit se connecter manuellement
            showAlert('Compte créé ! Connectez-vous maintenant.', 'success');
            document.getElementById('form-inscription').reset();
            setTimeout(() => {
                switchTab('connexion');
                document.getElementById('cx-email').value = email;
            }, 1500);
        } else {
            showAlert(data.erreur || 'Erreur lors de l\'inscription.');
        }
    } catch (err) {
        showAlert('Erreur de connexion au serveur.');
    } finally {
        btn.disabled = false;
        span.style.display = 'inline';
        loader.style.display = 'none';
    }
}

// =====================================================
// INIT
// =====================================================
document.addEventListener('DOMContentLoaded', () => {
    // Déjà connecté → redirige
    if (localStorage.getItem('token')) {
        window.location.href = getRedirectUrl();
        return;
    }

    const params = new URLSearchParams(window.location.search);
    if (params.get('tab') === 'inscription') {
        switchTab('inscription');
    }
});