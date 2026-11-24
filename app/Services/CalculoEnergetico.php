<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Estimativa da necessidade energética diária de cães e gatos e da porção por refeição.
 *
 * PASSO A PASSO (é a mesma conta usada por veterinários, simplificada):
 *
 *  1) RER (energia em repouso)   =  70 × peso(kg) ^ 0,75         [kcal/dia]
 *  2) MER (necessidade diária)   =  RER × FATOR                   [kcal/dia]
 *       O FATOR depende da espécie e da fase da vida:
 *
 *       Fase                     Cão    Gato
 *       filhote até 4 meses      3,0    2,5
 *       filhote 4 a 12 meses     2,0    2,0
 *       adulto castrado          1,6    1,2     <- usado como padrão (o formulário não pergunta castração)
 *       sênior                   1,4    1,1
 *
 *       "Sênior" começa em: gato 10 anos; cão pequeno 10, médio 8, grande 6 anos.
 *  3) Gramas de ração por dia    =  MER ÷ kcal por grama da ração   (KCAL_POR_GRAMA_RACAO)
 *  4) Refeições por dia          =  4 (até 4 meses), 3 (4 a 12 meses), 2 (a partir de 1 ano)
 *  5) Porção por refeição        =  gramas por dia ÷ refeições
 *
 * É uma ESTIMATIVA. Não considera castração, nível de atividade, doenças, gestação etc.
 */
final class CalculoEnergetico
{
    /** Ração seca típica: ~3.600 kcal por kg. Ajuste se usar uma ração diferente (veja o rótulo). */
    public const KCAL_POR_GRAMA_RACAO = 3.6;

    /**
     * @return array{rer:int,fator:float,fase:string,kcal_dia:int,refeicoes:int,g_dia:int,g_refeicao:int,kcal_por_grama:float}|null
     *         null quando não há peso válido (sem peso não dá para calcular).
     */
    public static function calcular(string $especie, ?float $pesoKg, ?int $idadeMeses, string $porte): ?array
    {
        if ($pesoKg === null || $pesoKg <= 0) {
            return null;
        }

        $gato   = $especie === 'gato';
        $meses  = $idadeMeses ?? 24; // sem idade informada: trata como adulto

        // 1) energia em repouso
        $rer = 70 * ($pesoKg ** 0.75);

        // 2) fase da vida -> fator e número de refeições
        $mesesSenior = $gato ? 120 : (['pequeno' => 120, 'medio' => 96, 'grande' => 72][$porte] ?? 96);

        if ($meses < 4) {
            $fase = 'filhote (até 4 meses)';
            $fator = $gato ? 2.5 : 3.0;
            $refeicoes = 4;
        } elseif ($meses < 12) {
            $fase = 'filhote (4 a 12 meses)';
            $fator = 2.0;
            $refeicoes = 3;
        } elseif ($meses >= $mesesSenior) {
            $fase = 'sênior';
            $fator = $gato ? 1.1 : 1.4;
            $refeicoes = 2;
        } else {
            $fase = 'adulto castrado';
            $fator = $gato ? 1.2 : 1.6;
            $refeicoes = 2;
        }

        // 3, 4 e 5) kcal -> gramas -> porção
        $kcalDia   = (int) round($rer * $fator);
        $gramasDia = $kcalDia / self::KCAL_POR_GRAMA_RACAO;

        return [
            'rer'            => (int) round($rer),
            'fator'          => $fator,
            'fase'           => $fase,
            'kcal_dia'       => $kcalDia,
            'refeicoes'      => $refeicoes,
            'g_dia'          => (int) round($gramasDia),
            'g_refeicao'     => max(1, (int) round($gramasDia / $refeicoes)),
            'kcal_por_grama' => self::KCAL_POR_GRAMA_RACAO,
        ];
    }
}
