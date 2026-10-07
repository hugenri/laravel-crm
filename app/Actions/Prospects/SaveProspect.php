<?php

namespace App\Actions\Prospects;

use App\Models\Prospect;

/**
 * Alta y edicion de un prospecto, en una sola clase para no duplicar la
 * normalizacion.
 *
 * El estado no entra en attributes(): en la edicion se descarta siempre
 * (cambiarlo es prospects.change_status) y en la alta si se acepta, menos
 * "convertido", que lo filtra StoreProspectRequest.
 */
class SaveProspect
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Prospect
    {
        $attributes = $this->attributes($data);

        if (array_key_exists('status', $data)) {
            $attributes['status'] = $data['status'];
        }

        // forceFill y no fill: "status" esta fuera de Fillable a proposito,
        // para que ningun fill() accidental lo deje marcado como convertido.
        $prospect = new Prospect;
        $prospect->forceFill($attributes);
        $prospect->save();

        return $prospect->refresh();
    }

    /**
     * Cada campo se escribe solo cuando fue enviado, como en UpdateClient.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Prospect $prospect, array $data): Prospect
    {
        foreach ($this->attributes($data) as $column => $value) {
            $prospect->{$column} = $value;
        }

        $prospect->save();

        return $prospect->refresh();
    }

    /**
     * Normaliza lo que se guarda: un texto en blanco se guarda como null y no
     * como cadena vacia, para no mostrar una raya en el listado.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $attributes = [];

        foreach (['name', 'company', 'source'] as $column) {
            if (array_key_exists($column, $data)) {
                $attributes[$column] = trim((string) $data[$column]);
            }
        }

        if (array_key_exists('email', $data)) {
            // Minusculas, como en CreateClient, para que la busqueda no dependa
            // de como se escribio.
            $email = mb_strtolower(trim((string) $data['email']));

            $attributes['email'] = $email === '' ? null : $email;
        }

        foreach (['user_id', 'phone', 'notes', 'follow_up_date'] as $column) {
            if (array_key_exists($column, $data)) {
                $value = $data[$column];

                $attributes[$column] = $value === '' ? null : $value;
            }
        }

        return $attributes;
    }
}
