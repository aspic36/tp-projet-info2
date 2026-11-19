<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class NotificationService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly MailService $mail
    ) {
    }

    public function envoyer(
        int $utilisateurId,
        string $type,
        string $titre,
        string $message,
        ?int $demandeId = null,
        ?string $email = null,
        bool $parEmail = true
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO notification (utilisateur_id, demande_id, type, titre, message)
             VALUES (:utilisateur_id, :demande_id, :type, :titre, :message)'
        );
        $stmt->execute([
            ':utilisateur_id' => $utilisateurId,
            ':demande_id'     => $demandeId,
            ':type'           => $type,
            ':titre'          => mb_substr($titre, 0, 160),
            ':message'         => mb_substr($message, 0, 500),
        ]);
        $notificationId = (int) $this->pdo->lastInsertId();

        if (!$parEmail) {
            return;
        }

        if ($email === null) {
            $lookup = $this->pdo->prepare('SELECT email FROM utilisateur WHERE id = :id');
            $lookup->execute([':id' => $utilisateurId]);
            $email = (string) ($lookup->fetchColumn() ?: '');
        }
        if ($email === '') {
            return;
        }

        $ok = $this->mail->envoyer($email, $titre, $message);
        $maj = $this->pdo->prepare(
            'UPDATE notification SET email_statut = :statut, email_erreur = :erreur WHERE id = :id'
        );
        $maj->execute([
            ':statut' => $ok ? 'ENVOYE' : 'ECHEC',
            ':erreur' => $ok ? null : 'Envoi indisponible',
            ':id'     => $notificationId,
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public function lister(int $utilisateurId, int $limite = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, demande_id, type, titre, message, lue, email_statut, cree_le
             FROM notification
             WHERE utilisateur_id = :utilisateur_id
             ORDER BY lue, cree_le DESC, id DESC
             LIMIT :limite'
        );
        $stmt->bindValue(':utilisateur_id', $utilisateurId, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];
            $row['demande_id'] = $row['demande_id'] === null ? null : (int) $row['demande_id'];
            $row['lue'] = (int) $row['lue'] === 1;
            return $row;
        }, $stmt->fetchAll());
    }

    public function compterNonLues(int $utilisateurId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM notification WHERE utilisateur_id = :utilisateur_id AND lue = 0'
        );
        $stmt->execute([':utilisateur_id' => $utilisateurId]);
        return (int) $stmt->fetchColumn();
    }

    public function marquerLue(int $utilisateurId, int $notificationId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE notification SET lue = 1 WHERE id = :id AND utilisateur_id = :utilisateur_id'
        );
        $stmt->execute([':id' => $notificationId, ':utilisateur_id' => $utilisateurId]);
    }

    public function marquerToutesLues(int $utilisateurId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE notification SET lue = 1 WHERE utilisateur_id = :utilisateur_id AND lue = 0'
        );
        $stmt->execute([':utilisateur_id' => $utilisateurId]);
    }
}
