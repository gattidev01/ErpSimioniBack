<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agendamentos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignUuid('veiculo_id')->constrained('veiculos')->restrictOnDelete();
            $table->date('data')->index();
            $table->string('hora', 5);
            $table->string('servico', 160)->default('');
            $table->string('status', 20)->default('agendado')->index();
            $table->text('observacoes')->nullable();
            $table->timestamp('lembrete_enviado_em')->nullable();
            $table->timestamps();
        });

        Schema::create('orcamentos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedInteger('numero')->unique();
            $table->foreignUuid('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignUuid('veiculo_id')->constrained('veiculos')->restrictOnDelete();
            $table->string('status', 20)->default('rascunho')->index();
            $table->date('data_emissao');
            $table->date('validade')->index();
            $table->unsignedInteger('km_entrada')->default(0);
            $table->text('solicitacao')->nullable();
            $table->text('diagnostico')->nullable();
            $table->decimal('desconto', 12, 2)->default(0);
            $table->string('forma_pagamento', 30)->default('PIX');
            $table->string('prazo_execucao', 60)->default('');
            $table->text('condicoes')->nullable();
            $table->text('observacoes')->nullable();
            $table->uuid('os_id')->nullable()->index();
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ordens_servico', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedInteger('numero')->unique();
            $table->foreignUuid('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignUuid('veiculo_id')->constrained('veiculos')->restrictOnDelete();
            $table->foreignUuid('mecanico_id')->nullable()->constrained('funcionarios')->nullOnDelete();
            $table->string('status', 20)->default('orcamento')->index();
            $table->date('data_abertura');
            $table->date('data_previsao');
            $table->date('data_conclusao')->nullable()->index();
            $table->unsignedInteger('km_entrada')->default(0);
            $table->text('defeito_relatado')->nullable();
            $table->text('diagnostico')->nullable();
            $table->text('observacoes')->nullable();
            $table->decimal('desconto', 12, 2)->default(0);
            $table->string('forma_pagamento', 30)->default('PIX');
            $table->boolean('estoque_baixado')->default(false);
            $table->foreignUuid('agendamento_id')->nullable()->constrained('agendamentos')->nullOnDelete();
            $table->foreignUuid('orcamento_id')->nullable()->constrained('orcamentos')->nullOnDelete();
            $table->timestamps();
        });

        // itens com a mesma estrutura nos dois documentos
        foreach (['orcamento_itens' => 'orcamentos', 'ordem_itens' => 'ordens_servico'] as $tabela => $pai) {
            Schema::create($tabela, function (Blueprint $table) use ($pai) {
                $table->uuid('id')->primary();
                $table->foreignUuid('documento_id')->constrained($pai)->cascadeOnDelete();
                $table->string('tipo', 10); // servico | peca
                $table->uuid('ref_id')->nullable()->index(); // serviço/peça do cadastro; null = item avulso
                $table->string('descricao', 160);
                $table->decimal('quantidade', 12, 3);
                $table->decimal('valor_unitario', 12, 2);
                $table->unsignedSmallInteger('ordem')->default(0);
            });
        }

        Schema::create('movimentacoes_estoque', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('peca_id')->constrained('pecas')->cascadeOnDelete();
            $table->string('tipo', 10); // entrada | saida | estorno | ajuste
            $table->decimal('quantidade', 12, 3); // positivo = entra, negativo = sai
            $table->decimal('saldo_apos', 12, 3);
            $table->decimal('custo_unitario', 12, 2)->nullable();
            $table->foreignUuid('os_id')->nullable()->constrained('ordens_servico')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('observacao', 200)->default('');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimentacoes_estoque');
        Schema::dropIfExists('ordem_itens');
        Schema::dropIfExists('orcamento_itens');
        Schema::dropIfExists('ordens_servico');
        Schema::dropIfExists('orcamentos');
        Schema::dropIfExists('agendamentos');
    }
};
