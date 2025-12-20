<?php

namespace App\Repositories;

use App\DTOs\JobInformationDto;
use App\Interfaces\JobInformationRepositoryInterface;
use App\Models\JobInformation;

class JobInformationRepository implements JobInformationRepositoryInterface
{
    public function getById(string $id): JobInformation
    {
        return JobInformation::with(['employee', 'team'])->findOrFail($id);
    }

    public function create(array $data): JobInformation
    {
        $jobDto = JobInformationDto::fromArray($data);

        return JobInformation::create($jobDto->toArray());
    }

    public function update(string $id, array $data): JobInformation
    {
        $job = $this->getById($id);
        $jobDto = JobInformationDto::fromArrayForUpdate($data, $job);
        $job->update($jobDto->toArray());

        return $job;
    }

    public function delete(string $id): JobInformation
    {
        $job = $this->getById($id);
        $job->delete();

        return $job;
    }
}
