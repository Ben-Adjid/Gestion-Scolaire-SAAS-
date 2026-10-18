<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('messages')) {
            Schema::create('messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sender_id');
                $table->unsignedBigInteger('recipient_id')->nullable();
                $table->unsignedBigInteger('class_id')->nullable();
                $table->string('subject');
                $table->longText('body');
                $table->string('type')->default('direct');
                $table->boolean('read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->unsignedBigInteger('etablissement_id')->default(1);
                $table->timestamps();

                $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('recipient_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('class_id')->references('id')->on('classes')->onDelete('cascade');

                $table->index(['recipient_id', 'read']);
                $table->index(['sender_id', 'created_at']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('messages');
    }
};
