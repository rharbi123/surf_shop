import mysql.connector
from dotenv import load_dotenv
import os

load_dotenv()

conn = mysql.connector.connect(
    host=os.getenv('DB_HOST'),
    user=os.getenv('DB_USER'),
    password=os.getenv('DB_PASSWORD'),
    database=os.getenv('DB_NAME'),
    port=int(os.getenv('DB_PORT', 8889))
)
cursor = conn.cursor()

cursor.execute("UPDATE planche SET prix_location_jour = ROUND(prix_achat * 0.05, 2)")
conn.commit()
print(f"{cursor.rowcount} planches mises à jour")
cursor.close()
conn.close()