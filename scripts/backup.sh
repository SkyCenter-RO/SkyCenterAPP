#!/bin/bash

# Database Backup Script for PostgreSQL
# Designed to be run as a daily cron job.

# Set directories
BACKUP_DIR="/var/www/html/storage/backups"
mkdir -p "$BACKUP_DIR"

# Timestamp
TIMESTAMP=$(date +"%Y-%m-%d_%H%M%S")
BACKUP_FILE="$BACKUP_DIR/db_backup_$TIMESTAMP.sql.gz"

# DB credentials - read from env or fallback to defaults
DB_HOST="${DB_HOST:-pgsql}"
DB_PORT="${DB_PORT:-5432}"
DB_NAME="${DB_DATABASE:-skycenter_app}"
DB_USER="${DB_USERNAME:-skycenter}"
PGPASSWORD="${DB_PASSWORD:-secret}"

export PGPASSWORD

echo "Starting database backup at $(date)..."

# Run pg_dump and compress with gzip
if pg_dump -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" | gzip > "$BACKUP_FILE"; then
    echo "Backup completed successfully: $BACKUP_FILE"
else
    echo "Error: Database backup failed!" >&2
    exit 1
fi

# Clean up backups older than 7 days
find "$BACKUP_DIR" -type f -name "db_backup_*.sql.gz" -mtime +7 -delete

echo "Backup process finished."
