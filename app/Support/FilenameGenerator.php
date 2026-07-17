<?php

// v1.1 — 2026-07-17 | Add coverageDocx() for WD Help Scout draft attachments
// v1.0 — 2026-05-22 | Structured filename generation for scripts and coverage docs/PDFs

namespace App\Support;

use App\Models\Assignment;
use App\Models\Setting;

class FilenameGenerator
{
    public const SR_TYPES = [
        'script_coverage' => 'Script Coverage (SR)',
        'notes_only'      => 'Notes Only (SR)',
        'deep_dive'       => 'Advanced Script Coverage (SR)',
        'book'            => 'Book Coverage (SR)',
        'budget'          => 'Budget Coverage (SR)',
        'short'           => 'Short Coverage (SR)',
    ];

    public const WD_TYPES = [
        'coverage'           => 'Coverage (WD)',
        'development_notes'  => 'Development Notes (WD)',
    ];

    /** Default suffix for each service type, configurable via settings table. */
    public const SUFFIX_DEFAULTS = [
        'filename_suffix_sr_script_coverage'   => 'scriptcoverage',
        'filename_suffix_sr_notes_only'        => 'notesonly',
        'filename_suffix_sr_deep_dive'         => 'devnotes',
        'filename_suffix_sr_book'              => 'bookcoverage',
        'filename_suffix_sr_budget'            => 'budgcoverage',
        'filename_suffix_sr_short'             => 'shortcoverage',
        'filename_suffix_wd_coverage'          => 'coverage',
        'filename_suffix_wd_development_notes' => 'devnotes',
    ];

    /** Script PDF filename: {base}.pdf */
    public static function script(Assignment $assignment): string
    {
        return self::base($assignment) . '.pdf';
    }

    /** Coverage Google Doc name (no extension): {base}_{suffix}-{initials} */
    public static function coverageDoc(Assignment $assignment, ?string $readerInitials = null): string
    {
        $base   = self::base($assignment);
        $suffix = self::suffix($assignment);
        $rider  = $readerInitials ? '-' . $readerInitials : '';

        return $suffix ? "{$base}_{$suffix}{$rider}" : "{$base}{$rider}";
    }

    /** Coverage PDF filename: coverageDoc + .pdf */
    public static function coveragePdf(Assignment $assignment, ?string $readerInitials = null): string
    {
        return self::coverageDoc($assignment, $readerInitials) . '.pdf';
    }

    /** Coverage DOCX filename: coverageDoc + .docx */
    public static function coverageDocx(Assignment $assignment, ?string $readerInitials = null): string
    {
        return self::coverageDoc($assignment, $readerInitials) . '.docx';
    }

    /** Base segment: {prefix}_{YYYYMMDD}_{slug-title}_{writerCode} */
    public static function base(Assignment $assignment): string
    {
        $vendor = strtolower($assignment->vendor ?? 'sr');
        $prefix = $vendor === 'wd' ? 'WD' : (string) $assignment->order_number;
        $date   = ($assignment->created_at ?? now())->format('Ymd');
        $title  = self::slugifyTitle($assignment->script_title ?? '');
        $writer = self::writerCode($assignment->writer_name ?? '');

        return implode('_', array_filter([$prefix, $date, $title, $writer]));
    }

    /** Resolve the suffix for an assignment's vendor + type from settings or defaults. */
    public static function suffix(Assignment $assignment): string
    {
        $vendor = strtolower($assignment->vendor ?? 'sr');
        $type   = $assignment->assignment_type ?? '';
        $key    = 'filename_suffix_' . $vendor . '_' . $type;
        $stored = Setting::getValue($key);

        return $stored !== null ? $stored : (self::SUFFIX_DEFAULTS[$key] ?? '');
    }

    /** All suffixes resolved (stored or default), keyed by settings key. */
    public static function allSuffixes(): array
    {
        $keys   = array_keys(self::SUFFIX_DEFAULTS);
        $stored = Setting::whereIn('key', $keys)->pluck('value', 'key')->toArray();

        $result = [];
        foreach (self::SUFFIX_DEFAULTS as $key => $default) {
            $result[$key] = $stored[$key] ?? $default;
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** "Star Wars: Episode IV" → "Star-Wars-Episode-IV" */
    public static function slugifyTitle(string $title): string
    {
        $slug = preg_replace('/[^a-zA-Z0-9\s-]/', '', $title);
        $slug = preg_replace('/\s+/', '-', trim($slug));
        $slug = preg_replace('/-+/', '-', $slug);

        return $slug ?: 'untitled';
    }

    /** "George Lucas" → "GLucas" (first char of first word + last word, alpha only) */
    public static function writerCode(string $name): string
    {
        $clean = preg_replace('/[^a-zA-Z\s]/', '', $name);
        $parts = array_values(array_filter(explode(' ', $clean)));

        if (empty($parts)) {
            return '';
        }

        return mb_substr($parts[0], 0, 1) . end($parts);
    }
}
