<?php
// =====================================================
// API/COMMANDES.PHP - Gestion des commandes
// -----------------------------------------------------
// Routes gérées par index.php :
//   GET    /commandes/{id}                  → détail
//   GET    /commandes?statut=en_attente     → liste filtrée (admin)
//   GET    /utilisateurs/{id}/commandes     → historique utilisateur
//   POST   /commandes                       → créer
//   PUT    /commandes/{id}                  → modifier statut
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
$id             = $_GET['id']             ?? null; // /commandes/{id}
$id_utilisateur = $_GET['id_utilisateur'] ?? null; // /utilisateurs/{id}/commandes
$statut         = $_GET['statut']         ?? null; // ?statut=en_attente

try {

    // =====================================================
    // GET
    // =====================================================
    if ($method === 'GET') {

        $payload = verifier_token();

        // GET /utilisateurs/{id}/commandes → historique utilisateur
        if ($id_utilisateur !== null) {

            if ($payload['role'] === 'client' && (int)$id_utilisateur !== (int)$payload['id_utilisateur']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'erreur' => 'Accès refusé']);
                exit;
            }

            $sql = "SELECT c.id_commande, c.date_commande, c.montant_total, c.statut,
                           GROUP_CONCAT(p.nom SEPARATOR ', ') AS planches
                    FROM commande c
                    LEFT JOIN commande_planche cp ON c.id_commande = cp.id_commande
                    LEFT JOIN planche p ON cp.id_planche = p.id_planche
                    WHERE c.id_utilisateur = ?
                    GROUP BY c.id_commande
                    ORDER BY c.date_commande DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([(int)$id_utilisateur]);

            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            exit;
        }

        // GET /commandes/{id} → détail d'une commande
        if ($id !== null) {

            $stmt = $pdo->prepare("SELECT id_utilisateur FROM commande WHERE id_commande = ?");
            $stmt->execute([(int)$id]);
            $commande = $stmt->fetch();

            if (!$commande) {
                http_response_code(404);
                echo json_encode(['success' => false, 'erreur' => 'Commande non trouvée']);
                exit;
            }

            if ($payload['role'] === 'client' && (int)$commande['id_utilisateur'] !== (int)$payload['id_utilisateur']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'erreur' => 'Accès refusé']);
                exit;
            }

            $sql = "SELECT c.id_commande, c.date_commande, c.montant_total, c.statut,
                           p.nom AS planche_nom, cp.quantite, cp.prix_unitaire,
                           pers.nom AS perso_nom, pers.prix_supplement, cpp.valeur_choisie
                    FROM commande c
                    LEFT JOIN commande_planche cp ON c.id_commande = cp.id_commande
                    LEFT JOIN planche p ON cp.id_planche = p.id_planche
                    LEFT JOIN commande_planche_perso cpp ON cp.id_commande_planche = cpp.id_commande_planche
                    LEFT JOIN personnalisation pers ON cpp.id_personnalisation = pers.id_personnalisation
                    WHERE c.id_commande = ?";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([(int)$id]);

            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            exit;
        }

        // GET /commandes?statut=en_attente → liste filtrée (admin)
        if ($statut !== null) {

            if ($payload['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'erreur' => 'Accès réservé à l\'admin']);
                exit;
            }

            $sql = "SELECT c.id_commande, c.date_commande, c.montant_total, u.email
                    FROM commande c
                    JOIN utilisateur u ON c.id_utilisateur = u.id_utilisateur
                    WHERE c.statut = ?
                    ORDER BY c.date_commande ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$statut]);

            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'erreur' => 'Paramètre manquant']);
        exit;
    }

    // =====================================================
    // POST /commandes → créer une commande
    // =====================================================
    if ($method === 'POST') {

        $payload = verifier_token();
        $data    = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'erreur' => 'JSON invalide']);
            exit;
        }

        if (empty($data['planches']) || !is_array($data['planches'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'erreur' => 'Champ planches requis (tableau)']);
            exit;
        }

        $montant_total = 0;

        foreach ($data['planches'] as $item) {
            if (empty($item['id_planche']) || empty($item['quantite'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'erreur' => 'Chaque planche doit avoir id_planche et quantite']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT prix_achat, stock FROM planche WHERE id_planche = ?");
            $stmt->execute([(int)$item['id_planche']]);
            $planche = $stmt->fetch();

            if (!$planche) {
                http_response_code(404);
                echo json_encode(['success' => false, 'erreur' => "Planche {$item['id_planche']} non trouvée"]);
                exit;
            }

            if ($planche['stock'] < $item['quantite']) {
                http_response_code(409);
                echo json_encode(['success' => false, 'erreur' => "Stock insuffisant pour la planche {$item['id_planche']}"]);
                exit;
            }

            $supplement = 0;
            if (!empty($item['personnalisations']) && is_array($item['personnalisations'])) {
                foreach ($item['personnalisations'] as $id_perso) {
                    $stmt2 = $pdo->prepare("SELECT prix_supplement FROM personnalisation WHERE id_personnalisation = ? AND id_planche = ?");
                    $stmt2->execute([(int)$id_perso, (int)$item['id_planche']]);
                    $perso = $stmt2->fetch();
                    if ($perso) $supplement += $perso['prix_supplement'];
                }
            }

            $montant_total += ($planche['prix_achat'] + $supplement) * $item['quantite'];
        }

        $stmt = $pdo->prepare("INSERT INTO commande (id_utilisateur, montant_total, statut) VALUES (?, ?, 'en attente')");
        $stmt->execute([(int)$payload['id_utilisateur'], round($montant_total, 2)]);
        $id_commande = (int)$pdo->lastInsertId();

        foreach ($data['planches'] as $item) {
            $stmt = $pdo->prepare("SELECT prix_achat FROM planche WHERE id_planche = ?");
            $stmt->execute([(int)$item['id_planche']]);
            $planche = $stmt->fetch();

            $stmt = $pdo->prepare("INSERT INTO commande_planche (id_commande, id_planche, quantite, prix_unitaire) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id_commande, (int)$item['id_planche'], (int)$item['quantite'], $planche['prix_achat']]);
            $id_commande_planche = (int)$pdo->lastInsertId();

            if (!empty($item['personnalisations']) && is_array($item['personnalisations'])) {
                foreach ($item['personnalisations'] as $id_perso) {
                    $stmt2 = $pdo->prepare("INSERT INTO commande_planche_perso (id_commande_planche, id_personnalisation) VALUES (?, ?)");
                    $stmt2->execute([$id_commande_planche, (int)$id_perso]);
                }
            }

            $stmt = $pdo->prepare("UPDATE planche SET stock = stock - ? WHERE id_planche = ?");
            $stmt->execute([(int)$item['quantite'], (int)$item['id_planche']]);
        }

        http_response_code(201);
        echo json_encode([
            'success'       => true,
            'message'       => 'Commande créée, en attente de paiement',
            'id_commande'   => $id_commande,
            'montant_total' => round($montant_total, 2)
        ]);
        exit;
    }

    // =====================================================
    // PUT /commandes/{id} → modifier statut (admin)
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
            echo json_encode(['success' => false, 'erreur' => 'ID commande requis dans l\'URL']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['statut'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'erreur' => 'Champ statut requis']);
            exit;
        }

        $statuts_valides = ['en attente', 'confirmée', 'expédiée', 'livrée', 'annulée'];
        if (!in_array($data['statut'], $statuts_valides)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'erreur' => 'Statut invalide']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id_commande FROM commande WHERE id_commande = ?");
        $stmt->execute([(int)$id]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'erreur' => 'Commande non trouvée']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE commande SET statut = ? WHERE id_commande = ?");
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