"""
SCRAPING - glisse-proshop.com
5 pages - ~100 planches
"""

import requests
from bs4 import BeautifulSoup
import json
import time
import os
import logging
import re

BASE_URL = "https://glisse-proshop.com"

PAGES = [
    "https://glisse-proshop.com/surf/planches-de-surf.html",
    "https://glisse-proshop.com/surf/surfboard-planche-rigide.html?pageNumber-9=2",
    "https://glisse-proshop.com/surf/surfboard-planche-rigide.html?pageNumber-9=3",
    "https://glisse-proshop.com/surf/surfboard-planche-rigide.html?pageNumber-9=4",
    "https://glisse-proshop.com/surf/surfboard-planche-rigide.html?pageNumber-9=5",
]

OUTPUT_FILE = "data/raw/planches.json"
os.makedirs("data/raw", exist_ok=True)
os.makedirs("logs", exist_ok=True)

HEADERS = {
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36"
}

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    handlers=[
        logging.FileHandler("logs/scraping.log", encoding="utf-8"),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)


def get_urls_depuis_page(page_url):
    response = requests.get(page_url, headers=HEADERS, timeout=15)
    soup = BeautifulSoup(response.text, "html.parser")

    urls = []
    for a in soup.select("a[href]"):
        href = a.get("href", "")
        if re.search(r'-\d{5,}\.html$', href):
            if href.startswith("http"):
                url = href
            else:
                url = BASE_URL + "/" + href.lstrip("/")
            if url not in urls and "glisse-proshop.com" in url:
                urls.append(url)

    urls = [u for u in urls if any(kw in u for kw in [
        "surfboard", "softboard", "planches-epoxy", "planche-surf"
    ])]

    return urls


def extraire_json_produit(soup):
    scripts = soup.find_all("script", type="text/javascript")
    for script in scripts:
        if script.string and "window.__change['4']" in script.string:
            match = re.search(r"window\.__change\['4'\]\s*=\s*(\{.*?\});\s*\n", script.string, re.DOTALL)
            if match:
                try:
                    return json.loads(match.group(1))
                except json.JSONDecodeError:
                    pass
    return None


def get_attr(data, key):
    try:
        attr = data["typology"]["attributes"][key]
        fv = attr.get("formattedValue", {})
        if fv:
            return fv.get("common", {}).get("title", "Non disponible")
        val = attr.get("value")
        if val:
            return str(val)
    except (KeyError, TypeError):
        pass
    return "Non disponible"


def get_attr_array(data, key):
    try:
        attr = data["typology"]["attributes"][key]
        val = attr.get("value", [])
        if isinstance(val, list) and val:
            return ", ".join([v.get("common", {}).get("title", "") for v in val])
    except (KeyError, TypeError):
        pass
    return "Non disponible"


def scrape_detail(url):
    try:
        response = requests.get(url, headers=HEADERS, timeout=15)
        soup = BeautifulSoup(response.text, "html.parser")

        data = extraire_json_produit(soup)
        if not data:
            logger.warning(f"JSON non trouve pour {url}")
            return None

        common = data.get("common", {})

        nom = re.sub(r'\s+', ' ', common.get("title", "Non disponible")).strip()

        marque = "Non disponible"
        brand = common.get("brand", {})
        if brand:
            marque = brand.get("common", {}).get("title", "Non disponible")

        prix = "Non disponible"
        price_data = data.get("price", {})
        if price_data:
            val = price_data.get("valueWithTax")
            if val:
                prix = f"{val} €"

        photo = "Non disponible"
        visuals = common.get("visuals", [])
        if visuals:
            photo = visuals[0].get("original", "Non disponible")

        shape = get_attr(data, "GLIS_CHAR_TYPE_SURF")
        niveau = get_attr(data, "glis_char_level")
        taille_vagues = get_attr(data, "glis_char_wave_size")
        nb_derives = get_attr(data, "glis_char_fins_number")
        conception = get_attr(data, "GLIS_CHAR_BOARD_SUP_TYPE")
        boitiers = get_attr_array(data, "GLIS_CHAR_BOITIER_DERIVE")

        volume = "Non disponible"
        try:
            grid = data["typology"]["attributes"]["glis_char_grid"]["value"]
            volumes = grid.get("Volume", [])
            if volumes:
                volume = " / ".join(volumes)
        except (KeyError, TypeError):
            pass

        description = "Non disponible"
        try:
            short_html = data["typology"]["attributes"]["glis_short_desc"]["value"]
            if short_html:
                desc_soup = BeautifulSoup(short_html, "html.parser")
                description = re.sub(r'\s+', ' ', desc_soup.get_text(separator=" ")).strip()
        except (KeyError, TypeError):
            pass

        return {
            "nom": nom,
            "marque": marque,
            "prix": prix,
            "shape": shape,
            "volume": volume,
            "niveau": niveau,
            "taille_vagues": taille_vagues,
            "nb_derives": nb_derives,
            "boitiers": boitiers,
            "conception": conception,
            "description": description,
            "photo": photo,
            "url": url,
            "source": "glisse-proshop.com"
        }

    except Exception as e:
        logger.error(f"Erreur sur {url} : {e}")
        return None


def run():
    logger.info("=" * 50)
    logger.info("DEMARRAGE SCRAPING - 5 PAGES")
    logger.info("=" * 50)

    # Collecte toutes les URLs sans doublons
    toutes_urls = []
    for i, page_url in enumerate(PAGES):
        logger.info(f"Page {i+1}/5 : {page_url}")
        urls = get_urls_depuis_page(page_url)
        nouvelles = [u for u in urls if u not in toutes_urls]
        toutes_urls.extend(nouvelles)
        logger.info(f"  -> {len(nouvelles)} nouvelles URLs ({len(toutes_urls)} total)")
        time.sleep(2)

    logger.info(f"Total URLs a scraper : {len(toutes_urls)}")

    # Scrape chaque planche
    planches = []
    for i, url in enumerate(toutes_urls):
        logger.info(f"Scraping {i+1}/{len(toutes_urls)} : {url}")
        planche = scrape_detail(url)
        if planche:
            planches.append(planche)
            logger.info(f"  -> OK : {planche['nom']} | {planche['marque']} | {planche['shape']}")
        else:
            logger.warning(f"  -> ECHEC")
        time.sleep(1)

    with open(OUTPUT_FILE, "w", encoding="utf-8") as f:
        json.dump(planches, f, ensure_ascii=False, indent=2)

    logger.info("=" * 50)
    logger.info(f"TERMINE : {len(planches)} planches -> {OUTPUT_FILE}")
    logger.info("=" * 50)


if __name__ == "__main__":
    run()