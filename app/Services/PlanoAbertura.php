<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Converte "quantos gramas liberar" em "quantos graus abrir a comporta (servo)".
 *
 * MODELO (simples e calibrável):
 *  - Uma ABERTURA = abre X graus, segura por um tempo curto e fecha na hora.
 *  - A 90° uma abertura libera entre g90Vazio (reservatório quase vazio) e g90Cheio (reservatório cheio).
 *    Com mais ração em cima, a pressão é maior e cai mais: interpolamos pelo nível atual (%).
 *  - Abaixo de 90° a quantidade é proporcional ao ângulo (aproximação linear).
 *  - Se o pedido passa do que uma abertura de 90° libera, divide em várias aberturas iguais (até 6).
 *
 * Exemplos (g90 vazio = 100 g, cheio = 150 g):
 *   90 g com reservatório cheio (100%)  -> g90 = 150 -> 1 abertura de 54°
 *   90 g com reservatório em 20%        -> g90 = 110 -> 1 abertura de 74°
 *   300 g com reservatório em 50%       -> g90 = 125 -> 3 aberturas de 72° (100 g cada)
 *
 * LIMITES: ângulo mínimo de 8° (abaixo disso a comporta quase não abre) - pedidos muito pequenos podem
 * liberar um pouco mais que o pedido. Máximo de 6 aberturas por refeição.
 * O ESP32 usa as mesmas constantes (ANGULO_MIN/ANGULO_MAX/PULSOS_MAX no sketch e em public/assets/plano.js).
 */
final class PlanoAbertura
{
    public const ANGULO_MIN   = 8;
    public const ANGULO_MAX   = 90;
    public const MAX_PULSOS   = 6;
    public const NIVEL_PADRAO = 50;   // usado quando ainda não há leitura do ultrassônico

    /** @return array{angulo:int, pulsos:int, g90:float, gramas_por_abertura:float} */
    public static function calcular(float $gramas, ?int $nivelPct, float $g90Vazio, float $g90Cheio): array
    {
        $nivel = max(0, min(100, $nivelPct ?? self::NIVEL_PADRAO));

        // quanto uma abertura de 90° libera com o reservatório neste nível
        $g90 = $g90Vazio + ($g90Cheio - $g90Vazio) * ($nivel / 100);
        if ($g90 <= 0) {
            $g90 = 100.0;   // calibração inválida: valor seguro
        }

        $pulsos = max(1, min(self::MAX_PULSOS, (int) ceil($gramas / $g90)));

        $porAbertura = $gramas / $pulsos;
        $angulo      = (int) round(self::ANGULO_MAX * $porAbertura / $g90);
        $angulo      = max(self::ANGULO_MIN, min(self::ANGULO_MAX, $angulo));

        return [
            'angulo'              => $angulo,
            'pulsos'              => $pulsos,
            'g90'                 => round($g90, 1),
            'gramas_por_abertura' => round($porAbertura, 1),
        ];
    }
}
