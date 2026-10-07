<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prospectos: las personas y empresas que todavía no son clientes.
     *
     * user_id nullOnDelete: el prospecto conserva sus datos y el
     * responsable queda en null.
     * client_id y contact_id nullOnDelete: los escribe ConvertProspect al
     * terminar y son un puntero de conveniencia, no historial; con
     * restrictOnDelete el cliente recién creado quedaría indestructible.
     *
     * El estado vive en config/prospects.php y el índice cubre el embudo:
     * estado y fecha de seguimiento.
     */
    public function up(): void
    {
        Schema::create('prospects', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('name');
            $table->string('company')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('source', 20);
            $table->string('status', 20)->default(config('prospects.initial_status'));
            $table->date('follow_up_date')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('client_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('contact_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->timestamps();

            // Embudo por estado, filtro por fuente, responsable y agenda de seguimiento.
            $table->index(['status', 'follow_up_date']);
            $table->index(['user_id', 'status']);
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospects');
    }
};
