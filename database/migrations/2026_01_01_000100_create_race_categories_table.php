<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('race_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 20)->unique();          // 5k / 10k
            $table->string('name');                         // Fun Run 5K
            $table->string('distance_label', 10);           // 5K
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();
            $table->json('features')->nullable();           // bullet list di kartu bib
            $table->unsignedInteger('price');               // rupiah, tanpa desimal
            $table->unsignedInteger('quota')->default(250);
            $table->unsignedInteger('bib_start')->default(1001);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('race_categories');
    }
};
