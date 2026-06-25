import json
import mysql.connector
from dotenv import load_dotenv
import os

# Charger les variables du .env
load_dotenv()

DB_HOST = os.getenv('DB_HOST')
DB_USER = os.getenv('DB_USER')
DB_PASSWORD = os.getenv('DB_PASSWORD')
DB_NAME = os.getenv('DB_NAME')
DB_PORT = int(os.getenv('DB_PORT', 8889))

print("=" * 60)
print("🚀 REMPLISSAGE DE LA BASE DE DONNÉES")
print("=" * 60)

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
    
    # =====================================================
    # ÉTAPE 1 : Insérer les SPOTS
    # =====================================================
    print("\n📍 Étape 1 : Insertion des spots...")
    
    spots = [
        ('Lacanau', 'Lacanau'),
        ('Hossegor', 'Soorts-Hossegor'),
        ('Capbreton', 'Capbreton'),
        ('Biscarrosse', 'Biscarrosse'),
        ('Mimizan', 'Mimizan'),
        ('Seignosse', 'Seignosse'),
        ('Anglet', 'Anglet')
    ]
    
    for nom, ville in spots:
        cursor.execute(
            "INSERT IGNORE INTO spot (nom, ville) VALUES (%s, %s)",
            (nom, ville)
        )
    
    conn.commit()
    print(f"   ✅ {len(spots)} spots insérés")
    
    # =====================================================
    # ÉTAPE 2 : Insérer les PLANCHES
    # =====================================================
    print("\n🏄 Étape 2 : Insertion des planches...")
    
    with open('data/cleaned/planches_cleaned.json', 'r', encoding='utf-8') as f:
        planches = json.load(f)
    
    for p in planches:
        cursor.execute("""
            INSERT INTO planche 
            (nom, marque, prix_achat, prix_location_jour, shape, volume, 
             niveau, taille_vagues, nb_derives, boitiers, conception, 
             description, photo, stock)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
        """, (
            p.get('nom', ''),
            p.get('marque', ''),
            p.get('prix', 0),
            p.get('prix', 0),
            p.get('shape', ''),
            json.dumps(p.get('volume', [])),
            p.get('niveau', 'Intermédiaire'),
            p.get('taille_vagues', 'Vagues moyennes'),
            p.get('nb_derives', 3),
            p.get('boitiers', 'FCS'),
            p.get('conception', 'Mousse'),
            p.get('description', ''),
            p.get('photo', ''),
            50  # Stock initial
        ))
    
    conn.commit()
    print(f"   ✅ {len(planches)} planches insérées")
    
    # =====================================================
    # ÉTAPE 3 : Insérer la MÉTÉO
    # =====================================================
    print("\n🌤️ Étape 3 : Insertion de la météo...")
    
    with open('data/cleaned/meteo_cleaned.json', 'r', encoding='utf-8') as f:
        meteo_data = json.load(f)
    
    total_previsions = 0
    
    for spot_data in meteo_data:
        spot_nom = spot_data.get('spot')
        
        # Récupérer l'id_spot
        cursor.execute("SELECT id_spot FROM spot WHERE nom = %s", (spot_nom,))
        result = cursor.fetchone()
        
        if result:
            id_spot = result[0]
            
            for prev in spot_data.get('previsions', []):
                cursor.execute("""
                    INSERT INTO meteo 
                    (id_spot, date_heure, temperature, ressenti, humidite, 
                     vent_vitesse, vent_direction, conditions, icone)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
                """, (
                    id_spot,
                    prev.get('date'),
                    prev.get('temperature'),
                    prev.get('ressenti'),
                    prev.get('humidite'),
                    prev.get('vent_vitesse'),
                    prev.get('vent_direction'),
                    prev.get('conditions'),
                    prev.get('icone')
                ))
                total_previsions += 1
    
    conn.commit()
    print(f"   ✅ {total_previsions} prévisions météo insérées")
    
    # =====================================================
    # RÉSUMÉ
    # =====================================================
    print("\n" + "=" * 60)
    print("✅ BASE DE DONNÉES REMPLIE AVEC SUCCÈS !")
    print("=" * 60)
    print(f"📊 Résumé :")
    print(f"   • Spots : {len(spots)}")
    print(f"   • Planches : {len(planches)}")
    print(f"   • Prévisions météo : {total_previsions}")
    print("=" * 60)
    
    cursor.close()
    conn.close()

except Exception as e:
    print(f"\n❌ ERREUR : {e}")
    print(f"Vérifie que :")
    print(f"   • Les fichiers JSON existent (data/planches_cleaned.json, data/meteo_cleaned.json)")
    print(f"   • Les paramètres du .env sont corrects")
    print(f"   • MAMP est lancé et MySQL fonctionne")