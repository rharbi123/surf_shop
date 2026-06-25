-- =====================================================
-- Surf Shop - Script de création de la base de données
-- =====================================================
 
-- Créer la base de données
CREATE DATABASE IF NOT EXISTS `surfshop_db` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;
 
USE `surfshop_db`;
 
-- =====================================================
-- TABLE : UTILISATEUR
-- =====================================================
CREATE TABLE `utilisateur` (
  `id_utilisateur` INT AUTO_INCREMENT PRIMARY KEY,
  `nom` VARCHAR(100) NOT NULL,
  `prenom` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `mot_de_passe` VARCHAR(255) NOT NULL,
  `role` ENUM('client', 'moniteur', 'admin') NOT NULL DEFAULT 'client',
  `niveau` VARCHAR(100),
  `date_inscription` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `consentement_rgpd` BOOLEAN NOT NULL DEFAULT FALSE,
  `date_consentement` DATETIME,
  INDEX `idx_email` (`email`),
  INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




-- =====================================================
-- TABLE : SPOT
-- =====================================================
CREATE TABLE `spot` (
  `id_spot` INT AUTO_INCREMENT PRIMARY KEY,
  `nom` VARCHAR(100) NOT NULL UNIQUE,
  `ville` VARCHAR(100) NOT NULL,
  INDEX `idx_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




-- =====================================================
-- TABLE : PLANCHE
-- =====================================================
CREATE TABLE `planche` (
  `id_planche` INT AUTO_INCREMENT PRIMARY KEY,
  `nom` VARCHAR(255) NOT NULL,
  `marque` VARCHAR(100) NOT NULL,
  `prix_achat` DECIMAL(10, 2) NOT NULL,
  `prix_location_jour` DECIMAL(10, 2) NOT NULL,
  `shape` VARCHAR(100),
  `volume` JSON,
  `niveau` VARCHAR(100),
  `taille_vagues` VARCHAR(100),
  `nb_derives` INT,
  `boitiers` VARCHAR(150),
  `conception` VARCHAR(150),
  `description` TEXT,
  `photo` TEXT,
  `stock` INT NOT NULL DEFAULT 0,
  INDEX `idx_marque` (`marque`),
  INDEX `idx_niveau` (`niveau`),
  INDEX `idx_shape` (`shape`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;





-- =====================================================
-- TABLE : METEO
-- =====================================================
CREATE TABLE `meteo` (
  `id_meteo` INT AUTO_INCREMENT PRIMARY KEY,
  `id_spot` INT NOT NULL,
  `date_heure` DATETIME NOT NULL,
  `temperature` DECIMAL(5, 2),
  `ressenti` DECIMAL(5, 2),
  `humidite` INT,
  `vent_vitesse` DECIMAL(5, 2),
  `vent_direction` INT,
  `conditions` VARCHAR(150),
  `icone` VARCHAR(10),
  FOREIGN KEY (`id_spot`) REFERENCES `spot` (`id_spot`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_id_spot` (`id_spot`),
  INDEX `idx_date_heure` (`date_heure`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




-- =====================================================
-- TABLE : PERSONNALISATION
-- =====================================================
CREATE TABLE `personnalisation` (
  `id_personnalisation` INT AUTO_INCREMENT PRIMARY KEY,
  `id_planche` INT NOT NULL,
  `nom` VARCHAR(255) NOT NULL,
  `type` VARCHAR(100),
  `prix_supplement` DECIMAL(10, 2) NOT NULL DEFAULT 0,
  FOREIGN KEY (`id_planche`) REFERENCES `planche` (`id_planche`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_id_planche` (`id_planche`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- =====================================================
-- TABLE : MONITEUR
-- =====================================================
CREATE TABLE `moniteur` (
  `id_moniteur` INT AUTO_INCREMENT PRIMARY KEY,
  `id_utilisateur` INT NOT NULL UNIQUE,
  `diplome` VARCHAR(255),
  FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id_utilisateur`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_id_utilisateur` (`id_utilisateur`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 


-- =====================================================
-- TABLE : COMMANDE
-- =====================================================
CREATE TABLE `commande` (
  `id_commande` INT AUTO_INCREMENT PRIMARY KEY,
  `id_utilisateur` INT NOT NULL,
  `date_commande` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `montant_total` DECIMAL(10, 2) NOT NULL,
  `statut` ENUM('en attente', 'confirmée', 'expédiée', 'livrée', 'annulée') NOT NULL DEFAULT 'en attente',
  FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id_utilisateur`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_id_utilisateur` (`id_utilisateur`),
  INDEX `idx_date_commande` (`date_commande`),
  INDEX `idx_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- =====================================================
-- TABLE : DISPONIBILITE_MONITEUR
-- =====================================================
CREATE TABLE `disponibilite_moniteur` (
  `id_disponibilite` INT AUTO_INCREMENT PRIMARY KEY,
  `id_moniteur` INT NOT NULL,
  `date_` DATE NOT NULL,
  `heure_debut` TIME NOT NULL,
  `heure_fin` TIME NOT NULL,
  FOREIGN KEY (`id_moniteur`) REFERENCES `moniteur` (`id_moniteur`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_id_moniteur` (`id_moniteur`),
  INDEX `idx_date` (`date_`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- =====================================================
-- TABLE : LOCATION
-- =====================================================
CREATE TABLE `location` (
  `id_location` INT AUTO_INCREMENT PRIMARY KEY,
  `id_utilisateur` INT NOT NULL,
  `id_planche` INT NOT NULL,
  `id_spot` INT,
  `date_debut` DATE NOT NULL,
  `date_fin` DATE NOT NULL,
  `montant_total` DECIMAL(10, 2) NOT NULL,
  `statut` ENUM('en attente', 'confirmée', 'actuelle', 'terminée', 'annulée') NOT NULL DEFAULT 'en attente',
  FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id_utilisateur`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`id_planche`) REFERENCES `planche` (`id_planche`) 
    ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (`id_spot`) REFERENCES `spot` (`id_spot`) 
    ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX `idx_id_utilisateur` (`id_utilisateur`),
  INDEX `idx_id_planche` (`id_planche`),
  INDEX `idx_id_spot` (`id_spot`),
  INDEX `idx_date_debut` (`date_debut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




-- =====================================================
-- TABLE : COURS
-- =====================================================
CREATE TABLE `cours` (
  `id_cours` INT AUTO_INCREMENT PRIMARY KEY,
  `id_moniteur` INT NOT NULL,
  `date_` DATE NOT NULL,
  `heure_debut` TIME NOT NULL,
  `duree` INT NOT NULL,
  `capacite_max` INT NOT NULL,
  `statut` ENUM('programmé', 'en cours', 'terminé', 'annulé') NOT NULL DEFAULT 'programmé',
  FOREIGN KEY (`id_moniteur`) REFERENCES `moniteur` (`id_moniteur`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_id_moniteur` (`id_moniteur`),
  INDEX `idx_date` (`date_`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;





-- =====================================================
-- TABLE : COMMANDE_PLANCHE
-- =====================================================
CREATE TABLE `commande_planche` (
  `id_commande_planche` INT AUTO_INCREMENT PRIMARY KEY,
  `id_commande` INT NOT NULL,
  `id_planche` INT NOT NULL,
  `quantite` INT NOT NULL DEFAULT 1,
  `prix_unitaire` DECIMAL(10, 2) NOT NULL,
  FOREIGN KEY (`id_commande`) REFERENCES `commande` (`id_commande`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`id_planche`) REFERENCES `planche` (`id_planche`) 
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX `idx_id_commande` (`id_commande`),
  INDEX `idx_id_planche` (`id_planche`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;





-- =====================================================
-- TABLE : RESERVATION_COURS
-- =====================================================
CREATE TABLE `reservation_cours` (
  `id_reservation` INT AUTO_INCREMENT PRIMARY KEY,
  `id_cours` INT NOT NULL,
  `id_utilisateur` INT NOT NULL,
  `date_reservation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `statut_presence` ENUM('confirmé', 'annulé', 'présent', 'absent') NOT NULL DEFAULT 'confirmé',
  FOREIGN KEY (`id_cours`) REFERENCES `cours` (`id_cours`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id_utilisateur`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_id_cours` (`id_cours`),
  INDEX `idx_id_utilisateur` (`id_utilisateur`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- TABLE : COMMANDE_PLANCHE_PERSO
-- =====================================================
CREATE TABLE `commande_planche_perso` (
  `id_ligne_perso` INT AUTO_INCREMENT PRIMARY KEY,
  `id_commande_planche` INT NOT NULL,
  `id_personnalisation` INT NOT NULL,
  `valeur_choisie` VARCHAR(255),
  FOREIGN KEY (`id_commande_planche`) REFERENCES `commande_planche` (`id_commande_planche`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`id_personnalisation`) REFERENCES `personnalisation` (`id_personnalisation`) 
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX `idx_id_commande_planche` (`id_commande_planche`),
  INDEX `idx_id_personnalisation` (`id_personnalisation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- TABLE : PAIEMENT
-- =====================================================
CREATE TABLE `paiement` (
  `id_paiement` INT AUTO_INCREMENT PRIMARY KEY,
  `montant` DECIMAL(10, 2) NOT NULL,
  `statut` ENUM('en attente', 'approuvé', 'refusé', 'remboursé') NOT NULL DEFAULT 'en attente',
  `stripe_payment_id` VARCHAR(255) UNIQUE,
  `type` ENUM('commande', 'location', 'cours') NOT NULL,
  `date_paiement` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `id_utilisateur` INT NOT NULL,
  `id_commande` INT,
  `id_location` INT,
  `id_reservation` INT,
  FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id_utilisateur`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`id_commande`) REFERENCES `commande` (`id_commande`) 
    ON DELETE SET NULL ON UPDATE CASCADE,
  FOREIGN KEY (`id_location`) REFERENCES `location` (`id_location`) 
    ON DELETE SET NULL ON UPDATE CASCADE,
  FOREIGN KEY (`id_reservation`) REFERENCES `reservation_cours` (`id_reservation`) 
    ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX `idx_id_utilisateur` (`id_utilisateur`),
  INDEX `idx_statut` (`statut`),
  INDEX `idx_date_paiement` (`date_paiement`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


SELECT DATE(date_heure), COUNT(*) 
FROM meteo 
GROUP BY DATE(date_heure) 
ORDER BY DATE(date_heure);





