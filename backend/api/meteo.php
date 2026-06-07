<?php
// =====================================================
// API/METEO.PHP - Météo en temps réel
// -----------------------------------------------------
// Routes gérées par index.php :
//   GET    /spots                      → liste des spots
//   GET    /spots/{nom}/meteo          → météo actuelle
//   GET    /spots/{nom}/previsions     → prévisions 7 jours
//   GET    /spots/{nom}/creneaux       → meilleurs créneaux
// =====================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$pdo = require(__DIR__ . '/../config.php');

if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo json_encode(['success' => false, 'erreur' => 'Connexion PDO non valide']);
    exit;
}

$method    = $_SERVER['REQUEST_METHOD'];
$ressource = $_GET['ressource'] ?? null; // 'spots', 'meteo', 'previsions', 'creneaux'
$spot      = $_GET['spot']      ?? null; // nom du spot

try {

    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'erreur' => 'Méthode HTTP non supportée']);
        exit;
    }

    // GET /spots → liste des spots
    if ($ressource === 'spots') {
        $stmt = $pdo->prepare("SELECT id_spot, nom, ville FROM spot ORDER BY nom ASC");
        $stmt->execute();
        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    // Toutes les autres routes nécessitent un spot
    if (!$spot) {
        http_response_code(400);
        echo json_encode(['success' => false, 'erreur' => 'Spot requis dans l\'URL (/spots/{nom}/meteo)']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id_spot, nom, ville FROM spot WHERE nom = ?");
    $stmt->execute([$spot]);
    $spot_data = $stmt->fetch();

    if (!$spot_data) {
        http_response_code(404);
        echo json_encode(['success' => false, 'erreur' => 'Spot non trouvé']);
        exit;
    }

    // GET /spots/{nom}/meteo → météo actuelle
    if ($ressource === 'meteo') {

        $sql = "SELECT MIN(m.id_meteo) AS id_meteo, m.date_heure, m.temperature, m.ressenti,
                       m.humidite, m.vent_vitesse, m.vent_direction, m.conditions, m.icone,
                       s.nom AS spot_nom, s.ville
                FROM meteo m
                JOIN spot s ON m.id_spot = s.id_spot
                WHERE s.nom = ?
                AND DATE(m.date_heure) = CURDATE()
                GROUP BY DATE_FORMAT(m.date_heure, '%H:%i')
                ORDER BY m.date_heure ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$spot]);
        $previsions = $stmt->fetchAll();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'spot'    => $spot_data,
            'date'    => date('Y-m-d'),
            'count'   => count($previsions),
            'data'    => $previsions
        ]);
        exit;
    }

    // GET /spots/{nom}/previsions → prévisions 7 jours
    if ($ressource === 'previsions') {

        $sql = "SELECT DATE(m.date_heure) AS jour,
                       MIN(m.temperature) AS temp_min,
                       MAX(m.temperature) AS temp_max,
                       AVG(m.vent_vitesse) AS vent_moyen,
                       GROUP_CONCAT(DISTINCT m.conditions SEPARATOR ', ') AS conditions
                FROM meteo m
                JOIN spot s ON m.id_spot = s.id_spot
                WHERE s.nom = ?
                AND m.date_heure BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(m.date_heure)
                ORDER BY jour ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$spot]);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'spot'    => $spot_data,
            'data'    => $stmt->fetchAll()
        ]);
        exit;
    }

    // GET /spots/{nom}/creneaux → meilleurs créneaux
    if ($ressource === 'creneaux') {

        $sql = "SELECT m.date_heure, m.temperature, m.vent_vitesse, m.conditions,
                       s.nom AS spot_nom
                FROM meteo m
                JOIN spot s ON m.id_spot = s.id_spot
                WHERE s.nom = ?
                AND m.vent_vitesse BETWEEN 5 AND 20
                AND m.date_heure BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
                ORDER BY m.vent_vitesse ASC, m.date_heure ASC
                LIMIT 10";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$spot]);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'spot'    => $spot_data,
            'data'    => $stmt->fetchAll()
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'erreur' => 'Route inconnue. Utilisez /spots, /spots/{nom}/meteo, /spots/{nom}/previsions, /spots/{nom}/creneaux']);
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