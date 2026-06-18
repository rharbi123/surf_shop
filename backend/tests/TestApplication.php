<?php
// =====================================================
// TEST_APPLICATION.PHP - Tests automatisés de l'application WaveCraft
// -----------------------------------------------------
// Couvre la logique métier critique : locations, commandes,
// cours, sécurité des comptes utilisateurs.
//
// Exécution : phpunit tests/test_application.php
// =====================================================

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../vendor/autoload.php';

class TestApplication extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = require __DIR__ . '/../config.php';
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
    }

    private function creerUtilisateurDeTest(): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, role, consentement_rgpd, date_consentement)
             VALUES ('Test', 'Client', ?, 'hash_temporaire', 'client', 1, NOW())"
        );
        $stmt->execute(['client.test.' . uniqid() . '@wavecraft.fr']);
        return (int) $this->pdo->lastInsertId();
    }

    private function creerPlancheDeTest(): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO planche (nom, marque, prix_achat, prix_location_jour, shape, niveau, stock)
             VALUES ('Planche test location', 'TestBrand', 500.00, 16.00, 'Fish', 'Débutant - Intermédiaire', 5)"
        );
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    // =====================================================
    // GROUPE 1 : LOCATIONS
    // =====================================================

    public function testConflitDeDatesDetecte(): void
    {
        $id_utilisateur = $this->creerUtilisateurDeTest();
        $id_planche     = $this->creerPlancheDeTest();

        $stmt = $this->pdo->prepare(
            "INSERT INTO location (id_utilisateur, id_planche, date_debut, date_fin, montant_total, statut)
             VALUES (?, ?, '2026-07-01', '2026-07-05', 80.00, 'confirmée')"
        );
        $stmt->execute([$id_utilisateur, $id_planche]);

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS conflits FROM location
             WHERE id_planche = ?
             AND statut IN ('confirmée', 'actuelle')
             AND NOT (date_fin < ? OR date_debut > ?)"
        );
        $stmt->execute([$id_planche, '2026-07-03', '2026-07-08']);
        $resultat = $stmt->fetch();

        $this->assertGreaterThan(0, $resultat['conflits'], "Un chevauchement de dates doit être détecté");
    }

    public function testAucunConflitSiPeriodesDisjointes(): void
    {
        $id_utilisateur = $this->creerUtilisateurDeTest();
        $id_planche     = $this->creerPlancheDeTest();

        $stmt = $this->pdo->prepare(
            "INSERT INTO location (id_utilisateur, id_planche, date_debut, date_fin, montant_total, statut)
             VALUES (?, ?, '2026-07-01', '2026-07-05', 80.00, 'confirmée')"
        );
        $stmt->execute([$id_utilisateur, $id_planche]);

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS conflits FROM location
             WHERE id_planche = ?
             AND statut IN ('confirmée', 'actuelle')
             AND NOT (date_fin < ? OR date_debut > ?)"
        );
        $stmt->execute([$id_planche, '2026-07-10', '2026-07-15']);
        $resultat = $stmt->fetch();

        $this->assertEquals(0, $resultat['conflits'], "Deux périodes disjointes ne doivent pas être en conflit");
    }

    public function testCalculMontantLocation(): void
    {
        $prix_jour  = 16.00;
        $date_debut = '2026-07-01';
        $date_fin   = '2026-07-05';

        $nb_jours      = (strtotime($date_fin) - strtotime($date_debut)) / 86400;
        $montant_total = $nb_jours * $prix_jour;

        $this->assertEquals(4, $nb_jours);
        $this->assertEquals(64.00, $montant_total);
    }

    // =====================================================
    // GROUPE 2 : COMMANDES
    // =====================================================

    public function testStockInsuffisantBloqueLaCommande(): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO planche (nom, marque, prix_achat, prix_location_jour, shape, niveau, stock)
             VALUES ('Planche test stock', 'TestBrand', 500.00, 20.00, 'Fish', 'Débutant - Intermédiaire', 2)"
        );
        $stmt->execute();
        $id_planche = (int) $this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare("SELECT stock FROM planche WHERE id_planche = ?");
        $stmt->execute([$id_planche]);
        $planche = $stmt->fetch();

        $quantite_demandee = 5;
        $stock_suffisant   = $planche['stock'] >= $quantite_demandee;

        $this->assertFalse($stock_suffisant, "Une commande supérieure au stock doit être refusée");
    }

    public function testCalculPrixCommandeAvecPersonnalisation(): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO planche (nom, marque, prix_achat, prix_location_jour, shape, niveau, stock)
             VALUES ('Planche test perso', 'TestBrand', 600.00, 25.00, 'Shortboard', 'Confirmé - Expert', 10)"
        );
        $stmt->execute();
        $id_planche = (int) $this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare(
            "INSERT INTO personnalisation (id_planche, nom, type, prix_supplement)
             VALUES (?, 'Dérive FCS II', 'derive', 45.00)"
        );
        $stmt->execute([$id_planche]);

        $stmt = $this->pdo->prepare("SELECT prix_achat FROM planche WHERE id_planche = ?");
        $stmt->execute([$id_planche]);
        $prix_base = (float) $stmt->fetch()['prix_achat'];

        $stmt = $this->pdo->prepare("SELECT prix_supplement FROM personnalisation WHERE id_planche = ?");
        $stmt->execute([$id_planche]);
        $supplement = (float) $stmt->fetch()['prix_supplement'];

        $quantite      = 1;
        $montant_total = ($prix_base + $supplement) * $quantite;

        $this->assertEquals(645.00, $montant_total);
    }

    // =====================================================
    // GROUPE 3 : COURS DE SURF
    // =====================================================

    public function testCoursCompletRefuseNouvelleReservation(): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, role, consentement_rgpd, date_consentement)
             VALUES ('Test', 'Moniteur', ?, 'hash_temporaire', 'moniteur', 1, NOW())"
        );
        $email_unique = 'moniteur.test.' . uniqid() . '@wavecraft.fr';
        $stmt->execute([$email_unique]);
        $id_utilisateur = (int) $this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare(
            "INSERT INTO moniteur (id_utilisateur, diplome) VALUES (?, 'BPJEPS Surf')"
        );
        $stmt->execute([$id_utilisateur]);
        $id_moniteur = (int) $this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare(
            "INSERT INTO cours (id_moniteur, date_, heure_debut, duree, capacite_max, statut)
             VALUES (?, '2026-08-01', '10:00:00', 150, 1, 'programmé')"
        );
        $stmt->execute([$id_moniteur]);
        $id_cours = (int) $this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare(
            "INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, role, consentement_rgpd, date_consentement)
             VALUES ('Test', 'Participant', ?, 'hash_temporaire', 'client', 1, NOW())"
        );
        $stmt->execute(['participant.test.' . uniqid() . '@wavecraft.fr']);
        $id_participant = (int) $this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare(
            "INSERT INTO reservation_cours (id_cours, id_utilisateur, statut_presence)
             VALUES (?, ?, 'confirmé')"
        );
        $stmt->execute([$id_cours, $id_participant]);

        $stmt = $this->pdo->prepare(
            "SELECT c.capacite_max, COUNT(rc.id_reservation) AS nb_inscrits
             FROM cours c
             LEFT JOIN reservation_cours rc ON c.id_cours = rc.id_cours AND rc.statut_presence != 'annulé'
             WHERE c.id_cours = ?
             GROUP BY c.id_cours"
        );
        $stmt->execute([$id_cours]);
        $resultat = $stmt->fetch();

        $place_disponible = $resultat['nb_inscrits'] < $resultat['capacite_max'];

        $this->assertFalse($place_disponible, "Un cours complet ne doit plus accepter de réservation");
    }

    // =====================================================
    // GROUPE 4 : SÉCURITÉ DES COMPTES
    // =====================================================

    public function testMotDePasseJamaisStockeEnClair(): void
    {
        $mot_de_passe_clair = 'SurfPassword2026';
        $hash               = password_hash($mot_de_passe_clair, PASSWORD_BCRYPT);

        $this->assertNotEquals($mot_de_passe_clair, $hash);
        $this->assertTrue(password_verify($mot_de_passe_clair, $hash));
        $this->assertFalse(password_verify('MauvaisMotDePasse', $hash));
    }

    public function testEmailInvalideEstRejete(): void
    {
        $email_invalide = 'pas-un-email';
        $email_valide   = 'client@wavecraft.fr';

        $this->assertFalse(filter_var($email_invalide, FILTER_VALIDATE_EMAIL));
        $this->assertNotFalse(filter_var($email_valide, FILTER_VALIDATE_EMAIL));
    }
}