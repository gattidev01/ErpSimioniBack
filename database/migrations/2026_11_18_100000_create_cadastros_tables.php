<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oficina_config', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120);
            $table->string('cnpj', 20)->default('');
            $table->string('telefone', 20)->default('');
            $table->string('email', 120)->default('');
            $table->string('endereco', 200)->default('');
            $table->unsignedSmallInteger('garantia_dias')->default(90);
            $table->string('logo_path')->nullable();
            $table->char('cor_documento', 7)->default('#d92d20');
            $table->unsignedSmallInteger('validade_orcamento_dias')->default(7);
            $table->text('condicoes_orcamento')->nullable();
            $table->string('email_alertas', 120)->nullable()->comment('Destino extra dos alertas diÃ¡rios');
            $table->timestamps();
        });

        Schema::create('funcionarios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nome', 120);
            $table->string('funcao', 60)->default('');
            $table->string('telefone', 20)->default('');
            $table->decimal('comissao', 5, 2)->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('funcionario_id')->nullable()->after('perfil')
                ->constrained('funcionarios')->nullOnDelete();
        });

        Schema::create('clientes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nome', 120)->index();
            $table->char('tipo', 2)->default('PF');
            $table->string('documento', 20)->default('')->index();
            $table->string('telefone', 20)->default('');
            $table->string('email', 120)->default('');
            $table->string('endereco', 200)->default('');
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });

        Schema::create('veiculos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->string('placa', 8)->unique();
            $table->string('marca', 40)->default('');
            $table->string('modelo', 60);
            $table->string('ano', 9)->default('');
            $table->string('cor', 30)->default('');
            $table->unsignedInteger('km')->default(0);
            $table->string('chassi', 17)->default('');
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });

        Schema::create('servicos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('descricao', 120);
            $table->string('categoria', 40)->default('Outros');
            $table->decimal('preco', 12, 2)->default(0);
            $table->decimal('tempo_estimado', 6, 2)->default(1);
            $table->timestamps();
        });

        Schema::create('pecas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 30)->nullable()->unique();
            $table->string('descricao', 120);
            $table->string('marca', 40)->default('');
            $table->string('unidade', 4)->default('UN');
            $table->decimal('custo', 12, 2)->default(0);
            $table->decimal('preco', 12, 2)->default(0);
            $table->decimal('estoque', 12, 3)->default(0);
            $table->decimal('estoque_minimo', 12, 3)->default(0);
            $table->string('localizacao', 20)->default('');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('funcionario_id'));
        Schema::dropIfExists('pecas');
        Schema::dropIfExists('servicos');
        Schema::dropIfExists('veiculos');
        Schema::dropIfExists('clientes');
        Schema::dropIfExists('funcionarios');
        Schema::dropIfExists('oficina_config');
    }
};
