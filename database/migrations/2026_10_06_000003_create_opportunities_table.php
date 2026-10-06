<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Oportunidade: o cliente recebeu preço e ainda pode comprar
 * (docs/arquitetura.md, "Oportunidades"). A mensagem que deu origem é a
 * identidade dela: o recálculo acha a linha pela âncora e não duplica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            // Onde ela abriu; a venda pode fechar num atendimento seguinte.
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('anchor_message_id')->unique()->constrained('messages')->cascadeOnDelete();
            $table->foreignId('closing_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained()->nullOnDelete();
            // open | won | lost | discarded
            $table->string('status', 10)->default('open');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->string('loss_reason', 30)->nullable();
            $table->bigInteger('value_cents')->nullable();
            // rule | owner
            $table->string('source', 10);
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            // Preenchido, a regra não mexe mais na linha.
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'opened_at']);
            $table->index(['contact_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
