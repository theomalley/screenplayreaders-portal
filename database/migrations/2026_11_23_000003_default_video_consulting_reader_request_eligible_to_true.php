<?php

// v1.0 — 2026-11-23 | Reader Request fee waiver eligibility now defaults to true for every
//                      role — a reader used to be hardcoded always-eligible with no toggle at
//                      all, and admin/editor defaulted to false and had to opt in; now
//                      everyone gets the same editable checkbox, defaulted on (see
//                      App\Support\VideoConsultingAccess::eligibleForReaderRequestWaiver()).
//                      Backfills every existing row to true — there's no way to tell "never
//                      touched" apart from "explicitly opted out" on the old plain-boolean
//                      column, and the new desired state is everyone starts eligible.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->boolean('video_consulting_reader_request_eligible')->default(true)->change();
        });

        DB::table('reader_profiles')->update(['video_consulting_reader_request_eligible' => true]);
    }

    public function down(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->boolean('video_consulting_reader_request_eligible')->default(false)->change();
        });
    }
};
