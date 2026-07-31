<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_concurrent_assignments')->nullable()->default(null)->change();
        });

        // Precedence flip: the global default cap now applies unless a reader has an
        // explicit override, so the old always-populated default(3) no longer means
        // anything — reset every reader to "no override" and let the global default bind.
        DB::table('reader_profiles')->update(['max_concurrent_assignments' => null]);
    }

    public function down(): void
    {
        DB::table('reader_profiles')->whereNull('max_concurrent_assignments')->update(['max_concurrent_assignments' => 3]);

        Schema::table('reader_profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_concurrent_assignments')->nullable(false)->default(3)->change();
        });
    }
};
