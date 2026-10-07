<?php

namespace App\Actions\Activities;

use App\Models\Activity;
use App\Models\Client;
use App\Models\Opportunity;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Alta de una actividad.
 *
 * La validación de la oportunidad se repite aquí porque esta acción puede
 * llamarse sin pasar por la petición web.
 */
class CreateActivity
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws DomainException
     */
    public function handle(array $data): Activity
    {
        return DB::transaction(function () use ($data): Activity {
            $client = Client::findOrFail($data['client_id']);

            return Activity::create([
                'client_id' => $client->getKey(),
                'opportunity_id' => $this->opportunity($data['opportunity_id'] ?? null, $client)?->getKey(),
                'user_id' => $data['user_id'],
                'type' => $data['type'],
                'activity_date' => $data['activity_date'],
                'status' => $data['status'],
                'description' => trim($data['description']),
            ])->refresh();
        });
    }

    /**
     * La oportunidad tiene que pertenecer al cliente, si se indica.
     *
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
