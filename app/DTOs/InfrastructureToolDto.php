<?php

namespace App\DTOs;

class InfrastructureToolDTO
{
    public int $no;
    public string $tech_stack_component;
    public string $vendor;
    public float $monthly_fee;
    public float $annual_fee;
    public string $expired_date;
    public string $status;

    public function __construct(array $data)
    {
        $this->no = $data['no'];
        $this->tech_stack_component = $data['tech_stack_component'];
        $this->vendor = $data['vendor'];
        $this->monthly_fee = floatval($data['monthly_fee']);
        $this->annual_fee = floatval($data['annual_fee']);
        $this->expired_date = $data['expired_date'];
        $this->status = $data['status'];
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
            'tech_stack_component' => $this->tech_stack_component,
            'vendor' => $this->vendor,
            'monthly_fee' => $this->monthly_fee,
            'annual_fee' => $this->annual_fee,
            'expired_date' => $this->expired_date,
            'status' => $this->status,
        ];
    }
}