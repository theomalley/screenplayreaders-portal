<?php

// v1.1 — 2026-11-23 | BUG FIX: ->first() picked exactly one non-test editor (no orderBy,
//                     arbitrary DB order) and added the weekly-flat adjustment only for
//                     that editor — every other qualifying editor's editor_weekly_flat
//                     was silently skipped every week. Now iterates ALL non-test editors
//                     with an editorProfile, applying the same weekly-flat/idempotency
//                     checks per editor instead of once globally.
// v1.0 — 2026-06-11 | Auto-add the editor's weekly flat-rate pay as a pending adjustment once per pay period

namespace App\Console\Commands;

use App\Models\EditorPayAdjustment;
use App\Models\User;
use App\Support\PayPeriod;
use Illuminate\Console\Command;

class AddEditorWeeklyFlatPay extends Command
{
    protected $signature   = 'editor-pay:add-weekly-flat';
    protected $description = "Add each editor's weekly flat-rate pay as a pending adjustment for the current pay period, if not already added.";

    public function handle(): int
    {
        $editors = User::where('role', 'editor')->where('is_test', false)->whereHas('editorProfile')->get();
        if ($editors->isEmpty()) {
            return Command::SUCCESS;
        }

        $addedBy = User::where('role', 'admin')->orderBy('id')->first();

        [$periodStart] = PayPeriod::current();
        $label = PayPeriod::label($periodStart);
        $description = "Weekly flat rate — {$label} (auto)";

        foreach ($editors as $editor) {
            $weeklyFlat = (float) ($editor->editorProfile->editor_weekly_flat ?? 0);
            if ($weeklyFlat <= 0) {
                continue;
            }

            $alreadyAdded = EditorPayAdjustment::where('user_id', $editor->id)
                ->where('description', $description)
                ->exists();

            if ($alreadyAdded) {
                continue;
            }

            EditorPayAdjustment::create([
                'user_id'          => $editor->id,
                'amount'           => $weeklyFlat,
                'description'      => $description,
                'added_by_user_id' => ($addedBy ?? $editor)->id,
            ]);

            $this->info("Added weekly flat rate adjustment of \${$weeklyFlat} for {$label} ({$editor->name}).");
        }

        return Command::SUCCESS;
    }
}
