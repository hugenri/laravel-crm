<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contactos: las personas que pertenecen a un cliente.
     *
     * restrictOnDelete: un cliente con contactos no se borra, se inactiva;
     * DeleteClient avisa antes de que explote la restricción.
     */
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('position', 120)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();

            $table->timestamps();

            // La ficha del cliente y el listado global ordenan por apellido.
            $table->index(['client_id', 'last_name']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
