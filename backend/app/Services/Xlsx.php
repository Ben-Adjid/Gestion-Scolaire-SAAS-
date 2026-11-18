<?php

namespace App\Services;

use ZipArchive;

/**
 * Génère un classeur Excel (.xlsx) sans dépendance externe : en-têtes stylés, colonnes dimensionnées,
 * ligne d'en-tête figée, filtres automatiques, montants formatés avec séparateurs.
 */
class Xlsx
{
    /**
     * @param  list<string>  $entetes
     * @param  list<list<scalar|null>>  $lignes
     * @param  list<string>  $colonnesMontant  intitulés des colonnes à formater en nombre
     * @param  string|null  $titre  ligne de titre fusionnée (ex. « Lycée X — Liste des élèves ») ; aucune si null
     */
    public static function generer(string $feuille, array $entetes, array $lignes, array $colonnesMontant = [], ?string $titre = null): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        $zip->open($chemin, ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::rels());
        $zip->addFromString('xl/workbook.xml', self::workbook($feuille));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels());
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::feuille($entetes, $lignes, $colonnesMontant, $titre));
        $zip->close();

        $contenu = file_get_contents($chemin);
        @unlink($chemin);

        return $contenu;
    }

    private static function feuille(array $entetes, array $lignes, array $colonnesMontant, ?string $titre = null): string
    {
        $nbColonnes = count($entetes);
        // Avec un titre, tout le tableau descend d'une ligne.
        $decalage = $titre !== null && $titre !== '' ? 1 : 0;
        $ligneEntete = 1 + $decalage;
        $derniereColonne = self::lettre($nbColonnes - 1);
        $derniere = $derniereColonne.(count($lignes) + $ligneEntete);

        // Largeur de colonne : la plus longue valeur, bornée entre 12 et 45 caractères.
        $largeurs = [];
        foreach ($entetes as $i => $intituleColonne) {
            $largeurs[$i] = min(45, max(12, mb_strlen((string) $intituleColonne) + 4));
        }
        foreach ($lignes as $ligne) {
            foreach (array_values($ligne) as $i => $valeur) {
                $largeurs[$i] = min(45, max($largeurs[$i] ?? 12, mb_strlen((string) $valeur) + 3));
            }
        }

        $cols = '';
        foreach ($largeurs as $i => $w) {
            $n = $i + 1;
            $cols .= "<col min=\"{$n}\" max=\"{$n}\" width=\"{$w}\" customWidth=\"1\"/>";
        }

        $rows = '';
        if ($decalage) {
            $rows .= '<row r="1" ht="30" customHeight="1">'.self::cellule('A1', (string) $titre, 4).'</row>';
        }

        $rows .= "<row r=\"{$ligneEntete}\" ht=\"26\" customHeight=\"1\">";
        foreach ($entetes as $i => $intitule) {
            $rows .= self::cellule(self::lettre($i).$ligneEntete, (string) $intitule, 1);
        }
        $rows .= '</row>';

        foreach ($lignes as $r => $ligne) {
            $numero = $r + $ligneEntete + 1;
            $rows .= "<row r=\"{$numero}\">";
            foreach (array_values($ligne) as $i => $valeur) {
                $ref = self::lettre($i).$numero;
                $estMontant = in_array($entetes[$i] ?? '', $colonnesMontant, true) && is_numeric($valeur);
                $rows .= $estMontant
                    ? "<c r=\"{$ref}\" s=\"3\"><v>".(float) $valeur.'</v></c>'
                    : self::cellule($ref, (string) ($valeur ?? ''), 2);
            }
            $rows .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="'.$ligneEntete.'" topLeftCell="A'.($ligneEntete + 1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols>'.$cols.'</cols>'
            .'<sheetData>'.$rows.'</sheetData>'
            .'<autoFilter ref="A'.$ligneEntete.':'.$derniere.'"/>'
            .($decalage ? '<mergeCells count="1"><mergeCell ref="A1:'.$derniereColonne.'1"/></mergeCells>' : '')
            .'</worksheet>';
    }

    private static function cellule(string $ref, string $texte, int $style): string
    {
        $echappe = htmlspecialchars($texte, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return "<c r=\"{$ref}\" s=\"{$style}\" t=\"inlineStr\"><is><t xml:space=\"preserve\">{$echappe}</t></is></c>";
    }

    private static function lettre(int $index): string
    {
        $lettre = '';
        $n = $index + 1;
        while ($n > 0) {
            $reste = ($n - 1) % 26;
            $lettre = chr(65 + $reste).$lettre;
            $n = intdiv($n - 1, 26);
        }

        return $lettre;
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>'
            .'<fonts count="4">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><sz val="11"/><color rgb="FF15302A"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="14"/><color rgb="FF1F5C4A"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="3">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF1F5C4A"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left/><right/><top/><bottom style="thin"><color rgb="FFE4DED3"/></bottom><diagonal/></border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="5">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>'
            .'<xf numFmtId="164" fontId="2" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'</cellXfs>'
            .'</styleSheet>';
    }

    /**
     * Lit la première feuille d'un classeur .xlsx.
     *
     * @return array{0: list<string>, 1: list<list<string>>} en-têtes et lignes (cellules vides incluses)
     */
    public static function lire(string $chemin): array
    {
        $zip = new ZipArchive();
        if ($zip->open($chemin) !== true) {
            return [[], []];
        }

        $chaines = [];
        $partages = $zip->getFromName('xl/sharedStrings.xml');
        if ($partages !== false) {
            $xml = simplexml_load_string($partages);
            foreach ($xml->si ?? [] as $si) {
                // Texte simple ou texte enrichi (plusieurs <r><t>) : on concatène.
                $chaines[] = implode('', array_map(fn ($t) => (string) $t, iterator_to_array($si->xpath('.//*[local-name()="t"]') ?: [])));
            }
        }

        $feuille = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($feuille === false) {
            return [[], []];
        }

        $xml = simplexml_load_string($feuille);
        $lignes = [];
        foreach ($xml->sheetData->row ?? [] as $row) {
            $ligne = [];
            foreach ($row->c as $cellule) {
                $position = self::indexColonne((string) $cellule['r']);
                $type = (string) $cellule['t'];
                if ($type === 'inlineStr') {
                    $valeur = implode('', array_map(fn ($t) => (string) $t, iterator_to_array($cellule->xpath('.//*[local-name()="t"]') ?: [])));
                } elseif ($type === 's') {
                    $valeur = $chaines[(int) $cellule->v] ?? '';
                } else {
                    $valeur = (string) $cellule->v;
                }
                $ligne[$position] = $valeur;
            }
            if ($ligne === []) {
                continue;
            }
            $max = max(array_keys($ligne));
            $lignes[] = array_map(fn ($i) => $ligne[$i] ?? '', range(0, $max));
        }

        // Une ligne de titre (une seule cellule remplie au-dessus des en-têtes) est ignorée :
        // nos propres exports se réimportent ainsi sans retouche.
        $remplies = fn (array $l) => count(array_filter($l, fn ($c) => trim((string) $c) !== ''));
        while (count($lignes) > 1 && $remplies($lignes[0]) <= 1 && $remplies($lignes[1]) > 1) {
            array_shift($lignes);
        }

        $entetes = array_shift($lignes) ?? [];

        return [array_map(fn ($e) => trim((string) $e), $entetes), $lignes];
    }

    /** Index de colonne (0 pour A, 25 pour Z, 26 pour AA) à partir d'une référence de cellule (ex. « C5 »). */
    private static function indexColonne(string $reference): int
    {
        preg_match('/^([A-Z]+)/', $reference, $m);
        $lettres = $m[1] ?? 'A';
        $index = 0;
        foreach (str_split($lettres) as $lettre) {
            $index = $index * 26 + (ord($lettre) - 64);
        }

        return $index - 1;
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private static function rels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private static function workbook(string $feuille): string
    {
        $nom = htmlspecialchars(mb_substr($feuille, 0, 31), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$nom.'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private static function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }
}
