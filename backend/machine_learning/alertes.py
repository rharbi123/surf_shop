# =====================================================
# ALERTES.PY - Système d'alertes email WaveCraft IA
# Lit les logs et envoie un email si seuils dépassés
# Usage : python alertes.py
# =====================================================

import re
import os
import smtplib
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart
from datetime import datetime
from collections import defaultdict
from dotenv import load_dotenv

load_dotenv()

LOG_FILE = os.path.join(os.path.dirname(__file__), 'logs', 'recommandation.log')

# =====================================================
# CONFIG EMAIL
# =====================================================
EMAIL_EXPEDITEUR   = os.getenv('EMAIL_EXPEDITEUR', '')
EMAIL_DESTINATAIRE = os.getenv('EMAIL_DESTINATAIRE', '')
EMAIL_MOT_DE_PASSE = os.getenv('GMAIL_APP_PASSWORD', '')

SEUIL_TAUX_ECHEC   = 10    # % au-delà duquel on alerte
SEUIL_TEMPS_MOYEN  = 5000  # ms au-delà duquel on alerte

# =====================================================
# PARSING DES LOGS
# =====================================================
def parser_logs(log_file):
    succes = []
    echecs = []

    pattern_succes = re.compile(
        r'(?P<date>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}),\d+ \| INFO \| '
        r'niveau=(?P<niveau>[^|]+) \| budget=(?P<budget>[^|]+) \| '
        r'type=(?P<type>[^|]+) \| spot=(?P<spot>[^|]+) \| '
        r'(?:date=(?P<date_label>[^|]*) \| )?'
        r'taille_vagues=(?P<taille_vagues>[^|]+) \| '
        r'nb_resultats=(?P<nb_resultats>\d+) \| temps_ms=(?P<temps_ms>\d+) \| succes=True'
    )

    pattern_echec = re.compile(
        r'(?P<date>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}),\d+ \| WARNING \| '
        r'niveau=(?P<niveau>[^|]+) \| budget=(?P<budget>[^|]+) \| '
        r'type=(?P<type>[^|]+) \| spot=(?P<spot>[^|]+) \| '
        r'nb_resultats=0 \| temps_ms=(?P<temps_ms>\d+) \| '
        r'succes=False \| message=(?P<message>.+)'
    )

    if not os.path.exists(log_file):
        print(f"Fichier log introuvable : {log_file}")
        return succes, echecs

    with open(log_file, 'r', encoding='utf-8') as f:
        for line in f:
            m = pattern_succes.search(line)
            if m:
                succes.append(m.groupdict())
                continue
            m = pattern_echec.search(line)
            if m:
                echecs.append(m.groupdict())

    return succes, echecs

# =====================================================
# CALCUL DES MÉTRIQUES
# =====================================================
def calculer_metriques(succes, echecs):
    total       = len(succes) + len(echecs)
    taux_echec  = round(len(echecs) / total * 100, 1) if total > 0 else 0
    taux_succes = round(len(succes) / total * 100, 1) if total > 0 else 0

    temps_ms_list = [int(r['temps_ms']) for r in succes]
    temps_moyen   = round(sum(temps_ms_list) / len(temps_ms_list)) if temps_ms_list else 0

    return {
        'total': total,
        'nb_succes': len(succes),
        'nb_echecs': len(echecs),
        'taux_succes': taux_succes,
        'taux_echec': taux_echec,
        'temps_moyen': temps_moyen,
    }

# =====================================================
# VÉRIFICATION DES SEUILS
# =====================================================
def verifier_seuils(metriques):
    alertes = []

    if metriques['taux_echec'] > SEUIL_TAUX_ECHEC:
        alertes.append(
            f"Taux d'échec élevé : {metriques['taux_echec']}% (seuil : {SEUIL_TAUX_ECHEC}%)"
        )

    if metriques['temps_moyen'] > SEUIL_TEMPS_MOYEN:
        alertes.append(
            f"Temps de réponse moyen trop élevé : {metriques['temps_moyen']} ms (seuil : {SEUIL_TEMPS_MOYEN} ms)"
        )

    return alertes

# =====================================================
# ENVOI DE L'EMAIL
# =====================================================
def envoyer_email(alertes, metriques, derniers_echecs):
    if not EMAIL_MOT_DE_PASSE:
        print("❌ GMAIL_APP_PASSWORD manquant dans le fichier .env")
        return False

    # Construire le contenu HTML de l'email
    alertes_html = ''.join([
        f'<li style="color:#dc2626;margin-bottom:8px;">⚠️ {a}</li>'
        for a in alertes
    ])

    echecs_html = ''
    for e in derniers_echecs[-5:]:
        echecs_html += f'''
        <tr>
            <td style="padding:8px;border-bottom:1px solid #f1f5f9">{e["date"]}</td>
            <td style="padding:8px;border-bottom:1px solid #f1f5f9">{e["niveau"].strip()}</td>
            <td style="padding:8px;border-bottom:1px solid #f1f5f9">{e["type"].strip()}</td>
            <td style="padding:8px;border-bottom:1px solid #f1f5f9">{e["message"].strip()[:60]}...</td>
        </tr>'''

    html_body = f'''
    <div style="font-family:Segoe UI,sans-serif;max-width:600px;margin:0 auto">
        <div style="background:#0f172a;padding:24px;border-radius:8px 8px 0 0">
            <h1 style="color:white;margin:0;font-size:20px">🚨 WaveCraft — Alerte Monitoring IA</h1>
            <p style="color:#94a3b8;margin:4px 0 0;font-size:13px">
                Générée le {datetime.now().strftime('%d/%m/%Y à %H:%M:%S')}
            </p>
        </div>

        <div style="background:white;padding:24px;border:1px solid #e2e8f0">

            <h2 style="font-size:16px;color:#0f172a;margin-bottom:12px">Alertes détectées</h2>
            <ul style="margin:0;padding-left:20px">
                {alertes_html}
            </ul>

            <hr style="border:none;border-top:1px solid #f1f5f9;margin:24px 0">

            <h2 style="font-size:16px;color:#0f172a;margin-bottom:12px">Métriques actuelles</h2>
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <tr style="background:#f8fafc">
                    <td style="padding:8px">Total requêtes</td>
                    <td style="padding:8px;font-weight:600">{metriques['total']}</td>
                </tr>
                <tr>
                    <td style="padding:8px">Taux de succès</td>
                    <td style="padding:8px;font-weight:600;color:#16a34a">{metriques['taux_succes']}%</td>
                </tr>
                <tr style="background:#f8fafc">
                    <td style="padding:8px">Taux d'échec</td>
                    <td style="padding:8px;font-weight:600;color:#dc2626">{metriques['taux_echec']}%</td>
                </tr>
                <tr>
                    <td style="padding:8px">Temps moyen</td>
                    <td style="padding:8px;font-weight:600">{metriques['temps_moyen']} ms</td>
                </tr>
            </table>

            <hr style="border:none;border-top:1px solid #f1f5f9;margin:24px 0">

            <h2 style="font-size:16px;color:#0f172a;margin-bottom:12px">Derniers échecs</h2>
            <table style="width:100%;border-collapse:collapse;font-size:12px">
                <tr style="background:#f1f5f9">
                    <th style="padding:8px;text-align:left">Date</th>
                    <th style="padding:8px;text-align:left">Niveau</th>
                    <th style="padding:8px;text-align:left">Type</th>
                    <th style="padding:8px;text-align:left">Message</th>
                </tr>
                {echecs_html if echecs_html else '<tr><td colspan="4" style="padding:8px;color:#16a34a">Aucun échec récent</td></tr>'}
            </table>

        </div>

        <div style="background:#f8fafc;padding:12px 24px;border-radius:0 0 8px 8px;border:1px solid #e2e8f0;border-top:none">
            <p style="margin:0;font-size:11px;color:#94a3b8;text-align:center">
                WaveCraft Monitoring · Alerte automatique générée depuis logs/recommandation.log
            </p>
        </div>
    </div>
    '''

    msg = MIMEMultipart('alternative')
    msg['Subject'] = f'🚨 WaveCraft — Alerte IA : {len(alertes)} problème(s) détecté(s)'
    msg['From']    = EMAIL_EXPEDITEUR
    msg['To']      = EMAIL_DESTINATAIRE
    msg.attach(MIMEText(html_body, 'html'))

    try:
        with smtplib.SMTP('smtp.gmail.com', 587) as server:
            server.starttls()
            server.login(EMAIL_EXPEDITEUR, EMAIL_MOT_DE_PASSE)
            server.sendmail(EMAIL_EXPEDITEUR, EMAIL_DESTINATAIRE, msg.as_string())
        print(f"✅ Email envoyé à {EMAIL_DESTINATAIRE}")
        return True
    except Exception as e:
        print(f"❌ Erreur envoi email : {e}")
        return False

# =====================================================
# MAIN
# =====================================================
if __name__ == '__main__':
    print("🔍 WaveCraft — Vérification des alertes...")

    succes, echecs = parser_logs(LOG_FILE)
    metriques      = calculer_metriques(succes, echecs)
    alertes        = verifier_seuils(metriques)

    print(f"   Total requêtes : {metriques['total']}")
    print(f"   Taux d'échec   : {metriques['taux_echec']}%")
    print(f"   Temps moyen    : {metriques['temps_moyen']} ms")
    print(f"   Alertes        : {len(alertes)}")

    if alertes:
        print("\n⚠️  Alertes détectées :")
        for a in alertes:
            print(f"   - {a}")
        print("\n📧 Envoi de l'email...")
        envoyer_email(alertes, metriques, echecs)
    else:
        print("\n✅ Aucune alerte — tous les seuils sont respectés.")