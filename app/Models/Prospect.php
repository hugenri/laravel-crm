<?php

namespace App\Models;

use Database\Factories\ProspectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prospectos: el embudo previo al cliente.
 *
 * "status", "client_id" y "contact_id" quedan fuera de Fillable a propósito:
 * los escribe una acción dedicada (ConvertProspect y change_status), no un
 * formulario. Así un fill() accidental no deja un prospecto convertido sin
 * cliente.
 */
#[Fillable(['user_id', 'name', 'company', 'phone', 'email', 'source', 'follow_up_date', 'notes'])]
class Prospect extends Model
{
    /** @use HasFactory<ProspectFactory> */
    use HasFactory;

    /**
     * Las etiquetas viajan con el JSON, como en el resto de los modulos, para
     * que la interfaz muestre las mismas palabras que el backend guardo.
     *
     * @var list<string>
     */
    protected $appends = [
        'status_label',
        'source_label',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'follow_up_date' => 'date',
        ];
    }

    /**
     * Responsable del prospecto. Nullable para no romper el borrado de
     * usuarios ni perder el historial; el formulario sí lo exige.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Cliente generado por la conversión. Es referencia, no historial: si se
     * borra, el prospecto conserva sus datos.
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Contacto generado por la conversion, que es el contacto principal del
     * cliente creado en ese mismo movimiento.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Determina si la ficha ofrece la acción de conversión y si sigue como
     * prospecto activo.
     */
    public function isConverted(): bool
    {
        return $this->status === config('prospects.converted_status');
    }

    /**
     * Etiqueta legible del estado. Null safe: el appended se calcula aunque
     * la relación se cargue con restricción de columnas.
     */
    protected function statusLabel(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->labelFor('status', 'prospects.statuses'));
    }

    protected function sourceLabel(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->labelFor('source', 'prospects.sources'));
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
     * Los estados que el formulario y la accion de cambio de estado ofrecen,
     * leidos del catalogo para que la lista no se repita en tres sitios.
     *
     * @return list<string>
     */
    public static function qualifiedStatuses(): array
    {
        return config('prospects.qualified_statuses');
    }

    /**
     * Prospectos que aún no se convirtieron: lo que queda por trabajar.
     *
     * @param  Builder<Prospect>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', '!=', config('prospects.converted_status'));
    }

    /**
     * @param  Builder<Prospect>  $query
     */
    public function scopeStatus(Builder $query, ?string $status): void
    {
        $query->when($status, fn (Builder $query) => $query->where('status', $status));
    }

    /**
     * @param  Builder<Prospect>  $query
     */
    public function scopeSource(Builder $query, ?string $source): void
    {
        $query->when($source, fn (Builder $query) => $query->where('source', $source));
    }

    /**
     * Búsqueda por nombre, empresa, teléfono y correo. El grupo se cierra con
     * paréntesis para que un filtro posterior no se escape de la búsqueda.
     *
     * @param  Builder<Prospect>  $query
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
                ->orWhere('company', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('email', 'like', $like);
        });
    }
}
