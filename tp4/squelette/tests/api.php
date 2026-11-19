<?php

declare(strict_types=1);

/**
 * TP6 / TP8 — Scénario HTTP complet contre l'API locale.
 * Prérequis : serveur démarré et base alimentée (tp3/002_seed.sql).
 * Usage : php tests/api.php [http://127.0.0.1:8000]
 *
 * Codes attendus : 201, 200, 400, 401, 403, 404, 409.
 */

require __DIR__ . '/bootstrap.php';

use App\Support\Config;

$base = rtrim($argv[1] ?? (string) Config::get('app.url', 'http://127.0.0.1:8000'), '/');
$jar = tempnam(sys_get_temp_dir(), 'stages_cookies_') . '.txt';
$echecs = 0;
$lignes = [];

function http(
    string $base,
    string $jar,
    string $method,
    string $path,
    ?array $body = null,
    ?string $csrf = null
): array {
    $ch = curl_init($base . $path);
    $headers = ['Accept: application/json'];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }
    if ($csrf !== null) {
        $headers[] = 'X-CSRF-Token: ' . $csrf;
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $erreur = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        fwrite(STDERR, "Serveur injoignable : $erreur\n");
        exit(2);
    }

    return ['status' => $status, 'json' => json_decode((string) $raw, true), 'brut' => (string) $raw];
}

function verifier(string $cas, int $attendu, array $reponse, string $commentaire = ''): void
{
    global $echecs, $lignes;
    $obtenu = $reponse['status'];
    $ok = $obtenu === $attendu;
    if (!$ok) {
        $echecs++;
    }
    $lignes[] = sprintf(
        "%-46s attendu %3d | obtenu %3d | %s%s",
        $cas,
        $attendu,
        $obtenu,
        $ok ? 'OK' : 'ECHEC',
        $commentaire === '' ? '' : ' | ' . $commentaire
    );
}

function session(string $base, string $jar, string $email): array
{
    $init = http($base, $jar, 'GET', '/api/session');
    $login = http($base, $jar, 'POST', '/api/session', [
        'email'    => $email,
        'motDePasse' => 'Stage2026!',
    ], $init['json']['csrfToken'] ?? null);

    return $login;
}

echo "Scénario API — base $base\n";
echo str_repeat('=', 96), "\n";

// 0. Santé
$sante = http($base, $jar, 'GET', '/api/sante');
verifier('T-00 GET /api/sante', 200, $sante);

// 1. Non authentifié
$anonyme = http($base, $jar, 'GET', '/api/demandes');
verifier('T-01 GET /api/demandes sans session', 401, $anonyme);

// 2. Connexion étudiante
$etudiant = session($base, $jar, 'rachid.kpanou@campus.test');
verifier('T-02 POST /api/session (étudiant)', 200, $etudiant);
$csrfEtudiant = $etudiant['json']['csrfToken'] ?? '';
$entrepriseId = 1;

// 3. Création d'une demande soumise
$creation = http($base, $jar, 'POST', '/api/demandes', [
    'entrepriseId' => $entrepriseId,
    'soumettre'    => true,
], $csrfEtudiant);
verifier('T-03 POST /api/demandes', 201, $creation);
$demandeId = (int) ($creation['json']['data']['id'] ?? 0);

// 4. Lecture de la liste
$liste = http($base, $jar, 'GET', '/api/demandes');
verifier('T-04 GET /api/demandes', 200, $liste);

// 5. L'étudiant tente de valider lui-même
$autoValidation = http($base, $jar, 'PATCH', "/api/demandes/$demandeId/statut", [
    'statut' => 'VALIDEE',
], $csrfEtudiant);
verifier('T-05 PATCH statut par l’étudiant', 403, $autoValidation);

// 6. Demande inexistante
$inexistante = http($base, $jar, 'GET', '/api/demandes/999999');
verifier('T-06 GET /api/demandes/999999', 404, $inexistante);

// 7. Responsable : refus sans motif
$responsable = session($base, $jar, 'responsable.stages@campus.test');
$csrfResp = $responsable['json']['csrfToken'] ?? '';
$sansMotif = http($base, $jar, 'PATCH', "/api/demandes/$demandeId/statut", [
    'statut' => 'REFUSEE',
], $csrfResp);
verifier('T-07 REFUSEE sans motif', 400, $sansMotif);

// 8. Responsable : validation
$validation = http($base, $jar, 'PATCH', "/api/demandes/$demandeId/statut", [
    'statut' => 'VALIDEE',
], $csrfResp);
verifier('T-08 SOUMISE→VALIDEE par le responsable', 200, $validation);

// 9. Validation répétée
$repete = http($base, $jar, 'PATCH', "/api/demandes/$demandeId/statut", [
    'statut' => 'REFUSEE',
    'motif'  => 'Nouvelle décision',
], $csrfResp);
verifier('T-09 VALIDEE→REFUSEE interdite', 409, $repete);

// 10. Sans jeton CSRF
$sansCsrf = http($base, $jar, 'PATCH', "/api/demandes/$demandeId/statut", [
    'statut' => 'VALIDEE',
]);
verifier('T-10 PATCH sans jeton CSRF', 403, $sansCsrf);

// 11. Historique
$historique = http($base, $jar, 'GET', "/api/demandes/$demandeId/historique");
verifier('T-11 GET historique', 200, $historique);

// 12. Secrétariat : date de soutenance inexistante
$secretariat = session($base, $jar, 'accueil@campus.test');
$csrfSec = $secretariat['json']['csrfToken'] ?? '';
$dateInvalide = http($base, $jar, 'POST', '/api/soutenances', [
    'demandeId' => $demandeId,
    'dateHeure' => '2026-02-30 10:00',
    'salle'     => 'B12',
    'dureeMin'  => 40,
], $csrfSec);
verifier('T-12 soutenance le 2026-02-30', 400, $dateInvalide);

// 13. Secrétariat : date passée
$datePassee = http($base, $jar, 'POST', '/api/soutenances', [
    'demandeId' => $demandeId,
    'dateHeure' => '2020-01-15 09:00',
    'salle'     => 'B12',
    'dureeMin'  => 40,
], $csrfSec);
verifier('T-13 soutenance dans le passé', 400, $datePassee);

// 14. Programmation valide
$soutenance = http($base, $jar, 'POST', '/api/soutenances', [
    'demandeId' => $demandeId,
    'dateHeure' => '2027-01-15 09:00',
    'salle'     => 'B12',
    'dureeMin'  => 40,
], $csrfSec);
verifier('T-14 POST /api/soutenances', 201, $soutenance);

// 15. Deuxième soutenance pour la même demande
$doublon = http($base, $jar, 'POST', '/api/soutenances', [
    'demandeId' => $demandeId,
    'dateHeure' => '2027-01-16 09:00',
    'salle'     => 'B12',
    'dureeMin'  => 40,
], $csrfSec);
verifier('T-15 deuxième soutenance (doublon)', 409, $doublon);

echo implode("\n", $lignes), "\n";
echo str_repeat('=', 96), "\n";
printf("Demande créée pour le test : #%d\n", $demandeId);
printf(
    "Événements d'historique : %s\n",
    implode(', ', array_map(
        static fn (array $e): string => ($e['statut_avant'] ?? '—') . '→' . $e['statut_apres'],
        $historique['json']['data'] ?? []
    ))
);
printf("Résultat : %d échec(s) sur %d cas.\n", $echecs, count($lignes));

@unlink($jar);
exit($echecs === 0 ? 0 : 1);
