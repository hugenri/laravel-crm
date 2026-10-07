<?php

namespace App\Actions\Clients;

use App\Models\Client;
use Illuminate\Support\Str;

class CreateClient
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Client
    {
        return Client::create([
            'type' => $data['type'],
            'name' => trim($data['name']),
            'rfc' => $this->rfc($data['rfc'] ?? null),
            'phone' => $data['phone'] ?? null,
            'email' => $this->email($data['email'] ?? null),
            'address' => $data['address'] ?? null,
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Se guarda en mayúsculas y sin espacios para que el índice único no tome
     * "gso101010abc" y "GSO101010ABC" como dos contribuyentes distintos.
     */
    private function rfc(?string $value): ?string
    {
        $value = Str::upper(trim((string) $value));

        return $value === '' ? null : $value;
    }

    private function email(?string $value): ?string
    {
        $value = mb_strtolower(trim((string) $value));

        return $value === '' ? null : $value;
    }
}
