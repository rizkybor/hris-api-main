<?php

namespace App\Support;

/**
 * Indonesian messages and field names for Laravel's built-in validation
 * rules, for modules whose own messages are Indonesian (Maintenance), so a
 * form never shows English and Indonesian errors side by side:
 *
 *   $request->validate($rules, ...IndonesianValidation::args());
 */
class IndonesianValidation
{
    public static function args(array $extraMessages = [], array $extraAttributes = []): array
    {
        return [$extraMessages + self::messages(), $extraAttributes + self::attributes()];
    }

    public static function messages(): array
    {
        return [
            'required' => ':Attribute wajib diisi.',
            'required_with' => ':Attribute wajib diisi.',
            'required_if' => ':Attribute wajib diisi.',
            'string' => ':Attribute harus berupa teks.',
            'numeric' => ':Attribute harus berupa angka.',
            'integer' => ':Attribute harus berupa bilangan bulat.',
            'boolean' => ':Attribute harus bernilai ya atau tidak.',
            'date' => ':Attribute bukan tanggal yang valid.',
            'date_format' => 'Format :attribute tidak valid.',
            'after_or_equal' => ':Attribute harus sama dengan atau setelah :date.',
            'exists' => ':Attribute yang dipilih tidak ditemukan.',
            'in' => ':Attribute yang dipilih tidak valid.',
            'not_in' => ':Attribute tidak boleh bernilai :values.',
            // Size rules: plain keys (custom message arrays don't pick the
            // per-type .numeric / .string variants).
            'min' => ':Attribute minimal :min.',
            'max' => ':Attribute maksimal :max.',
            'between' => ':Attribute harus di antara :min dan :max.',
        ];
    }

    public static function attributes(): array
    {
        return [
            'client_id' => 'klien',
            'project_id' => 'proyek',
            'name' => 'nama',
            'monthly_quota_hours' => 'kuota jam per bulan',
            'start_date' => 'tanggal mulai',
            'end_date' => 'tanggal berakhir',
            'status' => 'status',
            'leftover_policy' => 'kebijakan sisa jam',
            'leftover_action' => 'tindakan sisa jam',
            'bank_cap_hours' => 'batas saldo',
            'bank_expiry_months' => 'masa berlaku saldo',
            'overage_rate' => 'tarif kelebihan jam',
            'notes' => 'catatan',
            'hours' => 'jumlah jam',
            'reason' => 'alasan',
            'work_date' => 'tanggal pekerjaan',
            'title' => 'judul',
            'description' => 'deskripsi',
            'category' => 'kategori',
            'performed_by' => 'pelaksana',
            'visible_to_client' => 'tampil ke klien',
            'maintenance_feature_id' => 'fitur',
            'estimated_hours' => 'estimasi jam',
        ];
    }
}
