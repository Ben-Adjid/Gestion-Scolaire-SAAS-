<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtres de la liste des élèves, partagés entre l'écran et les exports : périmètre de l'enseignant,
 * classe, genre, recherche (nom, prénom, matricule, e-mail) et statut (actif = inscrit cette année).
 */
class FiltreEleves
{
    public static function appliquer(Builder $query, Request $request): Builder
    {
        $classeIds = TeacherScope::classeIds($request->user());
        if ($classeIds !== null) {
            $query->whereIn('classe_id', $classeIds);
        }

        if ($request->classe_id) {
            $query->where('classe_id', $request->classe_id);
        }

        if (in_array($request->query('genre'), ['M', 'F'], true)) {
            $query->where('genre', $request->query('genre'));
        }

        // Recherche insensible à la casse ; les espaces aux extrémités sont ignorés.
        $recherche = trim((string) $request->query('search', ''));
        if ($recherche !== '') {
            $query->where(fn ($q) => $q->where('nom', 'like', "%{$recherche}%")
                ->orWhere('prenom', 'like', "%{$recherche}%")
                ->orWhere('matricule', 'like', "%{$recherche}%")
                ->orWhere('email', 'like', "%{$recherche}%"));
        }

        $statut = $request->query('statut');
        if (in_array($statut, ['actif', 'inactif'], true)) {
            $inscrit = self::inscritCetteAnnee($request);
            $statut === 'actif'
                ? $query->whereHas('inscriptions', $inscrit)
                : $query->whereDoesntHave('inscriptions', $inscrit);
        }

        return $query;
    }

    /** Contrainte « inscription active pour l'année scolaire de l'école ». */
    public static function inscritCetteAnnee(Request $request): \Closure
    {
        $annee = $request->user()->etablissement?->annee_scolaire;

        return fn ($q) => $q->where('statut', 'active')->when($annee, fn ($i) => $i->where('annee_scolaire', $annee));
    }
}
