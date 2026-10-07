<?php

namespace App\Models;

use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['client_id', 'opportunity_id', 'user_id', 'type', 'activity_date', 'status', 'description'])]
class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    /**
     * Las etiquetas viajan con el JSON, como en clientes y oportunidades,
     * para que la interfaz muestre las mismas palabras que guardó el backend.
     *
     * @var list<string>
     */
    protected $appends = [
        'type_label',
        'status_label',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activity_date' => 'datetime',
        ];
    }

    /**
     * Cliente al que pertenece la actividad. Obligatorio: una actividad sin
     * cliente no tiene sentido comercial ni puede mostrarse en su ficha.
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Oportunidad con la que se relaciona el seguimiento, si la hay. Siempre
     * del mismo cliente que la actividad.
     *
     * @return BelongsTo<Opportunity, $this>
     */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    /**
     * Responsable de la actividad. Nullable para conservar el historial si se
     * elimina la cuenta; el formulario sí lo exige.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Etiqueta legible del tipo. Null safe: el appended se calcula aunque el
     * eager load restrinja columnas.
     */
    protected function typeLabel(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->labelFor('type', 'activities.types'));
    }

    protected function statusLabel(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->labelFor('status', 'activities.statuses'));
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
     * @param  Builder<Activity>  $query
     */
    public function scopeStatus(Builder $query, ?string $status): void
    {
        $query->when($status, fn (Builder $query) => $query->where('status', $status));
    }

    /**
     * @param  Builder<Activity>  $query
     */
    public function scopeType(Builder $query, ?string $type): void
    {
        $query->when($type, fn (Builder $query) => $query->where('type', $type));
    }

    /**
     * Búsqueda por descripción o nombre del cliente. El grupo se cierra con
     * paréntesis para que un filtro posterior no se escape de la búsqueda.
     *
     * @param  Builder<Activity>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $query) use ($term): void {
            $like = '%'.$term.'%';

            $query->where('description', 'like', $like)
                ->orWhereHas(
                    'client',
                    fn (Builder $query) => $query->where('name', 'like', $like),
                );
        });
    }

    /**
     * @param  Builder<Activity>  $query
     */
    public function scopeDateBetween(Builder $query, ?string $from, ?string $to): void
    {
        $query->when($from, fn (Builder $query, string $from) => $query->whereDate('activity_date', '>=', $from))
            ->when($to, fn (Builder $query, string $to) => $query->whereDate('activity_date', '<=', $to));
    }
}
