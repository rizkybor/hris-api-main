<?php

namespace App\Interfaces;

use App\Models\LicenseProduct;

/**
 * Licenses of the License Manager. Every method is scoped to one product:
 * a license of another product is not found.
 */
interface LicenseRepositoryInterface
{
    public function getAll(
        LicenseProduct $product,
        ?string $search,
        ?string $status,
        ?string $type,
        ?string $plan,
        ?int $limit,
        bool $execute,
        ?string $sortBy = null,
        ?string $sortDir = null,
        ?string $platform = null
    );

    public function getAllPaginated(
        LicenseProduct $product,
        ?string $search,
        ?string $status,
        ?string $type,
        ?string $plan,
        int $rowPerPage,
        ?string $sortBy = null,
        ?string $sortDir = null,
        ?string $platform = null
    );

    public function getById(
        LicenseProduct $product,
        string $id
    );

    public function create(
        LicenseProduct $product,
        array $data
    );

    public function update(
        LicenseProduct $product,
        string $id,
        array $data
    );

    public function delete(
        LicenseProduct $product,
        string $id
    );

    public function releaseActivation(
        LicenseProduct $product,
        string $licenseId,
        string $activationId
    );

    public function summary(LicenseProduct $product);

    public function overview();

    public function expiring(int $days, int $limit);

    public function recentEvents(int $limit);

    public function getEventsPaginated(
        LicenseProduct $product,
        string $licenseId,
        ?string $event,
        int $rowPerPage
    );
}
