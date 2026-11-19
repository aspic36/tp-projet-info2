<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AuditRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array{data:array<int,array<string,mixed>>,total:int,page:int,par_page:int} */
    public function search(?string $action, ?string $dateDebut, ?string $dateFin, int $page, int $parPage): array
    {
        $where = [];
        $params = [];
        if ($action !== null && $action !== '') {
            $where[] = 'a.action = :action';
            $params[':action'] = $action;
        }
        if ($dateDebut !== null && $dateDebut !== '') {
            $where[] = 'a.cree_le >= :date_debut';
            $params[':date_debut'] = $dateDebut . ' 00:00:00';
        }
        if ($dateFin !== null && $dateFin !== '') {
            $where[] = 'a.cree_le <= :date_fin';
            $params[':date_fin'] = $dateFin . ' 23:59:59';
        }

        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM journal_audit a' . $clause);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $sql = 'SELECT a.id, a.action, a.entite, a.entite_id, a.details, a.adresse_ip, a.cree_le,
                       u.prenom AS auteur_prenom, u.nom AS auteur_nom, u.role AS auteur_role
                FROM journal_audit a
                LEFT JOIN utilisateur u ON u.id = a.utilisateur_id'
            . $clause
            . ' ORDER BY a.cree_le DESC, a.id DESC LIMIT :limite OFFSET :decalage';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limite', $parPage, PDO::PARAM_INT);
        $stmt->bindValue(':decalage', ($page - 1) * $parPage, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data'     => $stmt->fetchAll(),
            'total'    => $total,
            'page'     => $page,
            'par_page' => $parPage,
        ];
    }
}
