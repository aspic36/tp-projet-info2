<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Support\Config;

$base = rtrim($argv[1] ?? (string) Config::get('app.url', 'http://127.0.0.1:8000'), '/');
$jar = tempnam(sys_get_temp_dir(), 'stages_six_') . '.txt';
$jarAnon = tempnam(sys_get_temp_dir(), 'stages_anon_') . '.txt';
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

function verifier(string $cas, int $attendu, array $reponse): void
{
    global $echecs, $lignes;
    $obtenu = $reponse['status'];
    $ok = $obtenu === $attendu;
    if (!$ok) {
        $echecs++;
    }
    $lignes[] = sprintf('%-46s attendu %3d | obtenu %3d | %s', $cas, $attendu, $obtenu, $ok ? 'OK' : 'ECHEC');
}

function session(string $base, string $jar, string $email): array
{
    $init = http($base, $jar, 'GET', '/api/session');

    return http($base, $jar, 'POST', '/api/session', [
        'email'      => $email,
        'motDePasse' => 'Stage2026!',
    ], $init['json']['csrfToken'] ?? null);
}

echo "Six cas TP6 — base $base\n";
echo str_repeat('=', 88), "\n";

$anonyme = http($base, $jarAnon, 'GET', '/api/demandes');
verifier('C-01 GET /api/demandes sans session', 401, $anonyme);

$etudiant = session($base, $jar, 'rachid.kpanou@campus.test');
$csrfEtudiant = $etudiant['json']['csrfToken'] ?? '';
$creation = http($base, $jar, 'POST', '/api/demandes', [
    'entrepriseId' => 1,
    'soumettre'    => true,
], $csrfEtudiant);
verifier('C-02 POST /api/demandes (succès)', 201, $creation);
$demandeId = (int) ($creation['json']['data']['id'] ?? 0);

$autoValidation = http($base, $jar, 'PATCH', "/api/demandes/$demandeId/statut", [
    'statut' => 'VALIDEE',
], $csrfEtudiant);
verifier('C-03 PATCH par un étudiant', 403, $autoValidation);

$inexistante = http($base, $jar, 'GET', '/api/demandes/999999');
verifier('C-04 GET /api/demandes/999999', 404, $inexistante);

$responsable = session($base, $jar, 'responsable.stages@campus.test');
$csrfResp = $responsable['json']['csrfToken'] ?? '';
$sansMotif = http($base, $jar, 'PATCH', "/api/demandes/$demandeId/statut", [
    'statut' => 'REFUSEE',
], $csrfResp);
verifier('C-05 REFUSEE sans motif', 400, $sansMotif);

http($base, $jar, 'PATCH', "/api/demandes/$demandeId/statut", [
    'statut' => 'VALIDEE',
], $csrfResp);
$repete = http($base, $jar, 'PATCH', "/api/demandes/$demandeId/statut", [
    'statut' => 'VALIDEE',
], $csrfResp);
verifier('C-06 validation répétée VALIDEE→VALIDEE', 409, $repete);
$apres = http($base, $jar, 'GET', "/api/demandes/$demandeId");

echo implode("\n", $lignes), "\n";
echo str_repeat('=', 88), "\n";
printf("Demande créée pour le test : #%d\n", $demandeId);
printf("Statut après répétition : %s\n", $apres['json']['data']['statut'] ?? '?');
printf("Résultat : %d échec(s) sur %d cas.\n", $echecs, count($lignes));

@unlink($jar);
@unlink($jarAnon);
exit($echecs === 0 ? 0 : 1);
