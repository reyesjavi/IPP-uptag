#!/usr/bin/env bash
# scripts/backup.sh — Copia de seguridad automatizada IPP-UPTAG
#
# Uso:
#   bash /var/www/ippuptag/scripts/backup.sh
#
# Crontab recomendado (2 AM diario):
#   0 2 * * * /var/www/ippuptag/scripts/backup.sh >> /var/log/ipp-backup.log 2>&1
#
# Requiere: mysqldump, gzip, .env con DB_* y UPLOAD_PATH

set -euo pipefail

# ── Cargar variables desde .env ───────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$SCRIPT_DIR/../.env"

if [[ ! -f "$ENV_FILE" ]]; then
    echo "[ERROR] No se encontró .env en $ENV_FILE"
    exit 1
fi

# Exportar variables del .env (ignora comentarios y líneas vacías)
set -o allexport
while IFS='=' read -r key value; do
    [[ -z "$key" || "$key" == \#* ]] && continue
    export "$key=$value"
done < <(grep -v '^\s*#' "$ENV_FILE" | grep -v '^\s*$')
set +o allexport

# ── Configuración ─────────────────────────────────────────────
DB_HOST="${DB_HOST:-localhost}"
DB_NAME="${DB_NAME:-ippuptag}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"

# Directorio de uploads (puede ser fuera del webroot en producción)
UPLOADS_DIR="${UPLOAD_PATH:-$SCRIPT_DIR/../uploads/reembolsos}"

# Directorio de destino para los backups
BACKUP_DIR="${BACKUP_DIR:-/var/backups/ippuptag}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"

TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# ── Crear directorio de backups ───────────────────────────────
mkdir -p "$BACKUP_DIR/db"
mkdir -p "$BACKUP_DIR/uploads"

echo "========================================"
echo "IPP-UPTAG Backup — $(date '+%Y-%m-%d %H:%M:%S')"
echo "========================================"

# ── 1. Backup de base de datos ────────────────────────────────
DB_FILE="$BACKUP_DIR/db/ippuptag_${TIMESTAMP}.sql.gz"
echo "[1/3] Volcando base de datos '$DB_NAME'..."

MYSQL_PWD="$DB_PASS" mysqldump \
    --host="$DB_HOST" \
    --user="$DB_USER" \
    --single-transaction \
    --routines \
    --triggers \
    --add-drop-table \
    "$DB_NAME" | gzip -9 > "$DB_FILE"

echo "      → $DB_FILE ($(du -sh "$DB_FILE" | cut -f1))"

# ── 2. Backup de uploads ──────────────────────────────────────
if [[ -d "$UPLOADS_DIR" ]]; then
    UPLOADS_FILE="$BACKUP_DIR/uploads/reembolsos_${TIMESTAMP}.tar.gz"
    echo "[2/3] Comprimiendo uploads desde '$UPLOADS_DIR'..."
    tar -czf "$UPLOADS_FILE" -C "$(dirname "$UPLOADS_DIR")" "$(basename "$UPLOADS_DIR")"
    echo "      → $UPLOADS_FILE ($(du -sh "$UPLOADS_FILE" | cut -f1))"
else
    echo "[2/3] ADVERTENCIA: directorio de uploads no encontrado: $UPLOADS_DIR"
fi

# ── 3. Rotación: eliminar backups más antiguos de N días ──────
echo "[3/3] Eliminando backups con más de ${RETENTION_DAYS} días..."
find "$BACKUP_DIR/db"      -name "*.sql.gz"    -mtime +"$RETENTION_DAYS" -delete -print
find "$BACKUP_DIR/uploads" -name "*.tar.gz"    -mtime +"$RETENTION_DAYS" -delete -print

echo ""
echo "Backup completado: $(date '+%Y-%m-%d %H:%M:%S')"
echo "Espacio en $BACKUP_DIR: $(du -sh "$BACKUP_DIR" | cut -f1)"
