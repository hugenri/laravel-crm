<?php

namespace App\Actions\Activities;

use App\Models\Activity;
use App\Models\Client;
use App\Models\Opportunity;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Edición de una actividad.
 *
 * Cada campo se escribe solo si llegó en la petición. Si cambia el cliente,
 * la oportunidad tiene que ser del cliente nuevo o ninguna.
 */
class UpdateActivity
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws DomainException
     */
    public function handle(Activity $activity, array $data): Activity
    {
        return DB::transaction(function () use ($activity, $data): Activity {
            $client = $activity->client;
            $clientChanged = false;

            if (array_key_exists('client_id', $data)) {
                $client = Client::findOrFail($data['client_id']);
                $clientChanged = $client->getKey() !== $activity->client_id;
                $activity->client_id = $client->getKey();
            }

            if (array_key_exists('opportunity_id', $data)) {
                $activity->opportunity_id = $this->opportunity($data['opportunity_id'], $client)?->getKey();
            } elseif ($clientChanged && $activity->opportunity_id !== null) {
                // El cliente cambió sin que la petición tocara la oportunidad:
                // revalidar evita heredar la de la empresa anterior.
                $activity->opportunity_id = $this->opportunity($activity->opportunity_id, $client)?->getKey();
            }

            foreach (['user_id', 'type', 'activity_date', 'status'] as $field) {
                if (array_key_exists($field, $data)) {
                    $activity->{$field} = $data[$field];
                }
            }

            if (array_key_exists('description', $data)) {
                $activity->description = trim($data['description']);
            }

            $activity->save();

            return $activity->refresh();
        });
    }

    /**
     * @throws DomainException
     */
    private function opportunity(mixed $opportunityId, Client $client): ?Opportunity
    {
        if ($opportunityId === null || $opportunityId === '') {
            return null;
        }

        $opportunity = Opportunity::find($opportunityId);

        if ($opportunity === null || $opportunity->client_id !== $client->getKey()) {
            throw new DomainException(__('activities.opportunity_of_another_client'));
        }

        return $opportunity;
    }
}
