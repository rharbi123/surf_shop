-- =====================================================
-- SURF SHOP - 54 REQUÊTES SQL 
-- =====================================================
 
-- =====================================================
-- 1️⃣ PLANCHES - VENTE
-- =====================================================

use surfshop_db;
 
-- R1 : Lister toutes les planches avec filtres
SELECT id_planche, nom, marque, prix_achat, shape, volume, niveau, taille_vagues, photo, stock
FROM planche
WHERE stock > 0
AND niveau = 'Débutant'
AND prix_achat BETWEEN 200 AND 500
ORDER BY prix_achat ASC;
 
-- R2 : Détails complets d'une planche
SELECT 
    id_planche, nom, marque, prix_achat, prix_location_jour, shape, volume, niveau,
    taille_vagues, nb_derives, boitiers, conception, description, photo, stock
FROM planche
WHERE id_planche = 1;
 
-- R3 : Planches par niveau de surfeur
SELECT nom, marque, prix_achat, niveau, shape, volume, photo
FROM planche
WHERE niveau = 'Débutant - Intermédiaire'
AND stock > 0
ORDER BY prix_achat ASC;
 
-- R4 : Planches par gamme de prix
SELECT id_planche, nom, marque, prix_achat, shape, niveau, photo
FROM planche
WHERE prix_achat >= 200 AND prix_achat <= 500
AND stock > 0
ORDER BY prix_achat ASC;
 
-- R5 : Planches selon conditions météo (taille vagues)
SELECT id_planche, nom, marque, prix_achat, taille_vagues, shape, niveau
FROM planche
WHERE taille_vagues = 'Petites vagues'
AND stock > 0
ORDER BY prix_achat ASC;
 
-- R6 : Mettre à jour le stock après achat
UPDATE planche
SET stock = stock - 1
WHERE id_planche = 1;
 
-- R7 : Planches en rupture de stock
SELECT id_planche, nom, marque, stock
FROM planche
WHERE stock <= 0
ORDER BY nom;
 
-- =====================================================
-- 2️⃣ COMMANDES - VENTE
-- =====================================================

INSERT INTO utilisateur 
(nom, prenom, email, mot_de_passe, role, niveau, date_inscription, consentement_rgpd, date_consentement)
VALUES ('Dupont', 'Jean', 'jean.dupont@email.com', 'password123', 'client', 'Débutant', NOW(), 1, NOW());
 
-- R8 : Créer une commande
INSERT INTO commande (id_utilisateur, date_commande, montant_total, statut)
VALUES (1, NOW(), 500.00, 'en attente');
 
-- R9 : Ajouter des planches à une commande
INSERT INTO commande_planche (id_commande, id_planche, quantite, prix_unitaire)
VALUES (1, 5, 1, 450.00);
 
-- R10 : Historique de commandes d'un utilisateur
SELECT 
    c.id_commande,
    c.date_commande,
    c.montant_total,
    c.statut,
    GROUP_CONCAT(p.nom SEPARATOR ', ') AS planches
FROM commande c
LEFT JOIN commande_planche cp ON c.id_commande = cp.id_commande
LEFT JOIN planche p ON cp.id_planche = p.id_planche
WHERE c.id_utilisateur = 1
GROUP BY c.id_commande
ORDER BY c.date_commande DESC;
 
-- R11 : Commandes en attente de paiement
SELECT c.id_commande, c.date_commande, c.montant_total, u.email
FROM commande c
JOIN utilisateur u ON c.id_utilisateur = u.id_utilisateur
WHERE c.statut = 'en attente'
ORDER BY c.date_commande ASC;
 
-- R12 : Marquer une commande comme confirmée
UPDATE commande
SET statut = 'confirmée'
WHERE id_commande = 1;
 
-- R13 : Détails complets d'une commande avec personnalisations
SELECT 
    c.id_commande,
    c.date_commande,
    c.montant_total,
    c.statut,
    p.nom AS planche_nom,
    cp.quantite,
    cp.prix_unitaire,
    pers.nom AS perso_nom,
    pers.prix_supplement,
    cpp.valeur_choisie
FROM commande c
LEFT JOIN commande_planche cp ON c.id_commande = cp.id_commande
LEFT JOIN planche p ON cp.id_planche = p.id_planche
LEFT JOIN commande_planche_perso cpp ON cp.id_commande_planche = cpp.id_commande_planche
LEFT JOIN personnalisation pers ON cpp.id_personnalisation = pers.id_personnalisation
WHERE c.id_commande = 1;
 
-- =====================================================
-- 3️⃣ LOCATIONS
-- =====================================================
 
-- R14 : Planches disponibles pour une période donnée
SELECT 
    p.id_planche,
    p.nom,
    p.marque,
    p.prix_location_jour,
    p.shape,
    p.volume,
    p.niveau
FROM planche p
WHERE p.id_planche NOT IN (
    SELECT l.id_planche
    FROM location l
    WHERE l.statut IN ('confirmée', 'actuelle')
    AND (
        (l.date_debut <= CURDATE() AND l.date_fin >= CURDATE())
        OR (l.date_debut <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND l.date_fin >= DATE_ADD(CURDATE(), INTERVAL 7 DAY))
        OR (l.date_debut >= CURDATE() AND l.date_fin <= DATE_ADD(CURDATE(), INTERVAL 7 DAY))
    )
)
ORDER BY p.prix_location_jour ASC;
 
-- R15 : Créer une location
INSERT INTO location (id_utilisateur, id_planche, id_spot, date_debut, date_fin, montant_total, statut)
VALUES (1, 5, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 5 DAY), 200.00, 'en attente');
 
-- R16 : Vérifier conflit de dates pour une planche
SELECT COUNT(*) as conflits
FROM location
WHERE id_planche = 5
AND statut IN ('confirmée', 'actuelle')
AND NOT (date_fin < CURDATE() OR date_debut > DATE_ADD(CURDATE(), INTERVAL 5 DAY));
 
-- R17 : Historique des locations d'un utilisateur
SELECT 
    l.id_location,
    l.date_debut,
    l.date_fin,
    l.montant_total,
    l.statut,
    p.nom AS planche_nom,
    s.nom AS spot_nom,
    DATEDIFF(l.date_fin, l.date_debut) AS nb_jours
FROM location l
JOIN planche p ON l.id_planche = p.id_planche
LEFT JOIN spot s ON l.id_spot = s.id_spot
WHERE l.id_utilisateur = 1
ORDER BY l.date_debut DESC;
 
-- R18 : Locations en cours (à retourner)
SELECT 
    l.id_location,
    u.nom,
    u.email,
    p.nom AS planche_nom,
    l.date_fin,
    s.nom AS spot_nom
FROM location l
JOIN utilisateur u ON l.id_utilisateur = u.id_utilisateur
JOIN planche p ON l.id_planche = p.id_planche
LEFT JOIN spot s ON l.id_spot = s.id_spot
WHERE l.statut = 'actuelle'
AND l.date_fin <= CURDATE()
ORDER BY l.date_fin ASC;
 
-- R19 : Locations à venir
SELECT 
    l.id_location,
    u.nom,
    u.email,
    p.nom,
    l.date_debut,
    l.date_fin,
    s.nom AS spot_nom
FROM location l
JOIN utilisateur u ON l.id_utilisateur = u.id_utilisateur
JOIN planche p ON l.id_planche = p.id_planche
LEFT JOIN spot s ON l.id_spot = s.id_spot
WHERE l.statut IN ('en attente', 'confirmée')
AND l.date_debut > CURDATE()
ORDER BY l.date_debut ASC;
 
-- R20 : Marquer une location comme terminée
UPDATE location
SET statut = 'terminée'
WHERE id_location = 1;
 
-- =====================================================
-- 4️⃣ COURS DE SURF
-- =====================================================
 
-- R21 : Lister les cours disponibles
SELECT 
    c.id_cours,
    c.date_,
    c.heure_debut,
    c.duree,
    c.capacite_max,
    COUNT(rc.id_reservation) AS places_occupees,
    (c.capacite_max - COUNT(rc.id_reservation)) AS places_restantes,
    m.id_utilisateur,
    u.nom AS moniteur_nom
FROM cours c
LEFT JOIN reservation_cours rc ON c.id_cours = rc.id_cours AND rc.statut_presence IN ('confirmé', 'présent')
JOIN moniteur m ON c.id_moniteur = m.id_moniteur
JOIN utilisateur u ON m.id_utilisateur = u.id_utilisateur
WHERE c.date_ >= CURDATE()
AND c.statut = 'programmé'
GROUP BY c.id_cours
HAVING places_restantes > 0
ORDER BY c.date_ ASC, c.heure_debut ASC;
 
-- R22 : Inscrire un utilisateur à un cours
INSERT INTO reservation_cours (id_cours, id_utilisateur, date_reservation, statut_presence)
VALUES (1, 1, NOW(), 'confirmé');
 
-- R23 : Vérifier si l'utilisateur est déjà inscrit
SELECT COUNT(*) as inscriptions
FROM reservation_cours
WHERE id_utilisateur = 1
AND id_cours = 1
AND statut_presence != 'annulé';
 
-- R24 : Planning d'un moniteur pour une semaine
SELECT 
    c.id_cours,
    c.date_,
    c.heure_debut,
    c.duree,
    c.capacite_max,
    COUNT(rc.id_reservation) AS inscrits,
    c.statut
FROM cours c
LEFT JOIN reservation_cours rc ON c.id_cours = rc.id_cours AND rc.statut_presence != 'annulé'
WHERE c.id_moniteur = 1
AND c.date_ BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
GROUP BY c.id_cours
ORDER BY c.date_ ASC, c.heure_debut ASC;
 
-- R25 : Lister les inscriptions à un cours
SELECT 
    u.id_utilisateur,
    u.nom,
    u.prenom,
    u.email,
    u.niveau,
    rc.date_reservation,
    rc.statut_presence
FROM reservation_cours rc
JOIN utilisateur u ON rc.id_utilisateur = u.id_utilisateur
WHERE rc.id_cours = 1
ORDER BY rc.date_reservation ASC;
 
-- R26 : Marquer la présence d'un utilisateur
UPDATE reservation_cours
SET statut_presence = 'présent'
WHERE id_utilisateur = 1 AND id_cours = 1;
 
-- R27 : Historique des cours d'un utilisateur
SELECT 
    c.id_cours,
    c.date_,
    c.heure_debut,
    c.duree,
    u.nom AS moniteur_nom,
    rc.statut_presence
FROM reservation_cours rc
JOIN cours c ON rc.id_cours = c.id_cours
JOIN moniteur m ON c.id_moniteur = m.id_moniteur
JOIN utilisateur u ON m.id_utilisateur = u.id_utilisateur
WHERE rc.id_utilisateur = 1
ORDER BY c.date_ DESC;
 
-- =====================================================
-- 5️⃣ MONITEURS
-- =====================================================
 
-- R28 : Lister les moniteurs avec leurs diplômes
SELECT 
    u.id_utilisateur,
    u.nom,
    u.prenom,
    u.email,
    m.diplome,
    COUNT(c.id_cours) AS nb_cours_proposes
FROM moniteur m
JOIN utilisateur u ON m.id_utilisateur = u.id_utilisateur
LEFT JOIN cours c ON m.id_moniteur = c.id_moniteur
GROUP BY m.id_moniteur
ORDER BY u.nom ASC;
 
-- R29 : Disponibilités d'un moniteur
SELECT 
    id_disponibilite,
    date_,
    heure_debut,
    heure_fin
FROM disponibilite_moniteur
WHERE id_moniteur = 1
AND date_ >= CURDATE()
ORDER BY date_ ASC, heure_debut ASC;
 
-- R30 : Ajouter une disponibilité
INSERT INTO disponibilite_moniteur (id_moniteur, date_, heure_debut, heure_fin)
VALUES (1, CURDATE(), '09:00:00', '12:00:00');
 
-- R31 : Planifier un cours selon disponibilité moniteur
SELECT 
    dm.id_disponibilite,
    dm.date_,
    dm.heure_debut,
    dm.heure_fin,
    COUNT(c.id_cours) AS cours_planifies
FROM disponibilite_moniteur dm
LEFT JOIN cours c ON dm.id_moniteur = c.id_moniteur
    AND c.date_ = dm.date_
    AND c.heure_debut >= dm.heure_debut
    AND c.heure_debut < dm.heure_fin
WHERE dm.id_moniteur = 1
AND dm.date_ >= CURDATE()
GROUP BY dm.id_disponibilite
ORDER BY dm.date_ ASC, dm.heure_debut ASC;
 
-- =====================================================
-- 6️⃣ MÉTÉO
-- =====================================================
 
-- R32 : Prévisions météo pour un spot et une date
SELECT 
    m.date_heure,
    m.temperature,
    m.ressenti,
    m.humidite,
    m.vent_vitesse,
    m.vent_direction,
    m.conditions,
    m.icone,
    s.nom AS spot_nom
FROM meteo m
JOIN spot s ON m.id_spot = s.id_spot
WHERE s.nom = 'Lacanau'
AND DATE(m.date_heure) = CURDATE()
ORDER BY m.date_heure ASC;
 
-- R33 : Météo pour les 7 prochains jours sur un spot
SELECT 
    DATE(m.date_heure) as jour,
    MIN(m.temperature) AS temp_min,
    MAX(m.temperature) AS temp_max,
    AVG(m.vent_vitesse) AS vent_moyen,
    GROUP_CONCAT(DISTINCT m.conditions SEPARATOR ', ') AS conditions
FROM meteo m
JOIN spot s ON m.id_spot = s.id_spot
WHERE s.nom = 'Lacanau'
AND m.date_heure BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(m.date_heure)
ORDER BY jour ASC;
 
-- R34 : Meilleur créneau météo pour louer
SELECT 
    m.date_heure,
    m.temperature,
    m.vent_vitesse,
    m.conditions,
    s.nom AS spot_nom
FROM meteo m
JOIN spot s ON m.id_spot = s.id_spot
WHERE m.vent_vitesse BETWEEN 5 AND 20
AND m.date_heure BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
ORDER BY m.vent_vitesse ASC, m.date_heure ASC
LIMIT 10;
 
-- R35 : Tous les spots
SELECT id_spot, nom, ville
FROM spot
ORDER BY nom ASC;
 
-- =====================================================
-- 7️⃣ UTILISATEURS
-- =====================================================
 
-- R36 : Créer un utilisateur (inscription)
INSERT INTO utilisateur 
(nom, prenom, email, mot_de_passe, role, niveau, date_inscription, consentement_rgpd, date_consentement)
VALUES ('Dupont', 'Jean', 'jean.dupont@email.com', 'hash_password', 'client', 'Débutant', NOW(), 1, NOW());
 
-- R37 : Récupérer un utilisateur par email (connexion)
SELECT id_utilisateur, nom, prenom, email, mot_de_passe, role, niveau
FROM utilisateur
WHERE email = 'jean.dupont@email.com';
 
-- R38 : Mettre à jour le profil utilisateur
UPDATE utilisateur
SET nom = 'Dupont', prenom = 'Jean', niveau = 'Intermédiaire'
WHERE id_utilisateur = 1;
 
-- R39 : Récupérer le niveau d'un utilisateur
SELECT niveau
FROM utilisateur
WHERE id_utilisateur = 1;
 
-- R40 : Lister tous les clients
SELECT 
    u.id_utilisateur,
    u.nom,
    u.prenom,
    u.email,
    u.niveau,
    u.date_inscription,
    COUNT(DISTINCT c.id_commande) AS nb_achats,
    COUNT(DISTINCT l.id_location) AS nb_locations,
    COUNT(DISTINCT rc.id_reservation) AS nb_cours
FROM utilisateur u
LEFT JOIN commande c ON u.id_utilisateur = c.id_utilisateur
LEFT JOIN location l ON u.id_utilisateur = l.id_utilisateur
LEFT JOIN reservation_cours rc ON u.id_utilisateur = rc.id_utilisateur
WHERE u.role = 'client'
GROUP BY u.id_utilisateur
ORDER BY u.date_inscription DESC;
 
-- =====================================================
-- 8️⃣ PAIEMENTS (STRIPE)
-- =====================================================
 
-- R41 : Créer un paiement pour commande
INSERT INTO paiement (montant, statut, stripe_payment_id, type, id_commande, id_utilisateur, date_paiement)
VALUES (500.00, 'en attente', 'pi_test_12345', 'commande', 1, 1, NOW());
 
-- R42 : Créer un paiement pour location
INSERT INTO paiement (montant, statut, stripe_payment_id, type, id_location, id_utilisateur, date_paiement)
VALUES (200.00, 'en attente', 'pi_test_67890', 'location', 1, 1, NOW());
 
-- R43 : Marquer un paiement comme approuvé
UPDATE paiement
SET statut = 'approuvé'
WHERE stripe_payment_id = 'pi_test_12345';
 
-- R44 : Historique des paiements d'un utilisateur (INCLUT COURS)
SELECT 
    p.id_paiement,
    p.montant,
    p.statut,
    p.type,
    p.date_paiement,
    COALESCE(c.id_commande, l.id_location, rc.id_reservation) AS reference
FROM paiement p
LEFT JOIN commande c ON p.id_commande = c.id_commande
LEFT JOIN location l ON p.id_location = l.id_location
LEFT JOIN reservation_cours rc ON p.id_reservation = rc.id_reservation
WHERE p.id_utilisateur = 1
ORDER BY p.date_paiement DESC;
 
-- R45 : Paiements en attente (INCLUT COURS)
SELECT 
    p.id_paiement,
    p.montant,
    p.type,
    u.email,
    COALESCE(c.date_commande, l.date_debut, rc.date_reservation) AS date_reference
FROM paiement p
LEFT JOIN commande c ON p.id_commande = c.id_commande
LEFT JOIN location l ON p.id_location = l.id_location
LEFT JOIN reservation_cours rc ON p.id_reservation = rc.id_reservation
LEFT JOIN utilisateur u ON p.id_utilisateur = u.id_utilisateur
WHERE p.statut = 'en attente'
ORDER BY COALESCE(c.date_commande, l.date_debut, rc.date_reservation) ASC;
 
-- =====================================================
-- 9️⃣ PERSONNALISATIONS
-- =====================================================
 
-- R46 : Lister les personnalisations disponibles
SELECT id_personnalisation, nom, type, prix_supplement, id_planche
FROM personnalisation
ORDER BY type ASC, nom ASC;
 
-- R47 : Personnalisations choisies pour une commande
SELECT 
    pers.nom,
    pers.type,
    pers.prix_supplement,
    cpp.valeur_choisie
FROM commande_planche_perso cpp
JOIN personnalisation pers ON cpp.id_personnalisation = pers.id_personnalisation
WHERE cpp.id_commande_planche = 1;
 
-- =====================================================
-- 🔟 RECOMMANDATION IA
-- =====================================================
 
-- R48 : Récupérer TOUTES les planches (pour l'algo de recommandation)
SELECT 
    id_planche,
    nom,
    marque,
    prix_achat,
    shape,
    volume,
    niveau,
    taille_vagues,
    nb_derives,
    conception,
    photo,
    stock
FROM planche
WHERE stock > 0
ORDER BY prix_achat ASC;
 
-- R49 : Planches compatibles avec le profil IA
SELECT 
    id_planche,
    nom,
    marque,
    prix_achat,
    niveau,
    shape,
    volume,
    photo
FROM planche
WHERE niveau = 'Débutant'
AND prix_achat <= 500
AND taille_vagues = 'Petites vagues'
AND stock > 0
ORDER BY prix_achat ASC;
 
-- =====================================================
-- 1️⃣1️⃣ STATISTIQUES & ANALYTICS
-- =====================================================
 
-- R50 : Revenus totaux par type
SELECT 
    SUM(CASE WHEN type = 'commande' THEN montant ELSE 0 END) AS revenus_achat,
    SUM(CASE WHEN type = 'location' THEN montant ELSE 0 END) AS revenus_location,
    SUM(CASE WHEN type = 'cours' THEN montant ELSE 0 END) AS revenus_cours,
    SUM(montant) AS total
FROM paiement
WHERE statut = 'approuvé';
 
-- R51 : Planches les plus vendues
SELECT 
    p.nom,
    p.marque,
    COUNT(cp.id_commande_planche) AS quantite_vendue,
    SUM(cp.prix_unitaire * cp.quantite) AS chiffre_affaires
FROM planche p
JOIN commande_planche cp ON p.id_planche = cp.id_planche
JOIN commande c ON cp.id_commande = c.id_commande
WHERE c.statut IN ('confirmée', 'expédiée', 'livrée')
GROUP BY p.id_planche
ORDER BY quantite_vendue DESC
LIMIT 10;
 
-- R52 : Spots les plus loués
SELECT 
    s.nom,
    COUNT(l.id_location) AS nb_locations,
    SUM(l.montant_total) AS revenus,
    AVG(DATEDIFF(l.date_fin, l.date_debut)) AS duree_moyenne_jours
FROM spot s
LEFT JOIN location l ON s.id_spot = l.id_spot
WHERE l.statut != 'annulée'
GROUP BY s.id_spot
ORDER BY nb_locations DESC;
 
-- R53 : Taux d'occupation des cours
SELECT 
    c.id_cours,
    c.date_,
    c.heure_debut,
    c.capacite_max,
    COUNT(rc.id_reservation) AS inscrits,
    ROUND((COUNT(rc.id_reservation) / c.capacite_max) * 100, 2) AS taux_occupation
FROM cours c
LEFT JOIN reservation_cours rc ON c.id_cours = rc.id_cours AND rc.statut_presence != 'annulé'
GROUP BY c.id_cours
ORDER BY c.date_ DESC;
 
-- R54 : Clients les plus actifs
SELECT 
    u.id_utilisateur,
    u.nom,
    u.email,
    COUNT(DISTINCT c.id_commande) AS nb_achats,
    COUNT(DISTINCT l.id_location) AS nb_locations,
    COUNT(DISTINCT rc.id_reservation) AS nb_cours,
    SUM(CASE WHEN c.statut IN ('confirmée', 'expédiée', 'livrée') THEN c.montant_total ELSE 0 END) +
    SUM(CASE WHEN l.statut != 'annulée' THEN l.montant_total ELSE 0 END) AS total_depense
FROM utilisateur u
LEFT JOIN commande c ON u.id_utilisateur = c.id_utilisateur
LEFT JOIN location l ON u.id_utilisateur = l.id_utilisateur
LEFT JOIN reservation_cours rc ON u.id_utilisateur = rc.id_utilisateur
WHERE u.role = 'client'
GROUP BY u.id_utilisateur
ORDER BY total_depense DESC
LIMIT 20;






INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, role, consentement_rgpd, date_consentement)
VALUES ('Test', 'Moniteur', 'moniteur.test@wavecraft.fr', 'hash_temporaire', 'moniteur', 1, NOW());



SELECT id_utilisateur FROM utilisateur WHERE email = 'moniteur.test@wavecraft.fr';


INSERT INTO moniteur (id_utilisateur, diplome)
VALUES (7, 'BPJEPS Surf');