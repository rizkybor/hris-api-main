<?php

namespace App\DTOs;

class SdmResourceDTO
{
    public int $no;
    public string $sdm_component;
    public string $metrik;
    public float $capacity_target;
    public float $actual;
    public string $rag_status;

    public function __construct(array $data)
    {
        $this->no = $data['no'];
        $this->sdm_component = $data['sdm_component'];
        $this->metrik = $data['metrik'];
        $this->capacity_target = floatval($data['capacity_target']);
        $this->actual = floatval($data['actual']);
        $this->rag_status = $data['rag_status'];
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public static function fromArrayForUpdate(array $data, $model): self
    {
        $merged = array_merge($model->toArray(), $data);
        return new self($merged);
    }

    public function toArray(): array
    {
        return [
            'no' => $this->no,
            'sdm_component' => $this->sdm_component,
            'metrik' => $this->metrik,
            'capacity_target' => $this->capacity_target,
            'actual' => $this->actual,
            'rag_status' => $this->rag_status,
        ];
    }
}