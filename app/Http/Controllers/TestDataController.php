<?php

// v1.0 — 2026-06-04 | Admin test data — seed dummy assignments, bulk reset, auto-reset toggle

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TestDataController extends Controller
{
    private const TITLES = [
        'The Last Signal', 'Borrowed Time', 'Paper Walls', 'After the Fall',
        'Broken Circuit', 'The Quiet Room', 'Wild Territory', 'Second Chance',
        'The Long Way Home', 'Distant Thunder', 'Between Lives', 'The Missing Hour',
        'Night Shift', 'Before the Storm', 'Echo Chamber', 'Open Water',
        'The Other Side', 'Crossroads', 'Dead Letters', 'Rising Tide',
    ];

    private const WRITERS = [
        'J. Hartwell', 'M. Chen', 'R. Okonkwo', 'S. Morales', 'D. Fitzgerald',
        'A. Patel', 'T. Williams', 'L. Nakamura', 'C. Johnson', 'B. Kowalski',
        'P. Nguyen', 'E. Brennan', 'K. Osei', 'N. Reyes', 'F. Marchetti',
    ];

    // type => [min_pages, max_pages, pay]
    private const TYPES = [
        'script_coverage' => [90,  130, 110.00],
        'notes_only'      => [80,  120,  55.00],
        'short'           => [15,   40,  70.00],
        'deep_dive'       => [100, 140, 155.00],
        'budget'          => [90,  120,  80.00],
        'book'            => [200, 300, 140.00],
    ];

    public function index()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $testCount       = Assignment::where('is_test', true)->count();
        $pendingReset    = Assignment::where('is_test', true)
            ->where('status', '!=', Assignment::STATUS_UNASSIGNED)
            ->count();
        $autoReset       = Setting::getValue('test_auto_reset', '0') === '1';
        $testScriptId    = Setting::getValue('test_script_drive_file_id', '');
        $testScriptName  = Setting::getValue('test_script_drive_filename', '');

        return view('admin.test-data', compact('testCount', 'pendingReset', 'autoReset', 'testScriptId', 'testScriptName'));
    }

    public function seed(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $count = (int) $request->input('count', 10);
        $count = max(1, min(50, $count));

        $types   = array_keys(self::TYPES);
        $titles  = self::TITLES;
        $writers = self::WRITERS;

        shuffle($titles);
        $created = 0;

        for ($i = 0; $i < $count; $i++) {
            $type              = $types[$i % count($types)];
            [$minP, $maxP, $pay] = self::TYPES[$type];
            $pages             = rand($minP, $maxP);
            $title             = $titles[$i % count($titles)];
            $writer            = $writers[$i % count($writers)];
            $orderNum          = 'TEST-' . strtoupper(substr(uniqid(), -6));

            $scriptFileId   = Setting::getValue('test_script_drive_file_id', '') ?: null;
            $scriptFilename = Setting::getValue('test_script_drive_filename', '') ?: null;

            Assignment::create([
                'order_number'          => $orderNum,
                'vendor'                => 'sr',
                'assignment_type'       => $type,
                'script_title'          => $title,
                'writer_name'           => $writer,
                'page_count'            => $pages,
                'pay_rate'              => $pay,
                'status'                => Assignment::STATUS_UNASSIGNED,
                'is_test'               => true,
                'unassigned_at'         => now(),
                'drive_script_file_id'  => $scriptFileId,
                'drive_script_filename' => $scriptFilename,
            ]);

            $created++;
        }

        return back()->with('success', "Created {$created} test assignment(s).");
    }

    public function reset()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        // Delete coverage submissions for all test assignments
        $testIds = Assignment::where('is_test', true)->pluck('id');

        DB::table('coverage_submissions')
            ->whereIn('assignment_id', $testIds)
            ->delete();

        // Reset all test assignments to unassigned
        Assignment::where('is_test', true)->update([
            'status'                  => Assignment::STATUS_UNASSIGNED,
            'assigned_reader_id'      => null,
            'accepted_at'             => null,
            'submitted_at'            => null,
            'completed_at'            => null,
            'reader_paid_at'          => null,
            'helpscout_draft_sent_at' => null,
            'drive_coverage_doc_id'   => null,
            'drive_coverage_pdf_id'   => null,
            'needs_attention_notes'   => null,
            'unassigned_at'           => now(),
        ]);

        $count = $testIds->count();

        return back()->with('success', "Reset {$count} test assignment(s) to Available.");
    }

    public function destroy()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $testIds = Assignment::where('is_test', true)->pluck('id');

        DB::table('coverage_submissions')->whereIn('assignment_id', $testIds)->delete();
        Assignment::where('is_test', true)->delete();

        return back()->with('success', "Deleted all {$testIds->count()} test assignment(s).");
    }

    public function saveTestScript(\Illuminate\Http\Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        if ($request->hasFile('pdf')) {
            $request->validate(['pdf' => 'required|file|mimes:pdf|max:20480']);
            $request->file('pdf')->storeAs('', 'test-script.pdf', ['disk' => 'local']);
            $filename = $request->file('pdf')->getClientOriginalName();
            Setting::setValue('test_script_drive_file_id', '__LOCAL_TEST__');
            Setting::setValue('test_script_drive_filename', $filename);
            return back()->with('success', 'Test script uploaded — all seeded assignments will use this file.');
        }

        // Clear
        Setting::setValue('test_script_drive_file_id', '');
        Setting::setValue('test_script_drive_filename', '');
        if (file_exists(storage_path('app/test-script.pdf'))) {
            unlink(storage_path('app/test-script.pdf'));
        }
        return back()->with('success', 'Test script cleared.');
    }

    public function toggleAutoReset(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $enabled = $request->boolean('enabled');
        Setting::setValue('test_auto_reset', $enabled ? '1' : '0');

        return back()->with('success', 'Auto-reset ' . ($enabled ? 'enabled' : 'disabled') . '.');
    }
}
