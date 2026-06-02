<?php
// =====================================================
// API/PAIEMENTS.PHP - Gestion des paiements
// Types : commande, location, cours
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

        $payload = verifier_token();

        // --- Historique paiements d'un utilisateur (R44) ---
        if ($action === 'historique') {

            $id_utilisateur = $_GET['id_utilisateur'] ?? null;

            if (!$id_utilisateur) {
                http_response_code(400);
                echo json_encode(['success' => false, 'erreur' => 'Paramètre id_utilisateur requis']);
                exit;
            }

            // Un client ne voit que ses propres paiements
            if ($payload['role'] === 'client' && (int)$id_utilisateur !== (int)$payload['id_utilisateur']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'erreur' => 'Accès refusé']);
                exit;
            }

            $sql = "SELECT p.id_paiement, p.montant, p.statut, p.type, p.date_paiement,
                           COALESCE(c.id_commande, l.id_location, rc.id_reservation) AS reference
                    FROM paiement p
                    LEFT JOIN commande c ON p.id_commande = c.id_commande
                    LEFT JOIN location l ON p.id_location = l.id_location
                    LEFT JOIN reservation_cours rc ON p.id_reservation = rc.id_reservation
                    WHERE p.id_utilisateur = ?
                    ORDER BY p.date_paiement DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([(int)$id_utilisateur]);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data'    => $stmt->fetchAll()
            ]);
            exit;
        }

        // --- Paiements en attente (admin) (R45) ---
        if ($action === 'en_attente') {

            if ($payload['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'erreur' => 'Accès réservé à l\'admin']);
                exit;
            }

            $sql = "SELECT p.id_paiement, p.montant, p.type, u.email,
                           COALESCE(c.date_commande, l.date_debut, rc.date_reservation) AS date_reference
                    FROM paiement p
                    LEFT JOIN commande c ON p.id_commande = c.id_commande
                    LEFT JOIN location l ON p.id_location = l.id_location
                    LEFT JOIN reservation_cours rc ON p.id_reservation = rc.id_reservation
                    LEFT JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
                    WHERE p.statut = 'en attente'
                    ORDER BY date_reference ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute();

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data'    => $stmt->fetchAll()
            ]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'erreur' => 'Action inconnue. Actions disponibles: historique, en_attente']);
        exit;
    }

    // =====================================================
    // POST - Créer un paiement (R41, R42, R43)
    // =====================================================
    if ($method === 'POST') {

        $payload = verifier_token();

        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'erreur' => 'JSON invalide']);
            exit;
        }

        // Valider les champs obligatoires
        $champs_obliges = ['type', 'stripe_payment_id'];
        foreach ($champs_obliges as $champ) {
            if (empty($data[$champ])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'erreur' => "Champ manquant: $champ"]);
                exit;
            }
        }

        $types_valides = ['commande', 'location', 'cours'];
        if (!in_array($data['type'], $types_valides)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'erreur' => 'Type invalide. Valeurs: commande, location, cours']);
            exit;
        }

        $id_commande   = null;
        $id_location   = null;
        $id_reservation = null;
        $montant       = 0;

        // Vérifier la référence selon le type et récupérer le montant
        if ($data['type'] === 'commande') {

            if (empty($data['id_commande'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'erreur' => 'Champ id_commande requis pour type commande']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT id_commande, montant_total, id_utilisateur, statut FROM commande WHERE id_commande = ?");
            $stmt->execute([(int)$data['id_commande']]);
            $commande = $stmt->fetch();

            if (!$commande) {
                http_response_code(404);
                echo json_encode(['success' => false, 'erreur' => 'Commande non trouvée']);
                exit;
            }

            // Vérifier que la commande appartient à l'utilisateur
            if ((int)$commande['id_utilisateur'] !== (int)$payload['id_utilisateur']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'erreur' => 'Accès refusé']);
                exit;
            }

            if ($commande['statut'] !== 'en attente') {
                http_response_code(409);
                echo json_encode(['success' => false, 'erreur' => 'Commande déjà payée ou annulée']);
                exit;
            }

            $id_commande = (int)$data['id_commande'];
            $montant     = $commande['montant_total'];

        } elseif ($data['type'] === 'location') {

            if (empty($data['id_location'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'erreur' => 'Champ id_location requis pour type location']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT id_location, montant_total, id_utilisateur, statut FROM location WHERE id_location = ?");
            $stmt->execute([(int)$data['id_location']]);
            $location = $stmt->fetch();

            if (!$location) {
                http_response_code(404);
                echo json_encode(['success' => false, 'erreur' => 'Location non trouvée']);
                exit;
            }

            if ((int)$location['id_utilisateur'] !== (int)$payload['id_utilisateur']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'erreur' => 'Accès refusé']);
                exit;
            }

            if ($location['statut'] !== 'en attente') {
                http_response_code(409);
                echo json_encode(['success' => false, 'erreur' => 'Location déjà payée ou annulée']);
                exit;
            }

            $id_location = (int)$data['id_location'];
            $montant     = $location['montant_total'];

        } elseif ($data['type'] === 'cours') {

            if (empty($data['id_reservation'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'erreur' => 'Champ id_reservation requis pour type cours']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT rc.id_reservation, rc.id_utilisateur, rc.statut_presence,
                                          c.id_cours
                                   FROM reservation_cours rc
                                   JOIN cours c ON rc.id_cours = c.id_cours
                                   WHERE rc.id_reservation = ?");
            $stmt->execute([(int)$data['id_reservation']]);
            $reservation = $stmt->fetch();

            if (!$reservation) {
                http_response_code(404);
                echo json_encode(['success' => false, 'erreur' => 'Réservation non trouvée']);
                exit;
            }

            if ((int)$reservation['id_utilisateur'] !== (int)$payload['id_utilisateur']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'erreur' => 'Accès refusé']);
                exit;
            }

            $id_reservation = (int)$data['id_reservation'];
            $montant        = isset($data['montant']) ? (float)$data['montant'] : 0;
        }

        // Insérer le paiement
        $stmt = $pdo->prepare("INSERT INTO paiement 
            (montant, statut, stripe_payment_id, type, id_utilisateur, id_commande, id_location, id_reservation)
            VALUES (?, 'en attente', ?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $montant,
            $data['stripe_payment_id'],
            $data['type'],
            (int)$payload['id_utilisateur'],
            $id_commande,
            $id_location,
            $id_reservation
        ]);

        $id_paiement = (int)$pdo->lastInsertId();

        http_response_code(201);
        echo json_encode([
            'success'     => true,
            'message'     => 'Paiement enregistré en attente de confirmation',
            'id_paiement' => $id_paiement,
            'montant'     => $montant
        ]);
        exit;
    }

    // =====================================================
    // PUT - Confirmer ou refuser un paiement (R43)
    // Simule le webhook Stripe
    // =====================================================
    if ($method === 'PUT') {

        $payload = verifier_token();

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'erreur' => 'Paramètre id requis']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['statut'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'erreur' => 'Champ statut requis']);
            exit;
        }

        $statuts_valides = ['approuvé', 'refusé', 'remboursé'];
        if (!in_array($data['statut'], $statuts_valides)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'erreur' => 'Statut invalide. Valeurs: approuvé, refusé, remboursé']);
            exit;
        }

        // Récupérer le paiement
        $stmt = $pdo->prepare("SELECT * FROM paiement WHERE id_paiement = ?");
        $stmt->execute([(int)$id]);
        $paiement = $stmt->fetch();

        if (!$paiement) {
            http_response_code(404);
            echo json_encode(['success' => false, 'erreur' => 'Paiement non trouvé']);
            exit;
        }

        // Seul l'admin ou le propriétaire peut mettre à jour
        if ($payload['role'] !== 'admin' && (int)$paiement['id_utilisateur'] !== (int)$payload['id_utilisateur']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'erreur' => 'Accès refusé']);
            exit;
        }

        // Mettre à jour le statut du paiement
        $stmt = $pdo->prepare("UPDATE paiement SET statut = ? WHERE id_paiement = ?");
        $stmt->execute([$data['statut'], (int)$id]);

        // Si paiement approuvé, confirmer la commande/location/réservation
        if ($data['statut'] === 'approuvé') {

            if ($paiement['id_commande']) {
                $stmt = $pdo->prepare("UPDATE commande SET statut = 'confirmée' WHERE id_commande = ?");
                $stmt->execute([$paiement['id_commande']]);
            }

            if ($paiement['id_location']) {
                $stmt = $pdo->prepare("UPDATE location SET statut = 'confirmée' WHERE id_location = ?");
                $stmt->execute([$paiement['id_location']]);
            }

            if ($paiement['id_reservation']) {
                $stmt = $pdo->prepare("UPDATE reservation_cours SET statut_presence = 'confirmé' WHERE id_reservation = ?");
                $stmt->execute([$paiement['id_reservation']]);
            }
        }

        // Si paiement refusé, remettre le stock pour une commande
        if ($data['statut'] === 'refusé' && $paiement['id_commande']) {
            $stmt = $pdo->prepare("SELECT id_planche, quantite FROM commande_planche WHERE id_commande = ?");
            $stmt->execute([$paiement['id_commande']]);
            $lignes = $stmt->fetchAll();

            foreach ($lignes as $ligne) {
                $stmt = $pdo->prepare("UPDATE planche SET stock = stock + ? WHERE id_planche = ?");
                $stmt->execute([$ligne['quantite'], $ligne['id_planche']]);
            }
        }

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => "Paiement {$data['statut']}"]);
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