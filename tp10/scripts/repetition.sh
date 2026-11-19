#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PORT="${PORT:-8002}"
BASE="http://127.0.0.1:$PORT"
DB="${DB:-stages_tp10_demo}"
ADMIN_USER="${ADMIN_USER:-root}"
ADMIN_PASS="${ADMIN_PASS:-root}"
OUT="$ROOT/tp10/preuves/repetition.txt"

mkdir -p "$ROOT/tp10/preuves" "$(dirname "$OUT")"
exec > >(tee "$OUT") 2>&1

TMP="$(mktemp -d)"
SERVER_PID=""
cleanup() {
  if [ -n "$SERVER_PID" ]; then
    kill "$SERVER_PID" 2>/dev/null || true
    wait "$SERVER_PID" 2>/dev/null || true
  fi
  rm -rf "$TMP"
}
trap cleanup EXIT

cd "$ROOT"

echo "Répétition technique TP10 — $(date -Iseconds)"
echo "URL : $BASE | base : $DB | commit : $(git rev-parse --short HEAD)"
echo "Chronomètre : temps écoulé depuis le début du scénario technique."
echo

reset_db() {
  docker exec tp6_check mysql -u"$ADMIN_USER" -p"$ADMIN_PASS" \
    -e "DROP DATABASE IF EXISTS \`$DB\`; CREATE DATABASE \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
  for sql in tp3/001_schema.sql tp3/002_seed.sql tp3/005_extensions.sql; do
    docker exec -i tp6_check mysql -u"$ADMIN_USER" -p"$ADMIN_PASS" "$DB" < "$sql" 2>/dev/null
  done
}

cat > "$TMP/env" <<EOF
APP_ENV=dev
APP_DEBUG=false
APP_URL=$BASE
DB_HOST=127.0.0.1
DB_PORT=3308
DB_NAME=$DB
DB_USER=$ADMIN_USER
DB_PASS=$ADMIN_PASS
MAIL_MODE=log
EOF

reset_db
echo "Base remise à zéro : 001 + 002 + 005 importés."

ENV_FILE="$TMP/env" php -S "127.0.0.1:$PORT" -t public public/index.php > "$TMP/server.log" 2>&1 &
SERVER_PID=$!
for _ in $(seq 1 60); do
  if curl -fsS "$BASE/api/sante" > /dev/null 2>&1; then break; fi
  sleep 0.25
done
curl -fsS "$BASE/api/sante" > /dev/null

START="$(date +%s%N)"
elapsed() {
  python3 -c 'import sys; print("+%.2fs" % ((int(sys.argv[2])-int(sys.argv[1]))/1e9))' "$START" "$(date +%s%N)"
}

http() {
  local jar="$1"; shift
  local method="$1"; shift
  local path="$1"; shift
  local body="${1-}"
  local csrf="${2-}"
  local args=(-sS -o "$TMP/body" -w '%{http_code}' -b "$jar" -c "$jar" -X "$method")
  if [ -n "$body" ]; then
    args+=(-H 'Content-Type: application/json' -d "$body")
  fi
  if [ -n "$csrf" ]; then
    args+=(-H "X-CSRF-Token: $csrf")
  fi
  curl "${args[@]}" "$BASE$path"
}

token() {
  python3 -c 'import json,sys; print(json.load(open(sys.argv[1],encoding="utf-8")).get("csrfToken",""))' "$TMP/body"
}

field() {
  python3 -c 'import json,sys; d=json.load(open(sys.argv[1],encoding="utf-8")); print(d.get("data",{}).get(sys.argv[2],""))' "$TMP/body" "$1"
}

excerpt() {
  python3 -c 'import sys; print(sys.stdin.read().replace("\n"," ").strip()[:100].rstrip())' < "$TMP/body"
}

trace() {
  printf '%-8s %-52s %s | %s\n' "$(elapsed)" "$1" "$2" "$(excerpt)"
}

echo
echo "Phase 1 — démonstration"
curl -fsS "$BASE/api/sante" -o "$TMP/body"
trace "GET /api/sante" "200"

curl -fsS -c "$TMP/etudiant.jar" "$BASE/api/session" -o "$TMP/body"
CSRF="$(token)"
CODE="$(http "$TMP/etudiant.jar" POST /api/session '{"email":"rachid.kpanou@campus.test","motDePasse":"Stage2026!"}' "$CSRF")"
CSRF_ETUDIANT="$(token)"
trace "POST /api/session étudiant" "$CODE"

CODE="$(http "$TMP/etudiant.jar" POST /api/demandes '{"entrepriseId":4,"soumettre":true}' "$CSRF_ETUDIANT")"
DEMANDE_ID="$(field id)"
trace "POST /api/demandes" "$CODE"

CODE="$(http "$TMP/etudiant.jar" GET "/api/demandes/$DEMANDE_ID")"
trace "GET /api/demandes/$DEMANDE_ID" "$CODE"

CODE="$(http "$TMP/etudiant.jar" GET "/api/demandes/$DEMANDE_ID/historique")"
trace "GET .../historique" "$CODE"

docker exec tp6_check mysql -u"$ADMIN_USER" -p"$ADMIN_PASS" -N -B "$DB" \
  -e "SELECT CONCAT('ligne base: #', id, ' ', statut) FROM demande_stage WHERE id=$DEMANDE_ID;" 2>/dev/null

curl -fsS -c "$TMP/responsable.jar" "$BASE/api/session" -o "$TMP/body"
CSRF="$(token)"
CODE="$(http "$TMP/responsable.jar" POST /api/session '{"email":"responsable.stages@campus.test","motDePasse":"Stage2026!"}' "$CSRF")"
CSRF_RESP="$(token)"
trace "POST /api/session responsable" "$CODE"

CODE="$(http "$TMP/responsable.jar" PATCH "/api/demandes/$DEMANDE_ID/statut" '{"statut":"REFUSEE"}' "$CSRF_RESP")"
trace "PATCH REFUSEE sans motif" "$CODE"

CODE="$(http "$TMP/responsable.jar" PATCH "/api/demandes/$DEMANDE_ID/statut" '{"statut":"VALIDEE"}' "$CSRF_RESP")"
trace "PATCH SOUMISE→VALIDEE" "$CODE"

CODE="$(http "$TMP/responsable.jar" PATCH "/api/demandes/$DEMANDE_ID/statut" '{"statut":"REFUSEE","motif":"Nouvelle décision"}' "$CSRF_RESP")"
trace "PATCH VALIDEE→REFUSEE" "$CODE"

curl -fsS -c "$TMP/secretariat.jar" "$BASE/api/session" -o "$TMP/body"
CSRF="$(token)"
CODE="$(http "$TMP/secretariat.jar" POST /api/session '{"email":"accueil@campus.test","motDePasse":"Stage2026!"}' "$CSRF")"
CSRF_SEC="$(token)"
trace "POST /api/session secrétariat" "$CODE"

CODE="$(http "$TMP/secretariat.jar" PATCH "/api/demandes/$DEMANDE_ID/encadreur" '{"encadreurId":1}' "$CSRF_SEC")"
trace "PATCH proposition encadreur" "$CODE"

CODE="$(http "$TMP/responsable.jar" PATCH "/api/demandes/$DEMANDE_ID/encadreur/confirmer" "" "$CSRF_RESP")"
trace "PATCH confirmation encadreur" "$CODE"

CODE="$(http "$TMP/secretariat.jar" POST /api/soutenances "{\"demandeId\":$DEMANDE_ID,\"dateHeure\":\"2027-01-15 09:00\",\"salle\":\"B12\",\"dureeMin\":40}" "$CSRF_SEC")"
SOUTENANCE_ID="$(field id)"
trace "POST /api/soutenances" "$CODE"

CODE="$(http "$TMP/secretariat.jar" POST /api/soutenances "{\"demandeId\":$DEMANDE_ID,\"dateHeure\":\"2027-01-16 09:00\",\"salle\":\"B12\",\"dureeMin\":40}" "$CSRF_SEC")"
trace "POST deuxième soutenance" "$CODE"

CODE="$(http "$TMP/secretariat.jar" GET '/api/soutenances/planning?annee=2027&mois=1')"
trace "GET /api/soutenances/planning?annee=2027&mois=1" "$CODE"

CODE="$(http "$TMP/secretariat.jar" GET "/api/soutenances/$SOUTENANCE_ID/convocation")"
trace "GET convocation" "$CODE"

CODE="$(http "$TMP/etudiant.jar" GET /api/demandes/2)"
trace "GET dossier d’un autre étudiant" "$CODE"

CODE="$(http "$TMP/etudiant.jar" POST /api/demandes '{"entrepriseId":7}')"
trace "POST sans jeton CSRF" "$CODE"

CODE="$(http "$TMP/etudiant.jar" GET /api/tableau-de-bord)"
trace "GET /api/tableau-de-bord" "$CODE"

CODE="$(http "$TMP/secretariat.jar" GET /api/exports/statistiques.csv)"
trace "GET /api/exports/statistiques.csv" "$CODE"

CODE="$(http "$TMP/etudiant.jar" GET "/api/documents/$DEMANDE_ID?type=convention&format=html")"
trace "GET /api/documents convention" "$CODE"

printf 'Fin scénario technique : %s\n' "$(elapsed)"

echo
echo "Phase 2 — campagne de tests sur base neuve"
reset_db
ENV_FILE="$TMP/env" php tests/connexion.php
ENV_FILE="$TMP/env" php tests/api-six-cas.php "$BASE"
ENV_FILE="$TMP/env" php tests/api.php "$BASE"
ENV_FILE="$TMP/env" php tests/cas-limites.php "$BASE"
ENV_FILE="$TMP/env" php tests/securite.php "$BASE"
printf 'Fin campagne : %s\n' "$(elapsed)"

echo
echo " solution de repli : tp7/preuves/*.jpeg, tp8/preuves/*.txt, tp9/preuves/*.txt"
echo "Fin du relevé : $(date -Iseconds)"
