from flask import Flask, request, jsonify
from flask_cors import CORS
import joblib
import numpy as np
from dotenv import load_dotenv
import os
import jwt
from functools import wraps
from groq import Groq
import logging
import time
from datetime import datetime

load_dotenv()

app = Flask(__name__)
CORS(app)

# =====================================================
# LOGGING
# =====================================================
os.makedirs('logs', exist_ok=True)

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s | %(levelname)s | %(message)s',
    handlers=[
        logging.FileHandler('logs/recommandation.log', encoding='utf-8'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

# Charger le modèle
nn     = joblib.load('modele_knn.pkl')
scaler = joblib.load('scaler.pkl')
df     = joblib.load('planches_df.pkl')

logger.info("Modèle KNN chargé avec succès")

niveau_map = {
    'Débutant - Intermédiaire': 1,
    'Intermédiaire - Confirmé': 2,
    'Confirmé - Expert': 3
}

taille_vagues_map = {
    'Petites vagues': 1,
    'Vagues moyennes': 2,
    'Grandes vagues': 3,
    'Vagues creuses': 4
}

# =====================================================
# MIDDLEWARE JWT
# =====================================================
def verifier_token(f):
    @wraps(f)
    def decorated(*args, **kwargs):
        auth = request.headers.get('Authorization', '')
        if not auth.startswith('Bearer '):
            return jsonify({'succes': False, 'erreur': 'Token manquant'}), 401

        token  = auth.split(' ')[1]
        secret = os.getenv('JWT_SECRET', 'surfshop_jwt_secret')

        try:
            jwt.decode(token, secret, algorithms=['HS256'])
        except jwt.ExpiredSignatureError:
            return jsonify({'succes': False, 'erreur': 'Token expiré'}), 401
        except jwt.InvalidTokenError:
            return jsonify({'succes': False, 'erreur': 'Token invalide'}), 401

        return f(*args, **kwargs)
    return decorated

# =====================================================
# FONCTION RECOMMANDATION ACHAT (KNN)
# =====================================================
def recommander(niveau_user, budget, taille_vagues_meteo, vent_vitesse, n=5):
    vecteur = [[
        niveau_map.get(niveau_user, 1),
        4,
        taille_vagues_map.get(taille_vagues_meteo, 1),
        budget,
        50,
        3
    ]]

    vecteur_scaled = scaler.transform(vecteur)
    distances, indices = nn.kneighbors(vecteur_scaled, n_neighbors=20)

    candidats = df.iloc[indices[0]].copy()
    candidats['distance'] = distances[0]

    candidats = candidats[candidats['prix'] <= budget]
    candidats = candidats[candidats['niveau_label'] == niveau_user]
    candidats = candidats.drop_duplicates(subset=['prix', 'shape_label', 'taille_vagues_label', 'volume_moyen'])

    if candidats.empty:
        prix_min_niveau = df[df['niveau_label'] == niveau_user]['prix'].min()
        return {
            'succes': False,
            'message': f"Budget insuffisant. Nos planches {niveau_user} débutent à {prix_min_niveau}€",
            'prix_min': float(prix_min_niveau),
            'planches': []
        }

    resultats = candidats.head(n)
    return {
        'succes': True,
        'nb_resultats': len(resultats),
        'planches': resultats[['nom', 'marque', 'niveau_label', 'shape_label',
                                'taille_vagues_label', 'prix', 'volume_moyen', 'distance']].to_dict('records')
    }

# =====================================================
# FONCTION RECOMMANDATION LOCATION (KNN, sans budget)
# =====================================================
def recommander_location(niveau_user, taille_vagues_meteo, n=5):
    vecteur = [[
        niveau_map.get(niveau_user, 1),
        4,
        taille_vagues_map.get(taille_vagues_meteo, 1),
        500,
        50,
        3
    ]]

    vecteur_scaled = scaler.transform(vecteur)
    # On cherche parmi toutes les planches pour ne pas rater les experts
    distances, indices = nn.kneighbors(vecteur_scaled, n_neighbors=len(df))

    candidats = df.iloc[indices[0]].copy()
    candidats['distance'] = distances[0]

    # Filtre par niveau uniquement d'abord
    candidats = candidats[candidats['niveau_label'] == niveau_user]

    if candidats.empty:
        return {
            'succes': False,
            'message': f"Aucune planche disponible pour le niveau {niveau_user}.",
            'planches': []
        }

    # Prioriser les planches qui correspondent à la taille de vagues météo
    match_vagues = candidats[candidats['taille_vagues_label'] == taille_vagues_meteo]
    if not match_vagues.empty:
        candidats = match_vagues

    candidats = candidats.drop_duplicates(subset=['shape_label', 'volume_moyen'])
    resultats = candidats.head(n)

    return {
        'succes': True,
        'nb_resultats': len(resultats),
        'planches': resultats[['nom', 'marque', 'niveau_label', 'shape_label',
                                'taille_vagues_label', 'volume_moyen', 'distance']].to_dict('records')
    }

# =====================================================
# FONCTION RAG + GROQ
# =====================================================
def generer_recommandation_ia(planches, niveau, taille_vagues, vent_vitesse,
                               type_recommandation='achat', spot=None,
                               date_label=None, conditions=None, budget=None):
    client = Groq(api_key=os.getenv('GROQ_API_KEY'))

    if type_recommandation == 'location':
        planches_texte = ""
        for i, p in enumerate(planches, 1):
            planches_texte += f"{i}. {p['nom']} ({p['marque']}) - {p['shape_label']} - volume moyen {round(p['volume_moyen'])}L\n"

        spot_info    = f"sur le spot de {spot}" if spot and spot != 'Non précisé' else ""
        meteo_detail = f"{conditions}, vent {vent_vitesse} m/s, {taille_vagues}" if conditions else f"{taille_vagues}, vent {vent_vitesse} m/s"
        contexte     = (
            f"Le client souhaite louer une planche pour surfer {spot_info} {date_label}.\n"
            f"Conditions météo prévues : {meteo_detail}."
        )
        instruction = (
            f"Commence ta réponse en mentionnant explicitement le spot ({spot}), la date ({date_label}) et les conditions météo prévues. "
            f"Ensuite recommande la planche la plus adaptée à ces conditions et au niveau du client. "
            f"Ne parle jamais de prix d'achat ni de budget."
        )
        profil = f"- Niveau : {niveau}\n- {contexte}"
    else:
        planches_texte = ""
        for i, p in enumerate(planches, 1):
            planches_texte += f"{i}. {p['nom']} ({p['marque']}) - {p['prix']}€ - {p['shape_label']} - volume moyen {round(p['volume_moyen'])}L\n"

        contexte    = "Le client cherche une planche pour un achat durable, toutes conditions confondues."
        instruction = "Recommande la planche la plus adaptée en tenant compte du niveau et du budget uniquement."
        profil      = f"- Niveau : {niveau}\n- Budget maximum : {budget}€\n- {contexte}"

    prompt = f"""Tu es un expert en surf dans un surf shop sur la côte Atlantique française.

Un client cherche une planche avec ce profil :
{profil}

Voici les planches disponibles correspondant à son profil :
{planches_texte}

Génère une recommandation courte et professionnelle (5-6 lignes maximum) en français.
{instruction}
Ne liste pas toutes les planches, choisis la meilleure et justifie ton choix."""

    response = client.chat.completions.create(
        model="llama-3.3-70b-versatile",
        messages=[{"role": "user", "content": prompt}],
        max_tokens=350
    )

    return response.choices[0].message.content

# =====================================================
# ROUTE : Health check (public)
# =====================================================
@app.route('/health', methods=['GET'])
def health():
    return jsonify({'status': 'ok', 'message': 'API Flask opérationnelle'})

# =====================================================
# ROUTE : Recommandation (protégée)
# =====================================================
@app.route('/recommander', methods=['POST'])
@verifier_token
def recommander_route():
    debut = time.time()
    data  = request.get_json()

    if not data:
        return jsonify({'succes': False, 'erreur': 'JSON invalide'}), 400

    niveau              = data.get('niveau')
    budget              = data.get('budget')
    taille_vagues       = data.get('taille_vagues', 'Vagues moyennes')
    vent_vitesse        = data.get('vent_vitesse', 0)
    type_recommandation = data.get('type', 'achat')
    spot                = data.get('spot', 'Non précisé')
    date_label          = data.get('date_label', '')
    conditions          = data.get('conditions', '')

    if not niveau:
        return jsonify({'succes': False, 'erreur': 'niveau requis'}), 400

    if type_recommandation == 'location':
        resultat = recommander_location(niveau, taille_vagues)
    else:
        if not budget:
            return jsonify({'succes': False, 'erreur': 'budget requis pour un achat'}), 400
        resultat = recommander(niveau, float(budget), taille_vagues, vent_vitesse)

    if resultat['succes']:
        recommandation_ia = generer_recommandation_ia(
            resultat['planches'], niveau,
            taille_vagues, vent_vitesse, type_recommandation,
            spot=spot, date_label=date_label, conditions=conditions,
            budget=budget
        )
        resultat['recommandation_ia'] = recommandation_ia

    temps_ms = int((time.time() - debut) * 1000)

    if resultat['succes']:
        logger.info(
            f"niveau={niveau} | budget={budget} | type={type_recommandation} | "
            f"spot={spot} | date={date_label} | taille_vagues={taille_vagues} | "
            f"nb_resultats={resultat['nb_resultats']} | temps_ms={temps_ms} | succes=True"
        )
    else:
        logger.warning(
            f"niveau={niveau} | budget={budget} | type={type_recommandation} | "
            f"spot={spot} | nb_resultats=0 | temps_ms={temps_ms} | "
            f"succes=False | message={resultat.get('message', '')}"
        )

    return jsonify(resultat)

if __name__ == '__main__':
    app.run(port=5000, debug=True)