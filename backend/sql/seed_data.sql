-- =====================================================
-- SEED DATA - Données de test pour surfshop_db
-- =====================================================

USE `surfshop_db`;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE paiement;
TRUNCATE TABLE commande_planche_perso;
TRUNCATE TABLE commande_planche;
TRUNCATE TABLE reservation_cours;
TRUNCATE TABLE commande;
TRUNCATE TABLE location;
TRUNCATE TABLE cours;
TRUNCATE TABLE disponibilite_moniteur;
TRUNCATE TABLE moniteur;
TRUNCATE TABLE meteo;
TRUNCATE TABLE personnalisation;
TRUNCATE TABLE planche;
TRUNCATE TABLE spot;
TRUNCATE TABLE utilisateur;
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- SPOTS
-- =====================================================
INSERT INTO spot (nom, ville) VALUES
('Lacanau', 'Lacanau'),
('Hossegor', 'Soorts-Hossegor'),
('Capbreton', 'Capbreton'),
('Biscarrosse', 'Biscarrosse'),
('Mimizan', 'Mimizan'),
('Seignosse', 'Seignosse'),
('Anglet', 'Anglet');

-- =====================================================
-- PLANCHES
-- =====================================================
INSERT INTO planche (nom, marque, prix_achat, prix_location_jour, shape, volume, niveau, taille_vagues, nb_derives, boitiers, conception, description, photo, stock) VALUES
('Longboard Classic 9\'2', 'Rip Curl', 650.00, 35.00, 'Longboard', '{"min": 75, "max": 90}', 'Débutant', 'Petites vagues', 1, 'Single', 'Mousse PU', 'Idéale pour apprendre, stabilité maximale', NULL, 5),
('Fish Twin 6\'4', 'Channel Islands', 480.00, 28.00, 'Fish', '{"min": 38, "max": 45}', 'Intermédiaire', 'Petites à moyennes vagues', 2, 'Twin', 'Résine époxy', 'Polyvalente et fun, parfaite pour vagues molles', NULL, 4),
('Shortboard Pro 6\'0', 'Pyzel', 520.00, 32.00, 'Shortboard', '{"min": 28, "max": 34}', 'Avancé', 'Moyennes à grosses vagues', 3, 'Thruster', 'Résine polyester', 'Performance pure pour surfeur confirmé', NULL, 3),
('Malibu 8\'0', 'NSP', 420.00, 30.00, 'Malibu', '{"min": 60, "max": 70}', 'Débutant - Intermédiaire', 'Petites vagues', 3, 'Thruster', 'Mousse EPS', 'Transition idéale du longboard au shortboard', NULL, 6),
('Gun 7\'6', 'Lost', 720.00, 40.00, 'Gun', '{"min": 45, "max": 55}', 'Expert', 'Grosses vagues', 3, 'Thruster', 'Résine polyester', 'Pour les grosses conditions, réservée aux experts', NULL, 2),
('Funboard 7\'0', 'BIC Sport', 380.00, 25.00, 'Funboard', '{"min": 52, "max": 62}', 'Débutant', 'Petites à moyennes vagues', 3, 'Thruster', 'Mousse EPS', 'Parfaite pour les cours de surf débutants', NULL, 8);

-- =====================================================
-- PERSONNALISATIONS
-- =====================================================
INSERT INTO personnalisation (id_planche, nom, type, prix_supplement) VALUES
(1, 'Leash 9 pieds', 'accessoire', 15.00),
(1, 'Wax tropical', 'accessoire', 5.00),
(2, 'Leash 6 pieds', 'accessoire', 12.00),
(3, 'Dérives futures F4', 'dérive', 45.00),
(6, 'Couleur custom', 'esthétique', 20.00);

-- =====================================================
-- UTILISATEURS (mot_de_passe = "password123" hashé bcrypt)
-- =====================================================
INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, role, niveau, date_inscription, consentement_rgpd, date_consentement) VALUES
('Admin', 'Surf', 'admin@surfshop.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL, NOW(), 1, NOW()),
('Martin', 'Lucas', 'lucas.martin@surfshop.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'moniteur', 'Expert', NOW(), 1, NOW()),
('Dubois', 'Sophie', 'sophie.dubois@surfshop.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'moniteur', 'Expert', NOW(), 1, NOW()),
('Dupont', 'Jean', 'jean.dupont@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 'Débutant', NOW(), 1, NOW()),
('Bernard', 'Marie', 'marie.bernard@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 'Intermédiaire', NOW(), 1, NOW()),
('Moreau', 'Pierre', 'pierre.moreau@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 'Avancé', NOW(), 1, NOW()),
('Leroy', 'Emma', 'emma.leroy@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 'Débutant', NOW(), 1, NOW()),
('Garcia', 'Thomas', 'thomas.garcia@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', 'Débutant', NOW(), 1, NOW());

-- =====================================================
-- MONITEURS
-- =====================================================
INSERT INTO moniteur (id_utilisateur, diplome) VALUES
(2, 'BPJEPS Surf + CQP Initiateur'),
(3, 'DEJEPS Surf Performance');

-- =====================================================
-- COURS (dates futures avec places disponibles)
-- =====================================================
INSERT INTO cours (id_moniteur, date_, heure_debut, duree, capacite_max, statut) VALUES
-- Cours dans les prochains jours
(1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '09:00:00', 90, 8, 'programmé'),
(1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '14:00:00', 120, 6, 'programmé'),
(2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '10:00:00', 90, 8, 'programmé'),
(1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '09:00:00', 90, 8, 'programmé'),
(2, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '11:00:00', 60, 10, 'programmé'),
(1, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '09:30:00', 120, 6, 'programmé'),
(2, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '14:30:00', 90, 8, 'programmé'),
(1, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '09:00:00', 90, 5, 'programmé'),
(2, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '15:00:00', 90, 8, 'programmé'),
(1, DATE_ADD(CURDATE(), INTERVAL 7 DAY), '10:00:00', 120, 10, 'programmé');

-- =====================================================
-- RESERVATIONS (quelques places occupées)
-- =====================================================
INSERT INTO reservation_cours (id_cours, id_utilisateur, date_reservation, statut_presence) VALUES
(1, 4, NOW(), 'confirmé'),
(1, 5, NOW(), 'confirmé'),
(1, 6, NOW(), 'confirmé'),
(2, 4, NOW(), 'confirmé'),
(2, 7, NOW(), 'confirmé'),
(3, 5, NOW(), 'confirmé'),
(3, 8, NOW(), 'confirmé'),
(4, 6, NOW(), 'confirmé'),
(5, 4, NOW(), 'confirmé');

-- =====================================================
-- DISPONIBILITES MONITEURS (7 prochains jours)
-- =====================================================
INSERT INTO disponibilite_moniteur (id_moniteur, date_, heure_debut, heure_fin) VALUES
(1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '08:00:00', '18:00:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '08:00:00', '18:00:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '08:00:00', '18:00:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '08:00:00', '18:00:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 7 DAY), '08:00:00', '18:00:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '09:00:00', '17:00:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '09:00:00', '17:00:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '09:00:00', '17:00:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '09:00:00', '17:00:00');

-- =====================================================
-- VERIFICATION
-- =====================================================
SELECT 'Utilisateurs' AS table_, COUNT(*) AS nb FROM utilisateur
UNION ALL SELECT 'Planches', COUNT(*) FROM planche
UNION ALL SELECT 'Moniteurs', COUNT(*) FROM moniteur
UNION ALL SELECT 'Cours', COUNT(*) FROM cours
UNION ALL SELECT 'Reservations', COUNT(*) FROM reservation_cours
UNION ALL SELECT 'Spots', COUNT(*) FROM spot;
