<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // "wa:<telefone>" quando o telefone é conhecido; senão "file:<nome>"
            // (exportação em que o contato só aparece pelo nome do arquivo).
            $table->string('external_key', 160);
            $table->string('phone', 32)->nullable();
            $table->string('name')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'external_key']);
        });

        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->timestamp('first_message_at')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->unsignedInteger('messages_count')->default(0);
            $table->timestamps();

            // Uma conversa por cliente: mensagens do zip e da API entram na mesma.
            $table->unique(['organization_id', 'contact_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('source', 10); // zip | api
            $table->string('external_id', 64);
            $table->timestamp('sent_at');
            $table->string('direction', 3); // in | out
            $table->string('author', 10); // contact | seller | bot | system
            $table->foreignId('seller_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body')->nullable();
            $table->string('media_type', 20)->nullable();
            $table->string('media_name')->nullable();
            $table->string('event', 20)->nullable();
            $table->text('quoted')->nullable();
            $table->timestamps();

            // Reimportar o mesmo período não duplica mensagem.
            $table->unique(['conversation_id', 'external_id']);
            $table->index(['organization_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('sellers');
        Schema::dropIfExists('contacts');
    }
};
