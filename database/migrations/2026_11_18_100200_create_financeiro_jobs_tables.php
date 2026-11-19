<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lancamentos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tipo', 10)->index(); // receita | despesa
            $table->string('descricao', 160);
            $table->string('categoria', 40);
            $table->decimal('valor', 12, 2);
            $table->date('vencimento')->index();
            $table->date('pago_em')->nullable()->index();
            $table->string('forma_pagamento', 30)->default('PIX');
            $table->foreignUuid('os_id')->nullable()->unique()->constrained('ordens_servico')->nullOnDelete();
            $table->timestamps();
        });

        // Histórico de e-mails/WhatsApps disparados pelos jobs
        Schema::create('envios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('documento_tipo', 20); // orcamento | ordem | agendamento
            $table->uuid('documento_id');
            $table->string('canal', 10); // email | whatsapp
            $table->string('destino', 120);
            $table->text('mensagem')->nullable();
            $table->string('status', 12)->default('pendente')->index(); // pendente | enviando | enviado | falhou
            $table->unsignedTinyInteger('tentativas')->default(0);
            $table->text('erro')->nullable();
            $table->timestamp('enviado_em')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['documento_tipo', 'documento_id']);
        });

        // Relatórios gerados em segundo plano
        Schema::create('relatorios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->string('formato', 4); // pdf | xlsx
            $table->json('parametros')->nullable();
            $table->string('status', 12)->default('pendente');
            $table->string('arquivo')->nullable();
            $table->text('erro')->nullable();
            $table->timestamp('concluido_em')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('relatorios');
        Schema::dropIfExists('envios');
        Schema::dropIfExists('lancamentos');
    }
};
