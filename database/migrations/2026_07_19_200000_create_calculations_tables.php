<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Baukalkulation: Räume mit Formen und Gewerke-Mengen, Preise
        // je Kalkulation — daraus entsteht eine Angebotssumme.
        Schema::create('calculations', function (Blueprint $table) {
            $table->id();
            $table->companyOwned();
            $table->string('name');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('waste_percent', 5, 2)->default(15);
            $table->decimal('wall_tile_height', 4, 2)->default(2.10);
            $table->decimal('price_parquet', 8, 2)->default(0);
            $table->decimal('price_floor_tiles', 8, 2)->default(0);
            $table->decimal('price_wall_tiles', 8, 2)->default(0);
            $table->decimal('price_silicone', 8, 2)->default(0);
            $table->decimal('price_skirting', 8, 2)->default(0);
            $table->decimal('price_painting', 8, 2)->default(0);
            $table->text('notes')->nullable();
            $table->businessMeta();

            $table->index(['company_id', 'name']);
        });

        Schema::create('calculation_rooms', function (Blueprint $table) {
            $table->id();
            $table->companyOwned();
            $table->foreignId('calculation_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('shape', 20); // rectangle | l_shape | trapezoid | triangle | manual
            $table->string('material', 20); // parquet | floor_tiles | wall_tiles
            $table->decimal('length', 8, 2)->nullable();
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->default(2.50);
            $table->decimal('length2', 8, 2)->nullable();
            $table->decimal('width2', 8, 2)->nullable();
            $table->decimal('depth', 8, 2)->nullable();
            $table->decimal('area_manual', 10, 2)->nullable();
            $table->decimal('perimeter_manual', 10, 2)->nullable();
            $table->unsignedSmallInteger('edges')->default(0);
            $table->decimal('door_width', 6, 2)->default(0);
            $table->decimal('opening_area', 8, 2)->default(0);
            $table->boolean('estimated')->default(false);
            $table->businessMeta();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calculation_rooms');
        Schema::dropIfExists('calculations');
    }
};
