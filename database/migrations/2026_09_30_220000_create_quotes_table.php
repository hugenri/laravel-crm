<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cotizaciones: las propuestas comerciales que el CRM emite al cliente.
     * El encabezado vive aquí y las partidas en quote_items.
     *
     * client_id restrictOnDelete: es un documento con folio e importes
     * propio, no se borra en cascada.
     * contact_id restrictOnDelete: a quién se mandó la propuesta es parte
     * del documento.
     * opportunity_id restrictOnDelete: sin el vínculo la cotización queda
     * huérfana.
     * user_id nullOnDelete: la cotización conserva su contenido y el
     * responsable queda en null.
     *
     * Importes en decimal, no float: un doble no representa 0.10 exacto y
     * los calcula CalculateQuoteTotals en el servidor. El folio sí es único:
     * dos cotizaciones iguales serían indistinguibles para el cliente.
     */
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();

            $table->string('folio', 30)->unique();

            $table->foreignId('client_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('contact_id')
                ->nullable()
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

            $table->date('issue_date');
            $table->date('valid_until');
            $table->string('status', 20)->default(config('quotes.initial_status'));

            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax_rate', 5, 2);
            $table->decimal('tax_amount', 15, 2);
            $table->decimal('total', 15, 2);

            $table->text('notes')->nullable();

            $table->timestamps();

            // Embudo por estado, historial por cliente o responsable y vencimiento por vigencia.
            $table->index(['status', 'issue_date']);
            $table->index(['client_id', 'issue_date']);
            $table->index(['opportunity_id', 'issue_date']);
            $table->index(['user_id', 'status']);
            $table->index('valid_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
