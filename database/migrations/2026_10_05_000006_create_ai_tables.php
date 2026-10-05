<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 32);
            $table->string('label', 80);
            // Cifrada (cast encrypted); para a tela, só os últimos caracteres.
            $table->text('api_key');
            $table->string('key_hint', 8);
            $table->string('model', 120);
            $table->boolean('is_default')->default(false);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'is_default']);
        });

        // Cada pergunta feita à IA, com a resposta e o consumo. conversation_id
        // e message_id apontam para Conversas sem chave estrangeira: domínio
        // não amarra a tabela do outro.
        Schema::create('ai_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('message_id')->nullable();
            $table->text('question');
            $table->text('answer')->nullable();
            $table->string('provider', 32);
            $table->string('model', 120);
            $table->string('status', 16);
            $table->string('error_code', 32)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'conversation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_questions');
        Schema::dropIfExists('ai_connections');
    }
};
