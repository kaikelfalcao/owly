<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A mensagem passa a saber de quem é (contact_id) e de onde veio (import_id),
 * sem depender da conversa. Prepara o corte em atendimentos: a conversa vai
 * poder ser refeita, e a mensagem não pode ir junto (docs/arquitetura.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->after('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_id')->nullable()->after('contact_id')->constrained()->nullOnDelete();
        });

        DB::table('messages')->update([
            'contact_id' => DB::raw('(select conversations.contact_id from conversations where conversations.id = messages.conversation_id)'),
        ]);

        // Não há como saber qual zip trouxe cada mensagem antiga. Só quando a
        // empresa tem uma única importação, e ela terminou, a resposta é
        // certa; nas outras o import_id fica nulo em vez de chutado.
        $single = DB::table('imports')
            ->where('status', '!=', 'pending')
            ->groupBy('organization_id')
            ->havingRaw('count(*) = 1')
            ->havingRaw("max(status) = 'done'")
            ->selectRaw('organization_id, max(id) as id')
            ->get();

        foreach ($single as $import) {
            DB::table('messages')->where('organization_id', $import->organization_id)->update(['import_id' => $import->id]);
        }

        Schema::table('messages', function (Blueprint $table) {
            $table->unsignedBigInteger('contact_id')->nullable(false)->change();
            // O índice novo entra antes de o único sair: a chave de
            // conversation_id nunca fica sem índice.
            $table->index(['conversation_id', 'sent_at']);
            $table->index('import_id');
            // Reimportar não duplica: a mesma mensagem do mesmo cliente,
            // qualquer que seja a conversa em que ela está.
            $table->unique(['contact_id', 'external_id']);
            $table->dropUnique(['conversation_id', 'external_id']);
        });

        // Sem cascata: apagar uma conversa nunca apaga mensagens. O banco
        // recusa apagar conversa que ainda tem mensagem; quem refaz conversas
        // move as mensagens antes. Apagar o cliente ou a empresa continua
        // levando tudo, pelas cascatas de contact_id e organization_id.
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->foreign('conversation_id')->references('id')->on('conversations');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->unique(['conversation_id', 'external_id']);
            $table->dropUnique(['contact_id', 'external_id']);
            $table->dropIndex(['conversation_id', 'sent_at']);
            $table->dropIndex(['import_id']);
            $table->dropForeign(['contact_id']);
            $table->dropForeign(['import_id']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['contact_id', 'import_id']);
        });
    }
};
