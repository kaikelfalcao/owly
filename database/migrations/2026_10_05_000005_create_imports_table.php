<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('format', 40); // adaptador que leu o arquivo
            $table->string('status', 10)->default('pending'); // pending | running | done | failed
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->char('file_hash', 64);
            // O zip fica guardado só até ser processado.
            $table->string('path')->nullable();
            $table->json('stats')->nullable();
            $table->json('problems')->nullable();
            $table->string('error_code', 40)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'file_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
