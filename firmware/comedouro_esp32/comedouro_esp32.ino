/*
 * PetFeeder - ESP32
 *
 * A cada 10 s envia um POST em JSON para o PHP com MEDIDAS CRUAS:
 *   - distancia_cm  : sensor ultrassônico HC-SR04 (distância até a ração; o servidor converte em %)
 *   - peso_tigela_g : célula de carga + HX711 (peso da tigela; o servidor calcula liberada x consumida)
 * Lê a resposta e, se o servidor mandar "alimentar", gira o motor e confirma na sincronização seguinte.
 *
 * PREPARAÇÃO (Arduino IDE):
 *   - Placa: "esp32 by Espressif Systems" (Gerenciador de Placas). WiFi.h e HTTPClient.h já vêm nele.
 *   - Bibliotecas (Gerenciador de Bibliotecas):
 *       "ArduinoJson" (Benoit Blanchon) versão 7.x
 *       "HX711 Arduino Library" (Bogdan Necula)   <- mesmo sem balança, a biblioteca precisa estar instalada
 *   - Monitor Serial: 115200 baud.
 *   - O sketch precisa estar numa pasta com o MESMO nome do arquivo (comedouro_esp32/comedouro_esp32.ino).
 *
 * LIGAÇÕES (ajuste os pinos abaixo se usar outros):
 *   HC-SR04: VCC 5V | GND | TRIG -> GPIO 5 | ECHO -> GPIO 18
 *     ATENÇÃO: o ECHO sai com 5 V e o ESP32 aguenta só 3,3 V. Use divisor de tensão (1 kΩ + 2 kΩ) ou um
 *     sensor de 3,3 V (HC-SR04P, JSN-SR04T).
 *   HX711 (opcional): VCC 3,3V | GND | DT -> GPIO 16 | SCK -> GPIO 17
 *   MOTOR: o ESP32 NUNCA alimenta o motor direto. GPIO 25 -> entrada do driver / módulo relé / MOSFET,
 *     e o motor usa fonte própria (GND em comum com o ESP32). Em motor DC, use diodo de proteção.
 *     Se o seu módulo liga com nível BAIXO (relé "active low"), troque MOTOR_LIGADO/MOTOR_DESLIGADO abaixo.
 *     Se a sua comporta usa servo, veja o comentário em ligarMotor()/desligarMotor().
 */
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <HX711.h>

// ===================== CONFIGURE AQUI =====================
const char* WIFI_SSID       = "NOME_DA_SUA_REDE";
const char* WIFI_SENHA      = "SENHA_DA_REDE";

// IP do COMPUTADOR que roda o PHP (ipconfig no Windows ou "ip a" no Linux).
// ESP32 e computador precisam estar na MESMA rede Wi-Fi (2,4 GHz).
const char* URL_SINCRONIZAR = "http://192.168.1.X:8000/api/sincronizar";

const unsigned long INTERVALO_MS = 10000;   // 10 segundos

// Pinos
const int PINO_TRIG      = 5;
const int PINO_ECHO      = 18;
const int PINO_HX711_DT  = 16;
const int PINO_HX711_SCK = 17;
const int PINO_MOTOR     = 25;
const int MOTOR_LIGADO    = HIGH;   // troque para LOW se o módulo for "active low"
const int MOTOR_DESLIGADO = LOW;

// Balança: coloque false se NÃO tiver célula de carga/HX711 (aí o motor funciona só por tempo)
const bool USA_BALANCA = true;
// Fator de calibração da balança. Como achar:
//   1) Deixe a tigela vazia e ligue o ESP32 (ele faz o "tare" = zera o peso).
//   2) Coloque um peso conhecido na tigela (ex.: 200 g) e veja o valor no Monitor Serial.
//   3) Ajuste o fator até a leitura bater com a realidade (fator = valor cru / peso conhecido).
const float FATOR_CALIBRACAO = 420.0;

// Motor: quantos gramas de ração caem por segundo com o motor ligado.
// Como achar: deixe 20 e peça um "Liberar agora" de 50 g; pese o que caiu e ajuste:
//   novo valor = valor atual x (50 / gramas que realmente caíram)
const float GRAMAS_POR_SEGUNDO = 20.0;
// Trava de segurança: o motor nunca fica ligado mais que isso, aconteça o que acontecer
const unsigned long MOTOR_MAX_MS = 20000;
// ==========================================================

HX711 balanca;
bool balancaOk = false;              // true quando o HX711 respondeu no setup()
String macEsp;                       // é o "codigo_mac_esp" cadastrado no sistema
unsigned long ultimoEnvioMs = 0;
int comandoConcluidoId = 0;          // id do último comando executado (avisado na próxima sincronização)

// ---------------------------------------------------------------- Motor
void ligarMotor() {
  digitalWrite(PINO_MOTOR, MOTOR_LIGADO);
  // Servo (biblioteca ESP32Servo): servo.write(ANGULO_ABERTO);
}

void desligarMotor() {
  digitalWrite(PINO_MOTOR, MOTOR_DESLIGADO);
  // Servo (biblioteca ESP32Servo): servo.write(ANGULO_FECHADO);
}

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

// ---------------------------------------------------------------- Liberar ração
// Gira o motor para despejar "gramas" de ração.
//   COM balança: gira até a tigela GANHAR o peso pedido (ou até o limite de segurança).
//   SEM balança: gira pelo tempo calculado com GRAMAS_POR_SEGUNDO.
void abrirComporta(int gramas) {
  unsigned long tempoEstimadoMs = (unsigned long)(gramas / GRAMAS_POR_SEGUNDO * 1000.0f);
  if (tempoEstimadoMs > MOTOR_MAX_MS) tempoEstimadoMs = MOTOR_MAX_MS;

  unsigned long limiteMs = tempoEstimadoMs * 3 / 2 + 500;        // com balança: 50% de folga sobre o estimado
  if (limiteMs > MOTOR_MAX_MS) limiteMs = MOTOR_MAX_MS;

  bool comBalanca = false;
  float pesoInicial = lerPesoGramas(comBalanca);

  Serial.printf(">>> ABRIR COMPORTA: despejar %d g (%s)\n", gramas, comBalanca ? "controle pela balanca" : "controle por tempo");

  unsigned long inicio = millis();
  ligarMotor();

  while (true) {
    unsigned long decorrido = millis() - inicio;

    if (comBalanca) {
      if (balanca.is_ready() && (balanca.get_units(1) - pesoInicial) >= gramas) break;   // já caiu o pedido
      if (decorrido >= limiteMs) {
        Serial.println("Limite de seguranca atingido (confira ração no reservatório e o motor).");
        break;
      }
    } else if (decorrido >= tempoEstimadoMs) {
      break;
    }
    delay(10);
  }

  desligarMotor();
  Serial.printf("Motor ligado por %lu ms\n", millis() - inicio);
  delay(2000);   // espera a ração assentar na tigela antes da próxima leitura de peso
}

// ---------------------------------------------------------------- Wi-Fi e sincronização
void conectarWiFi() {
  Serial.printf("Conectando ao Wi-Fi \"%s\"", WIFI_SSID);
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_SENHA);

  unsigned long inicio = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - inicio < 20000) {
    delay(500);
    Serial.print(".");
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.printf("\nConectado! IP do ESP32: %s\n", WiFi.localIP().toString().c_str());
  } else {
    // Causas comuns: nome/senha errados ou rede de 5 GHz (o ESP32 só usa 2,4 GHz)
    Serial.println("\nNao conectou. Confira SSID, senha e se a rede e 2,4 GHz.");
  }
}

void sincronizar() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("Sem Wi-Fi. Tentando reconectar...");
    WiFi.reconnect();
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
  http.begin(cliente, URL_SINCRONIZAR);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(5000);

  Serial.printf("\nEnviando: %s\n", corpo.c_str());
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
  int gramas    = recebido["gramas"] | 0;      // já descontado o que havia na tigela (regra de completar)
  int idComando = recebido["id"] | 0;

  if (strcmp(comando, "alimentar") == 0 && gramas > 0) {
    Serial.printf("Servidor mandou ALIMENTAR %d g (alvo %d g)\n", gramas, (int)(recebido["alvo"] | 0));
    abrirComporta(gramas);
    comandoConcluidoId = idComando;   // avisa o servidor (junto com o peso da tigela) na proxima sincronizacao
  } else if (strcmp(recebido["motivo"] | "", "tigela_cheia") == 0) {
    Serial.println("Pedido dispensado: a tigela ja tem a quantidade pedida.");
  } else {
    Serial.println("Nenhum comando pendente: comporta fechada.");
  }
}

void setup() {
  pinMode(PINO_MOTOR, OUTPUT);
  desligarMotor();                    // primeira coisa: garante o motor DESLIGADO ao ligar/reiniciar

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

  macEsp = WiFi.macAddress();   // so e confiavel depois de WiFi.mode()/WiFi.begin()
  Serial.printf("MAC deste ESP32 (cadastre no sistema como codigo_mac_esp): %s\n", macEsp.c_str());

  ultimoEnvioMs = millis() - INTERVALO_MS;   // faz a primeira sincronizacao logo ao ligar
}

void loop() {
  // millis() em vez de delay(): o ESP32 continua livre para outras tarefas entre os envios
  if (millis() - ultimoEnvioMs >= INTERVALO_MS) {
    ultimoEnvioMs = millis();
    sincronizar();
  }
}
