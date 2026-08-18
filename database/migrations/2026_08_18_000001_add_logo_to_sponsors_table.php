<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsors', function (Blueprint $table) {
            // Lokasi berkas logo di disk public (storage/app/public/sponsors/...).
            $table->string('logo_path')->nullable()->after('website_url');
            // logo | teks — bagaimana sponsor ini ditampilkan di landing page.
            $table->string('display_type', 10)->default('teks')->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('sponsors', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'display_type']);
        });
    }
};
