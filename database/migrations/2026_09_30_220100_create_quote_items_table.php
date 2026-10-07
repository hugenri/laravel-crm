<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Partidas de una cotización.
     *
     * "position" guarda el orden de captura: SaveQuote reemplaza el conjunto
     * completo y los ids nuevos no conservarían el lugar.
     * "amount" lo calcula el servidor para que PDF, pantalla y subtotal
     * digan exactamente lo mismo.
     * "quantity" admite tres decimales (horas, kilos o metros); el peso es
     * de dos.
     *
     * cascadeOnDelete: una partida sin cotización sería un registro
     * invisible que falsearía el subtotal; DeleteQuote se lleva las
     * partidas en la misma operación.
     */
    public function up(): void
    {
        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('quote_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->unsignedInteger('position');
            $table->string('description', 255);
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('amount', 15, 2);

            $table->timestamps();

            // El detalle siempre se lee ordenado y siempre completo.
            $table->index(['quote_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_items');
    }
};
