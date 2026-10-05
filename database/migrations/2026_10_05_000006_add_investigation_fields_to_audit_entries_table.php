<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O que faltava para investigar: por onde a ação chegou, o navegador e o
     * que mudou. Os índices passam a começar pela empresa, porque toda
     * consulta da tela filtra por ela antes do resto.
     */
    public function up(): void
    {
        Schema::table('audit_entries', function (Blueprint $table) {
            $table->string('channel', 16)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('changes')->nullable();

            $table->dropIndex(['organization_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['action']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['subject_type', 'subject_id']);

            $table->index(['organization_id', 'created_at']);
            $table->index(['organization_id', 'action']);
            $table->index(['organization_id', 'user_id']);
            $table->index(['organization_id', 'subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_entries', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'created_at']);
            $table->dropIndex(['organization_id', 'action']);
            $table->dropIndex(['organization_id', 'user_id']);
            $table->dropIndex(['organization_id', 'subject_type', 'subject_id']);

            $table->index('organization_id');
            $table->index('user_id');
            $table->index('action');
            $table->index('created_at');
            $table->index(['subject_type', 'subject_id']);

            $table->dropColumn(['channel', 'user_agent', 'changes']);
        });
    }
};
