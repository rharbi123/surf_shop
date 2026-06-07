<?php
// =====================================================
// API/PLANCHES.PHP - Gestion des planches
// Version professionnelle
// =====================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gestion CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// =====================================================
// FONCTION : Vérification du token Bearer
// =====================================================
function verifier_token(): void {
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (!$auth || !str_starts_with($auth, 'Bearer ')) {
        http_response_code(401);
        echo json_encode(['success' => false, 'erreur' => 'Token manquant ou invalide']);
        exit;
    }

    $token = substr($auth, 7);
    $token_valide = $_ENV['API_TOKEN'] ?? 'surfshop_secret_token';

    if ($token !== $token_valide) {
        http_response_code(401);
        echo json_encode(['success' => false, 'erreur' => 'Token invalide']);
        exit;
    }
}

// Charger la connexion
$pdo = require(__DIR__ . '/../config.php');

if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo json_encode(['success' => false, 'erreur' => 'Connexion PDO non valide']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;
$niveau = $_GET['niveau'] ?? null;
$prix_min = $_GET['prix_min'] ?? null;
$prix_max = $_GET['prix_max'] ?? null;

try {

    // =====================================================
    // GET - Récupérer les planches (public)
    // =====================================================
    if ($method === 'GET') {

        // Une planche spécifique (R2)
        if ($id) {
            $sql = "SELECT id_planche, nom, marque, prix_achat, prix_location_jour, shape, 
                           volume, niveau, taille_vagues, nb_derives, boitiers, conception, 
                           description, photo, stock
                    FROM planche
                    WHERE id_planche = ?";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([(int)$id]);
            $planche = $stmt->fetch();

            if (!$planche) {
                http_response_code(404);
                echo json_encode(['success' => false, 'erreur' => 'Planche non trouvée']);
                exit;
            }

            if ($planche['volume']) {
                $planche['volume'] = json_decode($planche['volume'], true);
            }

            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $planche]);
            exit;
        }

        // Toutes les planches avec filtres (R1, R3, R4)
        $sql = "SELECT id_planche, nom, marque, prix_achat, prix_location_jour, shape, volume, niveau, 
                       taille_vagues, photo, stock
                FROM planche
                WHERE stock > 0";

        $params = [];

        if ($niveau) {
            $sql .= " AND niveau = ?";
            $params[] = $niveau;
        }

        if ($prix_min) {
            $sql .= " AND prix_achat >= ?";
            $params[] = (float)$prix_min;
        }

        if ($prix_max) {
            $sql .= " AND prix_achat <= ?";
            $params[] = (float)$prix_max;
        }

        $sql .= " ORDER BY prix_achat ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $planches = $stmt->fetchAll();

        foreach ($planches as &$planche) {
            if ($planche['volume']) {
                $planche['volume'] = json_decode($planche['volume'], true);
            }
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'count' => count($planches),
            'data' => $planches
        ]);
        exit;
    }

    // =====================================================
    // POST - Créer une planche (protégé)
    // =====================================================
    elseif ($method === 'POST') {

        verifier_token();

        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'erreur' => 'JSON invalide']);
            exit;
        }

        $champs_obliges = ['nom', 'marque', 'prix_achat', 'shape', 'niveau'];
        foreach ($champs_obliges as $champ) {
            if (!isset($data[$champ])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'erreur' => "Champ manquant: $champ"]);
                exit;
            }
        }

        $volume = isset($data['volume']) && is_array($data['volume'])
                    ? json_encode($data['volume'])
                    : null;

        $sql = "INSERT INTO planche 
                (nom, marque, prix_achat, prix_location_jour, shape, volume, 
                 niveau, taille_vagues, nb_derives, boitiers, conception, description, photo, stock)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['nom'],
            $data['marque'],
            (float)$data['prix_achat'],
            isset($data['prix_location_jour']) ? (float)$data['prix_location_jour'] : null,
            $data['shape'],
            $volume,
            $data['niveau'],
            $data['taille_vagues'] ?? null,
            isset($data['nb_derives']) ? (int)$data['nb_derives'] : null,
            $data['boitiers'] ?? null,
            $data['conception'] ?? null,
            $data['description'] ?? null,
            $data['photo'] ?? null,
            isset($data['stock']) ? (int)$data['stock'] : 0
        ]);

        $id_planche = $pdo->lastInsertId();

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Planche créée',
            'id_planche' => (int)$id_planche
        ]);
        exit;
    }

    // =====================================================
    // Méthode non supportée
    // =====================================================
    else {
        http_response_code(405);
        echo json_encode(['success' => false, 'erreur' => 'Méthode HTTP non supportée']);
        exit;
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'erreur' => 'Erreur base de données'
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'erreur' => 'Erreur serveur'
    ]);
    exit;
}
?>