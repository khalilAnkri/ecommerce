"""Build the zip to upload on Lol@ for the PHP project (deadline 26/11).

Usage (from the repo root):
    py make_zip.py

Set GROUP and BUSINESS_MODEL below once, then just run it.
Only the items listed in INCLUDE go into the zip (whitelist), so notebooks/,
.venv/, .git/ and any stray file are left out automatically.
"""
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent

# ---- Settings -------------------------------------------------------------
GROUP = "2"                                   # our group number
BUSINESS_MODEL = ROOT / "business_model.pdf"  # the Business Model PDF
ZIP_ROOT = "poilu-and-co"                    # folder name inside the zip (as in README)
# --------------------------------------------------------------------------

# Website files + database + Python support for the website
INCLUDE = [
    "index.php",
    "pages",
    "includes",
    "assets",
    "database",
    "analytics",
    "README.md",
    "SETUP_GUIDE.md",
]

# Never zipped, even inside the folders above
SKIP_PARTS = {".ipynb_checkpoints", "__pycache__", ".venv"}


def main():
    if GROUP == "X":
        raise SystemExit("Set GROUP at the top of make_zip.py first.")

    out = ROOT / f"group{GROUP}.zip"
    count = 0
    with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as z:
        for item in INCLUDE:
            path = ROOT / item
            if not path.exists():
                print(f"  missing, skipped: {item}")
                continue
            files = [path] if path.is_file() else sorted(p for p in path.rglob("*") if p.is_file())
            for f in files:
                if SKIP_PARTS & set(f.parts):
                    continue
                z.write(f, f"{ZIP_ROOT}/{f.relative_to(ROOT).as_posix()}")
                count += 1
        if BUSINESS_MODEL.exists():
            z.write(BUSINESS_MODEL, f"{ZIP_ROOT}/business_model.pdf")
            count += 1
        else:
            print(f"  WARNING: {BUSINESS_MODEL.name} not found, the Business Model is NOT in the zip")

    print(f"{out.name}: {count} files")


if __name__ == "__main__":
    main()
