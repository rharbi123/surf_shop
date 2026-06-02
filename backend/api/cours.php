<?php
// =====================================================
// API/COURS.PHP - Gestion des cours de surf
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

// =====================================================
// FONCTION JWT
// =====================================================

function verifier_token(): array {
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (!$auth || !str_starts_with($auth, 'Bearer ')) {
        http_response_code(401);
        echo json_encode(['success' => false, 'erreur' => 'Token manquant']);
        exit;
    }

    $token = substr($auth, 7);
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

// =====================================================
// CONNEXION PDO
// =====================================================

$pdo = require(__DIR__ . '/../config.php');

if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo json_encode(['success' => false, 'erreur' => 'Connexion PDO non valide']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$id     = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

try {

    // =====================================================
    // GET
    // =====================================================
    if ($method === 'GET') {

        // --- Cours disponibles (public) (R21) ---
        if ($action === 'disponibles' || !$action) {

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
            echo json_encode([
                'success' => true,
                'count'   => $stmt->rowCount(),
                'data'    => $stmt->fetchAll()
            ]);
            exit;
        }

        $payload = verifier_token();

        // --- Inscrits à un cours (moniteur ou admin) (R25) ---
        if ($action === 'inscrits') {

            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'erreur' => 'Paramètre id requis']);
                exit;
            }

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
            echo json_encode([
                'success' => true,
                'data'    => $stmt->fetchAll()
            ]);
            exit;
        }

        // --- Planning d'un moniteur (moniteur ou admin) (R24) ---
        if ($action === 'planning') {

            $id_moniteur = $_GET['id_moniteur'] ?? null;

            if (!$id_moniteur) {
                http_response_code(400);
                echo json_encode(['success' => false, 'erreur' => 'Paramètre id_moniteur requis']);
                exit;
            }

            // Un moniteur ne peut voir que son propre planning
            if ($payload['role'] === 'moniteur') {
                $stmt = $pdo->prepare("SELECT id_moniteur FROM moniteur WHERE id_utilisateur = ?");
                $stmt->execute([(int)$payload['id_utilisateur']]);
                $moniteur = $stmt->fetch();
                if (!$moniteur || (int)$moniteur['id_moniteur'] !== (int)$id_moniteur) {
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
            echo json_encode([
                'success' => true,
                'data'    => $stmt->fetchAll()
            ]);
            exit;
        }

        // --- Historique des cours d'un utilisateur (R27) ---
        if ($action === 'historique') {

            $id_utilisateur = $_GET['id_utilisateur'] ?? null;

            if (!$id_utilisateur) {
                http_response_code(400);
                echo json_encode(['success' => false, 'erreur' => 'Paramètre id_utilisateur requis']);
                exit;
            }

            // Un client ne voit que son propre historique
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
            echo json_encode([
                'success' => true,
                'data'    => $stmt->fetchAll()
            ]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'erreur' => 'Action inconnue']);
        exit;
    }

    // =====================================================
    // POST
    // =====================================================
    if ($method === 'POST') {

        $payload = verifier_token();

        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'erreur' => 'JSON invalide']);
            exit;
        }

        // --- Créer un cours (admin uniquement) ---
        if ($action === 'creer') {

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

            // Vérifier que le moniteur n'a pas déjà un cours à ce créneau
            $sql_conflit = "SELECT COUNT(*) AS conflits FROM cours
                            WHERE id_moniteur = ?
                            AND date_ = ?
                            AND heure_debut = ?
                            AND statut != 'annulé'";

            $stmt = $pdo->prepare($sql_conflit);
            $stmt->execute([(int)$data['id_moniteur'], $data['date_'], $data['heure_debut']]);
            $conflit = $stmt->fetch();

            if ($conflit['conflits'] > 0) {
                http_response_code(409);
                echo json_encode(['success' => false, 'erreur' => 'Le moniteur a déjà un cours sur ce créneau']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO cours (id_moniteur, date_, heure_debut, duree, capacite_max, statut)
                                   VALUES (?, ?, ?, ?, ?, 'programmé')");

            $stmt->execute([
                (int)$data['id_moniteur'],
                $data['date_'],
                $data['heure_debut'],
                (int)$data['duree'],
                (int)$data['capacite_max']
            ]);

            http_response_code(201);
            echo json_encode([
                'success'  => true,
                'message'  => 'Cours créé',
                'id_cours' => (int)$pdo->lastInsertId()
            ]);
            exit;
        }

        // --- Réserver un cours (client connecté) (R22) ---
        if ($action === 'reserver') {

            if (empty($data['id_cours'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'erreur' => 'Champ id_cours requis']);
                exit;
            }

            // Vérifier que le cours existe et est programmé
            $stmt = $pdo->prepare("SELECT c.id_cours, c.capacite_max,
                                          COUNT(rc.id_reservation) AS places_occupees
                                   FROM cours c
                                   LEFT JOIN reservation_cours rc ON c.id_cours = rc.id_cours
                                       AND rc.statut_presence IN ('confirmé', 'présent')
                                   WHERE c.id_cours = ?
                                   AND c.statut = 'programmé'
                                   GROUP BY c.id_cours");
            $stmt->execute([(int)$data['id_cours']]);
            $cours = $stmt->fetch();

            if (!$cours) {
                http_response_code(404);
                echo json_encode(['success' => false, 'erreur' => 'Cours non trouvé ou non disponible']);
                exit;
            }

            // Vérifier qu'il reste des places
            if ($cours['places_occupees'] >= $cours['capacite_max']) {
                http_response_code(409);
                echo json_encode(['success' => false, 'erreur' => 'Cours complet']);
                exit;
            }

            // Vérifier que l'utilisateur n'est pas déjà inscrit (R23)
            $stmt = $pdo->prepare("SELECT COUNT(*) AS inscriptions FROM reservation_cours
                                   WHERE id_utilisateur = ? AND id_cours = ? AND statut_presence != 'annulé'");
            $stmt->execute([(int)$payload['id_utilisateur'], (int)$data['id_cours']]);
            $deja = $stmt->fetch();

            if ($deja['inscriptions'] > 0) {
                http_response_code(409);
                echo json_encode(['success' => false, 'erreur' => 'Déjà inscrit à ce cours']);
                exit;
            }

            // Créer la réservation (R22)
            $stmt = $pdo->prepare("INSERT INTO reservation_cours (id_cours, id_utilisateur, date_reservation, statut_presence)
                                   VALUES (?, ?, NOW(), 'confirmé')");
            $stmt->execute([(int)$data['id_cours'], (int)$payload['id_utilisateur']]);

            http_response_code(201);
            echo json_encode([
                'success'        => true,
                'message'        => 'Inscription au cours confirmée, en attente de paiement',
                'id_reservation' => (int)$pdo->lastInsertId()
            ]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'erreur' => 'Action inconnue']);
        exit;
    }

    // =====================================================
    // PUT - Marquer présence ou modifier statut (moniteur/admin)
    // =====================================================
    if ($method === 'PUT') {

        $payload = verifier_token();

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'erreur' => 'Paramètre id requis']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'erreur' => 'JSON invalide']);
            exit;
        }

        // --- Marquer présence d'un participant (moniteur/admin) (R26) ---
        if ($action === 'presence') {

            if (!in_array($payload['role'], ['admin', 'moniteur'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'erreur' => 'Accès réservé au moniteur ou admin']);
                exit;
            }

            if (empty($data['id_utilisateur']) || empty($data['statut_presence'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'erreur' => 'Champs id_utilisateur et statut_presence requis']);
                exit;
            }

            $statuts_valides = ['confirmé', 'annulé', 'présent', 'absent'];
            if (!in_array($data['statut_presence'], $statuts_valides)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'erreur' => 'Statut invalide']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE reservation_cours SET statut_presence = ?
                                   WHERE id_utilisateur = ? AND id_cours = ?");
            $stmt->execute([$data['statut_presence'], (int)$data['id_utilisateur'], (int)$id]);

            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Présence mise à jour']);
            exit;
        }

        // --- Modifier statut du cours (admin) ---
        if ($action === 'statut') {

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

        http_response_code(400);
        echo json_encode(['success' => false, 'erreur' => 'Action inconnue']);
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
?>