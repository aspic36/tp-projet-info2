<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Config;
use App\Support\Log;

final class MailService
{
    public function envoyer(string $destinataire, string $sujet, string $texte): bool
    {
        $mail = Config::get('mail', []);
        $from = (string) ($mail['from'] ?? 'no-reply@campus.test');
        $fromName = (string) ($mail['from_name'] ?? 'Gestion des stages');
        $mode = (string) ($mail['mode'] ?? 'log');

        $entetes = [
            'From: ' . $this->encoder($fromName) . ' <' . $from . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        if ($mode === 'log') {
            return $this->journaliser($destinataire, $sujet, $texte, $from);
        }

        $ok = @mail($destinataire, $this->encoder($sujet), $texte, implode("\r\n", $entetes));
        if (!$ok) {
            Log::error('Envoi de courriel impossible.', ['destinataire' => $destinataire, 'sujet' => $sujet]);
        }
        return $ok;
    }

    private function journaliser(string $destinataire, string $sujet, string $texte, string $from): bool
    {
        $dir = (string) Config::get('storage.mails');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }

        $nom = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.eml';
        $contenu = "From: $from\r\n"
            . "To: $destinataire\r\n"
            . 'Subject: ' . $this->encoder($sujet) . "\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $texte . "\r\n";

        return @file_put_contents($dir . '/' . $nom, $contenu, LOCK_EX) !== false;
    }

    private function encoder(string $valeur): string
    {
        return '=?UTF-8?B?' . base64_encode($valeur) . '?=';
    }
}
