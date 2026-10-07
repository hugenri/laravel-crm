<?php

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['type', 'name', 'rfc', 'phone', 'email', 'address', 'status', 'notes'])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    /**
     * Las etiquetas viajan con el JSON en vez de resolverse en Vue: la
     * interfaz muestra lo que guardó el backend, sin un segundo mapa que
     * mantener sincronizado.
     *
     * @var list<string>
     */
    protected $appends = [
        'type_label',
        'status_label',
    ];

    /**
     * Contactos que pertenecen al cliente.
     *
     * @return HasMany<Contact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * Oportunidades del cliente. Alimenta el historial de la ficha y le avisa
     * a DeleteClient que un cliente con oportunidades no se borra.
     *
     * @return HasMany<Opportunity, $this>
     */
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    /**
     * Actividades de seguimiento. Muestran el historial y, como en las
     * oportunidades, impiden borrar el cliente mientras tengan seguimiento.
     *
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * Cotizaciones del cliente. Mismo papel que las anteriores: impiden el
     * borrado porque el documento tiene folio e importes que aún se necesitan.
     *
     * @return HasMany<Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /**
     * La inactivación reemplaza a la baja: un cliente inactivo conserva
     * datos, contactos e historial.
     */
    public function isActive(): bool
    {
        return $this->status === config('clients.active_status');
    }

    /**
     * Contacto principal del cliente, si tiene alguno.
     */
    public function primaryContact(): ?Contact
    {
        return $this->contacts()->where('is_primary', true)->first();
    }

    /**
     * Etiqueta legible del tipo. Las claves de config('clients.types') son
     * los valores guardados en la columna, igual que en Permission::label().
     *
     * Null safe a propósito: el appended se calcula aunque la relación se
     * cargue con restricción de columnas; devolver null evita un TypeError.
     */
    protected function typeLabel(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->labelFor('type', 'clients.types'));
    }

    /**
     * Etiqueta legible del estado.
     */
    protected function statusLabel(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->labelFor('status', 'clients.statuses'));
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
     * @param  Builder<Client>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', config('clients.active_status'));
    }

    /**
     * Búsqueda por nombre, RFC y correo. El grupo se cierra con paréntesis
     * para que un filtro posterior no se escape de la búsqueda.
     *
     * @param  Builder<Client>  $query
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
                ->orWhere('rfc', 'like', $like)
                ->orWhere('email', 'like', $like);
        });
    }
}
