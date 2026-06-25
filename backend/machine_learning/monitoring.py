# =====================================================
# MONITORING.PY - Tableau de bord WaveCraft IA
# Lit les logs de recommandation et génère un rapport HTML
# Usage : python monitoring.py
# =====================================================

import re
import os
from datetime import datetime
from collections import defaultdict

LOG_FILE    = os.path.join(os.path.dirname(__file__), 'logs', 'recommandation.log')
OUTPUT_HTML = os.path.join(os.path.dirname(__file__), 'logs', 'rapport_monitoring.html')

# =====================================================
# PARSING DES LOGS
# =====================================================
def parser_logs(log_file):
    succes  = []
    echecs  = []
    alertes = []

    # date= est optionnel selon les versions du log
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

    pattern_alerte = re.compile(
        r'(?P<date>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}),\d+ \| CRITICAL \| '
        r'(?P<message>.+)'
    )

    if not os.path.exists(log_file):
        print(f"Fichier log introuvable : {log_file}")
        return succes, echecs, alertes

    with open(log_file, 'r', encoding='utf-8') as f:
        for line in f:
            m = pattern_succes.search(line)
            if m:
                succes.append(m.groupdict())
                continue
            m = pattern_echec.search(line)
            if m:
                echecs.append(m.groupdict())
                continue
            m = pattern_alerte.search(line)
            if m:
                alertes.append(m.groupdict())

    return succes, echecs, alertes

# =====================================================
# CALCUL DES MÉTRIQUES
# =====================================================
def calculer_metriques(succes, echecs):
    total       = len(succes) + len(echecs)
    taux_succes = round(len(succes) / total * 100, 1) if total > 0 else 0
    taux_echec  = round(len(echecs) / total * 100, 1) if total > 0 else 0

    temps_ms_list = [int(r['temps_ms']) for r in succes]
    temps_moyen   = round(sum(temps_ms_list) / len(temps_ms_list)) if temps_ms_list else 0
    temps_max     = max(temps_ms_list) if temps_ms_list else 0
    temps_min     = min(temps_ms_list) if temps_ms_list else 0

    par_niveau = defaultdict(int)
    par_type   = defaultdict(int)

    for r in succes:
        par_niveau[r['niveau'].strip()] += 1
        par_type[r['type'].strip()]     += 1

    for r in echecs:
        par_niveau[r['niveau'].strip()] += 0
        par_type[r['type'].strip()]     += 0

    nb_resultats_list = [int(r['nb_resultats']) for r in succes]
    moy_resultats = round(sum(nb_resultats_list) / len(nb_resultats_list), 1) if nb_resultats_list else 0

    return {
        'total': total,
        'nb_succes': len(succes),
        'nb_echecs': len(echecs),
        'taux_succes': taux_succes,
        'taux_echec': taux_echec,
        'temps_moyen': temps_moyen,
        'temps_max': temps_max,
        'temps_min': temps_min,
        'par_niveau': dict(par_niveau),
        'par_type': dict(par_type),
        'moy_resultats': moy_resultats,
    }

# =====================================================
# VÉRIFICATION DES SEUILS (ALERTES)
# =====================================================
def verifier_seuils(metriques, alertes_critiques):
    alertes = []

    if metriques['taux_echec'] > 10:
        alertes.append({
            'niveau': 'CRITIQUE',
            'message': f"Taux d'échec élevé : {metriques['taux_echec']}% (seuil : 10%)"
        })

    if metriques['temps_moyen'] > 5000:
        alertes.append({
            'niveau': 'CRITIQUE',
            'message': f"Temps de réponse moyen élevé : {metriques['temps_moyen']} ms (seuil : 5000 ms)"
        })

    if metriques['temps_max'] > 10000:
        alertes.append({
            'niveau': 'AVERTISSEMENT',
            'message': f"Pic de temps de réponse détecté : {metriques['temps_max']} ms"
        })

    for a in alertes_critiques:
        alertes.append({'niveau': 'CRITIQUE', 'message': a['message']})

    return alertes

# =====================================================
# GÉNÉRATION DU RAPPORT HTML
# =====================================================
def generer_html(metriques, alertes, succes, echecs):

    niveaux    = list(metriques['par_niveau'].keys())
    nb_niveaux = list(metriques['par_niveau'].values())
    types      = list(metriques['par_type'].keys())
    nb_types   = list(metriques['par_type'].values())

    alertes_html = ''
    if alertes:
        for a in alertes:
            color = '#dc2626' if a['niveau'] == 'CRITIQUE' else '#d97706'
            alertes_html += f'<div style="background:{color}15;border-left:4px solid {color};padding:10px 16px;border-radius:4px;margin-bottom:8px;"><strong style="color:{color}">{a["niveau"]}</strong> — {a["message"]}</div>'
    else:
        alertes_html = '<div style="background:#16a34a15;border-left:4px solid #16a34a;padding:10px 16px;border-radius:4px;color:#16a34a"><strong>Aucune alerte</strong> — Tous les seuils sont respectés</div>'

    derniers_echecs = ''
    for e in echecs[-5:]:
        derniers_echecs += f'<tr><td>{e["date"]}</td><td>{e["niveau"].strip()}</td><td>{e["type"].strip()}</td><td>{e["message"].strip()[:60]}...</td></tr>'

    html = f'''<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>WaveCraft — Monitoring IA</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
  * {{ box-sizing: border-box; margin: 0; padding: 0; }}
  body {{ font-family: 'Segoe UI', sans-serif; background: #f8fafc; color: #1e293b; }}
  .header {{ background: #0f172a; color: white; padding: 24px 40px; }}
  .header h1 {{ font-size: 22px; font-weight: 600; }}
  .header p {{ font-size: 13px; color: #94a3b8; margin-top: 4px; }}
  .container {{ max-width: 1100px; margin: 0 auto; padding: 32px 24px; }}
  .kpi-grid {{ display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 32px; }}
  .kpi {{ background: white; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }}
  .kpi .val {{ font-size: 32px; font-weight: 700; margin: 8px 0 4px; }}
  .kpi .label {{ font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: .5px; }}
  .kpi.success .val {{ color: #16a34a; }}
  .kpi.error .val {{ color: #dc2626; }}
  .kpi.time .val {{ color: #2563eb; }}
  .kpi.total .val {{ color: #7c3aed; }}
  .section {{ background: white; border-radius: 10px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.08); margin-bottom: 24px; }}
  .section h2 {{ font-size: 15px; font-weight: 600; margin-bottom: 16px; color: #0f172a; }}
  .charts {{ display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }}
  table {{ width: 100%; border-collapse: collapse; font-size: 13px; }}
  th {{ background: #f1f5f9; padding: 10px 12px; text-align: left; font-weight: 600; color: #475569; }}
  td {{ padding: 10px 12px; border-bottom: 1px solid #f1f5f9; color: #334155; }}
  tr:last-child td {{ border-bottom: none; }}
  .badge {{ display: inline-block; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; }}
  .badge.ok {{ background: #dcfce7; color: #16a34a; }}
  .badge.err {{ background: #fee2e2; color: #dc2626; }}
  .generated {{ text-align: center; color: #94a3b8; font-size: 12px; margin-top: 32px; }}
</style>
</head>
<body>
<div class="header">
  <h1>🤖 WaveCraft — Tableau de bord Monitoring IA</h1>
  <p>Généré le {datetime.now().strftime('%d/%m/%Y à %H:%M:%S')} · Modèle KNN + Llama 3.3</p>
</div>
<div class="container">

  <div class="kpi-grid">
    <div class="kpi total">
      <div class="label">Total requêtes</div>
      <div class="val">{metriques['total']}</div>
    </div>
    <div class="kpi success">
      <div class="label">Taux de succès</div>
      <div class="val">{metriques['taux_succes']}%</div>
    </div>
    <div class="kpi error">
      <div class="label">Taux d'échec</div>
      <div class="val">{metriques['taux_echec']}%</div>
    </div>
    <div class="kpi time">
      <div class="label">Temps moyen</div>
      <div class="val">{metriques['temps_moyen']} ms</div>
    </div>
  </div>

  <div class="section">
    <h2>⚠️ Alertes</h2>
    {alertes_html}
  </div>

  <div class="charts">
    <div class="section">
      <h2>Requêtes par niveau</h2>
      <canvas id="chartNiveau" height="200"></canvas>
    </div>
    <div class="section">
      <h2>Répartition achat / location</h2>
      <canvas id="chartType" height="200"></canvas>
    </div>
  </div>

  <div class="section">
    <h2>Métriques détaillées</h2>
    <table>
      <tr><th>Métrique</th><th>Valeur</th><th>Statut</th></tr>
      <tr><td>Temps de réponse minimum</td><td>{metriques['temps_min']} ms</td><td><span class="badge ok">OK</span></td></tr>
      <tr><td>Temps de réponse moyen</td><td>{metriques['temps_moyen']} ms</td><td><span class="badge {'ok' if metriques['temps_moyen'] < 5000 else 'err'}">{'OK' if metriques['temps_moyen'] < 5000 else 'CRITIQUE'}</span></td></tr>
      <tr><td>Temps de réponse maximum</td><td>{metriques['temps_max']} ms</td><td><span class="badge {'ok' if metriques['temps_max'] < 10000 else 'err'}">{'OK' if metriques['temps_max'] < 10000 else 'AVERTISSEMENT'}</span></td></tr>
      <tr><td>Nombre moyen de résultats</td><td>{metriques['moy_resultats']} planches</td><td><span class="badge ok">OK</span></td></tr>
      <tr><td>Taux d'échec</td><td>{metriques['taux_echec']}%</td><td><span class="badge {'ok' if metriques['taux_echec'] <= 10 else 'err'}">{'OK' if metriques['taux_echec'] <= 10 else 'CRITIQUE'}</span></td></tr>
    </table>
  </div>

  <div class="section">
    <h2>Derniers échecs (5 derniers)</h2>
    <table>
      <tr><th>Date</th><th>Niveau</th><th>Type</th><th>Message</th></tr>
      {derniers_echecs if derniers_echecs else '<tr><td colspan="4" style="color:#16a34a;text-align:center">Aucun échec enregistré</td></tr>'}
    </table>
  </div>

  <p class="generated">WaveCraft Monitoring · Rapport généré automatiquement depuis logs/recommandation.log</p>
</div>

<script>
new Chart(document.getElementById('chartNiveau'), {{
  type: 'bar',
  data: {{
    labels: {niveaux},
    datasets: [{{ label: 'Requêtes', data: {nb_niveaux}, backgroundColor: ['#818cf8','#34d399','#fb923c'] }}]
  }},
  options: {{ plugins: {{ legend: {{ display: false }} }}, scales: {{ y: {{ beginAtZero: true }} }} }}
}});

new Chart(document.getElementById('chartType'), {{
  type: 'doughnut',
  data: {{
    labels: {types},
    datasets: [{{ data: {nb_types}, backgroundColor: ['#60a5fa','#4ade80'] }}]
  }},
  options: {{ plugins: {{ legend: {{ position: 'bottom' }} }} }}
}});
</script>
</body>
</html>'''

    return html

# =====================================================
# MAIN
# =====================================================
if __name__ == '__main__':
    print("📊 WaveCraft — Génération du rapport de monitoring...")

    succes, echecs, alertes_critiques = parser_logs(LOG_FILE)
    metriques = calculer_metriques(succes, echecs)
    alertes   = verifier_seuils(metriques, alertes_critiques)

    print(f"   Requêtes analysées : {metriques['total']}")
    print(f"   Succès             : {metriques['nb_succes']}")
    print(f"   Échecs             : {metriques['nb_echecs']}")
    print(f"   Taux de succès     : {metriques['taux_succes']}%")
    print(f"   Temps moyen        : {metriques['temps_moyen']} ms")
    print(f"   Répartition types  : {dict(metriques['par_type'])}")
    print(f"   Alertes détectées  : {len(alertes)}")

    html = generer_html(metriques, alertes, succes, echecs)

    os.makedirs(os.path.dirname(OUTPUT_HTML), exist_ok=True)
    with open(OUTPUT_HTML, 'w', encoding='utf-8') as f:
        f.write(html)

    print(f"\n✅ Rapport généré : {OUTPUT_HTML}")