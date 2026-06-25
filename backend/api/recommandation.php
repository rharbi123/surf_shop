<?php
// =====================================================
// API/RECOMMANDATION.PHP - Pont PHP → Flask IA
// =====================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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

$pdo = require(__DIR__ . '/../config.php');

if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo json_encode(['success' => false, 'erreur' => 'Connexion PDO non valide']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'erreur' => 'Méthode HTTP non supportée']);
    exit;
}

$payload = verifier_token();

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'erreur' => 'JSON invalide']);
    exit;
}

if (empty($data['niveau'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'erreur' => 'niveau requis']);
    exit;
}

$type = $data['type'] ?? 'achat';

if ($type !== 'location' && empty($data['budget'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'erreur' => 'budget requis pour un achat']);
    exit;
}

$niveau        = $data['niveau'];
$budget        = isset($data['budget']) ? (float) $data['budget'] : 0;
$taille_vagues = 'Vagues moyennes';
$vent_vitesse  = 0;
$date_meteo    = !empty($data['date_selectionnee']) ? $data['date_selectionnee'] : date('Y-m-d');
$conditions    = '';
$label_date    = '';

// =====================================================
// Si location → récupérer la météo pour la date sélectionnée
// =====================================================
if ($type === 'location' && !empty($data['spot'])) {

    $sql = "SELECT m.vent_vitesse, m.conditions, m.temperature
            FROM meteo m
            JOIN spot s ON m.id_spot = s.id_spot
            WHERE s.nom = ?
            AND DATE(m.date_heure) = ?
            ORDER BY m.date_heure ASC
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$data['spot'], $date_meteo]);
    $meteo = $stmt->fetch();

    if ($meteo) {
        $vent_vitesse = (float) $meteo['vent_vitesse'];
        $conditions   = $meteo['conditions'];

        if ($vent_vitesse < 5) {
            $taille_vagues = 'Petites vagues';
        } elseif ($vent_vitesse < 15) {
            $taille_vagues = 'Vagues moyennes';
        } else {
            $taille_vagues = 'Grandes vagues';
        }
    }

    // Formater le label de date en français — sans IntlDateFormatter
    $today    = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));

    if ($date_meteo === $today) {
        $label_date = "aujourd'hui";
    } elseif ($date_meteo === $tomorrow) {
        $label_date = "demain";
    } else {
        $jours_fr = ['dimanche','lundi','mardi','mercredi','jeudi','vendredi','samedi'];
        $mois_fr  = ['','janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
        $ts       = strtotime($date_meteo);
        $label_date = "le " . $jours_fr[date('w', $ts)] . " " . date('j', $ts) . " " . $mois_fr[(int)date('n', $ts)];
    }
}

// =====================================================
// Appeler l'API Flask
// =====================================================
$flask_url = 'http://localhost:5000/recommander';

$headers_flask = getallheaders();
$token = substr($headers_flask['Authorization'] ?? $headers_flask['authorization'] ?? '', 7);

$payload_flask = json_encode([
    'niveau'        => $niveau,
    'budget'        => $budget,
    'taille_vagues' => $taille_vagues,
    'vent_vitesse'  => $vent_vitesse,
    'type'          => $type,
    'spot'          => $data['spot'] ?? null,
    'date_label'    => $label_date,
    'conditions'    => $conditions
]);

$ch = curl_init($flask_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_flask);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);

$response   = curl_exec($ch);
$http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'erreur' => 'Impossible de contacter l\'API IA']);
    exit;
}

http_response_code($http_code);
echo $response;
?>