<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Oportunidades: las posibles ventas de un cliente. Los datos del cliente
     * y del contacto no se copian: los resuelven las relaciones.
     *
     * client_id restrictOnDelete: un cliente con historial no se borra, se
     * inactiva.
     * contact_id nullOnDelete: la oportunidad sobrevive sin su contacto.
     * user_id nullOnDelete: no bloquea el borrado de usuarios.
     */
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('contact_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('name');

            // decimal y no float: un doble no representa centavos y el monto
            // se compara y se suma. Sin divisa: el alcance prohíbe multi-moneda.
            $table->decimal('amount', 15, 2)->nullable();

            $table->string('stage', 20)->default('nueva')->index();
            $table->string('status', 20)->default('abierta')->index();
            $table->date('expected_close_date')->nullable()->index();
            $table->text('notes')->nullable();

            $table->timestamps();

            // El listado filtra y ordena por cliente y responsable; la
            // búsqueda va por nombre.
            $table->index(['client_id', 'stage']);
            $table->index(['user_id', 'status']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
