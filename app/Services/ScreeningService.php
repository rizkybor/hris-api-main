<?php

namespace App\Services;

use App\Models\Screening;
use App\Models\ScreeningTemplate;
use App\Models\User;
use App\Notifications\ScreeningSubmitted;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

class ScreeningService
{
    public function __construct(private ScreeningRecommender $recommender) {}

    /** New screening on a copy of the current default template. */
    public function create(array $attributes): Screening
    {
        $template = ScreeningTemplate::default() ?? throw new RuntimeException('No screening template yet -- run ClientScreeningSeeder.');

        return Screening::create([
            ...$attributes,
            'screening_template_id' => $template->id,
            'template_snapshot' => $template->definition,
        ]);
    }

    public function inviteUrl(Screening $screening): string
    {
        $base = config($screening->lang === 'en' ? 'services.screening.form_url_en' : 'services.screening.form_url');

        return $base.'?t='.$screening->token;
    }

    /** The client finished the form: analyse it and tell the team. */
    public function submitted(Screening $screening): void
    {
        $screening->update(['status' => 'new', 'submitted_at' => now()]);
        $this->recommender->apply($screening);

        try {
            $notification = new ScreeningSubmitted($screening->fresh());
            if ($email = config('services.screening.notify_email')) {
                Notification::route('mail', $email)->notify($notification);
            }
            // In-app as well, so nothing is lost if the email doesn't arrive.
            Notification::send(User::role('manager')->get(), $notification);
        } catch (\Throwable $e) {
            Log::warning('Screening notification failed: '.$e->getMessage(), ['screening' => $screening->id]);
        }
    }
}
