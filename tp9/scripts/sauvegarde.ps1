# TP9 - Sauvegarde logique de la base
# Usage : powershell -ExecutionPolicy Bypass -File tp9/scripts/sauvegarde.ps1 [-Base stages]
param(
    [string]$Base     = "stages",
    [string]$Bin      = "C:\Mysql\mysql-8.0.46-winx64\bin",
    [string]$Dossier  = ""
)

$racine = (Resolve-Path (Join-Path $PSScriptRoot "..\..")).Path
if ($Dossier -eq "") { $Dossier = Join-Path $racine "sauvegardes" }

New-Item -ItemType Directory -Force $Dossier | Out-Null

$horodatage = Get-Date -Format "yyyyMMdd-HHmmss"
$fichier    = Join-Path $Dossier "$Base-$horodatage.sql"

# Redirection par cmd.exe : evite l'UTF-16 de PowerShell et garde les octets bruts.
$commande = "`"$Bin\mysqldump.exe`" -u root --single-transaction --routines --triggers " +
            "--default-character-set=utf8mb4 $Base > `"$fichier`""
cmd /c $commande
if ($LASTEXITCODE -ne 0) { throw "mysqldump a echoue (code $LASTEXITCODE)" }

$taille = (Get-Item $fichier).Length
$sha    = (Get-FileHash -Algorithm SHA256 $fichier).Hash
$lignes = (Get-Content $fichier -Encoding utf8 | Measure-Object -Line).Lines

Write-Output "Sauvegarde terminee"
Write-Output "  fichier   : $fichier"
Write-Output "  taille    : $taille octets"
Write-Output "  lignes    : $lignes"
Write-Output "  sha256    : $sha"
Write-Output $fichier
