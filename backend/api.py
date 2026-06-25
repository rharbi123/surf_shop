"""
API OpenWeather - Prévisions météo 5 jours
Source de données complémentaire pour le Surf Shop
"""

import requests
import json
import os
import logging
from datetime import datetime
from dotenv import load_dotenv

load_dotenv()

API_KEY = os.getenv("OPENWEATHER_API_KEY")
BASE_URL = "https://api.openweathermap.org/data/2.5/forecast"
OUTPUT_RAW = "data/raw/meteo.json"
OUTPUT_CLEANED = "data/cleaned/meteo_cleaned.json"
os.makedirs("data/raw", exist_ok=True)
os.makedirs("data/cleaned", exist_ok=True)
os.makedirs("logs", exist_ok=True)

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    handlers=[
        logging.FileHandler("logs/api.log", encoding="utf-8"),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

SPOTS = [
    {"nom": "Lacanau", "ville": "Lacanau", "pays": "FR"},
    {"nom": "Hossegor", "ville": "Soorts-Hossegor", "pays": "FR"},
    {"nom": "Capbreton", "ville": "Capbreton", "pays": "FR"},
    {"nom": "Biscarrosse", "ville": "Biscarrosse", "pays": "FR"},
    {"nom": "Mimizan", "ville": "Mimizan", "pays": "FR"},
    {"nom": "Seignosse", "ville": "Seignosse", "pays": "FR"},
    {"nom": "Anglet", "ville": "Anglet", "pays": "FR"},
]


def get_previsions(ville, pays):
    params = {
        "q": f"{ville},{pays}",
        "appid": API_KEY,
        "units": "metric",
        "lang": "fr",
        "cnt": 40
    }
    response = requests.get(BASE_URL, params=params, timeout=10)
    response.raise_for_status()
    return response.json()


def extraire_previsions(data, nom_spot):
    previsions = []
    for item in data["list"]:
        previsions.append({
            "date": datetime.fromtimestamp(item["dt"]).strftime("%Y-%m-%d %H:%M"),
            "temperature": item["main"]["temp"],
            "ressenti": item["main"]["feels_like"],
            "humidite": item["main"]["humidity"],
            "vent_vitesse": item["wind"]["speed"],
            "vent_direction": item["wind"].get("deg", None),
            "conditions": item["weather"][0]["description"],
            "icone": item["weather"][0]["icon"],
        })

    return {
        "spot": nom_spot,
        "ville": data["city"]["name"],
        "pays": data["city"]["country"],
        "previsions": previsions,
        "source": "openweathermap.org"
    }


def run():
    logger.info("=" * 50)
    logger.info("DEMARRAGE API OPENWEATHER - PREVISIONS 5 JOURS")
    logger.info("=" * 50)

    if not API_KEY:
        logger.error("Clé API manquante. Vérifie ton fichier .env")
        return

    resultats = []
    for spot in SPOTS:
        try:
            logger.info(f"Récupération prévisions : {spot['nom']}")
            data = get_previsions(spot["ville"], spot["pays"])
            meteo = extraire_previsions(data, spot["nom"])
            resultats.append(meteo)
            nb = len(meteo["previsions"])
            logger.info(f"  -> OK : {nb} créneaux récupérés sur 5 jours")
        except Exception as e:
            logger.error(f"  -> ECHEC {spot['nom']} : {e}")

    # Sauvegarder brut (avec pays)
    with open(OUTPUT_RAW, "w", encoding="utf-8") as f:
        json.dump(resultats, f, ensure_ascii=False, indent=2)
    logger.info(f"Brut sauvegardé -> {OUTPUT_RAW}")

    # Sauvegarder cleaned (sans pays)
    resultats_cleaned = [{k: v for k, v in spot.items() if k != 'pays'} for spot in resultats]
    with open(OUTPUT_CLEANED, "w", encoding="utf-8") as f:
        json.dump(resultats_cleaned, f, ensure_ascii=False, indent=2)
    logger.info(f"Cleaned sauvegardé -> {OUTPUT_CLEANED}")

    logger.info("=" * 50)
    logger.info(f"TERMINE : {len(resultats)} spots")
    logger.info("=" * 50)


if __name__ == "__main__":
    run()