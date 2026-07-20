<?php

// v1.0 — 2026-07-20 | Single source of truth for "which tiers can this reader reach" — used by
// Assignment::scopeAvailable()/scopeAcceptedRequests() and AssignmentPolicy::view()/accept() so
// the cross-visibility/escalation/type-restriction rules only need to be implemented once.

namespace App\Support;

use App\Models\ReaderProfile;
use App\Models\TierCrossVisibility;

class TierAccess
{
    /**
     * Per reader-tier "vantage point", the set of assignment tier ids reachable from it and
     * the assignment-type allowlist (null = unrestricted) that gates what's visible through it.
     *
     * For a normal tier, reachable = itself plus any tier it has been granted cross-visibility
     * into (can_view, or can_accept when $forAccept). For the onboarding tier, reachable is
     * always just itself when $forAccept — onboarding readers never accept outside their own
     * (sandbox) tier, regardless of what's stored in tier_cross_visibility.
     *
     * @return array<int, array{tierIds: array<int, int>, allowedTypes: ?array}>
     */
    public static function reachableTierGroups(ReaderProfile $profile, bool $forAccept): array
    {
        $readerTiers = $profile->tiers()->get();

        if ($readerTiers->isEmpty()) {
            return [];
        }

        $crossRows = TierCrossVisibility::whereIn('from_tier_id', $readerTiers->pluck('id'))->get();

        $groups = [];

        foreach ($readerTiers as $tier) {
            if ($tier->is_onboarding && $forAccept) {
                $groups[] = ['tierIds' => [$tier->id], 'allowedTypes' => $tier->allowed_assignment_types];
                continue;
            }

            $tierIds = [$tier->id];
            foreach ($crossRows->where('from_tier_id', $tier->id) as $row) {
                if ($forAccept ? $row->can_accept : $row->can_view) {
                    $tierIds[] = $row->to_tier_id;
                }
            }

            $groups[] = ['tierIds' => array_values(array_unique($tierIds)), 'allowedTypes' => $tier->allowed_assignment_types];
        }

        return $groups;
    }

    /** Flattened tier ids reachable by the reader for the given assignment type, across all their tiers. */
    public static function reachableTierIds(ReaderProfile $profile, bool $forAccept, ?string $assignmentType): array
    {
        $ids = [];

        foreach (self::reachableTierGroups($profile, $forAccept) as $group) {
            if ($group['allowedTypes'] !== null && ! in_array($assignmentType, $group['allowedTypes'], true)) {
                continue;
            }
            $ids = array_merge($ids, $group['tierIds']);
        }

        return array_values(array_unique($ids));
    }
}
