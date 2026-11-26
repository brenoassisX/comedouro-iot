<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Converte "quantos gramas liberar" em "quantas aberturas rápidas do servo".
 *
 * MODELO ATUAL: toda abertura é FIXA em 30° - abre, segura um instante e fecha na hora.
 *  - Quanto cai em uma abertura de 30° = g90 / 3 (a calibração é em gramas a 90°; padrão 56,3 g -> ~18,8 g por abertura,
 *    antes da medição). O g90 é interpolado pelo nível do reservatório (vazio -> cheio).
 *  - Pedido maior = MAIS aberturas de 30° (arredondado para o número mais próximo, mínimo 1, máximo 20).
 *
 * Calibração atual (medida na prática): 1 abertura de 30° = 20 g  ->  g90 = 60.
 * Exemplos:  20 g -> 1 abertura | 40 g -> 2 aberturas | 60 g -> 3 aberturas
 * O ESP32 espera 6 s entre uma abertura e a seguinte (INTERVALO_ABERTURAS_MS no sketch).
 *
 * A quantidade real é sempre múltiplo dos gramas de uma abertura (20 g). Ajuste a calibração em Dispositivos > Editar
 * (medindo quanto cai de verdade) ou os gramas de cada porção em Alimentação > Tamanhos das porções.
 * O ESP32 usa as mesmas constantes (ANGULO_MIN/ANGULO_MAX/PULSOS_MAX no sketch).
 */
final class PlanoAbertura
{
    public const ANGULO_MIN   = 8;
    public const ANGULO_MAX   = 90;
    public const ANGULO_FIXO  = 30;   // graus de CADA abertura
    public const MAX_PULSOS   = 20;
    public const NIVEL_PADRAO = 50;   // usado quando ainda não há leitura do ultrassônico

    /** @return array{angulo:int, pulsos:int, g90:float, gramas_por_abertura:float} */
    public static function calcular(float $gramas, ?int $nivelPct, float $g90Vazio, float $g90Cheio): array
    {
        $nivel = max(0, min(100, $nivelPct ?? self::NIVEL_PADRAO));

        // quanto uma abertura de 90° libera com o reservatório neste nível
        $g90 = $g90Vazio + ($g90Cheio - $g90Vazio) * ($nivel / 100);
        if ($g90 <= 0) {
            $g90 = 60.0;   // calibração inválida: valor seguro
        }

        $porAbertura = $g90 * self::ANGULO_FIXO / self::ANGULO_MAX;   // gramas em uma abertura de 30°
        $pulsos      = max(1, min(self::MAX_PULSOS, (int) round($gramas / $porAbertura)));

        return [
            'angulo'              => self::ANGULO_FIXO,
            'pulsos'              => $pulsos,
            'g90'                 => round($g90, 1),
            'gramas_por_abertura' => round($porAbertura, 1),
        ];
    }
}
