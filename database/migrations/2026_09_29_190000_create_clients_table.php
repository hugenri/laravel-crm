<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cartera comercial del CRM. Columnas planas y sin JSON: una migración
     * posterior puede añadir campos sin rediseñar el módulo.
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();

            $table->string('type', 20)->index();
            $table->string('name');

            // RFC único de contribuyente, pero el alta es válida sin él.
            $table->string('rfc', 13)->nullable()->unique();

            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('status', 20)->default('activo')->index();
            $table->text('notes')->nullable();

            $table->timestamps();

            // El listado ordena por nombre y la búsqueda filtra por nombre, RFC y correo.
            $table->index('name');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
