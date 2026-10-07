<?php

namespace App\Models;

use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Una propuesta comercial dirigida a un cliente.
 *
 * Los importes del encabezado salen de las partidas (subtotal, impuesto y
 * total) y nunca se aceptan desde el formulario; ver QuoteRequest.
 */
#[Fillable(['client_id', 'contact_id', 'opportunity_id', 'user_id', 'issue_date', 'valid_until', 'tax_rate', 'notes'])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    /*
     * "folio", "status", "subtotal", "tax_amount" y "total" quedan fuera de
     * Fillable: el folio lo genera SaveQuote a partir del identificador, el
     * estado lo cambia la acción dedicada (quotes.change_status) y los
     * importes calcula el servidor a partir de las partidas. Un fill()
     * accidental no puede tocar ninguno de los cinco.
     */
    protected $appends = [
        'status_label',
        'display_status',
        'display_status_label',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'valid_until' => 'date',

            // decimal, no float: un doble no representa 0.10 con exactitud.
            // "tax_rate" es un porcentaje: 16.00 significa 16%.
            'subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /**
     * Cliente al que se dirige la propuesta. Obligatorio: una cotizacion sin
     * cliente no tiene destinatario y no se podria enviar.
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Contacto del cliente al que se envio la propuesta. Opcional, y siempre del
     * mismo cliente que la cotizacion.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Oportunidad a la que responde la propuesta. Opcional: se puede cotizar
     * sin operación registrada; si se indica, es del mismo cliente.
     *
     * @return BelongsTo<Opportunity, $this>
     */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    /**
     * Responsable de la propuesta. Nullable para no perder la cotización ni
     * romper el borrado de usuarios; el formulario sí lo exige.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Partidas de la propuesta, en el orden en que se capturaron.
     *
     * @return HasMany<QuoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Solo se puede editar en borrador: una vez enviada, cambiarle los
     * importes haría que el guardado no fuera el que recibió el cliente. Ver
     * "editable_statuses" en config/quotes.php.
     */
    public function isEditable(): bool
    {
        return in_array($this->status, config('quotes.editable_statuses'), true);
    }

    /**
     * La vigencia se deriva de la fecha y no se guarda: una propuesta enviada
     * ya vencida se muestra como vencida sin tarea programada que recorra la
     * tabla. Solo aplica a estados que esperan respuesta.
     */
    public function isExpired(): bool
    {
        if ($this->valid_until === null) {
            return false;
        }

        if (! in_array($this->status, config('quotes.pending_statuses'), true)) {
            return false;
        }

        return $this->valid_until->isBefore(Carbon::today());
    }

    /**
     * Estado en pantalla, que difiere del guardado cuando la vigencia venció.
     * El listado filtra por la columna (lo que eligió el usuario) y muestra
     * el estado efectivo; filtrar por "vencida" trae solo las marcadas a mano.
     */
    protected function displayStatus(): Attribute
    {
        return Attribute::get(fn (): string => $this->isExpired()
            ? 'vencida'
            : (string) $this->status);
    }

    protected function statusLabel(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->labelFor('status', 'quotes.statuses'));
    }

    protected function displayStatusLabel(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->labelFor('display_status', 'quotes.statuses'));
    }

    /**
     * Etiqueta legible. Null safe: el appended se calcula aunque el eager
     * load restrinja columnas.
     */
    private function labelFor(string $column, string $configKey): ?string
    {
        $value = $this->{$column};

        if ($value === null) {
            return null;
        }

        return config($configKey)[$value] ?? $value;
    }

    /**
     * @param  Builder<Quote>  $query
     */
    public function scopeStatus(Builder $query, ?string $status): void
    {
        $query->when($status, fn (Builder $query) => $query->where('status', $status));
    }

    /**
     * Estados que el dashboard cuenta como pendientes, leidos de la configuracion
     * para que el panel no repita el catalogo.
     *
     * @param  Builder<Quote>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereIn('status', config('quotes.pending_statuses'));
    }

    /**
     * @param  Builder<Quote>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $query) use ($term): void {
            $like = '%'.$term.'%';

            // Por folio o por cliente. El grupo se cierra con paréntesis para
            // que un filtro posterior no se escape de la búsqueda.
            $query->where('folio', 'like', $like)
                ->orWhereHas(
                    'client',
                    fn (Builder $query) => $query->where('name', 'like', $like),
                );
        });
    }
}
