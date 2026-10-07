<?php

namespace App\Models;

use Database\Factories\OpportunityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['client_id', 'contact_id', 'user_id', 'name', 'amount', 'stage', 'expected_close_date', 'notes'])]
class Opportunity extends Model
{
    /** @use HasFactory<OpportunityFactory> */
    use HasFactory;

    /*
     * "stage" y "status" quedan fuera de Fillable a propósito: el estado se
     * deriva de la etapa (CreateOpportunity / ChangeOpportunityStatus) y un
     * fill() accidental no debe escribir un estado incoherente con su etapa.
     */
    /**
     * Las etiquetas viajan con el JSON, como en el módulo de clientes, para
     * que la interfaz muestre las mismas palabras que guardó el backend.
     *
     * @var list<string>
     */
    protected $appends = [
        'stage_label',
        'status_label',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // decimal, no float: el alcance maneja centavos en pesos y un
            // double no representa 0.10 de forma exacta.
            'amount' => 'decimal:2',
            'expected_close_date' => 'date',
        ];
    }

    /**
     * Cliente al que pertenece la oportunidad. Obligatorio: una oportunidad sin
     * cliente no tiene sentido comercial ni puede mostrarse en su ficha.
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Contacto del cliente con el que se trata la operacion. Opcional, y
     * siempre del mismo cliente que la oportunidad.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Responsable de la oportunidad. Nullable para no perder el historial ni
     * romper el borrado de usuarios; el formulario sí lo exige.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Actividades de seguimiento. Se muestran en la ficha y protegen el
     * borrado: una oportunidad con actividades no se elimina.
     *
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * Cotizaciones que responden a la oportunidad. Perder el vínculo dejaría
     * importes sin venta asociada, por eso usa restrictOnDelete y no se borra
     * una oportunidad con cotizaciones.
     *
     * @return HasMany<Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /**
     * ¿Sigue abierta? Determina si se muestra el boton de reabrir.
     */
    public function isOpen(): bool
    {
        return $this->status === config('opportunities.open_status');
    }

    public function isClosed(): bool
    {
        return ! $this->isOpen();
    }

    /**
     * Etapa legible. Null safe: el appended se calcula aunque el eager load
     * restrinja columnas.
     */
    protected function stageLabel(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->labelFor('stage', 'opportunities.stages'));
    }

    protected function statusLabel(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->labelFor('status', 'opportunities.statuses'));
    }

    private function labelFor(string $column, string $configKey): ?string
    {
        $value = $this->{$column};

        if ($value === null) {
            return null;
        }

        return config($configKey)[$value] ?? $value;
    }

    /**
     * @param  Builder<Opportunity>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', config('opportunities.open_status'));
    }

    /**
     * @param  Builder<Opportunity>  $query
     */
    public function scopeStage(Builder $query, ?string $stage): void
    {
        $query->when($stage, fn (Builder $query) => $query->where('stage', $stage));
    }

    /**
     * Búsqueda por nombre de la oportunidad o del cliente. El grupo se cierra
     * con paréntesis para que un filtro posterior no se escape de la búsqueda.
     *
     * @param  Builder<Opportunity>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $query) use ($term): void {
            $like = '%'.$term.'%';

            $query->where('name', 'like', $like)
                ->orWhereHas(
                    'client',
                    fn (Builder $query) => $query->where('name', 'like', $like),
                );
        });
    }
}
