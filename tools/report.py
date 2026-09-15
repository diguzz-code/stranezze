"""Produce un report read-only dal database Stranezze."""
from __future__ import annotations

import argparse
import json
import sqlite3
from pathlib import Path


def build_report(database_path: Path) -> dict[str, object]:
    connection = sqlite3.connect(f"file:{database_path}?mode=ro", uri=True)
    try:
        total = connection.execute("SELECT COUNT(*) FROM observations").fetchone()[0]
        favorites = connection.execute(
            "SELECT COUNT(*) FROM observations WHERE is_favorite = 1"
        ).fetchone()[0]
        categories = dict(
            connection.execute(
                "SELECT category, COUNT(*) FROM observations GROUP BY category ORDER BY category"
            ).fetchall()
        )
        return {"totale": total, "preferite": favorites, "per_categoria": categories}
    finally:
        connection.close()


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("database", type=Path, help="Percorso del file SQLite")
    parser.add_argument("--json", action="store_true", help="Stampa JSON")
    args = parser.parse_args()
    if not args.database.is_file():
        parser.error("database non trovato")
    report = build_report(args.database)
    if args.json:
        print(json.dumps(report, ensure_ascii=False, indent=2))
    else:
        print(f"Osservazioni: {report['totale']}")
        print(f"Preferite: {report['preferite']}")
        print("Per categoria:")
        for category, count in report["per_categoria"].items():
            print(f"- {category}: {count}")


if __name__ == "__main__":
    main()
