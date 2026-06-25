import requests
import mysql.connector
from dotenv import load_dotenv
import os
from datetime import datetime

# Charger les variables du .env
load_dotenv()

DB_HOST = os.getenv('DB_HOST')
DB_USER = os.getenv('DB_USER')
DB_PASSWORD = os.getenv('DB_PASSWORD')
DB_NAME = os.getenv('DB_NAME')
DB_PORT = int(os.getenv('DB_PORT', 8889))
API_KEY = os.getenv('OPENWEATHER_API_KEY')

# Coordonnées des spots
SPOTS = [
    ('Lacanau', 44.9305, -1.2172),
    ('Hossegor', 43.6667, -1.5833),
    ('Capbreton', 43.6558, -1.4365),
    ('Biscarrosse', 44.4000, -1.2000),
    ('Mimizan', 44.2167, -1.3167),
    ('Seignosse', 43.6456, -1.5201),
    ('Anglet', 43.4833, -1.5500)
]

print("=" * 70)
print("🔄 MISE À JOUR AUTOMATIQUE DE LA MÉTÉO")
print(f"   Heure : {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
print("=" * 70)

try:
    # Connexion à la base
    conn = mysql.connector.connect(
        host=DB_HOST,
        user=DB_USER,
        password=DB_PASSWORD,
        database=DB_NAME,
        port=DB_PORT
    )
    cursor = conn.cursor()
    
    total_inseres = 0
    spots_reussi = 0
    
    # Boucle sur chaque spot
    for spot_nom, lat, lon in SPOTS:
        print(f"\n📍 Traitement du spot : {spot_nom}...")
        
        try:
            # Appeler l'API OpenWeather
            url = "https://api.openweathermap.org/data/2.5/forecast"
            params = {
                'lat': lat,
                'lon': lon,
                'appid': API_KEY,
                'units': 'metric',
                'lang': 'fr'
            }
            
            response = requests.get(url, params=params, timeout=10)
            
            if response.status_code != 200:
                print(f"   ⚠️ Erreur API : {response.status_code}")
                continue
            
            data = response.json()
            
            # Récupérer l'id_spot
            cursor.execute("SELECT id_spot FROM spot WHERE nom = %s", (spot_nom,))
            result = cursor.fetchone()
            
            if not result:
                print(f"   ❌ Spot {spot_nom} introuvable en base")
                continue
            
            id_spot = result[0]
            
            # Insérer les prévisions
            count = 0
            for item in data['list']:
                date_heure = datetime.fromtimestamp(item['dt']).strftime('%Y-%m-%d %H:%M:%S')
                temperature = item['main']['temp']
                ressenti = item['main']['feels_like']
                humidite = item['main']['humidity']
                vent_vitesse = item['wind']['speed']
                vent_direction = item['wind'].get('deg', 0)
                conditions = item['weather'][0]['description']
                icone = item['weather'][0]['icon']
                
                cursor.execute("""
                    INSERT INTO meteo 
                    (id_spot, date_heure, temperature, ressenti, humidite, 
                     vent_vitesse, vent_direction, conditions, icone)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE 
                        temperature = VALUES(temperature),
                        ressenti = VALUES(ressenti),
                        humidite = VALUES(humidite),
                        vent_vitesse = VALUES(vent_vitesse),
                        vent_direction = VALUES(vent_direction),
                        conditions = VALUES(conditions),
                        icone = VALUES(icone)
                """, (
                    id_spot,
                    date_heure,
                    temperature,
                    ressenti,
                    humidite,
                    vent_vitesse,
                    vent_direction,
                    conditions,
                    icone
                ))
                count += 1
            
            conn.commit()
            print(f"   ✅ {count} prévisions mises à jour")
            total_inseres += count
            spots_reussi += 1
            
        except requests.exceptions.Timeout:
            print(f"   ❌ Timeout : l'API met trop de temps à répondre")
        except requests.exceptions.RequestException as e:
            print(f"   ❌ Erreur réseau : {e}")
        except Exception as e:
            print(f"   ❌ Erreur : {e}")
    
    # Supprimer les prévisions > 5 jours
    print(f"\n🗑️ Suppression des prévisions > 5 jours...")
    cursor.execute("""
        DELETE FROM meteo 
        WHERE date_heure < DATE_SUB(NOW(), INTERVAL 5 DAY)
    """)
    deleted = cursor.rowcount
    conn.commit()
    print(f"   ✅ {deleted} anciennes prévisions supprimées")
    
    # Résumé
    print("\n" + "=" * 70)
    print("✅ MISE À JOUR COMPLÉTÉE AVEC SUCCÈS !")
    print("=" * 70)
    print(f"📊 Résumé :")
    print(f"   • Spots traités : {spots_reussi}/{len(SPOTS)}")
    print(f"   • Prévisions insérées : {total_inseres}")
    print(f"   • Anciennes données supprimées : {deleted}")
    print(f"   • Heure de la mise à jour : {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("=" * 70)
    
    cursor.close()
    conn.close()

except Exception as e:
    print(f"\n❌ ERREUR FATALE : {e}")
    print(f"Vérifie que :")
    print(f"   • MAMP est lancé")
    print(f"   • Les paramètres du .env sont corrects")
    print(f"   • Ta clé API OpenWeather est valide")