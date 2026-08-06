#!/usr/bin/env python3
"""
LittyWatch GW1 Catalog Builder

Builds an item catalog from Guild Wars Wiki using the MediaWiki API.
Designed for GitHub Actions so the user's hosting server does not crawl the wiki.

Outputs:
  output/items.json
  output/items.csv
  output/icons/*.png|jpg|webp
  output/report.json
"""

from __future__ import annotations

import argparse
import csv
import hashlib
import json
import logging
import re
import sys
import time
from dataclasses import asdict, dataclass
from pathlib import Path
from typing import Any, Iterable
from urllib.parse import quote

import requests

API = "https://wiki.guildwars.com/api.php"
BASE = "https://wiki.guildwars.com"
USER_AGENT = (
    "LittyWatchCatalogBuilder/1.0 "
    "(Guild Wars item catalog; https://github.com/Rickboy26/LittyWatch)"
)

ITEM_TEMPLATE_PATTERNS = (
    r"\{\{\s*item infobox\b",
    r"\{\{\s*weapon infobox\b",
    r"\{\{\s*armor infobox\b",
    r"\{\{\s*miniature infobox\b",
    r"\{\{\s*material infobox\b",
    r"\{\{\s*key infobox\b",
    r"\{\{\s*consumable infobox\b",
    r"\{\{\s*trophy infobox\b",
)

SKIP_NAMESPACES = ("Category:", "File:", "Template:", "Guild Wars Wiki:", "Help:", "User:")
FILE_PARAM_RE = re.compile(
    r"^\s*\|\s*(?:image|icon|inventory image|inventory_icon)\s*=\s*(.+?)\s*$",
    re.I | re.M,
)
TYPE_PARAM_RE = re.compile(r"^\s*\|\s*(?:type|item type)\s*=\s*(.+?)\s*$", re.I | re.M)
RARITY_PARAM_RE = re.compile(r"^\s*\|\s*rarity\s*=\s*(.+?)\s*$", re.I | re.M)


@dataclass
class CatalogItem:
    name: str
    slug: str
    wiki_url: str
    page_id: int | None
    item_type: str | None
    rarity: str | None
    icon_source_url: str | None
    icon_file: str | None
    source_category: str
    status: str


class WikiClient:
    def __init__(self, delay: float = 0.65, timeout: int = 45) -> None:
        self.delay = delay
        self.timeout = timeout
        self.last_request = 0.0
        self.session = requests.Session()
        self.session.headers.update(
            {
                "User-Agent": USER_AGENT,
                "Accept": "application/json",
                "Accept-Language": "en-US,en;q=0.9",
                "Referer": BASE + "/wiki/Main_Page",
            }
        )

    def request(self, params: dict[str, Any], attempts: int = 5) -> dict[str, Any]:
        params = {"format": "json", "formatversion": "2", **params}
        last_error: Exception | None = None

        for attempt in range(1, attempts + 1):
            elapsed = time.monotonic() - self.last_request
            if elapsed < self.delay:
                time.sleep(self.delay - elapsed)

            try:
                response = self.session.get(API, params=params, timeout=self.timeout)
                self.last_request = time.monotonic()

                if response.status_code == 200:
                    data = response.json()
                    if "error" in data:
                        raise RuntimeError(f"MediaWiki API: {data['error']}")
                    return data

                if response.status_code not in (403, 408, 425, 429, 500, 502, 503, 504):
                    response.raise_for_status()

                last_error = RuntimeError(
                    f"HTTP {response.status_code}: {response.text[:200]}"
                )
            except (requests.RequestException, ValueError, RuntimeError) as exc:
                last_error = exc

            if attempt < attempts:
                time.sleep(min(30, attempt * 3))

        raise RuntimeError(f"Wiki request failed after {attempts} attempts: {last_error}")

    def category_members(self, category: str, member_type: str) -> Iterable[dict[str, Any]]:
        cont: dict[str, Any] = {}
        while True:
            data = self.request(
                {
                    "action": "query",
                    "list": "categorymembers",
                    "cmtitle": category,
                    "cmtype": member_type,
                    "cmlimit": "max",
                    **cont,
                }
            )
            yield from data.get("query", {}).get("categorymembers", [])
            if "continue" not in data:
                break
            cont = data["continue"]

    def page_wikitext(self, titles: list[str]) -> list[dict[str, Any]]:
        data = self.request(
            {
                "action": "query",
                "prop": "revisions",
                "rvprop": "content",
                "rvslots": "main",
                "titles": "|".join(titles),
            }
        )
        return data.get("query", {}).get("pages", [])

    def image_info(self, filename: str) -> str | None:
        if not filename.lower().startswith("file:"):
            filename = "File:" + filename
        data = self.request(
            {
                "action": "query",
                "prop": "imageinfo",
                "iiprop": "url",
                "titles": filename,
            }
        )
        pages = data.get("query", {}).get("pages", [])
        if not pages:
            return None
        infos = pages[0].get("imageinfo", [])
        return infos[0].get("url") if infos else None


def clean_value(value: str | None) -> str | None:
    if not value:
        return None
    value = re.sub(r"<!--.*?-->", "", value, flags=re.S)
    value = re.sub(r"\{\{.*?\}\}", "", value)
    value = re.sub(r"\[\[(?:[^|\]]+\|)?([^\]]+)\]\]", r"\1", value)
    value = value.strip().strip("[]{}")
    return value or None


def slugify(name: str) -> str:
    text = name.lower()
    text = re.sub(r"['’]", "", text)
    text = re.sub(r"[^a-z0-9]+", "-", text).strip("-")
    if text:
        return text
    return hashlib.sha1(name.encode("utf-8")).hexdigest()[:16]


def is_item_page(title: str, wikitext: str) -> bool:
    if title.startswith(SKIP_NAMESPACES):
        return False
    lower = wikitext.lower()
    return any(re.search(pattern, lower, re.I) for pattern in ITEM_TEMPLATE_PATTERNS)


def extract_revision_text(page: dict[str, Any]) -> str:
    revisions = page.get("revisions") or []
    if not revisions:
        return ""
    slots = revisions[0].get("slots") or {}
    main = slots.get("main") or {}
    return main.get("content") or ""


def discover_categories(
    client: WikiClient, roots: list[str], depth: int
) -> dict[str, int]:
    found: dict[str, int] = {}
    queue = [(root, 0) for root in roots]

    while queue:
        category, current_depth = queue.pop(0)
        if category in found and found[category] <= current_depth:
            continue
        found[category] = current_depth
        logging.info("Category %s (depth %d)", category, current_depth)

        if current_depth >= depth:
            continue

        for member in client.category_members(category, "subcat"):
            title = member.get("title")
            if isinstance(title, str):
                queue.append((title, current_depth + 1))

    return found


def chunks(values: list[str], size: int) -> Iterable[list[str]]:
    for index in range(0, len(values), size):
        yield values[index : index + size]


def download_icon(session: requests.Session, url: str, output_dir: Path, slug: str) -> str:
    response = session.get(url, timeout=60)
    response.raise_for_status()

    content_type = response.headers.get("content-type", "").lower()
    extension = Path(url.split("?", 1)[0]).suffix.lower()
    if extension not in (".png", ".jpg", ".jpeg", ".webp", ".gif"):
        extension = ".png" if "png" in content_type else ".jpg"

    filename = slug + extension
    path = output_dir / filename
    path.write_bytes(response.content)
    return filename


def build(args: argparse.Namespace) -> int:
    output = Path(args.output)
    icon_dir = output / "icons"
    output.mkdir(parents=True, exist_ok=True)
    icon_dir.mkdir(parents=True, exist_ok=True)

    client = WikiClient(delay=args.delay)
    categories = discover_categories(client, args.category, args.depth)

    page_sources: dict[str, str] = {}
    for category in categories:
        for member in client.category_members(category, "page"):
            title = member.get("title")
            if isinstance(title, str) and not title.startswith(SKIP_NAMESPACES):
                page_sources.setdefault(title, category)

    titles = sorted(page_sources)
    logging.info("Discovered %d candidate pages", len(titles))

    results: list[CatalogItem] = []
    icon_errors = 0
    skipped = 0

    for batch_number, batch in enumerate(chunks(titles, 40), start=1):
        logging.info("Reading batch %d (%d pages)", batch_number, len(batch))
        for page in client.page_wikitext(batch):
            title = page.get("title")
            if not isinstance(title, str):
                continue

            text = extract_revision_text(page)
            if not is_item_page(title, text):
                skipped += 1
                continue

            image_match = FILE_PARAM_RE.search(text)
            image_name = clean_value(image_match.group(1)) if image_match else None
            type_match = TYPE_PARAM_RE.search(text)
            rarity_match = RARITY_PARAM_RE.search(text)
            item_type = clean_value(type_match.group(1)) if type_match else None
            rarity = clean_value(rarity_match.group(1)) if rarity_match else None

            icon_url = None
            icon_file = None
            status = "ok"

            if image_name:
                try:
                    icon_url = client.image_info(image_name)
                    if icon_url and args.download_icons:
                        icon_file = download_icon(
                            client.session, icon_url, icon_dir, slugify(title)
                        )
                    elif not icon_url:
                        status = "icon-not-found"
                except Exception as exc:
                    icon_errors += 1
                    status = f"icon-error: {exc}"
                    logging.warning("Icon failed for %s: %s", title, exc)
            else:
                status = "no-icon-in-infobox"

            results.append(
                CatalogItem(
                    name=title,
                    slug=slugify(title),
                    wiki_url=BASE + "/wiki/" + quote(title.replace(" ", "_")),
                    page_id=page.get("pageid"),
                    item_type=item_type,
                    rarity=rarity,
                    icon_source_url=icon_url,
                    icon_file=icon_file,
                    source_category=page_sources.get(title, ""),
                    status=status,
                )
            )

    results.sort(key=lambda item: item.name.casefold())

    with (output / "items.json").open("w", encoding="utf-8") as handle:
        json.dump([asdict(item) for item in results], handle, ensure_ascii=False, indent=2)

    with (output / "items.csv").open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=list(asdict(results[0]).keys()) if results else [
            "name", "slug", "wiki_url", "page_id", "item_type", "rarity",
            "icon_source_url", "icon_file", "source_category", "status"
        ])
        writer.writeheader()
        for item in results:
            writer.writerow(asdict(item))

    report = {
        "categories": len(categories),
        "candidate_pages": len(titles),
        "items": len(results),
        "icons_downloaded": sum(1 for item in results if item.icon_file),
        "icon_errors": icon_errors,
        "skipped_non_items": skipped,
        "roots": args.category,
        "depth": args.depth,
    }
    (output / "report.json").write_text(
        json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8"
    )
    print(json.dumps(report, ensure_ascii=False, indent=2))
    return 0


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "--category",
        action="append",
        default=[],
        help="Root category; repeat for multiple roots",
    )
    parser.add_argument("--depth", type=int, default=2)
    parser.add_argument("--delay", type=float, default=0.75)
    parser.add_argument("--output", default="output")
    parser.add_argument(
        "--download-icons",
        action=argparse.BooleanOptionalAction,
        default=True,
    )
    args = parser.parse_args()
    if not args.category:
        args.category = [
            "Category:Miniatures",
            "Category:Crafting materials",
            "Category:Keys",
            "Category:Rare items",
            "Category:Containers",
            "Category:Consumables",
            "Category:Trophies",
            "Category:Weapons",
        ]
    return args


if __name__ == "__main__":
    logging.basicConfig(
        level=logging.INFO,
        format="%(asctime)s %(levelname)s %(message)s",
    )
    try:
        raise SystemExit(build(parse_args()))
    except KeyboardInterrupt:
        raise SystemExit(130)
    except Exception as exc:
        logging.exception("Catalog build failed: %s", exc)
        raise SystemExit(1)
