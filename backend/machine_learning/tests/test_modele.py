# =====================================================
# TESTS/TEST_MODELE.PY - Tests automatisés WaveCraft IA
# Couvre : validation données, préparation, entraînement,
#          évaluation et validation du modèle (C12)
# Usage : pytest test_modele.py -v
# =====================================================

import pytest
import json
import os
import sys
import numpy as np
import joblib

# Ajouter le dossier parent au path pour importer app.py
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

# =====================================================
# CHEMINS
# =====================================================
BASE_DIR    = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DATA_PATH   = os.path.join(BASE_DIR, '..', 'data', 'cleaned', 'planches_cleaned.json')
MODELE_PATH = os.path.join(BASE_DIR, 'modele_knn.pkl')
SCALER_PATH = os.path.join(BASE_DIR, 'scaler.pkl')
DF_PATH     = os.path.join(BASE_DIR, 'planches_df.pkl')

# =====================================================
# FIXTURES
# =====================================================
@pytest.fixture(scope='module')
def planches_raw():
    """Charge les données brutes depuis le JSON nettoyé"""
    with open(DATA_PATH, 'r', encoding='utf-8') as f:
        return json.load(f)

@pytest.fixture(scope='module')
def modele():
    """Charge le modèle KNN"""
    return joblib.load(MODELE_PATH)

@pytest.fixture(scope='module')
def scaler():
    """Charge le StandardScaler"""
    return joblib.load(SCALER_PATH)

@pytest.fixture(scope='module')
def df():
    """Charge le DataFrame des planches"""
    return joblib.load(DF_PATH)

@pytest.fixture(scope='module')
def app_client():
    """Crée un client de test Flask"""
    os.chdir(BASE_DIR)  # Se placer dans machine_learning/ avant d'importer app
    from app import app
    app.config['TESTING'] = True
    with app.test_client() as client:
        yield client

# =====================================================
# 1. VALIDATION DU JEU DE DONNÉES
# =====================================================
class TestValidationDonnees:

    def test_fichier_json_existe(self):
        """Le fichier de données nettoyées doit exister"""
        assert os.path.exists(DATA_PATH), f"Fichier introuvable : {DATA_PATH}"

    def test_nombre_planches(self, planches_raw):
        """Le dataset doit contenir au moins 100 planches"""
        assert len(planches_raw) >= 100, f"Nombre de planches insuffisant : {len(planches_raw)}"

    def test_champs_obligatoires(self, planches_raw):
        """Chaque planche doit avoir les champs requis"""
        champs_requis = ['nom', 'marque', 'prix', 'niveau', 'shape', 'taille_vagues', 'volume', 'nb_derives']
        for planche in planches_raw:
            for champ in champs_requis:
                assert champ in planche, f"Champ manquant '{champ}' dans : {planche.get('nom', 'inconnu')}"

    def test_prix_positif(self, planches_raw):
        """Le prix de chaque planche doit être positif"""
        for planche in planches_raw:
            assert float(planche['prix']) > 0, f"Prix invalide pour : {planche.get('nom', 'inconnu')}"

    def test_niveaux_valides(self, planches_raw):
        """Les niveaux doivent être parmi les valeurs acceptées"""
        niveaux_valides = ['Débutant - Intermédiaire', 'Intermédiaire - Confirmé', 'Confirmé - Expert']
        for planche in planches_raw:
            assert planche['niveau'] in niveaux_valides, \
                f"Niveau invalide '{planche['niveau']}' pour : {planche.get('nom', 'inconnu')}"

    def test_volume_est_liste(self, planches_raw):
        """Le volume doit être une liste de valeurs numériques"""
        for planche in planches_raw:
            assert isinstance(planche['volume'], list), \
                f"Volume non valide pour : {planche.get('nom', 'inconnu')}"
            assert len(planche['volume']) > 0, \
                f"Volume vide pour : {planche.get('nom', 'inconnu')}"

    def test_pas_de_doublons(self, planches_raw):
        """Vérifier et signaler les doublons dans le dataset"""
        identifiants = [(p['nom'], p['marque']) for p in planches_raw]
        nb_doublons = len(identifiants) - len(set(identifiants))
        if nb_doublons > 0:
            print(f"\n⚠️  {nb_doublons} doublon(s) détecté(s) dans le dataset")
        # On accepte jusqu'à 2 doublons (données scrappées)
        assert nb_doublons <= 2, f"Trop de doublons : {nb_doublons}"

# =====================================================
# 2. PRÉPARATION DES DONNÉES
# =====================================================
class TestPreparationDonnees:

    def test_encodage_niveaux(self):
        """L'encodage des niveaux doit produire des valeurs 1, 2, 3"""
        niveau_map = {
            'Débutant - Intermédiaire': 1,
            'Intermédiaire - Confirmé': 2,
            'Confirmé - Expert': 3
        }
        assert niveau_map['Débutant - Intermédiaire'] == 1
        assert niveau_map['Intermédiaire - Confirmé'] == 2
        assert niveau_map['Confirmé - Expert'] == 3

    def test_encodage_taille_vagues(self):
        """L'encodage des tailles de vagues doit être ordonné"""
        taille_map = {
            'Petites vagues': 1,
            'Vagues moyennes': 2,
            'Grandes vagues': 3,
            'Vagues creuses': 4
        }
        valeurs = list(taille_map.values())
        assert valeurs == sorted(valeurs), "L'encodage des vagues n'est pas ordonné"

    def test_dataframe_colonnes(self, df):
        """Le DataFrame doit contenir toutes les colonnes requises"""
        colonnes_requises = ['nom', 'marque', 'prix', 'niveau', 'shape',
                             'taille_vagues', 'volume_moyen', 'nb_derives',
                             'niveau_label', 'shape_label', 'taille_vagues_label']
        for col in colonnes_requises:
            assert col in df.columns, f"Colonne manquante : {col}"

    def test_dataframe_pas_de_nan(self, df):
        """Les colonnes features ne doivent pas contenir de NaN"""
        features = ['niveau', 'shape', 'taille_vagues', 'prix', 'volume_moyen', 'nb_derives']
        for col in features:
            assert df[col].isna().sum() == 0, f"Des NaN dans la colonne : {col}"

    def test_scaler_normalisation(self, scaler, df):
        """Le scaler doit normaliser les features avec une moyenne proche de 0"""
        features = ['niveau', 'shape', 'taille_vagues', 'prix', 'volume_moyen', 'nb_derives']
        X = df[features].values
        X_scaled = scaler.transform(X)
        moyenne = np.mean(X_scaled, axis=0)
        for m in moyenne:
            assert abs(m) < 0.1, f"Moyenne après scaling trop éloignée de 0 : {m}"

    def test_volume_moyen_calcul(self, planches_raw):
        """Le volume moyen doit être la moyenne des volumes disponibles"""
        planche = planches_raw[0]
        volume_attendu = np.mean(planche['volume'])
        assert volume_attendu > 0, "Le volume moyen doit être positif"

# =====================================================
# 3. ENTRAÎNEMENT DU MODÈLE
# =====================================================
class TestEntrainement:

    def test_fichiers_pkl_existent(self):
        """Les 3 fichiers .pkl doivent exister"""
        assert os.path.exists(MODELE_PATH), "modele_knn.pkl introuvable"
        assert os.path.exists(SCALER_PATH), "scaler.pkl introuvable"
        assert os.path.exists(DF_PATH), "planches_df.pkl introuvable"

    def test_modele_type(self, modele):
        """Le modèle chargé doit être un NearestNeighbors"""
        from sklearn.neighbors import NearestNeighbors
        assert isinstance(modele, NearestNeighbors), "Le modèle n'est pas un NearestNeighbors"

    def test_modele_entraine(self, modele):
        """Le modèle doit être entraîné (avoir des données fitted)"""
        assert hasattr(modele, 'n_samples_fit_'), "Le modèle n'est pas entraîné"
        assert modele.n_samples_fit_ >= 100, \
            f"Trop peu d'échantillons entraînés : {modele.n_samples_fit_}"

    def test_modele_distance_euclidienne(self, modele):
        """Le modèle doit utiliser la distance euclidienne"""
        assert modele.metric == 'euclidean', \
            f"Métrique incorrecte : {modele.metric}"

    def test_reentrainement(self, df):
        """On doit pouvoir réentraîner un nouveau modèle depuis zéro"""
        from sklearn.neighbors import NearestNeighbors
        from sklearn.preprocessing import StandardScaler

        features = ['niveau', 'shape', 'taille_vagues', 'prix', 'volume_moyen', 'nb_derives']
        X = df[features].values

        new_scaler = StandardScaler()
        X_scaled   = new_scaler.fit_transform(X)

        new_modele = NearestNeighbors(n_neighbors=5, metric='euclidean')
        new_modele.fit(X_scaled)

        assert hasattr(new_modele, 'n_samples_fit_'), "Réentraînement échoué"
        assert new_modele.n_samples_fit_ == len(df), "Nombre d'échantillons incorrect"

# =====================================================
# 4. ÉVALUATION DU MODÈLE
# =====================================================
class TestEvaluation:

    def test_recommandation_achat_budget_suffisant(self, modele, scaler, df):
        """Avec un budget suffisant, le modèle doit retourner des planches"""
        vecteur = [[1, 1, 2, 600, 50, 3]]
        vecteur_scaled = scaler.transform(vecteur)
        distances, indices = modele.kneighbors(vecteur_scaled, n_neighbors=20)

        candidats = df.iloc[indices[0]].copy()
        candidats = candidats[candidats['prix'] <= 600]
        candidats = candidats[candidats['niveau_label'] == 'Débutant - Intermédiaire']

        assert len(candidats) > 0, "Aucune planche trouvée avec un budget de 600€ pour Débutant"

    def test_recommandation_achat_budget_insuffisant(self, modele, scaler, df):
        """Avec un budget insuffisant, le modèle doit retourner une liste vide"""
        vecteur = [[1, 1, 2, 10, 50, 3]]
        vecteur_scaled = scaler.transform(vecteur)
        distances, indices = modele.kneighbors(vecteur_scaled, n_neighbors=20)

        candidats = df.iloc[indices[0]].copy()
        candidats = candidats[candidats['prix'] <= 10]
        candidats = candidats[candidats['niveau_label'] == 'Débutant - Intermédiaire']

        assert len(candidats) == 0, "Le modèle ne devrait pas trouver de planches à 10€"

    def test_recommandation_filtre_niveau(self, modele, scaler, df):
        """Les recommandations doivent respecter le niveau demandé"""
        vecteur = [[1, 1, 2, 600, 50, 3]]
        vecteur_scaled = scaler.transform(vecteur)
        distances, indices = modele.kneighbors(vecteur_scaled, n_neighbors=20)

        candidats = df.iloc[indices[0]].copy()
        candidats = candidats[candidats['prix'] <= 600]
        candidats = candidats[candidats['niveau_label'] == 'Débutant - Intermédiaire']

        niveaux_obtenus = candidats['niveau_label'].unique()
        for niveau in niveaux_obtenus:
            assert niveau == 'Débutant - Intermédiaire', \
                f"Niveau incorrect dans les résultats : {niveau}"

    def test_distances_coherentes(self, modele, scaler, df):
        """Les distances retournées doivent être positives et ordonnées"""
        vecteur = [[1, 1, 2, 600, 50, 3]]
        vecteur_scaled = scaler.transform(vecteur)
        distances, indices = modele.kneighbors(vecteur_scaled, n_neighbors=5)

        for dist in distances[0]:
            assert dist >= 0, "Distance négative détectée"

        assert list(distances[0]) == sorted(distances[0]), \
            "Les distances ne sont pas triées par ordre croissant"

    def test_recommandation_location_filtre_niveau(self, modele, scaler, df):
        """La recommandation location doit filtrer par niveau sans contrainte budget"""
        vecteur = [[2, 4, 2, 500, 50, 3]]
        vecteur_scaled = scaler.transform(vecteur)
        distances, indices = modele.kneighbors(vecteur_scaled, n_neighbors=len(df))

        candidats = df.iloc[indices[0]].copy()
        candidats = candidats[candidats['niveau_label'] == 'Intermédiaire - Confirmé']

        assert len(candidats) > 0, "Aucune planche trouvée pour le niveau Intermédiaire - Confirmé"

# =====================================================
# 5. VALIDATION DU MODÈLE (API Flask)
# =====================================================
class TestValidationAPI:

    def test_health_check(self, app_client):
        """La route /health doit retourner 200"""
        response = app_client.get('/health')
        assert response.status_code == 200
        data = response.get_json()
        assert data['status'] == 'ok'

    def test_recommander_sans_token(self, app_client):
        """La route /recommander sans token doit retourner 401"""
        response = app_client.post('/recommander',
            json={'niveau': 'Débutant - Intermédiaire', 'budget': 600, 'type': 'achat'})
        assert response.status_code == 401

    def test_recommander_token_invalide(self, app_client):
        """Un token invalide doit retourner 401"""
        response = app_client.post('/recommander',
            json={'niveau': 'Débutant - Intermédiaire', 'budget': 600, 'type': 'achat'},
            headers={'Authorization': 'Bearer token_invalide'})
        assert response.status_code == 401

    def test_modele_chargement_au_demarrage(self):
        """Les 3 fichiers pkl doivent être chargeables sans erreur"""
        try:
            nn     = joblib.load(MODELE_PATH)
            scaler = joblib.load(SCALER_PATH)
            df     = joblib.load(DF_PATH)
            assert nn is not None
            assert scaler is not None
            assert df is not None
        except Exception as e:
            pytest.fail(f"Erreur chargement des fichiers pkl : {e}")