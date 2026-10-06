<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A conversa vira atendimento: um cliente passa a ter vários, cada um com
 * começo e fim (docs/arquitetura.md, "Atendimentos"). Aqui muda só o
 * esquema; o corte depende do calendário da empresa e roda pelo comando
 * owly:recut. Até ele rodar, um atendimento por cliente é um estado válido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('seller_id')->nullable()->after('contact_id')->constrained()->nullOnDelete();
            // contact | company: quem mandou a mensagem que abriu.
            $table->string('opened_by', 10)->default('contact')->after('seller_id');
            // open | closed: se a próxima mensagem do cliente ainda cai nele.
            $table->string('status', 10)->default('open')->after('opened_by');
        });

        // Conversa sem mensagem não tem começo; não deveria existir.
        DB::table('conversations')
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('messages')->whereColumn('messages.conversation_id', 'conversations.id'))
            ->delete();

        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('first_message_at')->nullable(false)->change();
            $table->timestamp('last_message_at')->nullable(false)->change();
            // Um cliente, vários atendimentos; dois não começam no mesmo instante.
            $table->unique(['contact_id', 'first_message_at']);
            $table->index(['organization_id', 'last_message_at']);
            $table->dropUnique(['organization_id', 'contact_id']);
            $table->dropIndex(['last_message_at']);
        });
    }

    /**
     * Junta de novo: as mensagens de cada cliente voltam para o atendimento
     * mais antigo, que tem o id da conversa de antes. Nenhuma mensagem se perde.
     */
    public function down(): void
    {
        DB::table('messages')->update([
            'conversation_id' => DB::raw('(select c.id from conversations c where c.contact_id = messages.contact_id order by c.first_message_at, c.id limit 1)'),
        ]);

        DB::table('conversations')
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('messages')->whereColumn('messages.conversation_id', 'conversations.id'))
            ->delete();

        DB::table('conversations')->update([
            'messages_count' => DB::raw('(select count(*) from messages where messages.conversation_id = conversations.id)'),
            'first_message_at' => DB::raw('(select min(sent_at) from messages where messages.conversation_id = conversations.id)'),
            'last_message_at' => DB::raw('(select max(sent_at) from messages where messages.conversation_id = conversations.id)'),
        ]);

        Schema::table('conversations', function (Blueprint $table) {
            $table->unique(['organization_id', 'contact_id']);
            $table->index('last_message_at');
            $table->dropUnique(['contact_id', 'first_message_at']);
            $table->dropIndex(['organization_id', 'last_message_at']);
            $table->timestamp('first_message_at')->nullable()->change();
            $table->timestamp('last_message_at')->nullable()->change();
            $table->dropForeign(['seller_id']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['seller_id', 'opened_by', 'status']);
        });
    }
};
