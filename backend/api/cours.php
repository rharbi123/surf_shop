<?php
// =====================================================
// API/COURS.PHP - Gestion des cours de surf
// -----------------------------------------------------
// Routes gérées par index.php :
//   GET    /cours                              → cours disponibles (public)
//   GET    /cours/{id}/inscrits               → participants (moniteur/admin)
//   GET    /utilisateurs/{id}/cours           → historique utilisateur
//   GET    /moniteurs/{id}/cours              → planning moniteur
//   POST   /cours                             → créer (admin)
//   POST   /cours/{id}/reservations           → réserver
//   PUT    /cours/{id}                        → modifier statut cours (admin)
//   PUT    /cours/{id}/reservations/{uid}     → marquer présence (moniteur/admin)
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
$id             = $_GET['id']             ?? null; // /cours/{id}
$ressource      = $_GET['ressource']      ?? null; // 'inscrits', 'reserver', 'presence', 'planning'
$id_utilisateur = $_GET['id_utilisateur'] ?? null; // /utilisateurs/{id}/cours ou présence
$id_moniteur    = $_GET['id_moniteur']    ?? null; // /moniteurs/{id}/cours

try {

    // =====================================================
    // GET
    // =====================================================
    if ($method === 'GET') {

        // GET /cours → cours disponibles (public, pas de token requis)
        if ($id === null && $ressource === null && $id_utilisateur === null && $id_moniteur === null) {

            $sql = "SELECT c.id_cours, c.date_, c.heure_debut, c.duree, c.capacite_max,
                           COUNT(rc.id_reservation) AS places_occupees,
                           (c.capacite_max - COUNT(rc.id_reservation)) AS places_restantes,
                           u.nom AS moniteur_nom
                    FROM cours c
                    LEFT JOIN reservation_cours rc ON c.id_cours = rc.id_cours
                        AND rc.statut_presence IN ('confirmé', 'présent')
                    JOIN moniteur m ON c.id_moniteur = m.id_moniteur
                    JOIN utilisateur u ON m.id_utilisateur = u.id_utilisateur
                    WHERE c.date_ >= CURDATE()
                    AND c.statut = 'programmé'
                    GROUP BY c.id_cours
                    HAVING places_restantes > 0
                    ORDER BY c.date_ ASC, c.heure_debut ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute();

            http_response_code(200);
            echo json_encode(['success' => true, 'count' => $stmt->rowCount(), 'data' => $stmt->fetchAll()]);
            exit;
        }

        $payload = verifier_token();

        // GET /cours/{id}/inscrits → participants d'un cours (moniteur/admin)
        if ($id !== null && $ressource === 'inscrits') {

            if (!in_array($payload['role'], ['admin', 'moniteur'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'erreur' => 'Accès réservé au moniteur ou admin']);
                exit;
            }

            $sql = "SELECT u.id_utilisateur, u.nom, u.prenom, u.email, u.niveau,
                           rc.date_reservation, rc.statut_presence
                    FROM reservation_cours rc
                    JOIN utilisateur u ON rc.id_utilisateur = u.id_utilisateur
                    WHERE rc.id_cours = ?
                    ORDER BY rc.date_reservation ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([(int)$id]);

            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            exit;
        }

        // GET /utilisateurs/{id}/cours → historique cours d'un utilisateur
        if ($id_utilisateur !== null) {

            if ($payload['role'] === 'client' && (int)$id_utilisateur !== (int)$payload['id_utilisateur']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'erreur' => 'Accès refusé']);
                exit;
            }

            $sql = "SELECT c.id_cours, c.date_, c.heure_debut, c.duree,
                           u.nom AS moniteur_nom, rc.statut_presence
                    FROM reservation_cours rc
                    JOIN cours c ON rc.id_cours = c.id_cours
                    JOIN moniteur m ON c.id_moniteur = m.id_moniteur
                    JOIN utilisateur u ON m.id_utilisateur = u.id_utilisateur
                    WHERE rc.id_utilisateur = ?
                    ORDER BY c.date_ DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([(int)$id_utilisateur]);

            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            exit;
        }

        // GET /moniteurs/{id}/cours → planning d'un moniteur
        if ($id_moniteur !== null && $ressource === 'planning') {

            if ($payload['role'] === 'moniteur') {
                $stmt = $pdo->prepare("SELECT id_moniteur FROM moniteur WHERE id_utilisateur = ?");
                $stmt->execute([(int)$payload['id_utilisateur']]);
                $mon = $stmt->fetch();
                if (!$mon || (int)$mon['id_moniteur'] !== (int)$id_moniteur) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'erreur' => 'Accès refusé']);
                    exit;
                }
            } elseif ($payload['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'erreur' => 'Accès réservé au moniteur ou admin']);
                exit;
            }

            $sql = "SELECT c.id_cours, c.date_, c.heure_debut, c.duree, c.capacite_max,
                           COUNT(rc.id_reservation) AS inscrits, c.statut
                    FROM cours c
                    LEFT JOIN reservation_cours rc ON c.id_cours = rc.id_cours
                        AND rc.statut_presence != 'annulé'
                    WHERE c.id_moniteur = ?
                    AND c.date_ BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                    GROUP BY c.id_cours
                    ORDER BY c.date_ ASC, c.heure_debut ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([(int)$id_moniteur]);

            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'erreur' => 'Paramètre manquant']);
        exit;
    }

    // =====================================================
    // POST
    // =====================================================
    if ($method === 'POST') {

        $payload = verifier_token();
        $data    = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'erreur' => 'JSON invalide']);
            exit;
        }

        // POST /cours → créer un cours (admin)
        if ($id === null) {

            if ($payload['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'erreur' => 'Accès réservé à l\'admin']);
                exit;
            }

            $champs_obliges = ['id_moniteur', 'date_', 'heure_debut', 'duree', 'capacite_max'];
            foreach ($champs_obliges as $champ) {
                if (empty($data[$champ])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'erreur' => "Champ manquant: $champ"]);
                    exit;
                }
            }

            $stmt = $pdo->prepare("SELECT COUNT(*) AS conflits FROM cours
                                   WHERE id_moniteur = ? AND date_ = ? AND heure_debut = ? AND statut != 'annulé'");
            $stmt->execute([(int)$data['id_moniteur'], $data['date_'], $data['heure_debut']]);
            if ($stmt->fetch()['conflits'] > 0) {
                http_response_code(409);
                echo json_encode(['success' => false, 'erreur' => 'Le moniteur a déjà un cours sur ce créneau']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO cours (id_moniteur, date_, heure_debut, duree, capacite_max, statut)
                                   VALUES (?, ?, ?, ?, ?, 'programmé')");
            $stmt->execute([(int)$data['id_moniteur'], $data['date_'], $data['heure_debut'], (int)$data['duree'], (int)$data['capacite_max']]);

            http_response_code(201);
            echo json_encode(['success' => true, 'message' => 'Cours créé', 'id_cours' => (int)$pdo->lastInsertId()]);
            exit;
        }

        // POST /cours/{id}/reservations → réserver un cours
        if ($id !== null && $ressource === 'reserver') {

            $stmt = $pdo->prepare("SELECT c.id_cours, c.capacite_max, COUNT(rc.id_reservation) AS places_occupees
                                   FROM cours c
                                   LEFT JOIN reservation_cours rc ON c.id_cours = rc.id_cours
                                       AND rc.statut_presence IN ('confirmé', 'présent')
                                   WHERE c.id_cours = ? AND c.statut = 'programmé'
                                   GROUP BY c.id_cours");
            $stmt->execute([(int)$id]);
            $cours = $stmt->fetch();

            if (!$cours) {
                http_response_code(404);
                echo json_encode(['success' => false, 'erreur' => 'Cours non trouvé ou non disponible']);
                exit;
            }

            if ($cours['places_occupees'] >= $cours['capacite_max']) {
                http_response_code(409);
                echo json_encode(['success' => false, 'erreur' => 'Cours complet']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT COUNT(*) AS inscriptions FROM reservation_cours
                                   WHERE id_utilisateur = ? AND id_cours = ? AND statut_presence != 'annulé'");
            $stmt->execute([(int)$payload['id_utilisateur'], (int)$id]);
            if ($stmt->fetch()['inscriptions'] > 0) {
                http_response_code(409);
                echo json_encode(['success' => false, 'erreur' => 'Déjà inscrit à ce cours']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO reservation_cours (id_cours, id_utilisateur, date_reservation, statut_presence)
                                   VALUES (?, ?, NOW(), 'confirmé')");
            $stmt->execute([(int)$id, (int)$payload['id_utilisateur']]);

            http_response_code(201);
            echo json_encode(['success' => true, 'message' => 'Inscription confirmée', 'id_reservation' => (int)$pdo->lastInsertId()]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'erreur' => 'Paramètre manquant']);
        exit;
    }

    // =====================================================
    // PUT
    // =====================================================
    if ($method === 'PUT') {

        $payload = verifier_token();

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'erreur' => 'ID cours requis dans l\'URL']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'erreur' => 'JSON invalide']);
            exit;
        }

        // PUT /cours/{id}/reservations/{uid} → marquer présence
        if ($ressource === 'presence' && $id_utilisateur !== null) {

            if (!in_array($payload['role'], ['admin', 'moniteur'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'erreur' => 'Accès réservé au moniteur ou admin']);
                exit;
            }

            if (empty($data['statut_presence'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'erreur' => 'Champ statut_presence requis']);
                exit;
            }

            $statuts_valides = ['confirmé', 'annulé', 'présent', 'absent'];
            if (!in_array($data['statut_presence'], $statuts_valides)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'erreur' => 'Statut invalide']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE reservation_cours SET statut_presence = ? WHERE id_utilisateur = ? AND id_cours = ?");
            $stmt->execute([$data['statut_presence'], (int)$id_utilisateur, (int)$id]);

            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Présence mise à jour']);
            exit;
        }

        // PUT /cours/{id} → modifier statut du cours (admin)
        if ($payload['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'erreur' => 'Accès réservé à l\'admin']);
            exit;
        }

        $statuts_valides = ['programmé', 'en cours', 'terminé', 'annulé'];
        if (empty($data['statut']) || !in_array($data['statut'], $statuts_valides)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'erreur' => 'Statut invalide']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE cours SET statut = ? WHERE id_cours = ?");
        $stmt->execute([$data['statut'], (int)$id]);

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Statut du cours mis à jour']);
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