<?php

namespace App\Http\Controllers\Api;

use App\Enums\StatusOS;
use App\Http\Controllers\Controller;
use App\Models\Agendamento;
use App\Models\Lancamento;
use App\Models\Orcamento;
use App\Models\OrdemServico;
use App\Models\Peca;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $inicioMes = today()->startOfMonth()->toDateString();
        $fimMes = today()->endOfMonth()->toDateString();

        $abertas = OrdemServico::with(['cliente:id,nome', 'veiculo:id,placa,modelo', 'itens'])
            ->whereIn('status', ['orcamento', 'aprovada', 'em_andamento', 'aguardando_peca'])
            ->orderBy('data_previsao')->get();

        $concluidasMes = OrdemServico::with('itens')
            ->whereIn('status', [StatusOS::Concluida->value, StatusOS::Entregue->value])
            ->whereBetween('data_conclusao', [$inicioMes, $fimMes])->get();

        $recebidoMes = Lancamento::where('tipo', 'receita')->whereBetween('pago_em', [$inicioMes, $fimMes])->sum('valor');
        $pagoMes = Lancamento::where('tipo', 'despesa')->whereBetween('pago_em', [$inicioMes, $fimMes])->sum('valor');

        $orcAguardando = Orcamento::with('itens')->whereIn('status', ['rascunho', 'enviado'])->get();

        return response()->json([
            'os_abertas' => $abertas->count(),
            'os_atrasadas' => $abertas->filter(fn ($o) => $o->data_previsao->lt(today()))->count(),
            'prontas_entrega' => OrdemServico::where('status', StatusOS::Concluida->value)->count(),
            'faturamento_mes' => round((float) $recebidoMes, 2),
            'despesas_mes' => round((float) $pagoMes, 2),
            'ticket_medio_mes' => $concluidasMes->count() ? round($concluidasMes->sum(fn ($o) => $o->total()) / $concluidasMes->count(), 2) : 0,
            'concluidas_mes' => $concluidasMes->count(),
            'a_receber' => round((float) Lancamento::where('tipo', 'receita')->whereNull('pago_em')->sum('valor'), 2),
            'orcamentos_aguardando' => $orcAguardando->count(),
            'orcamentos_aguardando_valor' => round($orcAguardando->sum(fn ($o) => $o->total()), 2),
            'ordens_abertas' => $abertas->take(7)->values(),
            'agenda' => Agendamento::with(['cliente:id,nome', 'veiculo:id,placa,modelo'])
                ->whereBetween('data', [today()->toDateString(), today()->addDays(7)->toDateString()])
                ->whereIn('status', ['agendado', 'confirmado'])
                ->orderBy('data')->orderBy('hora')->limit(7)->get(),
            'estoque_baixo' => Peca::query()->estoqueBaixo()->orderBy('descricao')->get(),
            'contas_vencidas' => Lancamento::query()->vencidos()->orderBy('vencimento')->get(),
        ]);
    }
}
