#!/usr/bin/env bash
set -euo pipefail

: "${DB_HOST:=127.0.0.1}"
: "${DB_PORT:=3308}"
: "${DB_USER:=root}"
: "${DB_PASS:?DB_PASS requis}"

fichier="${1:?Fichier de sauvegarde requis}"
cible="${2:-stages_test}"

if [[ ! "$cible" =~ ^[A-Za-z0-9_]+$ ]]; then
    printf 'Nom de base invalide : %s\n' "$cible" >&2
    exit 1
fi

if [[ ! -f "$fichier" ]]; then
    printf 'Sauvegarde introuvable : %s\n' "$fichier" >&2
    exit 1
fi

mysql \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USER" \
    --password="$DB_PASS" \
    -e "DROP DATABASE IF EXISTS $cible; CREATE DATABASE $cible CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"

mysql \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USER" \
    --password="$DB_PASS" \
    --default-character-set=utf8mb4 \
    "$cible" < "$fichier"

printf 'Restauration terminée : %s -> %s\n' "$fichier" "$cible"
