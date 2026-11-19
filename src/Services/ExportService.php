<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\ApiException;
use App\Repositories\DemandeRepository;
use App\Support\Pdf;
use PDO;
use ZipArchive;

final class ExportService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly DemandeRepository $demandes,
        private readonly Pdf $pdf
    ) {
    }

    /** @return array{contenu:string,nom:string,mime:string} */
    public function demandesCsv(array $user): array
    {
        $this->exigerExport($user);
        $lignes = [];
        foreach ($this->demandes->findAllForExport() as $d) {
            $lignes[] = [
                $d['id'],
                $d['etudiant_prenom'] . ' ' . $d['etudiant_nom'],
                $d['matricule'],
                $d['filiere'],
                $d['promotion'],
                $d['entreprise'],
                $d['entreprise_ville'],
                $d['statut'],
                $d['sujet'] ?? '',
                $d['date_debut'] ?? '',
                $d['date_fin'] ?? '',
                $d['tuteur_entreprise'] ?? '',
                trim((string) ($d['encadreur_prenom'] ?? '') . ' ' . (string) ($d['encadreur_nom'] ?? '')),
                $d['note'] ?? '',
                $d['date_soumission'] ?? '',
                $d['date_decision'] ?? '',
            ];
        }
        $entete = ['ID', 'Étudiant', 'Matricule', 'Filière', 'Promotion', 'Entreprise', 'Ville', 'Statut',
            'Sujet', 'Début', 'Fin', 'Tuteur entreprise', 'Encadreur', 'Note', 'Soumission', 'Décision'];
        return [
            'contenu' => $this->csv($entete, $lignes),
            'nom'     => 'demandes-' . date('Ymd') . '.csv',
            'mime'    => 'text/csv; charset=utf-8',
        ];
    }

    /** @return array{contenu:string,nom:string,mime:string} */
    public function statistiquesCsv(array $user): array
    {
        $this->exigerExport($user);
        $parStatut = $this->compter('SELECT statut AS libelle, COUNT(*) AS total FROM demande_stage GROUP BY statut');
        $parFiliere = $this->compter('SELECT e.filiere AS libelle, COUNT(*) AS total FROM demande_stage d JOIN etudiant e ON e.id = d.etudiant_id GROUP BY e.filiere');
        $parEntreprise = $this->compter('SELECT en.raison_sociale AS libelle, COUNT(*) AS total FROM demande_stage d JOIN entreprise en ON en.id = d.entreprise_id GROUP BY en.raison_sociale ORDER BY total DESC LIMIT 20');
        $parEncadreur = $this->compter("SELECT CONCAT(u.prenom, ' ', u.nom) AS libelle, COUNT(*) AS total FROM demande_stage d JOIN encadreur enc ON enc.id = d.encadreur_id JOIN utilisateur u ON u.id = enc.utilisateur_id GROUP BY u.id, u.prenom, u.nom ORDER BY total DESC");
        $lignes = [];
        foreach ([['Statut', $parStatut], ['Filière', $parFiliere], ['Entreprise', $parEntreprise], ['Encadreur', $parEncadreur]] as $bloc) {
            foreach ($bloc[1] as $ligne) {
                $lignes[] = [$bloc[0], $ligne['libelle'], $ligne['total']];
            }
        }
        return [
            'contenu' => $this->csv(['Catégorie', 'Libellé', 'Total'], $lignes),
            'nom'     => 'statistiques-' . date('Ymd') . '.csv',
            'mime'    => 'text/csv; charset=utf-8',
        ];
    }

    /** @return array{contenu:string,nom:string,mime:string} */
    public function demandesPdf(array $user): array
    {
        $this->exigerExport($user);
        $html = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><style>
            body{font-family:Segoe UI,Arial,sans-serif;font-size:10px;color:#16202c}
            h1{font-size:20px;margin:0 0 4px} p{color:#55637a;margin:0 0 14px}
            table{width:100%;border-collapse:collapse} th,td{border:1px solid #d8dee9;padding:4px 5px;text-align:left}
            th{background:#eef3f8;font-size:9px;text-transform:uppercase}
            tr:nth-child(even){background:#f8fafc}
        </style></head><body><h1>Demandes de stage</h1><p>Export du ' . date('d/m/Y à H:i') . '</p><table><thead><tr>
            <th>Étudiant</th><th>Matricule</th><th>Entreprise</th><th>Statut</th><th>Encadreur</th><th>Soumission</th><th>Note</th>
        </tr></thead><tbody>';
        foreach ($this->demandes->findAllForExport() as $d) {
            $html .= '<tr><td>' . $this->e($d['etudiant_prenom'] . ' ' . $d['etudiant_nom']) . '</td>'
                . '<td>' . $this->e($d['matricule']) . '</td>'
                . '<td>' . $this->e($d['entreprise']) . '</td>'
                . '<td>' . $this->e($d['statut']) . '</td>'
                . '<td>' . $this->e(trim((string) ($d['encadreur_prenom'] ?? '') . ' ' . (string) ($d['encadreur_nom'] ?? ''))) . '</td>'
                . '<td>' . $this->e((string) ($d['date_soumission'] ?? '')) . '</td>'
                . '<td>' . $this->e((string) ($d['note'] ?? '')) . '</td></tr>';
        }
        $html .= '</tbody></table></body></html>';
        $fichier = $this->pdf->depuisHtml($html, 'demandes-' . date('Ymd'));
        $contenu = (string) file_get_contents($fichier['chemin']);
        @unlink($fichier['chemin']);
        return ['contenu' => $contenu, 'nom' => $fichier['nom'], 'mime' => 'application/pdf'];
    }

    /** @return array{contenu:string,nom:string,mime:string} */
    public function globalZip(array $user): array
    {
        $this->exigerExport($user);
        $zip = new ZipArchive();
        $chemin = tempnam(sys_get_temp_dir(), 'stages_export_');
        if ($chemin === false || $zip->open($chemin, ZipArchive::OVERWRITE) !== true) {
            throw new ApiException(500, 'Archive indisponible.');
        }
        $zip->addFromString('demandes.csv', $this->demandesCsv($user)['contenu']);
        $zip->addFromString('statistiques.csv', $this->statistiquesCsv($user)['contenu']);
        $zip->addFromString('utilisateurs.csv', $this->utilisateursCsv());
        $zip->addFromString('entreprises.csv', $this->entreprisesCsv());
        $zip->addFromString('soutenances.csv', $this->soutenancesCsv());
        $zip->close();
        $contenu = (string) file_get_contents($chemin);
        @unlink($chemin);
        return ['contenu' => $contenu, 'nom' => 'export-global-' . date('Ymd') . '.zip', 'mime' => 'application/zip'];
    }

    private function utilisateursCsv(): string
    {
        $rows = $this->pdo->query(
            'SELECT id, email, role, prenom, nom, telephone, actif, date_creation FROM utilisateur ORDER BY id'
        )->fetchAll();
        $lignes = [];
        foreach ($rows as $row) {
            $lignes[] = array_values($row);
        }
        return $this->csv(['ID', 'Email', 'Rôle', 'Prénom', 'Nom', 'Téléphone', 'Actif', 'Créé le'], $lignes);
    }

    private function entreprisesCsv(): string
    {
        $rows = $this->pdo->query(
            'SELECT id, raison_sociale, ville, email_contact, secteur, contact_nom, telephone, adresse, statut, motif_statut FROM entreprise ORDER BY raison_sociale'
        )->fetchAll();
        $lignes = [];
        foreach ($rows as $row) {
            $lignes[] = array_values($row);
        }
        return $this->csv(['ID', 'Raison sociale', 'Ville', 'Email', 'Secteur', 'Contact', 'Téléphone', 'Adresse', 'Statut', 'Motif'], $lignes);
    }

    private function soutenancesCsv(): string
    {
        $rows = $this->pdo->query(
            'SELECT s.id, s.demande_id, s.date_heure, s.salle, s.duree_min, s.convocation_envoyee,
                    u.prenom, u.nom, en.raison_sociale
             FROM soutenance s
             JOIN demande_stage d ON d.id = s.demande_id
             JOIN etudiant e ON e.id = d.etudiant_id
             JOIN utilisateur u ON u.id = e.utilisateur_id
             JOIN entreprise en ON en.id = d.entreprise_id
             ORDER BY s.date_heure'
        )->fetchAll();
        $lignes = [];
        foreach ($rows as $row) {
            $lignes[] = array_values($row);
        }
        return $this->csv(['ID', 'Demande', 'Date', 'Salle', 'Durée', 'Convoquée', 'Prénom', 'Nom', 'Entreprise'], $lignes);
    }

    /** @return array<int,array<string,mixed>> */
    private function compter(string $sql): array
    {
        return $this->pdo->query($sql)->fetchAll();
    }

    /** @param array<int,string> $entete
     *  @param array<int,array<int,mixed>> $lignes
     */
    private function csv(array $entete, array $lignes): string
    {
        $flux = fopen('php://temp', 'r+');
        if ($flux === false) {
            throw new ApiException(500, 'Export CSV indisponible.');
        }
        fwrite($flux, "\xEF\xBB\xBF");
        fputcsv($flux, $entete, ';', '"', '\\');
        foreach ($lignes as $ligne) {
            fputcsv($flux, $ligne, ';', '"', '\\');
        }
        rewind($flux);
        $contenu = (string) stream_get_contents($flux);
        fclose($flux);
        return $contenu;
    }

    private function e(string $valeur): string
    {
        return htmlspecialchars($valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function exigerExport(array $user): void
    {
        if (!in_array($user['role'], ['RESPONSABLE', 'SECRETARIAT', 'ADMIN'], true)) {
            throw new ApiException(403, 'Export réservé au personnel.');
        }
    }
}
