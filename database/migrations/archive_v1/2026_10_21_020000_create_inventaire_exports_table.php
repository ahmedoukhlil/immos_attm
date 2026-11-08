<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventaire_exports')) {
            return;
        }

        Schema::create('inventaire_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventaire_id')->constrained('inventaires')->cascadeOnDelete();
            $table->unsignedInteger('requested_by');
            $table->string('format', 10);
            $table->string('status', 20)->default('pending');
            $table->string('disk', 30)->default('local');
            $table->string('path', 500)->nullable();
            $table->string('filename')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['inventaire_id', 'requested_by', 'status'], 'inv_exports_lookup_index');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventaire_exports');
    }
};
