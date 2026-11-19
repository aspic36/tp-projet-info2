<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Support\Config;

$base = rtrim($argv[1] ?? (string) Config::get('app.url', 'http://127.0.0.1:8000'), '/');
$jar = tempnam(sys_get_temp_dir(), 'stages_limites_') . '.txt';
$echecs = 0;
$lignes = [];

function http(string $base, string $jar, string $method, string $path, ?array $body = null, ?string $csrf = null): array
{
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

    return ['status' => $status, 'json' => json_decode((string) $raw, true)];
}

function verifier(string $cas, int $attendu, array $reponse, string $complement = ''): void
{
    global $echecs, $lignes;
    $obtenu = $reponse['status'];
    $ok = $obtenu === $attendu;
    if (!$ok) {
        $echecs++;
    }
    $lignes[] = sprintf(
        '%-42s attendu %3d | obtenu %3d | %s%s',
        $cas,
        $attendu,
        $obtenu,
        $ok ? 'OK' : 'ECHEC',
        $complement === '' ? '' : ' | ' . $complement
    );
}

function session(string $base, string $jar, string $email): array
{
    $init = http($base, $jar, 'GET', '/api/session');

    return http($base, $jar, 'POST', '/api/session', [
        'email'      => $email,
        'motDePasse' => 'Stage2026!',
    ], $init['json']['csrfToken'] ?? null);
}

echo "Quatre cas limites TP8 — base $base\n";
echo str_repeat('=', 92), "\n";

$etudiant = session($base, $jar, 'rachid.kpanou@campus.test');
$csrfEtudiant = $etudiant['json']['csrfToken'] ?? '';
$creation = http($base, $jar, 'POST', '/api/demandes', [
    'entrepriseId' => 1,
    'soumettre'    => true,
], $csrfEtudiant);
$demandeId = (int) ($creation['json']['data']['id'] ?? 0);

$responsable = session($base, $jar, 'responsable.stages@campus.test');
$csrfResp = $responsable['json']['csrfToken'] ?? '';
$inexistante = http($base, $jar, 'PATCH', '/api/demandes/999999/statut', [
    'statut' => 'VALIDEE',
], $csrfResp);
verifier('C-01 valider la demande 999999', 404, $inexistante);

$sansMotif = http($base, $jar, 'PATCH', "/api/demandes/$demandeId/statut", [
    'statut' => 'REFUSEE',
], $csrfResp);
verifier('C-02 refuser sans motif', 400, $sansMotif);

http($base, $jar, 'PATCH', "/api/demandes/$demandeId/statut", [
    'statut' => 'VALIDEE',
], $csrfResp);
$repete = http($base, $jar, 'PATCH', "/api/demandes/$demandeId/statut", [
    'statut' => 'VALIDEE',
], $csrfResp);
$apres = http($base, $jar, 'GET', "/api/demandes/$demandeId");
verifier('C-03 valider une demande déjà validée', 409, $repete, 'statut=' . ($apres['json']['data']['statut'] ?? '?'));

$secretariat = session($base, $jar, 'accueil@campus.test');
$csrfSec = $secretariat['json']['csrfToken'] ?? '';
$dateInvalide = http($base, $jar, 'POST', '/api/soutenances', [
    'demandeId' => $demandeId,
    'dateHeure' => '2026-02-30 10:00',
    'salle'     => 'B12',
    'dureeMin'  => 40,
], $csrfSec);
verifier('C-04 soutenance le 2026-02-30', 400, $dateInvalide);

echo implode("\n", $lignes), "\n";
echo str_repeat('=', 92), "\n";
printf("Demande créée pour le test : #%d\n", $demandeId);
printf("Résultat : %d échec(s) sur %d cas.\n", $echecs, count($lignes));

@unlink($jar);
exit($echecs === 0 ? 0 : 1);
