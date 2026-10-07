<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Actividades: el seguimiento comercial de clientes y oportunidades.
     * Los datos del cliente y la oportunidad no se copian: los resuelven las
     * relaciones.
     *
     * client_id restrictOnDelete: un cliente con actividades no se borra, se
     * inactiva.
     * opportunity_id restrictOnDelete: nullOnDelete rompería el vínculo de la
     * actividad con su oportunidad.
     * user_id nullOnDelete: la actividad conserva sus datos y el
     * responsable queda en null.
     */
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('opportunity_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('type', 20)->index();
            $table->dateTime('activity_date')->index();
            $table->string('status', 20)->index();
            $table->text('description');

            $table->timestamps();

            // El listado consulta por cliente, oportunidad, responsable y rango de fechas.
            $table->index(['client_id', 'activity_date']);
            $table->index(['opportunity_id', 'activity_date']);
            $table->index(['user_id', 'activity_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
