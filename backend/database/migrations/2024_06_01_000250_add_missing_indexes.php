<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Cette migration est vide car les indexes ont déjà été créés
     * par les migrations précédentes (000230 et autres).
     * On la laisse juste pour que le système de migration la marque comme "Ran".
     */
    public function up()
    {
        // Indexes already exist from previous migrations
        // No action needed
    }

    public function down()
    {
        // No action needed
    }
};
