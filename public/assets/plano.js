/*
 * Estimativa da abertura da comporta, mostrada abaixo do campo "Quantidade (g)".
 * É a MESMA conta do servidor (app/Services/PlanoAbertura.php): a quantidade vira graus de abertura,
 * considerando quanto o reservatório libera a 90° (interpolado pelo nível atual de ração).
 * É só uma estimativa visual: quem manda no motor é o servidor, na hora de liberar.
 *
 * Uso: <input data-plano="id-do-elemento-de-saida" data-nivel="62" data-g90-vazio="100" data-g90-cheio="150">
 */
(function () {
    var ANGULO_MIN = 8, ANGULO_MAX = 90, MAX_PULSOS = 6, NIVEL_PADRAO = 50;

    function plano(gramas, nivel, g90Vazio, g90Cheio) {
        nivel = Math.max(0, Math.min(100, nivel));
        var g90 = g90Vazio + (g90Cheio - g90Vazio) * (nivel / 100);
        if (!(g90 > 0)) { g90 = 100; }

        var pulsos = Math.max(1, Math.min(MAX_PULSOS, Math.ceil(gramas / g90)));
        var angulo = Math.round(ANGULO_MAX * (gramas / pulsos) / g90);
        angulo = Math.max(ANGULO_MIN, Math.min(ANGULO_MAX, angulo));
        return { pulsos: pulsos, angulo: angulo };
    }

    document.querySelectorAll('input[data-plano]').forEach(function (campo) {
        var saida = document.getElementById(campo.dataset.plano);
        if (!saida) { return; }

        function atualizar() {
            var gramas = parseFloat(campo.value);
            if (!(gramas > 0)) { saida.textContent = ''; return; }

            var semLeitura = campo.dataset.nivel === '';
            var nivel = semLeitura ? NIVEL_PADRAO : parseFloat(campo.dataset.nivel);
            var p = plano(gramas, nivel, parseFloat(campo.dataset.g90Vazio), parseFloat(campo.dataset.g90Cheio));

            saida.textContent = 'Comporta (estimativa, reservatório ' +
                (semLeitura ? 'sem leitura, considerando 50%' : 'em ' + Math.round(nivel) + '%') + '): ' +
                (p.pulsos === 1 ? '1 abertura' : p.pulsos + ' aberturas') + ' de ' + p.angulo + '°.';
        }

        campo.addEventListener('input', atualizar);
        atualizar();
    });
})();
