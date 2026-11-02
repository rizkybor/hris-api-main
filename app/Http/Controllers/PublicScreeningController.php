<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Screening;
use App\Models\ScreeningTemplate;
use App\Services\ScreeningForm;
use App\Services\ScreeningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Client Screening for the company website (no login). Only questions and
 * answers travel through here -- never prices, rules or recommendations.
 * Open form: POST creates a new lead. Invite link: GET/PUT with the token.
 */
class PublicScreeningController extends Controller
{
    public function __construct(private ScreeningForm $form, private ScreeningService $service) {}

    private function lang(Request $request): string
    {
        return $request->input('lang') === 'en' ? 'en' : 'id';
    }

    private function byToken(?string $token): ?Screening
    {
        return $token && preg_match('/^[A-Za-z0-9]{32,64}$/', $token)
            ? Screening::where('token', $token)->first()
            : null;
    }

    public function form(Request $request)
    {
        $lang = $this->lang($request);
        $token = $request->query('t');

        if ($token) {
            $screening = $this->byToken($token);
            if (! $screening || ! $screening->linkIsOpen()) {
                return ResponseHelper::jsonResponse(false, $screening && $screening->submitted_at ? 'already_submitted' : 'link_closed', null, 410);
            }

            return ResponseHelper::jsonResponse(true, 'OK', [
                'form' => $this->form->publicDefinition($screening->template_snapshot, $lang),
                'contact' => [
                    'name' => $screening->contact_name, 'company' => $screening->company,
                    'email' => $screening->email, 'phone' => $screening->phone,
                ],
                'answers' => (object) ($screening->answers ?? []),
            ], 200);
        }

        $template = ScreeningTemplate::default();
        if (! $template) {
            return ResponseHelper::jsonResponse(false, 'unavailable', null, 503);
        }

        return ResponseHelper::jsonResponse(true, 'OK', [
            'form' => $this->form->publicDefinition($template->definition, $lang),
            'contact' => null,
            'answers' => (object) [],
        ], 200);
    }

    private function contactRules(): array
    {
        return [
            'contact.name' => ['required', 'string', 'max:120'],
            'contact.company' => ['nullable', 'string', 'max:150'],
            'contact.email' => ['nullable', 'required_without:contact.phone', 'email:rfc', 'max:150'],
            'contact.phone' => ['nullable', 'required_without:contact.email', 'string', 'max:40', 'regex:/^[0-9+()\-\s]{6,40}$/'],
            'answers' => ['required', 'array'],
        ];
    }

    private function contactFields(array $contact): array
    {
        // Also used unvalidated by the invite autosave, so lengths are capped.
        $field = fn ($key, $max) => is_scalar($contact[$key] ?? null) ? (mb_substr(trim((string) $contact[$key]), 0, $max) ?: null) : null;

        return [
            'contact_name' => $field('name', 120),
            'company' => $field('company', 150),
            'email' => $field('email', 150),
            'phone' => $field('phone', 40),
        ];
    }

    /** Validation errors for empty required questions, keyed answers.<key>. */
    private function missing(array $definition, array $answers, string $lang): ?array
    {
        $missing = $this->form->missing($definition, $answers, $lang);

        return $missing ? collect($missing)->mapWithKeys(fn ($label, $key) => [
            "answers.$key" => [$lang === 'en' ? "Please answer: $label" : "Mohon isi: $label"],
        ])->all() : null;
    }

    /** Open form on the website: always a new lead. */
    public function store(Request $request)
    {
        // Honeypot: a hidden field people never fill. Bots get a normal-
        // looking reply and nothing is stored.
        if (filled($request->input('company_website'))) {
            return ResponseHelper::jsonResponse(true, 'Thank you', null, 201);
        }

        $lang = $this->lang($request);
        $validator = Validator::make($request->all(), $this->contactRules());
        if ($validator->fails()) {
            return ResponseHelper::jsonResponse(false, 'Validation error', $validator->errors(), 422);
        }

        $template = ScreeningTemplate::default();
        if (! $template) {
            return ResponseHelper::jsonResponse(false, 'unavailable', null, 503);
        }
        $answers = $this->form->clean($template->definition, $request->input('answers', []));
        if ($errors = $this->missing($template->definition, $answers, $lang)) {
            return ResponseHelper::jsonResponse(false, 'Validation error', $errors, 422);
        }

        $screening = $this->service->create([
            ...$this->contactFields($request->input('contact', [])),
            'source' => 'landing',
            'status' => 'draft',
            'lang' => $lang,
            'answers' => $answers,
            'ip_address' => $request->ip(),
        ]);
        $this->service->submitted($screening);

        return ResponseHelper::jsonResponse(true, 'Thank you', ['code' => $screening->code], 201);
    }

    /** Invite link: save progress, or submit with submit=true. */
    public function update(Request $request, string $token)
    {
        $screening = $this->byToken($token);
        if (! $screening || ! $screening->linkIsOpen()) {
            return ResponseHelper::jsonResponse(false, 'link_closed', null, 410);
        }

        $lang = $this->lang($request);
        $submit = $request->boolean('submit');
        $definition = $screening->template_snapshot;
        $answers = $this->form->clean($definition, $request->input('answers', []));

        if ($submit) {
            $validator = Validator::make($request->all(), $this->contactRules());
            if ($validator->fails()) {
                return ResponseHelper::jsonResponse(false, 'Validation error', $validator->errors(), 422);
            }
            if ($errors = $this->missing($definition, $answers, $lang)) {
                return ResponseHelper::jsonResponse(false, 'Validation error', $errors, 422);
            }
        }

        $contact = array_filter($this->contactFields($request->input('contact', [])), fn ($v) => $v !== null);
        $screening->update([...$contact, 'answers' => $answers, 'lang' => $lang, 'ip_address' => $request->ip()]);

        if ($submit) {
            $this->service->submitted($screening);
        }

        return ResponseHelper::jsonResponse(true, $submit ? 'Thank you' : 'Saved', ['submitted' => $submit], 200);
    }
}
