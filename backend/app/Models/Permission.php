<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Permission granulaire accordable à un compte, en complément de son rôle. */
class Permission extends Model
{
    public $timestamps = false;

    protected $fillable = ['code', 'module', 'libelle'];

    public function utilisateurs(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'permission_user');
    }
}
