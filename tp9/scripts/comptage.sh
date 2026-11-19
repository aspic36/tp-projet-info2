#!/usr/bin/env bash
set -euo pipefail

: "${DB_HOST:=127.0.0.1}"
: "${DB_PORT:=3308}"
: "${DB_USER:=root}"
: "${DB_PASS:?DB_PASS requis}"

source_db="${1:-stages}"
cible="${2:-stages_test}"

for base in "$source_db" "$cible"; do
    if [[ ! "$base" =~ ^[A-Za-z0-9_]+$ ]]; then
        printf 'Nom de base invalide : %s\n' "$base" >&2
        exit 1
    fi
done

tables=$(mysql \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USER" \
    --password="$DB_PASS" \
    -N -B \
    -e "SELECT table_name FROM information_schema.tables WHERE table_schema='$source_db' AND table_type='BASE TABLE' ORDER BY table_name")

mapfile -t liste <<< "$tables"

declare -A valeurs
for base in "$source_db" "$cible"; do
    for table in "${liste[@]}"; do
        if nombre=$(mysql \
            --host="$DB_HOST" \
            --port="$DB_PORT" \
            --user="$DB_USER" \
            --password="$DB_PASS" \
            -N -B \
            "$base" -e "SELECT COUNT(*) FROM \`$table\`" 2>/dev/null); then
            valeurs["$base:$table"]="$nombre"
        else
            valeurs["$base:$table"]=-1
        fi
    done
done

printf '%-28s %12s %12s\n' 'table' "$source_db" "$cible"
printf '%s\n' '--------------------------------------------------------------------'

identiques=true
total_source=0
total_cible=0

for table in "${liste[@]}"; do
    gauche="${valeurs[$source_db:$table]:--1}"
    droite="${valeurs[$cible:$table]:--1}"
    total_source=$((total_source + gauche))
    total_cible=$((total_cible + droite))
    if [[ "$gauche" != "$droite" ]]; then
        identiques=false
        printf '%-28s %12s %12s  <-- ECART\n' "$table" "$gauche" "$droite"
    else
        printf '%-28s %12s %12s\n' "$table" "$gauche" "$droite"
    fi
done

printf '\n'
printf 'Totaux : %s = %s | %s = %s\n' "$source_db" "$total_source" "$cible" "$total_cible"
if [[ "$identiques" == true ]]; then
    printf 'Verdict : IDENTIQUES\n'
else
    printf 'Verdict : DIFFERENTES\n'
    exit 1
fi
