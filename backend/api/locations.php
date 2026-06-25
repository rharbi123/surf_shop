<?php
// =====================================================
// API/LOCATIONS.PHP - Gestion des locations
// -----------------------------------------------------
// Routes gérées par index.php :
//   GET    /locations?disponible=1&date_debut=&date_fin=  → planches dispo
//   GET    /locations?statut=en_cours|a_venir             → liste filtrée (admin)
//   GET    /utilisateurs/{id}/locations                   → historique utilisateur
//   POST   /locations                                     → créer
//   PUT    /locations/{id}                                → modifier statut
// =====================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function verifier_token(): array {
    $headers = getallheaders();
    $auth    = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (!$auth || !str_starts_with($auth, 'Bearer ')) {
        http_response_code(401);
        echo json_encode(['success' => false, 'erreur' => 'Token manquant']);
        exit;
    }

    $token  = substr($auth, 7);
    $secret = $_ENV['JWT_SECRET'] ?? 'surfshop_jwt_secret';

    try {
        $decoded = JWT::decode($token, new Key($secret, 'HS256'));
        return (array) $decoded;
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['success' => false, 'erreur' => 'Token invalide ou expiré']);
        exit;
    }
}

$pdo = require(__DIR__ . '/../config.php');

if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo json_encode(['success' => false, 'erreur' => 'Connexion PDO non valide']);
    exit;
}

$method         = $_SERVER['REQUEST_METHOD'];
$id             = $_GET['id']             ?? null; // /locations/{id}
$id_utilisateur = $_GET['id_utilisateur'] ?? null; // /utilisateurs/{id}/locations
$disponible     = $_GET['disponible']     ?? null; // ?disponible=1
$statut         = $_GET['statut']         ?? null; // ?statut=en_cours|a_venir
$date_debut     = $_GET['date_debut']     ?? null;
$date_fin       = $_GET['date_fin']       ?? null;

try {

    // =====================================================
    // GET
    // =====================================================
    if ($method === 'GET') {

        $payload = verifier_token();

        // GET /utilisateurs/{id}/locations → historique utilisateur
        if ($id_utilisateur !== null) {

            if ($payload['role'] !== 'admin' && (int)$id_utilisateur !== (int)$payload['id_utilisateur']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'erreur' => 'Accès refusé']);
                exit;
            }

            $sql = "SELECT l.id_location, l.date_debut, l.date_fin, l.montant_total, l.statut,
                           p.nom AS planche_nom, s.nom AS spot_nom,
                           DATEDIFF(l.date_fin, l.date_debut) AS nb_jours
                    FROM location l
                    JOIN planche p ON l.id_planche = p.id_planche
                    LEFT JOIN spot s ON l.id_spot = s.id_spot
                    WHERE l.id_utilisateur = ?
                    ORDER BY l.date_debut DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([(int)$id_utilisateur]);

            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            exit;
        }

        // GET /locations?disponible=1&date_debut=&date_fin= → planches disponibles
        if ($disponible !== null) {

            if (!$date_debut || !$date_fin) {
                http_response_code(400);
                echo json_encode(['success' => false, 'erreur' => 'Paramètres date_debut et date_fin requis']);
                exit;
            }

            $sql = "SELECT p.id_planche, p.nom, p.marque, p.prix_location_jour, p.shape, p.volume, p.niveau
                    FROM planche p
                    WHERE p.id_planche NOT IN (
                        SELECT l.id_planche
                        FROM location l
                        WHERE l.statut IN ('confirmée', 'actuelle')
                        AND NOT (l.date_fin < ? OR l.date_debut > ?)
                    )
                    AND p.stock > 0
                    ORDER BY p.prix_location_jour ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$date_debut, $date_fin]);
            $planches = $stmt->fetchAll();

            foreach ($planches as &$p) {
                if ($p['volume']) {
                    $p['volume'] = json_decode($p['volume'], true);
                }
            }

            http_response_code(200);
            echo json_encode(['success' => true, 'count' => count($planches), 'data' => $planches]);
            exit;
        }

        // GET /locations?statut=en_cours → locations en cours (admin)
        if ($statut === 'en_cours') {

            if ($payload['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'erreur' => 'Accès réservé à l\'admin']);
                exit;
            }

            $sql = "SELECT l.id_location, u.nom, u.email, p.nom AS planche_nom, l.date_fin, s.nom AS spot_nom
                    FROM location l
                    JOIN utilisateur u ON l.id_utilisateur = u.id_utilisateur
                    JOIN planche p ON l.id_planche = p.id_planche
                    LEFT JOIN spot s ON l.id_spot = s.id_spot
                    WHERE l.statut = 'actuelle'
                    AND l.date_fin <= CURDATE()
                    ORDER BY l.date_fin ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute();

            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            exit;
        }

        // GET /locations?statut=a_venir → locations à venir (admin)
        if ($statut === 'a_venir') {

            if ($payload['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'erreur' => 'Accès réservé à l\'admin']);
                exit;
            }

            $sql = "SELECT l.id_location, u.nom, u.email, p.nom AS planche_nom,
                           l.date_debut, l.date_fin, s.nom AS spot_nom
                    FROM location l
                    JOIN utilisateur u ON l.id_utilisateur = u.id_utilisateur
                    JOIN planche p ON l.id_planche = p.id_planche
                    LEFT JOIN spot s ON l.id_spot = s.id_spot
                    WHERE l.statut IN ('en attente', 'confirmée')
                    AND l.date_debut > CURDATE()
                    ORDER BY l.date_debut ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute();

            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'erreur' => 'Paramètre manquant. Utilisez ?disponible=1&date_debut=&date_fin= ou ?statut=en_cours|a_venir']);
        exit;
    }

    // =====================================================
    // POST /locations → créer une location
    // =====================================================
    if ($method === 'POST') {

        $payload = verifier_token();
        $data    = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'erreur' => 'JSON invalide']);
            exit;
        }

        $champs_obliges = ['id_planche', 'date_debut', 'date_fin'];
        foreach ($champs_obliges as $champ) {
            if (empty($data[$champ])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'erreur' => "Champ manquant: $champ"]);
                exit;
            }
        }

        if ($data['date_fin'] <= $data['date_debut']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'erreur' => 'date_fin doit être après date_debut']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id_planche, prix_location_jour, stock FROM planche WHERE id_planche = ?");
        $stmt->execute([(int)$data['id_planche']]);
        $planche = $stmt->fetch();

        if (!$planche) {
            http_response_code(404);
            echo json_encode(['success' => false, 'erreur' => 'Planche non trouvée']);
            exit;
        }

        if ($planche['stock'] <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'erreur' => 'Planche non disponible en stock']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) AS conflits FROM location
                               WHERE id_planche = ?
                               AND statut IN ('confirmée', 'actuelle')
                               AND NOT (date_fin < ? OR date_debut > ?)");
        $stmt->execute([(int)$data['id_planche'], $data['date_debut'], $data['date_fin']]);
        $conflit = $stmt->fetch();

        if ($conflit['conflits'] > 0) {
            http_response_code(409);
            echo json_encode(['success' => false, 'erreur' => 'Planche déjà réservée sur cette période']);
            exit;
        }

        $nb_jours      = (strtotime($data['date_fin']) - strtotime($data['date_debut'])) / 86400;
        $montant_total = $nb_jours * $planche['prix_location_jour'];

        $stmt = $pdo->prepare("INSERT INTO location (id_utilisateur, id_planche, id_spot, date_debut, date_fin, montant_total, statut)
                               VALUES (?, ?, ?, ?, ?, ?, 'en attente')");
        $stmt->execute([
            (int)$payload['id_utilisateur'],
            (int)$data['id_planche'],
            isset($data['id_spot']) ? (int)$data['id_spot'] : null,
            $data['date_debut'],
            $data['date_fin'],
            round($montant_total, 2)
        ]);

        http_response_code(201);
        echo json_encode([
            'success'       => true,
            'message'       => 'Location créée, en attente de paiement',
            'id_location'   => (int)$pdo->lastInsertId(),
            'montant_total' => round($montant_total, 2),
            'nb_jours'      => $nb_jours
        ]);
        exit;
    }

    // =====================================================
    // PUT /locations/{id} → modifier statut (admin)
    // =====================================================
    if ($method === 'PUT') {

        $payload = verifier_token();

        if ($payload['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'erreur' => 'Accès réservé à l\'admin']);
            exit;
        }

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'erreur' => 'ID location requis dans l\'URL']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['statut'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'erreur' => 'Champ statut requis']);
            exit;
        }

        $statuts_valides = ['en attente', 'confirmée', 'actuelle', 'terminée', 'annulée'];
        if (!in_array($data['statut'], $statuts_valides)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'erreur' => 'Statut invalide']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id_location FROM location WHERE id_location = ?");
        $stmt->execute([(int)$id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'erreur' => 'Location non trouvée']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE location SET statut = ? WHERE id_location = ?");
        $stmt->execute([$data['statut'], (int)$id]);

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Statut mis à jour']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'erreur' => 'Méthode HTTP non supportée']);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'erreur' => 'Erreur base de données']);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'erreur' => 'Erreur serveur']);
    exit;
}