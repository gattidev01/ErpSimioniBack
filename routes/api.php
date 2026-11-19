<?php

use App\Http\Controllers\Api\AgendamentoController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\ConfiguracaoController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EnvioController;
use App\Http\Controllers\Api\FuncionarioController;
use App\Http\Controllers\Api\LancamentoController;
use App\Http\Controllers\Api\NotificacaoController;
use App\Http\Controllers\Api\OrcamentoController;
use App\Http\Controllers\Api\OrdemServicoController;
use App\Http\Controllers\Api\PecaController;
use App\Http\Controllers\Api\RelatorioController;
use App\Http\Controllers\Api\ServicoController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\VeiculoController;
use Illuminate\Support\Facades\Route;

/*
| Perfis:
|  admin      → tudo
|  atendente  → operação (clientes, orçamentos, OS, agenda, estoque, financeiro, relatórios)
|  mecanico   → somente as OS atribuídas a ele (ver, diagnóstico, status) e consulta de catálogo
*/

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // ---------- todos os perfis ----------
    Route::get('servicos', [ServicoController::class, 'index']);
    Route::get('pecas', [PecaController::class, 'index']);
    Route::get('ordens', [OrdemServicoController::class, 'index']);
    Route::get('ordens/{ordem}', [OrdemServicoController::class, 'show']);
    Route::put('ordens/{ordem}', [OrdemServicoController::class, 'update']);
    Route::post('ordens/{ordem}/status', [OrdemServicoController::class, 'status']);
    Route::get('ordens/{ordem}/pdf', [OrdemServicoController::class, 'pdf']);
    Route::get('configuracoes', [ConfiguracaoController::class, 'show']);
    Route::get('notificacoes', [NotificacaoController::class, 'index']);
    Route::post('notificacoes/lidas', [NotificacaoController::class, 'marcarLidas']);

    // ---------- administrador e atendente ----------
    Route::middleware('perfil:admin,atendente')->group(function () {
        Route::get('dashboard', DashboardController::class);

        Route::apiResource('clientes', ClienteController::class)->parameters(['clientes' => 'cliente']);
        Route::apiResource('veiculos', VeiculoController::class)->parameters(['veiculos' => 'veiculo']);
        Route::apiResource('servicos', ServicoController::class)->except(['index', 'show'])->parameters(['servicos' => 'servico']);
        Route::apiResource('pecas', PecaController::class)->except('index')->parameters(['pecas' => 'peca']);
        Route::post('pecas/{peca}/entrada', [PecaController::class, 'entrada']);

        Route::post('ordens', [OrdemServicoController::class, 'store']);
        Route::delete('ordens/{ordem}', [OrdemServicoController::class, 'destroy']);
        Route::post('ordens/{ordem}/faturar', [OrdemServicoController::class, 'faturar']);
        Route::post('ordens/{ordem}/enviar', [EnvioController::class, 'enviarOrdem']);

        Route::apiResource('orcamentos', OrcamentoController::class)->parameters(['orcamentos' => 'orcamento']);
        Route::post('orcamentos/{orcamento}/status', [OrcamentoController::class, 'status']);
        Route::post('orcamentos/{orcamento}/converter', [OrcamentoController::class, 'converter']);
        Route::post('orcamentos/{orcamento}/duplicar', [OrcamentoController::class, 'duplicar']);
        Route::get('orcamentos/{orcamento}/pdf', [OrcamentoController::class, 'pdf']);
        Route::post('orcamentos/{orcamento}/enviar', [EnvioController::class, 'enviarOrcamento']);

        Route::get('envios', [EnvioController::class, 'index']);
        Route::post('envios/{envio}/reenviar', [EnvioController::class, 'reenviar']);

        Route::apiResource('lancamentos', LancamentoController::class)->except('show')->parameters(['lancamentos' => 'lancamento']);
        Route::post('lancamentos/{lancamento}/baixar', [LancamentoController::class, 'baixar']);

        Route::get('agendamentos/conflito', [AgendamentoController::class, 'conflito']);
        Route::apiResource('agendamentos', AgendamentoController::class)->except('show')->parameters(['agendamentos' => 'agendamento']);

        Route::get('funcionarios', [FuncionarioController::class, 'index']);

        Route::get('relatorios', [RelatorioController::class, 'index']);
        Route::post('relatorios', [RelatorioController::class, 'store'])->middleware('throttle:10,1');
        Route::get('relatorios/{relatorio}', [RelatorioController::class, 'show']);
        Route::get('relatorios/{relatorio}/download', [RelatorioController::class, 'download']);
    });

    // ---------- somente administrador ----------
    Route::middleware('perfil:admin')->group(function () {
        Route::apiResource('funcionarios', FuncionarioController::class)->except(['index', 'show'])->parameters(['funcionarios' => 'funcionario']);
        Route::apiResource('usuarios', UsuarioController::class)->except('show')->parameters(['usuarios' => 'usuario']);

        Route::put('configuracoes', [ConfiguracaoController::class, 'update']);
        Route::post('configuracoes/logo', [ConfiguracaoController::class, 'enviarLogo']);
        Route::delete('configuracoes/logo', [ConfiguracaoController::class, 'removerLogo']);

        Route::get('backups', [BackupController::class, 'index']);
        Route::post('backups', [BackupController::class, 'store'])->middleware('throttle:3,1');
        Route::get('backups/{nome}', [BackupController::class, 'download'])->where('nome', '[A-Za-z0-9.\-]+');
    });
});
