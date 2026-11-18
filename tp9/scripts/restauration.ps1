# TP9 - Restauration d'une sauvegarde dans une base cible
# Usage : powershell -ExecutionPolicy Bypass -File tp9/scripts/restauration.ps1 -Fichier <dump.sql> [-Cible stages_test]
param(
    [Parameter(Mandatory = $true)][string]$Fichier,
    [string]$Cible = "stages_test",
    [string]$Bin   = "C:\Mysql\mysql-8.0.46-winx64\bin"
)

if (-not (Test-Path $Fichier)) { throw "Sauvegarde introuvable : $Fichier" }

# On repart d'une base vide : la restauration doit recreer toutes les tables.
& "$Bin\mysql.exe" -u root -e "DROP DATABASE IF EXISTS $Cible; CREATE DATABASE $Cible CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"
if ($LASTEXITCODE -ne 0) { throw "Creation de $Cible impossible" }

$commande = "`"$Bin\mysql.exe`" -u root --default-character-set=utf8mb4 $Cible < `"$Fichier`""
cmd /c $commande
if ($LASTEXITCODE -ne 0) { throw "Restauration echouee (code $LASTEXITCODE)" }

Write-Output "Restauration terminee : $Fichier -> $Cible"
