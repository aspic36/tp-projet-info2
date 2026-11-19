<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\ApiException;

final class Pdf
{
    public function depuisHtml(string $html, string $nom): array
    {
        $binaire = trim((string) shell_exec('command -v chromium 2>/dev/null'));
        if ($binaire === '') {
            $binaire = trim((string) shell_exec('command -v chromium-browser 2>/dev/null'));
        }
        if ($binaire === '') {
            throw new ApiException(503, 'Génération PDF indisponible sur ce serveur.');
        }

        $dir = (string) Config::get('storage.exports');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new ApiException(500, 'Dossier d’export indisponible.');
        }
        $base = $dir . '/' . bin2hex(random_bytes(8));
        $fichierHtml = $base . '.html';
        $fichierPdf = $base . '.pdf';
        $profil = $base . '-profil';
        file_put_contents($fichierHtml, $html);

        $commande = escapeshellarg($binaire)
            . ' --headless=new --no-sandbox --disable-gpu --no-pdf-header-footer'
            . ' --user-data-dir=' . escapeshellarg($profil)
            . ' --print-to-pdf=' . escapeshellarg($fichierPdf)
            . ' ' . escapeshellarg('file://' . $fichierHtml)
            . ' 2>&1';
        @exec($commande, $sortie, $code);

        @unlink($fichierHtml);
        $this->supprimerDossier($profil);
        if ($code !== 0 || !is_file($fichierPdf) || filesize($fichierPdf) < 100) {
            @unlink($fichierPdf);
            Log::error('Échec de génération PDF.', ['code' => $code, 'sortie' => implode("\n", $sortie)]);
            throw new ApiException(500, 'Génération PDF impossible.');
        }

        return ['chemin' => $fichierPdf, 'nom' => $nom . '.pdf'];
    }

    private function supprimerDossier(string $dossier): void
    {
        if (!is_dir($dossier)) {
            return;
        }
        $elements = scandir($dossier) ?: [];
        foreach ($elements as $element) {
            if ($element === '.' || $element === '..') {
                continue;
            }
            $chemin = $dossier . '/' . $element;
            if (is_dir($chemin)) {
                $this->supprimerDossier($chemin);
            } else {
                @unlink($chemin);
            }
        }
        @rmdir($dossier);
    }
}
