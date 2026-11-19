<?php

declare(strict_types=1);

/**
 * TP8 — Audit de sécurité de l'API et de la base.
 * Usage : php tests/securite.php [http://127.0.0.1:8000]
 */

require __DIR__ . '/bootstrap.php';

use App\Database\Database;
use App\Support\Config;

$base = rtrim($argv[1] ?? (string) Config::get('app.url', 'http://127.0.0.1:8000'), '/');
$jarEtudiant = tempnam(sys_get_temp_dir(), 'stages_a_') . '.txt';
$jarEtudiantB = tempnam(sys_get_temp_dir(), 'stages_b_') . '.txt';
$echecs = 0;

function appel(string $base, string $jar, string $method, string $path, ?array $body = null, ?string $csrf = null): array
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
    curl_close($ch);

    if ($raw === false) {
        fwrite(STDERR, "Serveur injoignable.\n");
        exit(2);
    }

    return ['status' => $status, 'json' => json_decode((string) $raw, true)];
}

function controler(string $id, string $libelle, int $attendu, int $obtenu): void
{
    global $echecs;
    $ok = $attendu === $obtenu;
    if (!$ok) {
        $echecs++;
    }
    printf("%-5s %-52s attendu %3d | obtenu %3d | %s\n", $id, $libelle, $attendu, $obtenu, $ok ? 'OK' : 'ECHEC');
}

function controlerVrai(string $id, string $libelle, bool $condition): void
{
    global $echecs;
    if (!$condition) {
        $echecs++;
    }
    printf("%-5s %-52s %s\n", $id, $libelle, $condition ? 'OK' : 'ECHEC');
}

function connecter(string $base, string $jar, string $email): ?string
{
    $init = appel($base, $jar, 'GET', '/api/session');
    $login = appel($base, $jar, 'POST', '/api/session', [
        'email'      => $email,
        'motDePasse' => 'Stage2026!',
    ], $init['json']['csrfToken'] ?? null);

    return $login['status'] === 200 ? (string) ($login['json']['csrfToken'] ?? '') : null;
}

$pdo = Database::pdo();
$demandeA = (int) $pdo->query(
    "SELECT d.id FROM demande_stage d
     JOIN etudiant e ON e.id = d.etudiant_id
     JOIN utilisateur u ON u.id = e.utilisateur_id
     WHERE u.email = 'rachid.kpanou@campus.test'
     ORDER BY d.id LIMIT 1"
)->fetchColumn();

echo "Audit de sécurité — base $base\n";
echo str_repeat('=', 96), "\n";

// --- Autorisations --------------------------------------------------------
$csrfA = connecter($base, $jarEtudiant, 'rachid.kpanou@campus.test');
$csrfB = connecter($base, $jarEtudiantB, 'clarisse.sodjinou@campus.test');
controlerVrai('S-01', 'connexion des deux étudiants', $csrfA !== null && $csrfB !== null);

$lecture = appel($base, $jarEtudiantB, 'GET', "/api/demandes/$demandeA");
controler('S-02', "étudiant B lit le dossier #$demandeA de l'étudiant A", 403, $lecture['status']);
controlerVrai('S-03', 'la réponse 403 ne contient aucune donnée du dossier', !isset($lecture['json']['data']));

$escalade = appel($base, $jarEtudiant, 'PATCH', "/api/demandes/$demandeA/encadreur", ['encadreurId' => 1], $csrfA);
controler('S-04', 'étudiant affecte un encadreur', 403, $escalade['status']);

$comptes = appel($base, $jarEtudiant, 'GET', '/api/comptes');
controler('S-05', 'étudiant lit la liste des comptes', 403, $comptes['status']);

// --- Injection SQL --------------------------------------------------------
$injectionId = appel($base, $jarEtudiant, 'GET', '/api/demandes/' . rawurlencode('1 OR 1=1'));
controler('S-06', 'identifiant « 1 OR 1=1 »', 404, $injectionId['status']);

$injectionRecherche = appel($base, $jarEtudiant, 'GET', '/api/demandes?q=' . rawurlencode("' OR 1=1 --"));
controler('S-07', 'recherche « \' OR 1=1 -- »', 200, $injectionRecherche['status']);
controlerVrai(
    'S-08',
    'aucune ligne retournée par la chaîne injectée',
    ($injectionRecherche['json']['data'] ?? null) === []
);

$injectionLogin = appel($base, $jarEtudiant, 'POST', '/api/session', [
    'email'      => "admin' OR 1=1 --",
    'motDePasse' => 'x',
], $csrfA);
controler('S-09', 'connexion avec « admin\' OR 1=1 -- »', 401, $injectionLogin['status']);

// --- Mots de passe --------------------------------------------------------
$hashs = $pdo->query('SELECT email, mot_de_passe FROM utilisateur')->fetchAll();
$tousHaches = true;
$aucunClair = true;
foreach ($hashs as $ligne) {
    if (!str_starts_with((string) $ligne['mot_de_passe'], '$2y$')) {
        $tousHaches = false;
    }
    if ($ligne['mot_de_passe'] === 'Stage2026!') {
        $aucunClair = false;
    }
}
controlerVrai('S-10', 'tous les mots de passe sont hachés en bcrypt', $tousHaches);
controlerVrai('S-11', 'aucun mot de passe stocké en clair', $aucunClair);

$hashDemo = (string) $pdo->query(
    "SELECT mot_de_passe FROM utilisateur WHERE email = 'rachid.kpanou@campus.test'"
)->fetchColumn();
controlerVrai('S-12', 'password_verify accepte le mot de passe de démonstration', password_verify('Stage2026!', $hashDemo));

// --- CSRF -----------------------------------------------------------------
$sansJeton = appel($base, $jarEtudiant, 'POST', '/api/demandes', ['entrepriseId' => 7]);
controler('S-13', 'écriture sans jeton CSRF', 403, $sansJeton['status']);

$mauvaisJeton = appel($base, $jarEtudiant, 'POST', '/api/demandes', ['entrepriseId' => 7], str_repeat('a', 64));
controler('S-14', 'écriture avec un faux jeton CSRF', 403, $mauvaisJeton['status']);

$bonJeton = appel($base, $jarEtudiant, 'POST', '/api/demandes', ['entrepriseId' => 7, 'soumettre' => false], $csrfA);
controler('S-15', 'écriture avec le jeton valide (création brouillon)', 201, $bonJeton['status']);

// --- Fuite d'information --------------------------------------------------
$erreur = appel($base, $jarEtudiant, 'PATCH', "/api/demandes/$demandeA/statut", ['statut' => 'INCONNU'], $csrfA);
$corps = json_encode($erreur['json'], JSON_UNESCAPED_UNICODE);
controler('S-16', 'statut inconnu', 400, $erreur['status']);
controlerVrai('S-17', 'aucun détail SQL/PDO dans la réponse', stripos($corps, 'sqlstate') === false && stripos($corps, 'pdo') === false);

echo str_repeat('=', 96), "\n";
printf("Résultat : %d échec(s).\n", $echecs);

@unlink($jarEtudiant);
@unlink($jarEtudiantB);
exit($echecs === 0 ? 0 : 1);
