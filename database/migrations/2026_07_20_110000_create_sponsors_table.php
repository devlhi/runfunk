<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            // utama | pendukung | media — menentukan urutan & ukuran tampil di landing page.
            $table->string('tier', 20)->default('pendukung');
            $table->string('website_url')->nullable();
            $table->string('note', 160)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'tier', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsors');
    }
};
