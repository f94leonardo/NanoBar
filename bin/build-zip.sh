#!/usr/bin/env bash
# Build script: crea il pacchetto ZIP di produzione del plugin.
# Uso: bash bin/build-zip.sh [--skip-npm] [--skip-lint]
set -euo pipefail

PLUGIN_SLUG="nanobar"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"

# ── Argomenti ────────────────────────────────────────────────────────────────
SKIP_NPM=false
SKIP_LINT=false
for arg in "$@"; do
    case "$arg" in
        --skip-npm)  SKIP_NPM=true ;;
        --skip-lint) SKIP_LINT=true ;;
        -h|--help)
            echo "Uso: bash bin/build-zip.sh [--skip-npm] [--skip-lint]"
            echo ""
            echo "  --skip-npm   Non esegue 'npm run build' (usa gli asset CSS già compilati)"
            echo "  --skip-lint  Non esegue 'composer run phpcs' prima del build"
            exit 0
            ;;
    esac
done

# ── Versione ─────────────────────────────────────────────────────────────────
VERSION=$(grep -m1 "^\s*\* Version:" "$PLUGIN_DIR/nanobar.php" | sed 's/.*Version:\s*//' | tr -d '[:space:]')
if [[ -z "$VERSION" ]]; then
    echo "ERRORE: impossibile leggere la versione dall'header del plugin (nanobar.php)." >&2
    exit 1
fi

ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
DIST_DIR="${PLUGIN_DIR}/dist"
ZIP_PATH="${DIST_DIR}/${ZIP_NAME}"
BUILD_DIR=$(mktemp -d)
DEST="${BUILD_DIR}/${PLUGIN_SLUG}"

echo "════════════════════════════════════════════════"
echo " NanoBar  v${VERSION}"
echo " Output: ${ZIP_PATH}"
echo "════════════════════════════════════════════════"

# Pulizia garantita all'uscita (anche in caso di errore)
trap 'echo "→ Pulizia build dir..."; rm -rf "$BUILD_DIR"' EXIT

cd "$PLUGIN_DIR"

# ── 1. Lint ───────────────────────────────────────────────────────────────────
if [[ "$SKIP_LINT" == false ]]; then
    echo ""
    echo "→ [1/4] Esecuzione phpcs..."
    # phpcs esce con 0 (pulito) o 1 (errori/warning trovati) in condizioni
    # normali; qualsiasi altro exit code è un crash (es. PHP fatal error) e va
    # trattato come un fallimento del build, non silenziosamente come "0 errori".
    set +e
    PHPCS_OUT=$(vendor/bin/phpcs --report=summary 2>&1)
    PHPCS_EXIT=$?
    set -e
    if [[ "$PHPCS_EXIT" -gt 1 ]]; then
        echo "$PHPCS_OUT"
        echo ""
        echo "ERRORE: phpcs è terminato in modo anomalo (exit ${PHPCS_EXIT}), non per violazioni di stile." >&2
        exit 1
    fi
    PHPCS_ERRORS=$(echo "$PHPCS_OUT" | grep -oE 'A TOTAL OF [0-9]+ ERROR' | grep -oE '[0-9]+' | head -1 || echo "0")
    if [[ "${PHPCS_ERRORS:-0}" -gt 0 ]]; then
        echo "$PHPCS_OUT"
        echo ""
        echo "ERRORE: PHPCS ha rilevato ${PHPCS_ERRORS} errori. Correggi prima di creare il pacchetto." >&2
        exit 1
    fi
    PHPCS_WARNINGS=$(echo "$PHPCS_OUT" | grep -oE 'AND [0-9]+ WARNING' | grep -oE '[0-9]+' | head -1 || echo "0")
    echo "   ✓ 0 errori PHPCS${PHPCS_WARNINGS:+ (${PHPCS_WARNINGS} warning ignorati)}"
else
    echo "→ [1/4] Lint saltato (--skip-lint)"
fi

# ── 2. Assets ─────────────────────────────────────────────────────────────────
if [[ "$SKIP_NPM" == false ]]; then
    echo ""
    echo "→ [2/4] Build assets SCSS..."
    npm run build --silent
    echo "   ✓ assets/css/frontend.css e assets/css/admin.css aggiornati"
else
    echo "→ [2/4] Build npm saltato (--skip-npm)"
fi

# ── 3. Copia file di produzione ───────────────────────────────────────────────
echo ""
echo "→ [3/4] Copia file produzione in build dir..."
mkdir -p "$DEST"

rsync -a \
    --exclude='.git/' \
    --exclude='.github/' \
    --exclude='.claude/' \
    --exclude='.agents/' \
    --exclude='.wordpress-org/' \
    --exclude='node_modules/' \
    --exclude='assets/scss/' \
    --exclude='vendor/' \
    --exclude='composer.json' \
    --exclude='composer.lock' \
    --exclude='package.json' \
    --exclude='package-lock.json' \
    --exclude='dist/' \
    --exclude='.editorconfig' \
    --exclude='.gitignore' \
    --exclude='.phpcs-cache' \
    --exclude='.phpstan-cache/' \
    --exclude='phpcs.xml.dist' \
    --exclude='phpstan.neon.dist' \
    --exclude='CLAUDE.md' \
    --exclude='skills-lock.json' \
    --exclude='bin/' \
    --exclude='README.md' \
    "$PLUGIN_DIR/" "$DEST/"

# Nessuno step di build lato Composer: il plugin usa un autoloader proprio
# (nanobar.php), senza dipendenze runtime — il pacchetto è già pronto così
# com'è, esattamente come verrà eseguito da chi lo installa da WordPress.org.

# ── 4. ZIP ────────────────────────────────────────────────────────────────────
echo ""
echo "→ [4/4] Creazione ZIP..."
mkdir -p "$DIST_DIR"
rm -f "$ZIP_PATH"
cd "$BUILD_DIR"
zip -r "$ZIP_PATH" "${PLUGIN_SLUG}/" --quiet

SIZE=$(du -sh "$ZIP_PATH" | cut -f1)
FILES=$(unzip -l "$ZIP_PATH" | tail -1 | awk '{print $2}')

echo ""
echo "════════════════════════════════════════════════"
echo " ✓  Pacchetto pronto!"
echo "    File:        ${ZIP_PATH}"
echo "    Dimensione:  ${SIZE}"
echo "    File totali: ${FILES}"
echo "════════════════════════════════════════════════"
