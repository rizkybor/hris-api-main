<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\DashboardWidgetLayout;
use App\Models\User;
use App\Support\DashboardWidgetRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        $orderedKeys = $this->orderedKeysFor($user);

        $savedSizes = DashboardWidgetLayout::where('user_id', $user->id)->pluck('size', 'widget_key');
        $registryByKey = collect(DashboardWidgetRegistry::WIDGETS)->keyBy('key');

        $widgets = $orderedKeys->values()->map(fn ($key, $index) => [
            'key' => $key,
            'position' => $index,
            // "small"/"medium"/"large" -- the user's own resize choice if
            // they've made one, else the widget's registry default.
            'size' => $savedSizes[$key] ?? $registryByKey[$key]['size'] ?? 'small',
        ]);

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
        $allowedKeys = $this->allowedKeysFor($user);

        foreach ($validated['order'] as $index => $key) {
            if (! $allowedKeys->contains($key)) {
                continue;
            }

            // Only `position` is touched here -- updateOrCreate's second
            // array is the sole set of attributes written, so an existing
            // row's `size` (if the user already resized this widget) is
            // left untouched rather than reset to null.
            DashboardWidgetLayout::updateOrCreate(
                ['user_id' => $user->id, 'widget_key' => $key],
                ['position' => $index]
            );
        }

        return ResponseHelper::jsonResponse(true, 'Dashboard Layout Saved Successfully', null, 200);
    }

    /**
     * Persist a single widget's chosen size (macOS/iOS-style widget
     * resize) without disturbing the order of anything else.
     *
     * A saved position is only meaningful *relative to other saved
     * positions* (see orderedKeysFor()) -- a lone saved row for just this
     * one widget would always sort before the unsaved rest (saved block,
     * then fallback block), which would wrongly promote it to the front
     * regardless of what position value was written. ensureFullyMaterialized()
     * avoids that by writing every currently-visible widget's *current*
     * position before touching size, so "saved" coverage is always
     * complete and position comparisons stay meaningful.
     */
    public function updateSize(Request $request)
    {
        $validated = $request->validate([
            'widget_key' => ['required', 'string', 'in:'.implode(',', DashboardWidgetRegistry::keys())],
            'size' => ['required', 'string', 'in:'.implode(',', DashboardWidgetRegistry::SIZES)],
        ]);

        $user = Auth::user();

        if (! $this->allowedKeysFor($user)->contains($validated['widget_key'])) {
            return ResponseHelper::jsonResponse(false, 'You do not have access to this widget.', null, 403);
        }

        $this->ensureFullyMaterialized($user);

        DashboardWidgetLayout::where('user_id', $user->id)
            ->where('widget_key', $validated['widget_key'])
            ->update(['size' => $validated['size']]);

        return ResponseHelper::jsonResponse(true, 'Widget Size Updated Successfully', null, 200);
    }

    /**
     * Write a position row for every currently-visible widget that doesn't
     * already have one, at its current effective (merged) position --
     * a no-op on ordering, but guarantees "saved position" coverage is
     * all-or-nothing from here on, so a later partial write (like resizing
     * just one widget) can never desync from the rest.
     */
    private function ensureFullyMaterialized(User $user): void
    {
        $orderedKeys = $this->orderedKeysFor($user);
        $existingKeys = DashboardWidgetLayout::where('user_id', $user->id)->pluck('widget_key');

        foreach ($orderedKeys as $index => $key) {
            if ($existingKeys->contains($key)) {
                continue;
            }

            DashboardWidgetLayout::create([
                'user_id' => $user->id,
                'widget_key' => $key,
                'position' => $index,
            ]);
        }
    }

    /**
     * Clear the user's saved order/sizes so their dashboard falls back to
     * the registry's defaults (same as a brand new user who never
     * customized anything).
     */
    public function resetOrder(Request $request)
    {
        DashboardWidgetLayout::where('user_id', Auth::id())->delete();

        return ResponseHelper::jsonResponse(true, 'Dashboard Layout Reset Successfully', null, 200);
    }

    private function allowedKeysFor(User $user): Collection
    {
        return collect(DashboardWidgetRegistry::WIDGETS)
            ->filter(fn ($widget) => $user->can($widget['permission']))
            ->pluck('key');
    }

    private function orderedKeysFor(User $user): Collection
    {
        $available = $this->allowedKeysFor($user);

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

        return $savedOrder->intersect($available)
            ->concat($fallbackOrder->intersect($available)->diff($savedOrder))
            ->unique()
            ->values();
    }
}
