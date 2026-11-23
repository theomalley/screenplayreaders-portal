<?php

namespace Tests\Feature;

use App\Http\Controllers\RatebookController;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the RatebookController::wordCountTotals() fix (2026-11-22):
 * WD "development_notes" must not include the synopsis minimum — per spec (and the
 * sibling StoreCoverageSubmissionRequest fix), Synopsis only applies to "coverage".
 */
class RatebookWordCountTotalsTest extends TestCase
{
    use RefreshDatabase;

    private function callWordCountTotals(array $wc): array
    {
        $controller = new RatebookController();
        $method     = new \ReflectionMethod($controller, 'wordCountTotals');
        $method->setAccessible(true);

        return $method->invoke($controller, $wc);
    }

    public function test_development_notes_total_excludes_the_synopsis_minimum(): void
    {
        $wc = Setting::getWordCounts();

        $totals = $this->callWordCountTotals($wc);

        $expectedDevNotes = $wc['wc_wd_logline'] + $wc['wc_wd_notes_development_notes'];
        $this->assertSame($expectedDevNotes, $totals['rate_wd_development_notes']);
    }

    public function test_coverage_total_still_includes_the_synopsis_minimum(): void
    {
        $wc = Setting::getWordCounts();

        $totals = $this->callWordCountTotals($wc);

        $expectedCoverage = $wc['wc_wd_logline'] + $wc['wc_wd_synopsis'] + $wc['wc_wd_notes_coverage'];
        $this->assertSame($expectedCoverage, $totals['rate_wd_coverage']);
    }
}
