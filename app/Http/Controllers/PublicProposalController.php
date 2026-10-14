<?php

namespace App\Http\Controllers;

use App\Models\Proposal;
use App\Services\ProposalPdfService;

/**
 * The public side of a proposal's share link: no login, just the token.
 * Always renders the proposal as last saved, so revisions show up on the
 * same link.
 */
class PublicProposalController extends Controller
{
    public function show(string $token, ProposalPdfService $pdfService)
    {
        $proposal = preg_match('/^[A-Za-z0-9]{32,64}$/', $token)
            ? Proposal::where('share_token', $token)->first()
            : null;

        if (! $proposal || ! $proposal->isShareActive()) {
            return response()->view('proposals.link-unavailable', [], 404);
        }

        $proposal->forceFill([
            'share_view_count' => $proposal->share_view_count + 1,
            'share_last_viewed_at' => now(),
        ])->saveQuietly();

        return $pdfService->response($proposal)->withHeaders([
            // Always fetch the latest saved version; never index the link.
            'Cache-Control' => 'no-store, max-age=0',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
