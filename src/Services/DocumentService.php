<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\ApiException;
use App\Repositories\SoutenanceRepository;
use App\Support\Pdf;

final class DocumentService
{
    private const TYPES = ['convention', 'attestation', 'pv', 'convocation'];

    public function __construct(
        private readonly DemandeService $demandes,
        private readonly SoutenanceRepository $soutenances,
        private readonly Pdf $pdf
    ) {
    }

    /** @return array{contenu:string,nom:string,mime:string} */
    public function generer(array $user, int $demandeId, string $type, string $format = 'html'): array
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new ApiException(404, 'Document inconnu.');
        }
        $demande = $this->demandes->detail($user, $demandeId);
        if (!in_array($demande['statut'], ['VALIDEE'], true)) {
            throw new ApiException(409, 'La demande doit être validée pour générer ce document.');
        }
        $soutenance = $demande['soutenance'];
        if (in_array($type, ['pv', 'convocation'], true) && $soutenance === null) {
            throw new ApiException(409, 'La soutenance doit être programmée.');
        }

        $titre = match ($type) {
            'convention'  => 'Convention de stage',
            'attestation' => 'Attestation de stage',
            'pv'          => 'Procès-verbal de soutenance',
            'convocation' => 'Convocation de soutenance',
        };
        $html = $this->html($titre, $this->corps($type, $demande, $soutenance));
        $nom = $type . '-demande-' . $demandeId;

        if ($format === 'pdf') {
            $fichier = $this->pdf->depuisHtml($html, $nom);
            $contenu = (string) file_get_contents($fichier['chemin']);
            @unlink($fichier['chemin']);
            return ['contenu' => $contenu, 'nom' => $fichier['nom'], 'mime' => 'application/pdf'];
        }

        return ['contenu' => $html, 'nom' => $nom . '.html', 'mime' => 'text/html; charset=utf-8'];
    }

    private function corps(string $type, array $demande, ?array $soutenance): string
    {
        $etudiant = $this->e($demande['etudiant_prenom'] . ' ' . $demande['etudiant_nom']);
        $entreprise = $this->e($demande['entreprise']);
        $ville = $this->e($demande['entreprise_ville']);
        $filiere = $this->e($demande['filiere']);
        $matricule = $this->e($demande['matricule']);
        $sujet = $this->e((string) ($demande['sujet'] ?? 'Stage en entreprise'));
        $debut = $this->e((string) ($demande['date_debut'] ?? ''));
        $fin = $this->e((string) ($demande['date_fin'] ?? ''));
        $tuteur = $this->e((string) ($demande['tuteur_entreprise'] ?? ''));
        $encadreur = $this->e(trim((string) ($demande['encadreur_prenom'] ?? '') . ' ' . (string) ($demande['encadreur_nom'] ?? '')));

        if ($type === 'convention') {
            return "<p>Entre l’établissement et <strong>$entreprise</strong> ($ville), il est convenu d’accueillir
                <strong>$etudiant</strong>, matricule <strong>$matricule</strong>, étudiant en <strong>$filiere</strong>,
                pour un stage du <strong>$debut</strong> au <strong>$fin</strong>.</p>
                <p><strong>Objet :</strong> $sujet</p>
                <p><strong>Missions :</strong> " . nl2br($this->e((string) ($demande['missions'] ?? 'À préciser'))) . "</p>
                <p><strong>Tuteur en entreprise :</strong> $tuteur<br>
                <strong>Encadreur pédagogique :</strong> $encadreur</p>";
        }

        if ($type === 'attestation') {
            return "<p>Nous attestons que <strong>$etudiant</strong>, matricule <strong>$matricule</strong>,
                a effectué un stage au sein de <strong>$entreprise</strong> à <strong>$ville</strong>
                du <strong>$debut</strong> au <strong>$fin</strong>.</p>
                <p>Le stage avait pour objet : <strong>$sujet</strong>.</p>";
        }

        if ($type === 'convocation') {
            $jury = '';
            foreach (($soutenance['jury'] ?? []) as $membre) {
                $jury .= '<li>' . $this->e($membre['role_jury'] . ' : ' . $membre['prenom'] . ' ' . $membre['nom']) . '</li>';
            }
            return "<p><strong>$etudiant</strong> est convoqué(e) à sa soutenance de stage.</p>
                <p><strong>Date :</strong> " . $this->e((string) $soutenance['date_heure']) . "<br>
                <strong>Salle :</strong> " . $this->e((string) $soutenance['salle']) . "<br>
                <strong>Durée :</strong> " . (int) $soutenance['duree_min'] . " minutes<br>
                <strong>Entreprise :</strong> $entreprise</p>
                " . ($jury === '' ? '' : "<p><strong>Jury :</strong></p><ul>$jury</ul>");
        }

        $jury = '';
        foreach (($soutenance['jury'] ?? []) as $membre) {
            $jury .= '<li>' . $this->e($membre['role_jury'] . ' : ' . $membre['prenom'] . ' ' . $membre['nom']) . '</li>';
        }
        $note = $demande['note'] === null ? 'À compléter' : number_format((float) $demande['note'], 2, ',', ' ') . '/20';
        return "<p><strong>Étudiant :</strong> $etudiant — <strong>Matricule :</strong> $matricule<br>
            <strong>Entreprise :</strong> $entreprise ($ville)<br>
            <strong>Sujet :</strong> $sujet</p>
            <p><strong>Date :</strong> " . $this->e((string) $soutenance['date_heure']) . " — <strong>Salle :</strong> " . $this->e((string) $soutenance['salle']) . "</p>
            <p><strong>Jury :</strong></p><ul>$jury</ul>
            <p><strong>Note :</strong> $note — <strong>Mention :</strong> " . $this->e((string) ($demande['mention'] ?? 'À compléter')) . "</p>
            <p><strong>Appréciation :</strong> " . nl2br($this->e((string) ($demande['appreciation'] ?? ''))) . "</p>";
    }

    private function html(string $titre, string $corps): string
    {
        return '<!doctype html><html lang="fr"><head><meta charset="utf-8"><style>
            @page{size:A4;margin:18mm}
            body{font-family:Georgia,serif;color:#16202c;font-size:12pt;line-height:1.55}
            .entete{display:flex;justify-content:space-between;border-bottom:2px solid #0f5c86;padding-bottom:8px;margin-bottom:24px}
            .entete strong{font-size:13pt}.entete span{color:#55637a;font-size:9pt}
            h1{font-size:20pt;color:#0f5c86;margin:0 0 18px;text-align:center}
            .signatures{display:flex;justify-content:space-between;margin-top:48px;gap:24px}
            .signature{width:30%;border-top:1px solid #16202c;padding-top:6px;font-size:9pt;text-align:center}
        </style></head><body><div class="entete"><div><strong>Université — L3 SIL</strong><br><span>Gestion des stages universitaires</span></div>
        <span>' . date('d/m/Y') . '</span></div><h1>' . $this->e($titre) . '</h1>' . $corps
        . '<div class="signatures"><div class="signature">L’étudiant</div><div class="signature">L’entreprise</div><div class="signature">L’établissement</div></div></body></html>';
    }

    private function e(string $valeur): string
    {
        return htmlspecialchars($valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
