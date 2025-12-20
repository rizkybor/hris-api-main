<?php

namespace App\Repositories;

use App\Interfaces\EmergencyContactRepositoryInterface;
use App\Models\EmergencyContact;

class EmergencyContactRepository implements EmergencyContactRepositoryInterface
{
    public function getById(string $id): EmergencyContact
    {
        return EmergencyContact::with(['employee'])->findOrFail($id);
    }

    public function create(array $data): EmergencyContact
    {
        return EmergencyContact::create($data);
    }

    public function update(string $id, array $data): EmergencyContact
    {
        $contact = $this->getById($id);
        $contact->update($data);

        return $contact;
    }

    public function delete(string $id): EmergencyContact
    {
        $contact = $this->getById($id);
        $contact->delete();

        return $contact;
    }
}
