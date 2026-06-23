<?php

// v1.0 — 2026-06-11 | Auto-add the editor's weekly flat-rate pay as a pending adjustment once per pay period

namespace App\Console\Commands;

use App\Models\EditorPayAdjustment;
use App\Models\User;
use App\Support\PayPeriod;
use Illuminate\Console\Command;

class AddEditorWeeklyFlatPay extends Command
{
    protected $signature   = 'editor-pay:add-weekly-flat';
    protected $description = "Add the editor's weekly flat-rate pay as a pending adjustment for the current pay period, if not already added.";

    public function handle(): int
    {
        $editor = User::where('role', 'editor')->where('is_test', false)->whereHas('editorProfile')->first();
        if (! $editor) {
            return Command::SUCCESS;
        }

        $weeklyFlat = (float) ($editor->editorProfile->editor_weekly_flat ?? 0);
        if ($weeklyFlat <= 0) {
            return Command::SUCCESS;
        }

        [$periodStart] = PayPeriod::current();
        $label = PayPeriod::label($periodStart);

        $alreadyAdded = EditorPayAdjustment::where('user_id', $editor->id)
            ->where('created_at', '>=', $periodStart->copy()->utc())
            ->where('description', 'like', 'Weekly flat rate%')
            ->exists();

        if ($alreadyAdded) {
            return Command::SUCCESS;
        }

        $addedBy = User::where('role', 'admin')->orderBy('id')->first() ?? $editor;

        EditorPayAdjustment::create([
            'user_id'          => $editor->id,
            'amount'           => $weeklyFlat,
            'description'      => "Weekly flat rate — {$label} (auto)",
            'added_by_user_id' => $addedBy->id,
        ]);

        $this->info("Added weekly flat rate adjustment of \${$weeklyFlat} for {$label}.");

        return Command::SUCCESS;
    }
}
