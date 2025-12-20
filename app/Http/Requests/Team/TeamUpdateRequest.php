<?php

namespace App\Http\Requests\Team;

use App\Enums\Department;
use App\Enums\TeamStatus;
use Illuminate\Foundation\Http\FormRequest;

class TeamUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'expected_size' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'icon' => ['sometimes', 'image', 'max:2048'],
            'department' => ['sometimes', 'required', 'string', 'in:'.implode(',', array_column(Department::cases(), 'value'))],
            'status' => ['nullable', 'string', 'in:'.implode(',', array_column(TeamStatus::cases(), 'value'))],
            'team_lead_id' => ['nullable', 'exists:users,id'],
            'responsibilities' => ['sometimes', 'required', 'array'],
            'responsibilities.*' => ['string'],
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'Nama Tim',
            'expected_size' => 'Ukuran Tim yang Diharapkan',
            'description' => 'Deskripsi Tim',
            'icon' => 'Icon Tim',
            'department' => 'Departemen',
            'status' => 'Status',
            'team_lead_id' => 'Ketua Tim',
            'responsibilities' => 'Tanggung Jawab',
        ];
    }
}
