<?php

namespace App\Traits;

use App\Models\Etablissement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    public static function bootBelongsToTenant()
    {
        // 1. Scope queries by etablissement_id
        //    Le scope est ignoré si aucun utilisateur n'est encore authentifié
        //    (ex: pendant la route /login), aucun utilisateur n'est connecté.
        static::addGlobalScope('tenant', function (Builder $builder) {
            $user = Auth::user();
            if ($user && $user->etablissement_id) {
                $builder->where($builder->getQuery()->from . '.etablissement_id', $user->etablissement_id);
            }
        });

        // 2. Automatically set etablissement_id on create
        static::creating(function (Model $model) {
            if ($model instanceof \App\Models\User && $model->role === 'super_admin') {
                return;
            }

            if (!$model->etablissement_id) {
                $user = Auth::user();
                if ($user && $user->etablissement_id) {
                    $model->etablissement_id = $user->etablissement_id;
                } else {
                    $model->etablissement_id = Etablissement::query()->latest('id')->value('id');
                }
            }
        });
    }

    public function etablissement()
    {
        return $this->belongsTo(Etablissement::class);
    }
}
