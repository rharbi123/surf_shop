<?php
// =====================================================
// API/UTILISATEURS.PHP - Gestion des utilisateurs
// Rôles : client, moniteur, admin
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
// FONCTIONS JWT
// =====================================================

function generer_token(int $id_utilisateur, string $role): string {
    $secret = $_ENV['JWT_SECRET'] ?? 'surfshop_jwt_secret';
    $payload = [
        'iat'            => time(),
        'exp'            => time() + 3600 * 24,
        'id_utilisateur' => $id_utilisateur,
        'role'           => $role
    ];
    return JWT::encode($payload, $secret, 'HS256');
}

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
        http_response_code(500);
        echo json_encode(['success' => false, 'erreur' => $e->getMessage()]);
        exit;
    }
}

function verifier_admin(): array {
    $payload = verifier_token();
    if ($payload['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'erreur' => 'Accès réservé à l\'admin']);
        exit;
    }
    return $payload;
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
$action = $_GET['action'] ?? null;
$id     = $_GET['id'] ?? null;

try {

    // =====================================================
    // POST
    // =====================================================
    if ($method === 'POST') {

        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'erreur' => 'JSON invalide']);
            exit;
        }

        // -------------------------------------------------
        // INSCRIPTION CLIENT (public)
        // -------------------------------------------------
        if ($action === 'inscription') {

            $champs_obliges = ['nom', 'prenom', 'email', 'mot_de_passe'];
            foreach ($champs_obliges as $champ) {
                if (empty($data[$champ])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'erreur' => "Champ manquant: $champ"]);
                    exit;
                }
            }

            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'erreur' => 'Email invalide']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateur WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['success' => false, 'erreur' => 'Email déjà utilisé']);
                exit;
            }

            $mot_de_passe_hash = password_hash($data['mot_de_passe'], PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("INSERT INTO utilisateur 
                (nom, prenom, email, mot_de_passe, role, niveau, consentement_rgpd, date_consentement)
                VALUES (?, ?, ?, ?, 'client', ?, ?, NOW())");

            $stmt->execute([
                $data['nom'],
                $data['prenom'],
                $data['email'],
                $mot_de_passe_hash,
                $data['niveau'] ?? null,
                isset($data['consentement_rgpd']) && $data['consentement_rgpd'] ? 1 : 0
            ]);

            $id_nouveau = (int) $pdo->lastInsertId();
            $token = generer_token($id_nouveau, 'client');

            http_response_code(201);
            echo json_encode([
                'success'        => true,
                'message'        => 'Inscription réussie',
                'id_utilisateur' => $id_nouveau,
                'token'          => $token
            ]);
            exit;
        }

        // -------------------------------------------------
        // CONNEXION (public - tous les rôles)
        // -------------------------------------------------
        if ($action === 'connexion') {

            if (empty($data['email']) || empty($data['mot_de_passe'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'erreur' => 'Email et mot de passe requis']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT id_utilisateur, nom, prenom, email, mot_de_passe, role, niveau 
                                   FROM utilisateur WHERE email = ?");
            $stmt->execute([$data['email']]);
            $utilisateur = $stmt->fetch();

            if (!$utilisateur || !password_verify($data['mot_de_passe'], $utilisateur['mot_de_passe'])) {
                http_response_code(401);
                echo json_encode(['success' => false, 'erreur' => 'Email ou mot de passe incorrect']);
                exit;
            }

            // Si moniteur, récupérer son diplôme
            $extra = [];
            if ($utilisateur['role'] === 'moniteur') {
                $stmt2 = $pdo->prepare("SELECT id_moniteur, diplome FROM moniteur WHERE id_utilisateur = ?");
                $stmt2->execute([$utilisateur['id_utilisateur']]);
                $moniteur = $stmt2->fetch();
                if ($moniteur) {
                    $extra['id_moniteur'] = $moniteur['id_moniteur'];
                    $extra['diplome']     = $moniteur['diplome'];
                }
            }

            $token = generer_token($utilisateur['id_utilisateur'], $utilisateur['role']);
            unset($utilisateur['mot_de_passe']);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Connexion réussie',
                'data'    => array_merge($utilisateur, $extra),
                'token'   => $token
            ]);
            exit;
        }

        // -------------------------------------------------
        // CRÉER UN MONITEUR (admin uniquement)
        // -------------------------------------------------
        if ($action === 'creer_moniteur') {

            verifier_admin();

            $champs_obliges = ['nom', 'prenom', 'email', 'mot_de_passe'];
            foreach ($champs_obliges as $champ) {
                if (empty($data[$champ])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'erreur' => "Champ manquant: $champ"]);
                    exit;
                }
            }

            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'erreur' => 'Email invalide']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateur WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['success' => false, 'erreur' => 'Email déjà utilisé']);
                exit;
            }

            $mot_de_passe_hash = password_hash($data['mot_de_passe'], PASSWORD_BCRYPT);

            // Créer l'utilisateur avec rôle moniteur
            $stmt = $pdo->prepare("INSERT INTO utilisateur 
                (nom, prenom, email, mot_de_passe, role, consentement_rgpd, date_consentement)
                VALUES (?, ?, ?, ?, 'moniteur', 1, NOW())");

            $stmt->execute([
                $data['nom'],
                $data['prenom'],
                $data['email'],
                $mot_de_passe_hash
            ]);

            $id_utilisateur = (int) $pdo->lastInsertId();

            // Créer l'entrée dans la table moniteur
            $stmt = $pdo->prepare("INSERT INTO moniteur (id_utilisateur, diplome) VALUES (?, ?)");
            $stmt->execute([
                $id_utilisateur,
                $data['diplome'] ?? null
            ]);

            $id_moniteur = (int) $pdo->lastInsertId();

            http_response_code(201);
            echo json_encode([
                'success'        => true,
                'message'        => 'Moniteur créé avec succès',
                'id_utilisateur' => $id_utilisateur,
                'id_moniteur'    => $id_moniteur
            ]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'erreur' => 'Action inconnue']);
        exit;
    }

    // =====================================================
    // GET - Profil (protégé)
    // =====================================================
    if ($method === 'GET') {

        $payload = verifier_token();

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'erreur' => 'Paramètre id requis']);
            exit;
        }

        // Un utilisateur ne voit que son propre profil, sauf admin
        if ($payload['role'] !== 'admin' && (int)$id !== (int)$payload['id_utilisateur']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'erreur' => 'Accès refusé']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id_utilisateur, nom, prenom, email, role, niveau, date_inscription 
                               FROM utilisateur WHERE id_utilisateur = ?");
        $stmt->execute([(int)$id]);
        $utilisateur = $stmt->fetch();

        if (!$utilisateur) {
            http_response_code(404);
            echo json_encode(['success' => false, 'erreur' => 'Utilisateur non trouvé']);
            exit;
        }

        // Si moniteur, ajouter les infos de la table moniteur
        if ($utilisateur['role'] === 'moniteur') {
            $stmt2 = $pdo->prepare("SELECT id_moniteur, diplome FROM moniteur WHERE id_utilisateur = ?");
            $stmt2->execute([(int)$id]);
            $moniteur = $stmt2->fetch();
            if ($moniteur) {
                $utilisateur['id_moniteur'] = $moniteur['id_moniteur'];
                $utilisateur['diplome']     = $moniteur['diplome'];
            }
        }

        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $utilisateur]);
        exit;
    }

    // =====================================================
    // PUT - Modifier profil (protégé)
    // =====================================================
    if ($method === 'PUT') {

        $payload = verifier_token();

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'erreur' => 'Paramètre id requis']);
            exit;
        }

        if ($payload['role'] !== 'admin' && (int)$id !== (int)$payload['id_utilisateur']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'erreur' => 'Accès refusé']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'erreur' => 'JSON invalide']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id_utilisateur, role FROM utilisateur WHERE id_utilisateur = ?");
        $stmt->execute([(int)$id]);
        $utilisateur = $stmt->fetch();

        if (!$utilisateur) {
            http_response_code(404);
            echo json_encode(['success' => false, 'erreur' => 'Utilisateur non trouvé']);
            exit;
        }

        // Mettre à jour les infos de base
        $stmt = $pdo->prepare("UPDATE utilisateur SET nom = ?, prenom = ?, niveau = ? WHERE id_utilisateur = ?");
        $stmt->execute([
            $data['nom']    ?? null,
            $data['prenom'] ?? null,
            $data['niveau'] ?? null,
            (int)$id
        ]);

        // Si moniteur, mettre à jour le diplôme
        if ($utilisateur['role'] === 'moniteur' && isset($data['diplome'])) {
            $stmt = $pdo->prepare("UPDATE moniteur SET diplome = ? WHERE id_utilisateur = ?");
            $stmt->execute([$data['diplome'], (int)$id]);
        }

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Profil mis à jour']);
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