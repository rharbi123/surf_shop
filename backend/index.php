<?php
// =====================================================
// INDEX.PHP - Routeur REST WaveCraft
// -----------------------------------------------------
// Conventions REST appliquées :
//   - Ressources au pluriel
//   - Hiérarchie parent/enfant : /utilisateurs/5/commandes
//   - Filtres en query string    : /commandes?statut=en_attente
//   - L'action est portée par la méthode HTTP (GET/POST/PUT/DELETE)
//   - Aucun paramètre "action" dans l'URL
//
// CARTE DES ROUTES
// -----------------------------------------------------
//  PLANCHES
//   GET    /planches                       liste (filtres ?niveau=&prix_min=&prix_max=)
//   GET    /planches/{id}                  détail
//   POST   /planches                       créer
//
//  AUTH
//   POST   /auth/login                     connexion
//
//  UTILISATEURS
//   POST   /utilisateurs                   inscription
//   GET    /utilisateurs/{id}              profil
//   PUT    /utilisateurs/{id}              modifier profil
//   POST   /moniteurs                      créer un moniteur (admin)
//   GET    /utilisateurs/{id}/commandes    historique commandes
//   GET    /utilisateurs/{id}/locations    historique locations
//   GET    /utilisateurs/{id}/cours        historique cours
//   GET    /utilisateurs/{id}/paiements    historique paiements
//
//  COMMANDES
//   GET    /commandes?statut=en_attente    liste filtrée (admin)
//   GET    /commandes/{id}                 détail
//   POST   /commandes                      créer
//   PUT    /commandes/{id}                 modifier statut
//
//  LOCATIONS
//   GET    /locations?disponible=1&date_debut=&date_fin=   planches dispo
//   GET    /locations?statut=en_cours|a_venir              liste filtrée (admin)
//   POST   /locations                      créer
//   PUT    /locations/{id}                 modifier statut
//
//  COURS
//   GET    /cours                          cours disponibles
//   GET    /cours/{id}/inscrits            participants (moniteur/admin)
//   POST   /cours                          créer (admin)
//   POST   /cours/{id}/reservations        réserver
//   PUT    /cours/{id}                     modifier statut du cours
//   PUT    /cours/{id}/reservations/{uid}  marquer présence
//   GET    /moniteurs/{id}/cours           planning moniteur
//
//  SPOTS / MÉTÉO
//   GET    /spots                          liste des spots
//   GET    /spots/{nom}/meteo              météo actuelle
//   GET    /spots/{nom}/previsions         prévisions
//   GET    /spots/{nom}/creneaux           meilleurs créneaux
//
//  PAIEMENTS
//   GET    /paiements?statut=en_attente    liste filtrée (admin)
//   POST   /paiements                      créer
//   PUT    /paiements/{id}                 confirmer / refuser
//
//  RECOMMANDATIONS
//   POST   /recommandations                recommandation IA
// =====================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Enlever le préfixe /api
if (strpos($uri, '/api/') === 0) {
    $uri = substr($uri, 4);
} elseif ($uri === '/api') {
    $uri = '';
}
$uri = trim($uri, '/');

$segments = explode('/', $uri);

$resource = $segments[0] ?? '';
$param1   = $segments[1] ?? null;
$param2   = $segments[2] ?? null;
$param3   = $segments[3] ?? null;

function not_found($msg = 'Route non trouvée') {
    http_response_code(404);
    echo json_encode(['success' => false, 'erreur' => $msg]);
    exit;
}

switch ($resource) {

    // =====================================================
    // PLANCHES
    // =====================================================
    case 'planches':
        // /planches/{id}  → détail (les filtres ?niveau= restent dans $_GET)
        if ($param1 !== null) {
            $_GET['id'] = $param1;
        }
        require __DIR__ . '/api/planches.php';
        break;

    // =====================================================
    // AUTH (connexion)
    // =====================================================
    case 'auth':
        // POST /auth/login
        if ($param1 === 'login') {
            $_GET['ressource'] = 'login';
            require __DIR__ . '/api/utilisateurs.php';
        } else {
            not_found();
        }
        break;

    // =====================================================
    // UTILISATEURS
    // =====================================================
    case 'utilisateurs':
        // Sous-ressources : /utilisateurs/{id}/commandes, /locations, /cours, /paiements
        if ($param1 !== null && $param2 !== null) {
            $_GET['id_utilisateur'] = $param1;
            switch ($param2) {
                case 'commandes':
                    require __DIR__ . '/api/commandes.php';
                    break;
                case 'locations':
                    require __DIR__ . '/api/locations.php';
                    break;
                case 'cours':
                    require __DIR__ . '/api/cours.php';
                    break;
                case 'paiements':
                    require __DIR__ . '/api/paiements.php';
                    break;
                default:
                    not_found();
            }
        }
        // /utilisateurs/{id}  → profil (GET/PUT)
        elseif ($param1 !== null) {
            $_GET['id'] = $param1;
            require __DIR__ . '/api/utilisateurs.php';
        }
        // POST /utilisateurs  → inscription
        else {
            $_GET['ressource'] = 'inscription';
            require __DIR__ . '/api/utilisateurs.php';
        }
        break;

    // =====================================================
    // MONITEURS
    // =====================================================
    case 'moniteurs':
        // POST /moniteurs            → créer un moniteur (admin)
        // GET  /moniteurs/{id}/cours → planning du moniteur
        if ($param1 !== null && $param2 === 'cours') {
            $_GET['id_moniteur'] = $param1;
            $_GET['ressource']   = 'planning';
            require __DIR__ . '/api/cours.php';
        } elseif ($param1 === null && $method === 'POST') {
            $_GET['ressource'] = 'creer_moniteur';
            require __DIR__ . '/api/utilisateurs.php';
        } else {
            not_found();
        }
        break;

    // =====================================================
    // COMMANDES
    // =====================================================
    case 'commandes':
        // /commandes/{id} → détail ou PUT statut
        // /commandes?statut=... → liste filtrée (query string déjà dans $_GET)
        if ($param1 !== null) {
            $_GET['id'] = $param1;
        }
        require __DIR__ . '/api/commandes.php';
        break;

    // =====================================================
    // LOCATIONS
    // =====================================================
    case 'locations':
        // /locations/{id} → PUT statut
        // /locations?disponible=1&... ou ?statut=... → query string
        if ($param1 !== null) {
            $_GET['id'] = $param1;
        }
        require __DIR__ . '/api/locations.php';
        break;

    // =====================================================
    // COURS
    // =====================================================
    case 'cours':
        // /cours/{id}/inscrits
        if ($param1 !== null && $param2 === 'inscrits') {
            $_GET['id']        = $param1;
            $_GET['ressource'] = 'inscrits';
        }
        // /cours/{id}/reservations            (POST = réserver)
        // /cours/{id}/reservations/{uid}      (PUT = présence)
        elseif ($param1 !== null && $param2 === 'reservations') {
            $_GET['id'] = $param1;
            if ($param3 !== null) {
                $_GET['id_utilisateur'] = $param3;
                $_GET['ressource']      = 'presence';
            } else {
                $_GET['ressource'] = 'reserver';
            }
        }
        // /cours/{id}  (PUT = statut du cours)
        elseif ($param1 !== null) {
            $_GET['id'] = $param1;
        }
        require __DIR__ . '/api/cours.php';
        break;

    // =====================================================
    // SPOTS / MÉTÉO
    // =====================================================
    case 'spots':
        // GET /spots                  → liste des spots
        // GET /spots/{nom}/meteo      → météo actuelle
        // GET /spots/{nom}/previsions → prévisions
        // GET /spots/{nom}/creneaux   → meilleurs créneaux
        if ($param1 === null) {
            $_GET['ressource'] = 'spots';
        } else {
            $_GET['spot'] = $param1;
            if ($param2 === 'previsions') {
                $_GET['ressource'] = 'previsions';
            } elseif ($param2 === 'creneaux') {
                $_GET['ressource'] = 'creneaux';
            } else {
                $_GET['ressource'] = 'meteo';
            }
        }
        require __DIR__ . '/api/meteo.php';
        break;

    // =====================================================
    // PAIEMENTS
    // =====================================================
    case 'paiements':
        // /paiements/{id} → PUT confirmer/refuser
        // /paiements?statut=... → query string
        if ($param1 !== null) {
            $_GET['id'] = $param1;
        }
        require __DIR__ . '/api/paiements.php';
        break;

    // =====================================================
    // RECOMMANDATIONS
    // =====================================================
    case 'recommandations':
    case 'recommandation':
        require __DIR__ . '/api/recommandation.php';
        break;

    default:
        not_found();
}