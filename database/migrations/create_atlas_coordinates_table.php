<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atlas_coordinates', function (Blueprint $table) {
            $table->id();
            $table->morphs('coordinable');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('method')->nullable();
            $table->timestamp('geocoded_at')->nullable();
            $table->timestamps();

            $table->unique(['coordinable_type', 'coordinable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atlas_coordinates');
    }
};
