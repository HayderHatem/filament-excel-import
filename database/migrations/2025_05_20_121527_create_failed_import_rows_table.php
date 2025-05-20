<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('failed_import_rows')) {
            Schema::create('failed_import_rows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('import_id')->constrained('imports')->cascadeOnDelete();
                $table->json('data');
                $table->json('validation_errors')->nullable();
                $table->text('error')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('failed_import_rows');
    }
};
