<?php
// =====================================================
// CONFIG.PHP - Connexion à la base de données
// =====================================================

require_once __DIR__ . '/vendor/autoload.php';

// Le fichier .env n'existe qu'en local (il est volontairement
// absent sur GitHub pour des raisons de sécurité). En CI, les
// variables sont déjà injectées par GitHub Actions via les secrets.
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Récupérer les paramètres
$db_host = $_ENV['DB_HOST'];
$db_port = $_ENV['DB_PORT'];
$db_name = $_ENV['DB_NAME'];
$db_user = $_ENV['DB_USER'];
$db_password = $_ENV['DB_PASSWORD'];

// Créer la connexion PDO
try {
    $pdo = new PDO(
        "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'erreur' => 'Erreur de connexion à la base de données',
        'details' => $e->getMessage()
    ]);
    exit;
}

return $pdo;
?>