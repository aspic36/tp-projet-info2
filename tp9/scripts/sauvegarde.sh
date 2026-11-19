#!/usr/bin/env bash
set -euo pipefail

: "${DB_HOST:=127.0.0.1}"
: "${DB_PORT:=3308}"
: "${DB_USER:=root}"
: "${DB_PASS:?DB_PASS requis}"

base="${1:-stages}"
dossier="${2:-sauvegardes}"

if [[ ! "$base" =~ ^[A-Za-z0-9_]+$ ]]; then
    printf 'Nom de base invalide : %s\n' "$base" >&2
    exit 1
fi

mkdir -p "$dossier"
fichier="${dossier}/${base}-$(date +%Y%m%d-%H%M%S).sql"

mysqldump \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USER" \
    --password="$DB_PASS" \
    --single-transaction \
    --routines \
    --triggers \
    --default-character-set=utf8mb4 \
    "$base" > "$fichier"

sha=$(sha256sum "$fichier")
sha=${sha%% *}
lignes=$(wc -l < "$fichier")
octets=$(wc -c < "$fichier")

printf 'Sauvegarde terminée\n'
printf '  base    : %s\n' "$base"
printf '  fichier : %s\n' "$fichier"
printf '  taille  : %s octets\n' "$octets"
printf '  lignes  : %s\n' "$lignes"
printf '  sha256  : %s\n' "$sha"
printf '%s\n' "$fichier"
