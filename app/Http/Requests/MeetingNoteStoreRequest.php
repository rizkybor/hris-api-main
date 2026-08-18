<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MeetingNoteStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_number' => ['nullable', 'string', 'max:255', 'unique:meeting_notes,document_number'],
            'title' => ['required', 'string', 'max:255'],
            'meeting_type' => ['required', 'in:internal,external'],
            'meeting_date' => ['required', 'date'],
            'body' => ['nullable', 'string'],
            'action_items' => ['nullable', 'array'],
            'action_items.*.text' => ['required_with:action_items', 'string', 'max:500'],
            'action_items.*.done' => ['nullable', 'boolean'],
            'action_items.*.assignee' => ['nullable', 'string', 'max:255'],
            'attendee_employee_ids' => ['nullable', 'array'],
            'attendee_employee_ids.*' => ['integer', 'exists:employee_profiles,id'],
            'external_attendees' => ['nullable', 'array'],
            'external_attendees.*.name' => ['required_with:external_attendees', 'string', 'max:255'],
            'external_attendees.*.organization' => ['nullable', 'string', 'max:255'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,doc,docx,png,jpg,jpeg', 'max:5120'],
        ];
    }
}
