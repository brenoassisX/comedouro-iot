/*
 * PetSync - ESP32 (versão com comporta por SERVO)
 *
 * A cada 10 s envia um POST em JSON para o PHP com MEDIDAS CRUAS:
 *   - distancia_cm  : sensor ultrassônico HC-SR04 (distância até a ração; o servidor converte em %)
 *   - peso_tigela_g : célula de carga + HX711 (peso da tigela; o servidor calcula liberada x consumida)
 * Lê a resposta e, se o servidor mandar "alimentar", abre a comporta os GRAUS pedidos, fecha NA HORA e
 * confirma na sincronização seguinte. Quem converte gramas em graus é o servidor (considera o nível de
 * ração do reservatório); o ESP32 só executa e, com balança, corrige as aberturas seguintes.
 *
 * Aceita VÁRIAS REDES Wi-Fi (ex.: casa e hotspot do celular), cada uma com o seu endereço de servidor.
 *
 * BALANÇA: desativada por padrão (USA_BALANCA = false). O servidor só planeja as aberturas pelo ângulo.
 *
 * PREPARAÇÃO (Arduino IDE):
 *   - Placa: "esp32 by Espressif Systems" (Gerenciador de Placas). WiFi.h e HTTPClient.h já vêm nele.
 *   - Bibliotecas (Gerenciador de Bibliotecas):
 *       "ArduinoJson" (Benoit Blanchon) versão 7.x
 *       "HX711 Arduino Library" (Bogdan Necula)   <- mesmo sem balança, a biblioteca precisa estar instalada
 *       "ESP32Servo" (Kevin Harrington, John K. Bennett)   <- NOVA: controla o servo da comporta
 *   - Monitor Serial: 115200 baud.
 *   - O sketch precisa estar numa pasta com o MESMO nome do arquivo (comedouro_esp32/comedouro_esp32.ino).
 *
 * LIGAÇÕES (ajuste os pinos abaixo se usar outros):
 *   HC-SR04: VCC 5V | GND | TRIG -> GPIO 5 | ECHO -> GPIO 18
 *     ATENÇÃO: o ECHO sai com 5 V e o ESP32 aguenta só 3,3 V. Use divisor de tensão (1 kΩ + 2 kΩ) ou um
 *     sensor de 3,3 V (HC-SR04P, JSN-SR04T).
 *   HX711 (opcional): VCC 3,3V | GND | DT -> GPIO 16 | SCK -> GPIO 17
 *   SERVO (comporta): sinal -> GPIO 25. Alimente o servo com 5 V de FONTE PRÓPRIA (ele puxa corrente demais
 *     para o pino 5V/USB do ESP32) e ligue o GND da fonte ao GND do ESP32. Um capacitor de 470 a 1000 µF
 *     perto do servo evita que o ESP32 reinicie quando a comporta abre.
 */
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <HX711.h>
#include <ESP32Servo.h>

// ===================== CONFIGURE AQUI =====================

// Redes Wi-Fi conhecidas, em ORDEM DE PRIORIDADE (2,4 GHz). Cada uma tem o seu endereço do servidor:
//   - o IP é o do COMPUTADOR que roda o PHP NAQUELA rede (o script scripts\iniciar_teste.ps1 mostra e copia);
//   - o IP muda de rede para rede, por isso cada rede tem a sua própria URL (inclua a porta, ex.: :8080).
struct Rede {
  const char* ssid;
  const char* senha;
  const char* url;
};

const Rede REDES[] = {
    { "y",    "corinthians",    "http://10.137.127.101:8000/api/sincronizar"  },
  { "NOME_DO_HOTSPOT_CELULAR", "SENHA_DO_HOTSPOT_CELULAR", "http://192.168.43.X:8080/api/sincronizar" },
};
const int TOTAL_REDES = sizeof(REDES) / sizeof(REDES[0]);

const unsigned long TEMPO_POR_REDE_MS    = 10000;  // quanto esperar por cada rede antes de tentar a próxima
const unsigned long ESPERA_RECONECTAR_MS = 30000;  // com o Wi-Fi caído, só tenta de novo a cada 30 s
const unsigned long INTERVALO_MS         = 10000;  // sincroniza a cada 10 segundos

// Pinos
const int PINO_TRIG      = 5;
const int PINO_ECHO      = 18;
const int PINO_HX711_DT  = 16;
const int PINO_HX711_SCK = 17;
const int PINO_SERVO     = 13;

// Balança: coloque false se NÃO tiver célula de carga/HX711 (aí só as aberturas planejadas são feitas)
const bool USA_BALANCA = false;
// Fator de calibração da balança (fator = valor cru / peso conhecido; veja o roteiro de teste)
const float FATOR_CALIBRACAO = 420.0;

// Comporta (servo)
const int ANGULO_FECHADO = 0;                      // posição fechada: ajuste se o seu servo fechar em outro ângulo
// CALIBRAÇÃO PELO SKETCH:
//   ANGULO_FORCADO = 0  -> obedece o ângulo que o servidor manda (30°).
//   ANGULO_FORCADO = 25 -> ignora o servidor e abre SEMPRE 25° (o número de aberturas continua o do servidor).
const int ANGULO_FORCADO = 0;
const unsigned long ABERTURA_TESTE_MS = 300;       // tempo aberta nos testes feitos pelo Monitor Serial
const int ANGULO_MIN = 8;                          // mesmos limites do servidor (PlanoAbertura.php)
const int ANGULO_MAX = 90;
const int PULSOS_MAX = 20;                         // até 20 aberturas de 30° por refeição
const int EXTRA_ABERTURAS = 2;                     // com balança, até 2 aberturas além do plano para completar o peso
const unsigned long INTERVALO_ABERTURAS_MS = 6000;   // espera entre uma abertura e a seguinte (poupa o mecanismo)
const unsigned long TEMPO_FECHAR_MS    = 250;      // tempo para o servo chegar ao fechado antes de seguir
const unsigned long ESPERA_ASSENTAR_MS = 1200;     // espera a ração assentar na tigela antes de pesar
const float MARGEM_G = 5.0;                        // margem de erro da balança (igual à do servidor)
// ==========================================================

HX711 balanca;
Servo servo;
bool balancaOk = false;              // true quando o HX711 respondeu no setup()
String macEsp;                       // é o "codigo_mac_esp" cadastrado no sistema
String urlAtual;                     // endereço do servidor da rede em que o ESP32 está conectado
unsigned long ultimoEnvioMs = 0;
unsigned long ultimaTentativaWiFiMs = 0;
int comandoConcluidoId = 0;          // id do último comando executado (avisado na próxima sincronização)

// ---------------------------------------------------------------- Sensores
// Distância em cm do sensor até a ração (mediana de 5 leituras). Devolve -1 se o sensor não leu.
float lerDistanciaCm() {
  float leituras[5];
  int validas = 0;

  for (int i = 0; i < 5; i++) {
    digitalWrite(PINO_TRIG, LOW);
    delayMicroseconds(2);
    digitalWrite(PINO_TRIG, HIGH);
    delayMicroseconds(10);
    digitalWrite(PINO_TRIG, LOW);

    unsigned long duracao = pulseIn(PINO_ECHO, HIGH, 30000UL);   // até 30 ms (~5 m); 0 = sem eco
    if (duracao > 0) {
      leituras[validas++] = duracao * 0.0343f / 2.0f;            // velocidade do som: 0,0343 cm/µs
    }
    delay(30);
  }

  if (validas == 0) return -1;

  for (int i = 1; i < validas; i++) {                            // ordena (insertion sort) para pegar a mediana
    float v = leituras[i];
    int j = i - 1;
    while (j >= 0 && leituras[j] > v) { leituras[j + 1] = leituras[j]; j--; }
    leituras[j + 1] = v;
  }
  return leituras[validas / 2];
}

// Peso atual da tigela em gramas. "ok" = false se não há balança ou o HX711 não respondeu.
float lerPesoGramas(bool &ok) {
  if (!balancaOk || !balanca.is_ready()) {
    ok = false;
    return 0;
  }
  ok = true;
  return balanca.get_units(5);   // média de 5 leituras
}

// ---------------------------------------------------------------- Comporta (servo)
// UMA abertura: abre "graus", segura "ms" e FECHA NA HORA (comando direto ao ângulo fechado, sem etapas:
// o servo vai na velocidade máxima).
void abrirEFechar(int graus, unsigned long ms) {
  servo.write(graus);
  delay(ms);
  servo.write(ANGULO_FECHADO);
  delay(TEMPO_FECHAR_MS);
}

// Libera "gramas" de ração com o plano do servidor: "pulsos" aberturas de "angulo" graus, "aberturaMs" cada.
//   SEM balança: faz exatamente as aberturas planejadas.
//   COM balança: depois de cada abertura confere o peso. Para quando a tigela ganhou o pedido; se faltar,
//   recalcula o ângulo da próxima abertura pelo que a anterior realmente liberou (gramas por grau).
void abrirComporta(int gramas, int angulo, int pulsos, unsigned long aberturaMs) {
  if (ANGULO_FORCADO > 0) angulo = ANGULO_FORCADO;
  angulo     = constrain(angulo, ANGULO_MIN, ANGULO_MAX);
  pulsos     = constrain(pulsos, 1, PULSOS_MAX);
  aberturaMs = constrain(aberturaMs, 100UL, 3000UL);

  bool comBalanca = false;
  float pesoInicial = lerPesoGramas(comBalanca);
  float pesoAnterior = pesoInicial;

  Serial.printf(">>> ABRIR COMPORTA: %d g | plano: %d abertura(s) de %d graus por %lu ms (%s)\n",
                gramas, pulsos, angulo, aberturaMs, comBalanca ? "com balanca" : "sem balanca");

  int maxAberturas = comBalanca ? pulsos + EXTRA_ABERTURAS : pulsos;
  float gramasPorGrau = 0;   // aprendido na prática (só com balança)

  for (int i = 0; i < maxAberturas; i++) {
    if (i > 0) {   // intervalo entre aberturas: não força o mecanismo
      Serial.printf("  aguardando %lu s para a proxima abertura...\n", INTERVALO_ABERTURAS_MS / 1000);
      delay(INTERVALO_ABERTURAS_MS);
    }
    int anguloAtual = angulo;

    if (comBalanca && i > 0 && gramasPorGrau > 0.2f) {
      float restante = gramas - (pesoAnterior - pesoInicial);
      anguloAtual = constrain((int)round(restante / gramasPorGrau), ANGULO_MIN, ANGULO_MAX);
    }

    abrirEFechar(anguloAtual, aberturaMs);
    if (comBalanca) delay(ESPERA_ASSENTAR_MS);   // sem balança não há o que esperar: segue para a próxima abertura

    if (comBalanca) {
      bool ok;
      float pesoAgora = lerPesoGramas(ok);
      if (ok) {
        float liberadoAbertura = pesoAgora - pesoAnterior;
        float ganhoTotal = pesoAgora - pesoInicial;
        if (liberadoAbertura > 1.0f) gramasPorGrau = liberadoAbertura / anguloAtual;
        pesoAnterior = pesoAgora;

        Serial.printf("  abertura %d: %d graus -> +%.0f g (total %.0f de %d g)\n",
                      i + 1, anguloAtual, liberadoAbertura, ganhoTotal, gramas);

        if (ganhoTotal >= gramas - MARGEM_G) break;   // já caiu o pedido
      }
    }
  }

  servo.write(ANGULO_FECHADO);   // garantia: comporta fechada ao terminar
}

// ---------------------------------------------------------------- Wi-Fi e sincronização
// Tenta as redes da lista, na ordem. Ao conectar, guarda a URL do servidor DAQUELA rede em urlAtual.
bool conectarWiFi() {
  WiFi.mode(WIFI_STA);

  for (int i = 0; i < TOTAL_REDES; i++) {
    Serial.printf("Tentando a rede \"%s\"", REDES[i].ssid);
    WiFi.disconnect();
    delay(100);
    WiFi.begin(REDES[i].ssid, REDES[i].senha);

    unsigned long inicio = millis();
    while (WiFi.status() != WL_CONNECTED && millis() - inicio < TEMPO_POR_REDE_MS) {
      delay(500);
      Serial.print(".");
    }

    if (WiFi.status() == WL_CONNECTED) {
      urlAtual = REDES[i].url;
      Serial.printf("\nConectado a \"%s\"! IP do ESP32: %s\n", REDES[i].ssid, WiFi.localIP().toString().c_str());
      Serial.printf("Servidor desta rede: %s\n", urlAtual.c_str());
      return true;
    }
    Serial.println(" sem sucesso.");
  }

  // Causas comuns: nome/senha errados, rede de 5 GHz (o ESP32 só usa 2,4 GHz) ou rede fora de alcance
  Serial.println("Nenhuma rede da lista disponivel. Confira nomes, senhas e se a rede e 2,4 GHz.");
  return false;
}

void sincronizar() {
  if (WiFi.status() != WL_CONNECTED) {
    // Sem Wi-Fi: procura uma rede conhecida, mas no máximo a cada 30 s (cada tentativa demora alguns segundos)
    if (millis() - ultimaTentativaWiFiMs >= ESPERA_RECONECTAR_MS) {
      ultimaTentativaWiFiMs = millis();
      Serial.println("Sem Wi-Fi. Procurando uma rede conhecida...");
      conectarWiFi();
    }
    return;
  }

  // ---------- 1) Lê os sensores e monta o JSON ----------
  JsonDocument envio;
  envio["codigo_mac_esp"] = macEsp;

  float distancia = lerDistanciaCm();
  if (distancia > 0) {
    envio["distancia_cm"] = roundf(distancia * 10) / 10;   // 1 casa decimal
  }                                                        // sem leitura: omite o campo (o servidor mantém o nível anterior)

  bool pesoOk;
  float peso = lerPesoGramas(pesoOk);
  if (pesoOk) {
    envio["peso_tigela_g"] = roundf(peso * 10) / 10;
  }

  if (comandoConcluidoId > 0) {
    envio["comando_concluido"] = comandoConcluidoId;       // marca a liberação anterior como concluída
  }

  String corpo;
  serializeJson(envio, corpo);

  // ---------- 2) Envia o POST ----------
  WiFiClient cliente;
  HTTPClient http;
  http.begin(cliente, urlAtual);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(5000);

  Serial.printf("\nEnviando para %s: %s\n", urlAtual.c_str(), corpo.c_str());
  int codigoHttp = http.POST(corpo);

  if (codigoHttp <= 0) {
    // -1 = servidor inacessivel (IP errado, PHP iniciado so em localhost, firewall bloqueando a porta)
    // -11 = tempo esgotado esperando a resposta
    Serial.printf("Falha de conexao (%d): %s\n", codigoHttp, http.errorToString(codigoHttp).c_str());
    http.end();
    return;
  }

  String resposta = http.getString();
  http.end();
  Serial.printf("HTTP %d -> %s\n", codigoHttp, resposta.c_str());

  // ---------- 3) Lê a resposta ----------
  JsonDocument recebido;
  DeserializationError erro = deserializeJson(recebido, resposta);
  if (erro) {
    Serial.printf("Resposta nao e JSON valido: %s\n", erro.c_str());
    return;
  }

  const char* status = recebido["status"] | "erro";
  if (strcmp(status, "ok") != 0) {
    // 404 = MAC nao cadastrado | 422 = dado invalido | 500 = erro no servidor/banco
    Serial.printf("Servidor recusou: %s\n", recebido["mensagem"] | "sem mensagem");
    return;
  }

  comandoConcluidoId = 0;   // o servidor ja registrou o aviso anterior
  Serial.printf("Nivel de racao calculado pelo servidor: %d%%\n", (int)(recebido["nivel"] | -1));

  // ---------- 4) Decide o que fazer ----------
  const char* comando = recebido["comando"] | "nenhum";
  int gramas      = recebido["gramas"] | 0;         // já descontado o que havia na tigela (regra de completar)
  int angulo      = recebido["angulo"] | 45;        // graus de abertura de CADA abertura
  int pulsos      = recebido["pulsos"] | 1;         // quantas aberturas
  int aberturaMs  = recebido["abertura_ms"] | 300;  // quanto tempo fica aberta em cada uma
  int idComando   = recebido["id"] | 0;

  if (strcmp(comando, "alimentar") == 0 && gramas > 0) {
    Serial.printf("Servidor mandou ALIMENTAR %d g (alvo %d g)\n", gramas, (int)(recebido["alvo"] | 0));
    abrirComporta(gramas, angulo, pulsos, aberturaMs);
    comandoConcluidoId = idComando;   // avisa o servidor (junto com o peso da tigela) na proxima sincronizacao
  } else if (strcmp(recebido["motivo"] | "", "tigela_cheia") == 0) {
    Serial.println("Pedido dispensado: a tigela ja tem a quantidade pedida.");
  } else {
    Serial.println("Nenhum comando pendente: comporta fechada.");
  }
}

void setup() {
  // Primeira coisa: servo no ângulo FECHADO ao ligar/reiniciar
  ESP32PWM::allocateTimer(0);
  servo.setPeriodHertz(50);
  servo.attach(PINO_SERVO, 500, 2400);
  servo.write(ANGULO_FECHADO);

  Serial.begin(115200);
  delay(500);

  pinMode(PINO_TRIG, OUTPUT);
  pinMode(PINO_ECHO, INPUT);

  // Balança: com a tigela VAZIA e no lugar, o tare define o "zero".
  if (USA_BALANCA) {
    balanca.begin(PINO_HX711_DT, PINO_HX711_SCK);
    balanca.set_scale(FATOR_CALIBRACAO);
    if (balanca.wait_ready_timeout(2000)) {   // sem isso o programa travaria se o HX711 estivesse desligado
      balanca.tare();
      balancaOk = true;
      Serial.println("Balanca pronta (tare feito com a tigela vazia).");
    } else {
      Serial.println("HX711 nao respondeu: seguindo SEM balanca (confira DT/SCK e a alimentacao).");
    }
  }

  conectarWiFi();
  ultimaTentativaWiFiMs = millis();

  macEsp = WiFi.macAddress();   // so e confiavel depois de WiFi.mode()/WiFi.begin()
  Serial.printf("MAC deste ESP32 (cadastre no sistema como codigo_mac_esp): %s\n", macEsp.c_str());

  ultimoEnvioMs = millis() - INTERVALO_MS;   // faz a primeira sincronizacao logo ao ligar
}

// TESTE PELO MONITOR SERIAL (115200, "Nova linha"): digite um número de 1 a 90 e Enter -> o servo abre
// esse ângulo UMA vez e fecha. Pese a ração que caiu e anote os gramas por ângulo.
void testeSerial() {
  if (!Serial.available()) return;
  int graus = Serial.parseInt();
  while (Serial.available()) Serial.read();   // limpa o resto da linha
  if (graus >= 1 && graus <= ANGULO_MAX) {
    Serial.printf("TESTE: abrindo %d graus por %lu ms...\n", graus, ABERTURA_TESTE_MS);
    abrirEFechar(graus, ABERTURA_TESTE_MS);
    Serial.println("TESTE: fechado. Pese a racao que caiu.");
  } else {
    Serial.println("Digite um angulo de 1 a 90.");
  }
}

void loop() {
  testeSerial();
  // millis() em vez de delay(): o ESP32 continua livre para outras tarefas entre os envios
  if (millis() - ultimoEnvioMs >= INTERVALO_MS) {
    ultimoEnvioMs = millis();
    sincronizar();
  }
}
