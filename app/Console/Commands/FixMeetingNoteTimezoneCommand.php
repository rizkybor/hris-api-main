<?php

namespace App\Console\Commands;

use App\Models\MeetingNote;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * One-off repair for meeting_date values saved before the form sent its
 * time zone. The API used to store the typed time ("10:00", meant as WIB)
 * as if it were UTC, so every screen showed it 7 hours later. Those rows
 * need 7 hours taken off.
 *
 * Not every old row is off, though: a note saved with the form's default
 * time (never touched) was stored as real UTC and already shows correctly.
 * Nothing records which case a row is, so this guesses -- a meeting_date
 * from 0 to 2 hours before created_at with an "uneven" minute looks like
 * the untouched default -- and only lists its plan unless --apply is given,
 * so the guesses can be checked first (and overridden with --skip/--only).
 *
 * Rows it has fixed are remembered in storage, so running it again never
 * shifts the same row twice. Run it right after deploying the fix: a row
 * edited after that is already correct (use --before to exclude them).
 */
class FixMeetingNoteTimezoneCommand extends Command
{
    protected $signature = 'meeting-notes:fix-timezone
        {--apply : Save the changes (without it, only the plan is shown)}
        {--skip= : Comma-separated note ids to leave as they are}
        {--only= : Comma-separated note ids to shift, ignoring the guess}
        {--before= : Only rows last updated before this WIB time, e.g. "2026-10-21 09:00"}';

    protected $description = 'Shift meeting dates that were saved as WIB-labelled-UTC back to real UTC';

    private const LEDGER = 'meeting-note-timezone-fix.json';

    private const TZ = 'Asia/Jakarta';

    public function handle(): int
    {
        $done = $this->ledger();
        $skip = $this->ids('skip');
        $only = $this->ids('only');
        $before = $this->option('before') ? Carbon::parse($this->option('before'), self::TZ)->utc() : null;

        $rows = [];
        $toShift = [];

        MeetingNote::withTrashed()->whereNotNull('meeting_date')->orderBy('id')->get()
            ->each(function (MeetingNote $note) use ($done, $skip, $only, $before, &$rows, &$toShift) {
                if (in_array($note->id, $done, true) || ($only && ! in_array($note->id, $only, true))) {
                    return;
                }
                if ($before && $note->updated_at && $note->updated_at->gte($before)) {
                    return;
                }

                [$shift, $reason] = $only
                    ? [true, 'listed in --only']
                    : (in_array($note->id, $skip, true) ? [false, 'listed in --skip'] : $this->guess($note));

                $rows[] = [
                    $note->id,
                    $note->document_number,
                    mb_strimwidth((string) $note->title, 0, 34, '…'),
                    $this->wib($note->meeting_date),
                    $shift ? $this->wib($note->meeting_date->copy()->subHours(7)) : '(unchanged)',
                    $reason,
                ];
                if ($shift) {
                    $toShift[] = $note;
                }
            });

        if (! $rows) {
            $this->info('Nothing to check.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Number', 'Title', 'Shown now (WIB)', 'After fix (WIB)', 'Why'], $rows);

        if (! $this->option('apply')) {
            $this->line(count($toShift).' note(s) would be shifted. Re-run with --apply to save (adjust with --skip / --only).');

            return self::SUCCESS;
        }

        foreach ($toShift as $note) {
            $note->meeting_date = $note->meeting_date->copy()->subHours(7);
            $note->saveQuietly();
            $done[] = $note->id;
        }
        Storage::put(self::LEDGER, json_encode(array_values(array_unique($done))));

        $this->info(count($toShift).' note(s) shifted.');

        return self::SUCCESS;
    }

    /** [shift?, reason] for a row nobody marked by hand. */
    private function guess(MeetingNote $note): array
    {
        $created = $note->created_at;
        $minutesBefore = $created ? $note->meeting_date->diffInMinutes($created, false) : null;

        // The untouched default was "now" when the form opened: shortly
        // before the save, at whatever minute it happened to be.
        if ($minutesBefore !== null && $minutesBefore >= 0 && $minutesBefore <= 120 && $note->meeting_date->minute % 15 !== 0) {
            return [false, 'looks like the default time (already correct)'];
        }

        return [true, 'typed time saved 7h off'];
    }

    private function wib(Carbon $date): string
    {
        return $date->copy()->timezone(self::TZ)->format('Y-m-d H:i');
    }

    private function ids(string $option): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) $this->option($option)))));
    }

    private function ledger(): array
    {
        return Storage::exists(self::LEDGER) ? (json_decode(Storage::get(self::LEDGER), true) ?: []) : [];
    }
}
