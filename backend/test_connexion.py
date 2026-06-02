import mysql.connector
from dotenv import load_dotenv
import os

# Charger les variables du .env
load_dotenv()

# Récupérer les paramètres
DB_HOST = os.getenv('DB_HOST')
DB_USER = os.getenv('DB_USER')
DB_PASSWORD = os.getenv('DB_PASSWORD')
DB_NAME = os.getenv('DB_NAME')
DB_PORT = int(os.getenv('DB_PORT', 8889))  # Port par défaut 8889

print(f"🔍 Tentative de connexion à {DB_HOST}:{DB_PORT}...")
print(f"   User: {DB_USER}")
print(f"   Database: {DB_NAME}")

try:
    conn = mysql.connector.connect(
        host=DB_HOST,
        user=DB_USER,
        password=DB_PASSWORD,
        database=DB_NAME,
        port=DB_PORT
    )
    cursor = conn.cursor()
    cursor.execute("SELECT COUNT(*) FROM planche")
    nb_planches = cursor.fetchone()[0]
    
    print(f"✅ Connexion réussie !")
    print(f"✅ Nombre de planches en base : {nb_planches}")
    
    conn.close()
except Exception as e:
    print(f"❌ Erreur de connexion : {e}")