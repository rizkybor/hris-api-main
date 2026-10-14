<?php

namespace App\Http\Requests;

use App\Models\Proposal;
use App\Services\Cloudinary\CloudinaryFolders;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProposalRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'client_name' => ['required', 'string', 'max:255'],
            'proposal_date' => ['required', 'date'],
            'letter_number' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(Proposal::STATUSES)],
            // Only a public id our own upload endpoint handed out.
            'cover_image' => ['nullable', 'string', 'max:255', 'starts_with:'.CloudinaryFolders::companyFiles('proposals').'/'],
            'signatory_name' => ['nullable', 'string', 'max:255'],
            'signatory_title' => ['nullable', 'string', 'max:255'],
            'use_signature' => ['nullable', 'boolean'],
            // Optional on create: left out, the proposal starts from the
            // standard JCD template (see ProposalTemplate).
            'sections' => [$this->isMethod('post') ? 'nullable' : 'required', 'array', 'max:60'],
            'sections.*.id' => ['required', 'string', 'max:64'],
            'sections.*.title' => ['nullable', 'string', 'max:255'],
            'sections.*.content' => ['nullable', 'string', 'max:300000'],
            'sections.*.page_break_before' => ['nullable', 'boolean'],
            // Only used to fill the template's greeting on create.
            'recipient' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'Judul',
            'client_id' => 'Klien',
            'client_name' => 'Nama Klien',
            'proposal_date' => 'Tanggal',
            'letter_number' => 'Nomor Surat',
            'sections' => 'Isi Proposal',
            'sections.*.content' => 'Isi Bagian',
        ];
    }
}
