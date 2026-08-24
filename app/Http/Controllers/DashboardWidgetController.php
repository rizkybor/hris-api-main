<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\DashboardWidgetLayout;
use App\Support\DashboardWidgetRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardWidgetController extends Controller
{
    /**
     * Widgets the current user has permission for, in their saved order,
     * with anything never explicitly positioned yet -- a brand new user, or
     * a widget newly granted after they last customized their layout --
     * appended at the end in registry order.
     *
     * Saved positions and registry-default positions are deliberately never
     * compared as raw integers: a saved position is only meaningful among
     * other saved positions, and mixing it with an unrelated widget's
     * untouched default index can produce a collision (two widgets landing
     * on the same "position" value) whenever the visible widget set has
     * changed since the user last reordered. Splicing two ordered lists
     * (saved-and-still-available, then everything else) avoids that
     * entirely rather than relying on sort stability to paper over it.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $available = collect(DashboardWidgetRegistry::WIDGETS)
            ->filter(fn ($widget) => $user->can($widget['permission']))
            ->pluck('key');

        $savedOrder = DashboardWidgetLayout::where('user_id', $user->id)
            ->orderBy('position')
            ->pluck('widget_key');

        // Fallback for anything not yet explicitly saved: the user's role's
        // default order first (so a first-time view matches what that role
        // originally saw), then registry order for anything the default
        // list doesn't mention (e.g. a widget an admin granted manually
        // beyond the role's usual set).
        $fallbackOrder = collect(DashboardWidgetRegistry::defaultOrderFor($user->getRoleNames()))
            ->concat(DashboardWidgetRegistry::keys());

        $orderedKeys = $savedOrder->intersect($available)
            ->concat($fallbackOrder->intersect($available)->diff($savedOrder))
            ->unique()
            ->values();

        $widgets = $orderedKeys->map(fn ($key, $index) => ['key' => $key, 'position' => $index]);

        return ResponseHelper::jsonResponse(true, 'Dashboard Widgets Retrieved Successfully', $widgets, 200);
    }

    /**
     * Persist the user's chosen widget order (drag-and-drop result). Keys
     * the user doesn't currently have permission for are silently dropped
     * rather than rejecting the whole request, since a stale client-side
     * list (e.g. a role change mid-session) shouldn't block saving the rest.
     */
    public function updateOrder(Request $request)
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['string', 'in:'.implode(',', DashboardWidgetRegistry::keys())],
        ]);

        $user = Auth::user();
        $allowedKeys = collect(DashboardWidgetRegistry::WIDGETS)
            ->filter(fn ($widget) => $user->can($widget['permission']))
            ->pluck('key');

        foreach ($validated['order'] as $index => $key) {
            if (! $allowedKeys->contains($key)) {
                continue;
            }

            DashboardWidgetLayout::updateOrCreate(
                ['user_id' => $user->id, 'widget_key' => $key],
                ['position' => $index]
            );
        }

        return ResponseHelper::jsonResponse(true, 'Dashboard Layout Saved Successfully', null, 200);
    }

    /**
     * Clear the user's saved order so their dashboard falls back to the
     * registry's default order (same as a brand new user who never
     * customized anything).
     */
    public function resetOrder(Request $request)
    {
        DashboardWidgetLayout::where('user_id', Auth::id())->delete();

        return ResponseHelper::jsonResponse(true, 'Dashboard Layout Reset Successfully', null, 200);
    }
}
