<?php

namespace App\Models;

use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['client_id', 'first_name', 'last_name', 'position', 'phone', 'email', 'is_primary', 'notes'])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = [
        'display_name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    /**
     * Cliente del contacto. La FK restringe el borrado para que un contacto
     * nunca quede huérfano.
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Oportunidades en las que participa este contacto. La columna queda en null
     * al eliminar el contacto, para que la oportunidad sobreviva.
     *
     * @return HasMany<Opportunity, $this>
     */
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    /**
     * Cotizaciones enviadas al contacto. La columna no es null al borrarlo:
     * el contacto es parte del documento. DeleteContact no lo borra si tiene
     * alguna y la FK restrictOnDelete es la segunda línea de defensa.
     *
     * @return HasMany<Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /**
     * Nombre y apellido juntos, como se muestra en las listas.
     *
     * Null safe: el appended se calcula aunque la relación se cargue con
     * restricción de columnas.
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->first_name === null) {
                return '';
            }

            return trim($this->first_name.' '.$this->last_name);
        });
    }

    /**
     * @param  Builder<Contact>  $query
     */
    public function scopePrimary(Builder $query): void
    {
        $query->where('is_primary', true);
    }

    /**
     * Búsqueda del listado global de contactos.
     *
     * @param  Builder<Contact>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $query) use ($term): void {
            $like = '%'.$term.'%';

            $query->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhereHas(
                    'client',
                    fn (Builder $query) => $query->where('name', 'like', $like),
                );
        });
    }
}
