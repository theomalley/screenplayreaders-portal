<?php

// v1.0 — 2026-05-25 | Admin-only statistics dashboard — per-reader and combined stats

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\CoverageSubmission;
use App\Models\FollowupQuestion;
use App\Models\User;
use App\Support\Permission;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class StatisticsController extends Controller
{
    public static array $PERIODS = [
        'this_week'  => 'This Week',
        'last_week'  => 'Last Week',
        'this_month' => 'This Month',
        'last_month' => 'Last Month',
        'last_30'    => 'Last 30 Days',
        'last_60'    => 'Last 60 Days',
        'last_90'    => 'Last 90 Days',
        'this_year'  => 'This Year',
        'last_year'  => 'Last Year',
        'all_time'   => 'All Time',
    ];

    public function index()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $period = request()->input('period', 'all_time');
        if (! array_key_exists($period, self::$PERIODS)) {
            $period = 'all_time';
        }

        [$start, $end] = $this->dateRange($period);

        // Load completed SR assignments with coverage submissions and reader info
        $assignments = Assignment::with(['assignedReader.readerProfile', 'coverageSubmission'])
            ->where('status', Assignment::STATUS_COMPLETED)
            ->where('vendor', 'sr')
            ->when($start, fn($q) => $q->where('completed_at', '>=', $start))
            ->when($end,   fn($q) => $q->where('completed_at', '<=', $end))
            ->get();

        // Per-reader stats
        $readerStats = $assignments
            ->groupBy('assigned_reader_id')
            ->map(fn($group) => $this->computeStats($group))
            ->sortBy('reader_name');

        // Combined stats
        $combined = $this->computeStats($assignments);

        // Volume over recent windows (counts only — ignore the period selector)
        $volumeStats = $this->volumeStats();

        // Followup response speed stats
        $followupStats = $this->followupStats($start, $end);

        return view('statistics.index', compact('readerStats', 'combined', 'volumeStats', 'period', 'followupStats'));
    }

    private function computeStats(Collection $assignments): array
    {
        $count = $assignments->count();
        if ($count === 0) {
            return [
                'reader_name' => 'Unknown',
                'count'       => 0,
                'avg_turnaround_days' => null,
                'avg_score'   => null,
                'pass'        => 0, 'consider' => 0, 'recommend' => 0,
                'pass_pct'    => 0, 'consider_pct' => 0, 'recommend_pct' => 0,
            ];
        }

        $first = $assignments->first();
        $readerName = $first->assignedReader?->readerProfile?->displayName()
                   ?? $first->assignedReader?->name
                   ?? 'Unknown';

        // Turnaround = accepted_at → completed_at (days, decimal)
        $turnarounds = $assignments
            ->filter(fn($a) => $a->accepted_at && $a->completed_at)
            ->map(fn($a) => $a->accepted_at->diffInHours($a->completed_at) / 24.0);

        $avgTurnaround = $turnarounds->isNotEmpty()
            ? round($turnarounds->average(), 1)
            : null;

        // SR scores — average across all 22 score fields per submission
        $SR_SCORE_FIELDS = [
            'sr_score_concept', 'sr_score_opening_pages', 'sr_score_theme',
            'sr_score_story_logic', 'sr_score_story_element', 'sr_score_setting',
            'sr_score_story_bogged', 'sr_score_scenes_impact', 'sr_score_stakes',
            'sr_score_tension', 'sr_score_characters_interesting', 'sr_score_characters_choices',
            'sr_score_characters_motivations', 'sr_score_characters_different',
            'sr_score_antagonistic', 'sr_score_dialogue', 'sr_score_action_text',
            'sr_score_climax', 'sr_score_work_feels', 'sr_score_target_audience',
            'sr_score_content', 'sr_score_format',
        ];

        $scoredSubmissions = $assignments
            ->filter(fn($a) => $a->coverageSubmission !== null)
            ->map(fn($a) => $a->coverageSubmission);

        $allScores = $scoredSubmissions->flatMap(function ($sub) use ($SR_SCORE_FIELDS) {
            return collect($SR_SCORE_FIELDS)
                ->map(fn($f) => $sub->$f)
                ->filter(fn($v) => is_numeric($v) && $v > 0);
        });

        $avgScore = $allScores->isNotEmpty() ? round($allScores->average(), 1) : null;

        // Recommendation breakdown (pass/consider/recommend)
        $recs = $assignments
            ->filter(fn($a) => $a->coverageSubmission?->sr_recommendation !== null)
            ->map(fn($a) => strtolower($a->coverageSubmission->sr_recommendation));

        $pass      = $recs->filter(fn($r) => str_contains($r, 'pass'))->count();
        $consider  = $recs->filter(fn($r) => str_contains($r, 'consider'))->count();
        $recommend = $recs->filter(fn($r) => str_contains($r, 'recommend') && ! str_contains($r, 'consider'))->count();
        $recTotal  = $recs->count();

        return [
            'reader_name'          => $readerName,
            'reader_user'          => $first->assignedReader,
            'count'                => $count,
            'avg_turnaround_days'  => $avgTurnaround,
            'avg_score'            => $avgScore,
            'pass'                 => $pass,
            'consider'             => $consider,
            'recommend'            => $recommend,
            'pass_pct'             => $recTotal > 0 ? round($pass      / $recTotal * 100) : 0,
            'consider_pct'         => $recTotal > 0 ? round($consider  / $recTotal * 100) : 0,
            'recommend_pct'        => $recTotal > 0 ? round($recommend / $recTotal * 100) : 0,
        ];
    }

    private function volumeStats(): array
    {
        $tz  = config('app.timezone', 'America/Los_Angeles');
        $now = Carbon::now($tz);

        $windows = [
            'This Week'   => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'Last Week'   => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
            'This Month'  => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'Last 30 Days'=> [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'Last 90 Days'=> [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay()],
            'This Year'   => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'All Time'    => [null, null],
        ];

        return collect($windows)->map(function ($range) {
            [$start, $end] = $range;
            return Assignment::where('status', Assignment::STATUS_COMPLETED)
                ->when($start, fn($q) => $q->where('completed_at', '>=', $start))
                ->when($end,   fn($q) => $q->where('completed_at', '<=', $end))
                ->count();
        })->all();
    }

    private function followupStats(?Carbon $start, ?Carbon $end): array
    {
        $base = FollowupQuestion::with(['assignment.assignedReader.readerProfile'])
            ->when($start, fn($q) => $q->where('followup_questions.created_at', '>=', $start))
            ->when($end,   fn($q) => $q->where('followup_questions.created_at', '<=', $end));

        // Metric 1: total speed — customer submission → HelpScout draft (completed_at)
        $totalSpeeds = (clone $base)
            ->whereNotNull('completed_at')
            ->get()
            ->map(fn($fq) => $fq->created_at->diffInHours($fq->completed_at));

        // Metric 2: reader speed — sent to reader (unanswered_at) → reader answered (answered_at)
        $readerSpeeds = (clone $base)
            ->whereNotNull('unanswered_at')
            ->whereNotNull('answered_at')
            ->get();

        $readerSpeedHours = $readerSpeeds->map(
            fn($fq) => $fq->unanswered_at->diffInHours($fq->answered_at)
        );

        // Per-reader breakdown of reader response speed
        $perReader = $readerSpeeds
            ->groupBy(fn($fq) => $fq->assignment?->assigned_reader_id)
            ->map(function ($group) {
                $first    = $group->first();
                $reader   = $first->assignment?->assignedReader;
                $name     = $reader?->readerProfile?->displayName() ?? $reader?->name ?? 'Unknown';
                $hours    = $group->map(fn($fq) => $fq->unanswered_at->diffInHours($fq->answered_at));
                return [
                    'reader_name' => $name,
                    'count'       => $group->count(),
                    'avg_hours'   => round($hours->average(), 1),
                ];
            })
            ->sortBy('reader_name')
            ->values();

        return [
            'avg_total_hours'  => $totalSpeeds->isNotEmpty()  ? round($totalSpeeds->average(), 1)      : null,
            'avg_reader_hours' => $readerSpeedHours->isNotEmpty() ? round($readerSpeedHours->average(), 1) : null,
            'total_count'      => $totalSpeeds->count(),
            'reader_count'     => $readerSpeedHours->count(),
            'per_reader'       => $perReader,
        ];
    }

    private function dateRange(string $period): array
    {
        $tz  = config('app.timezone', 'America/Los_Angeles');
        $now = Carbon::now($tz);

        return match ($period) {
            'this_week'  => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'last_week'  => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'last_30'    => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'last_60'    => [$now->copy()->subDays(59)->startOfDay(), $now->copy()->endOfDay()],
            'last_90'    => [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay()],
            'this_year'  => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'last_year'  => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            'all_time'   => [null, null],
        };
    }
}
