<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Historico;
use App\Models\Pet;
use DateTimeImmutable;
use PDOException;
use RuntimeException;

/**
 * Relatórios de alimentação por pet, em 3 períodos: semanal, mensal e personalizado.
 *
 * Rotas (public/index.php):
 *   GET /relatorios      -> index()  tela com totais e gráfico
 *   GET /relatorios/pdf  -> pdf()    versão para impressão (o navegador salva como PDF)
 *
 * Parâmetros da URL (todos opcionais):
 *   pet=ID  periodo=semanal|mensal|personalizado
 *   data=AAAA-MM-DD (semanal: a semana que contém esta data)   mes=AAAA-MM (mensal)
 *   de=AAAA-MM-DD&ate=AAAA-MM-DD (personalizado, no máximo 92 dias)
 *
 * O QUE É CONTADO: refeições em historico com status 'enviado' ou 'concluido' (o ESP32 já recebeu).
 *
 * POSSÍVEIS SITUAÇÕES:
 *  - Gráfico vazio / "Nenhuma refeição" -> não há registros do pet no período (ou o pet_id do
 *    histórico não é o deste pet). Faça um "Liberar agora" e sincronize o ESP32 para gerar dados.
 *  - Datas inválidas na URL -> são ignoradas e valem os padrões (semana/mês atual, últimos 7 dias).
 *  - "Unknown column 'enviado_em'" -> tabela historico diferente do schema.sql.
 */
class RelatorioController
{
    private const DIAS_SEMANA = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sab', 7 => 'Dom'];

    // ==================================================================
    // GET /relatorios
    // ==================================================================
    public function index(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        extract($this->montarRelatorio());
        require BASE_PATH . '/app/Views/relatorios.php';
    }

    // ==================================================================
    // GET /relatorios/pdf  ->  página limpa que abre a janela de impressão
    // ==================================================================
    public function pdf(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            header('Location: /login');
            exit;
        }

        extract($this->montarRelatorio());
        require BASE_PATH . '/app/Views/relatorio_pdf.php';
    }

    // ==================================================================
    // Funções de apoio
    // ==================================================================

    /** Calcula tudo que as duas telas precisam. Devolve um array cujas chaves viram variáveis da View. */
    private function montarRelatorio(): array
    {
        $erro   = '';
        $pets   = [];
        $petAtual = null;
        $resumo = [];

        // ---- Período ----
        $periodo = $this->param('periodo');
        if (!in_array($periodo, ['semanal', 'mensal', 'personalizado'], true)) {
            $periodo = 'semanal';
        }
        $hoje = new DateTimeImmutable('today');

        if ($periodo === 'mensal') {
            $mes    = $this->lerData($this->param('mes') . '-01') ?? $hoje;
            $inicio = $mes->modify('first day of this month');
            $fim    = $mes->modify('last day of this month');
        } elseif ($periodo === 'personalizado') {
            $de  = $this->lerData($this->param('de')) ?? $hoje->modify('-6 days');
            $ate = $this->lerData($this->param('ate')) ?? $hoje;
            if ($ate < $de) {
                [$de, $ate] = [$ate, $de];
            }
            if ($de->diff($ate)->days > 91) {
                $ate = $de->modify('+91 days');
            }
            $inicio = $de;
            $fim    = $ate;
        } else {
            $ref    = $this->lerData($this->param('data')) ?? $hoje;
            $inicio = $ref->modify('monday this week');
            $fim    = $inicio->modify('+6 days');
        }

        // ---- Pets e dados ----
        try {
            $pets = Pet::listarPorUsuario((int) $_SESSION['usuario_id']);

            $pedido = (int) $this->param('pet');
            $petAtual = $pets[0] ?? null;
            foreach ($pets as $p) {
                if ((int) $p['id'] === $pedido) {
                    $petAtual = $p;
                }
            }

            if ($petAtual !== null) {
                $resumo = Historico::resumoPorDia(
                    (int) $petAtual['id'],
                    $inicio->format('Y-m-d') . ' 00:00:00',
                    $fim->modify('+1 day')->format('Y-m-d') . ' 00:00:00'
                );
            }
        } catch (PDOException $e) {
            error_log('[relatorios] ' . $e->getMessage());
            $erro = 'Erro no banco de dados: ' . $e->getMessage(); // na apresentação final, mensagem genérica
        } catch (RuntimeException $e) {
            error_log('[relatorios] ' . $e->getMessage());
            $erro = $e->getMessage();
        }

        // ---- Um item por dia do período (dias sem refeição = 0) ----
        $dias   = [];
        $total  = 0;
        $gramas = 0;
        $curto  = $inicio->diff($fim)->days <= 6;   // até 7 dias: rótulo "Seg", "Ter"...; senão o número do dia

        for ($d = $inicio; $d <= $fim; $d = $d->modify('+1 day')) {
            $n  = $resumo[$d->format('Y-m-d')]['refeicoes'] ?? 0;
            $g  = $resumo[$d->format('Y-m-d')]['gramas'] ?? 0;
            $total  += $n;
            $gramas += $g;
            $dias[] = [
                'rotulo'    => $curto ? self::DIAS_SEMANA[(int) $d->format('N')] : $d->format('d'),
                'data'      => $d->format('d/m/Y'),
                'refeicoes' => $n,
                'gramas'    => $g,
            ];
        }

        $media = $total > 0 ? (int) round($gramas / $total) : 0;
        $kg    = $gramas / 1000;

        // ---- Escala do eixo vertical do gráfico ----
        $maior = max(array_column($dias, 'refeicoes') ?: [0]);
        $topo  = max(3, $maior);
        if ($topo > 6) {
            $passo = (int) ceil($topo / 4);
            $topo  = $passo * 4;
            $ticks = [$topo, $passo * 3, $passo * 2, $passo, 0];
        } else {
            $ticks = range($topo, 0);
        }

        // ---- Parâmetros para os links (aba, PDF) ----
        $petId    = $petAtual !== null ? (int) $petAtual['id'] : 0;
        $consulta = ['pet' => $petId, 'periodo' => $periodo];
        if ($periodo === 'mensal') {
            $consulta['mes'] = $inicio->format('Y-m');
        } elseif ($periodo === 'personalizado') {
            $consulta['de']  = $inicio->format('Y-m-d');
            $consulta['ate'] = $fim->format('Y-m-d');
        } else {
            $consulta['data'] = $inicio->format('Y-m-d');
        }

        return [
            'erro' => $erro, 'pets' => $pets, 'petAtual' => $petAtual, 'petId' => $petId,
            'periodo' => $periodo, 'inicio' => $inicio, 'fim' => $fim,
            'rotuloPeriodo' => $inicio->format('d/m/Y') . ' – ' . $fim->format('d/m/Y'),
            'dias' => $dias, 'total' => $total, 'gramas' => $gramas, 'kg' => $kg, 'media' => $media,
            'topo' => $topo, 'ticks' => $ticks, 'consulta' => $consulta,
        ];
    }

    /** Lê um parâmetro da URL como texto (ignora arrays). */
    private function param(string $nome): string
    {
        $v = $_GET[$nome] ?? '';
        return is_string($v) ? $v : '';
    }

    /** 'AAAA-MM-DD' válido -> data; qualquer outra coisa -> null. */
    private function lerData(string $texto): ?DateTimeImmutable
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $texto);
        return ($d !== false && $d->format('Y-m-d') === $texto) ? $d : null;
    }
}
