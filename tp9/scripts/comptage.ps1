# TP9 - Comptage exact des lignes de chaque table, pour comparer deux bases
# Usage : powershell -ExecutionPolicy Bypass -File tp9/scripts/comptage.ps1 [-Bases stages,stages_test]
param(
    [string[]]$Bases = @("stages", "stages_test"),
    [string]$Bin     = "C:\Mysql\mysql-8.0.46-winx64\bin"
)

$tables = @("utilisateur", "etudiant", "encadreur", "entreprise", "demande_stage", "historique", "soutenance")

# -File passe « stages,stages_test » comme une seule chaîne : on la decoupe.
$Bases = @($Bases | ForEach-Object { $_ -split ',' } | Where-Object { $_ -ne '' })

# COUNT(*) exact : table_rows d'information_schema n'est qu'une estimation InnoDB.
$morceaux = @()
foreach ($t in $tables) { $morceaux += "SELECT '$t' AS t, COUNT(*) AS n FROM $t" }
$requete = ($morceaux -join " UNION ALL ")

$resultat = @{}
foreach ($b in $Bases) {
    $lignes = & "$Bin\mysql.exe" -u root -N -B --default-character-set=utf8mb4 $b -e $requete 2>$null
    $resultat[$b] = @{}
    foreach ($l in $lignes) {
        $c = $l -split "`t"
        if ($c.Count -ge 2) { $resultat[$b][$c[0]] = [int]$c[1] }
    }
}

$entete = "table".PadRight(18)
foreach ($b in $Bases) { $entete += $b.PadLeft(14) }
Write-Output $entete
Write-Output ("-" * $entete.Length)

$identiques = $true
foreach ($t in $tables) {
    $ligne = $t.PadRight(18)
    $valeurs = @()
    foreach ($b in $Bases) {
        $v = if ($resultat[$b].ContainsKey($t)) { $resultat[$b][$t] } else { -1 }
        $valeurs += $v
        $ligne += ("$v").PadLeft(14)
    }
    if (($valeurs | Select-Object -Unique).Count -gt 1) { $identiques = $false; $ligne += "   <-- ECART" }
    Write-Output $ligne
}

Write-Output ""
Write-Output ("Totaux par base : " + (($Bases | ForEach-Object {
    $s = 0; foreach ($t in $tables) { if ($resultat[$_].ContainsKey($t)) { $s += $resultat[$_][$t] } }
    "$_ = $s"
}) -join " | "))
Write-Output ("Verdict : " + $(if ($identiques) { "IDENTIQUES" } else { "DIFFERENTES" }))
