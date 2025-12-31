<?php

namespace App\DTOs;

class FixedCostDTO
{
    public int $no;
    public string $financial_items;
    public string $description;
    public float $budget;
    public float $actual;

    public function __construct(array $data)
    {
        $this->no = $data['no'];
        $this->financial_items = $data['financial_items'];
        $this->description = $data['description'];
        $this->budget = floatval($data['budget']);
        $this->actual = floatval($data['actual']);
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
            'financial_items' => $this->financial_items,
            'description' => $this->description,
            'budget' => $this->budget,
            'actual' => $this->actual,
        ];
    }
}