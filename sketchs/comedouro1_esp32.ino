#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <ESP32Servo.h>

// ===================== CONFIGURE AQUI =====================

struct Rede {
  const char* ssid;
  const char* senha;
  const char* url;
};

// Ajuste a URL com o IPv4 do PC na rede ativa e a porta do seu servidor PHP
const Rede REDES[] = {
  { "A07 de Pietro", "987654321", "http://10.241.14.101:8000/api/sincronizar" },
  { "Rosa", "Mariarosa01@", "http://192.168.0.31:8000/api/sincronizar" },
};
const int TOTAL_REDES = sizeof(REDES) / sizeof(REDES[0]);

const unsigned long TEMPO_POR_REDE_MS    = 10000;
const unsigned long ESPERA_RECONECTAR_MS = 30000;
const unsigned long INTERVALO_MS         = 3000;

// Pinos conforme sua fiação atual:
// Sensor: Azul = +5V | Preto = GND | Laranja = Trig (D18) | Vermelho = Echo (D5)
const int PINO_TRIG  = 18; // Laranja -> D18
const int PINO_ECHO  = 5;  // Vermelho -> D5 (use divisor resistivo 5V -> 3.3V no Echo)
const int PINO_SERVO = 13; // Sinal PWM do servomotor MG995 (D13)

// Ângulos do servomotor (ajuste conforme a mecânica da comporta)
const int ANGULO_FECHADO = 0;
const int ANGULO_ABERTO  = 90;

// Dosagem e segurança (teste por tempo, sem balança)
const float GRAMAS_POR_SEGUNDO = 50.0;
const unsigned long MOTOR_MAX_MS = 15000;
// ==========================================================

Servo servoComporta;

String macEsp;
String urlAtual;
unsigned long ultimoEnvioMs = 0;
unsigned long ultimaTentativaWiFiMs = 0;
int comandoConcluidoId = 0;

// ---------------------------------------------------------------- Servo
void garantirServoPronto() {
  if (!servoComporta.attached()) {
    ESP32PWM::allocateTimer(0);
    ESP32PWM::allocateTimer(1);
    ESP32PWM::allocateTimer(2);
    ESP32PWM::allocateTimer(3);

    servoComporta.setPeriodHertz(50);
    servoComporta.attach(PINO_SERVO, 500, 2400);
  }
}

void abrirComportaFisica() {
  garantirServoPronto();
  servoComporta.write(ANGULO_ABERTO);
}

void fecharComportaFisica() {
  garantirServoPronto();
  servoComporta.write(ANGULO_FECHADO);
}

// ---------------------------------------------------------------- Sensor Ultrassônico
float lerDistanciaCm() {
  float leituras[5];
  int validas = 0;

  for (int i = 0; i < 5; i++) {
    digitalWrite(PINO_TRIG, LOW);
    delayMicroseconds(2);
    digitalWrite(PINO_TRIG, HIGH);
    delayMicroseconds(10);
    digitalWrite(PINO_TRIG, LOW);

    unsigned long duracao = pulseIn(PINO_ECHO, HIGH, 30000UL);
    if (duracao > 0) {
      leituras[validas++] = duracao * 0.0343f / 2.0f;
    }
    delay(20);
  }

  if (validas == 0) return -1;

  for (int i = 1; i < validas; i++) {
    float v = leituras[i];
    int j = i - 1;
    while (j >= 0 && leituras[j] > v) {
      leituras[j + 1] = leituras[j];
      j--;
    }
    leituras[j + 1] = v;
  }
  return leituras[validas / 2];
}

// ---------------------------------------------------------------- Liberar ração
void abrirComporta(int gramas) {
  unsigned long tempoEstimadoMs = (unsigned long)((gramas / GRAMAS_POR_SEGUNDO) * 1000.0f);
  if (tempoEstimadoMs < 1000) tempoEstimadoMs = 1000;
  if (tempoEstimadoMs > MOTOR_MAX_MS) tempoEstimadoMs = MOTOR_MAX_MS;

  Serial.printf(">>> ABRIR COMPORTA: despejar %d g (controle por tempo)\n", gramas);

  unsigned long inicio = millis();
  abrirComportaFisica();

  while (millis() - inicio < tempoEstimadoMs) {
    delay(10);
  }

  fecharComportaFisica();
  Serial.printf("Comporta fechada apos %lu ms\n", millis() - inicio);
  delay(800);
}

// ---------------------------------------------------------------- Wi-Fi e sincronização
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

  Serial.println("Nenhuma rede da lista disponivel.");
  return false;
}

void sincronizar() {
  if (WiFi.status() != WL_CONNECTED) {
    if (millis() - ultimaTentativaWiFiMs >= ESPERA_RECONECTAR_MS) {
      ultimaTentativaWiFiMs = millis();
      Serial.println("Sem Wi-Fi. Procurando uma rede conhecida...");
      conectarWiFi();
    }
    return;
  }

  JsonDocument envio;
  envio["codigo_mac_esp"] = macEsp;

  float distancia = lerDistanciaCm();
  Serial.printf("Leitura HC-SR04 (Trig D18 / Echo D5): %.1f cm\n", distancia);
  if (distancia > 0) {
    envio["distancia_cm"] = roundf(distancia * 10) / 10;
  }

  if (comandoConcluidoId > 0) {
    envio["comando_concluido"] = comandoConcluidoId;
  }

  String corpo;
  serializeJson(envio, corpo);

  WiFiClient cliente;
  HTTPClient http;
  http.begin(cliente, urlAtual);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(5000);

  Serial.printf("\nEnviando para %s: %s\n", urlAtual.c_str(), corpo.c_str());
  int codigoHttp = http.POST(corpo);

  if (codigoHttp <= 0) {
    Serial.printf("Falha de conexao (%d): %s\n", codigoHttp, http.errorToString(codigoHttp).c_str());
    http.end();
    return;
  }

  String resposta = http.getString();
  http.end();
  Serial.printf("HTTP %d -> %s\n", codigoHttp, resposta.c_str());

  JsonDocument recebido;
  DeserializationError erro = deserializeJson(recebido, resposta);
  if (erro) {
    Serial.printf("Resposta nao e JSON valido: %s\n", erro.c_str());
    return;
  }

  const char* status = recebido["status"] | "erro";
  if (strcmp(status, "ok") != 0) {
    Serial.printf("Servidor recusou: %s\n", recebido["mensagem"] | "sem mensagem");
    return;
  }

  comandoConcluidoId = 0;
  Serial.printf("Nivel de racao calculado pelo servidor: %d%%\n", (int)(recebido["nivel"] | -1));

  const char* comando = recebido["comando"] | "nenhum";
  int gramas    = recebido["gramas"] | 0;
  int idComando = recebido["id"] | 0;

  if (strcmp(comando, "alimentar") == 0 && gramas > 0) {
    Serial.printf("Servidor mandou ALIMENTAR %d g (alvo %d g)\n", gramas, (int)(recebido["alvo"] | 0));
    abrirComporta(gramas);
    comandoConcluidoId = idComando;
  } else if (strcmp(recebido["motivo"] | "", "tigela_cheia") == 0) {
    Serial.println("Pedido dispensado: a tigela ja tem a quantidade pedida.");
  } else {
    Serial.println("Nenhum comando pendente: comporta fechada.");
  }
}

void setup() {
  Serial.begin(115200);
  delay(500);

  garantirServoPronto();
  fecharComportaFisica();
  delay(500);

  pinMode(PINO_TRIG, OUTPUT);
  pinMode(PINO_ECHO, INPUT);
  digitalWrite(PINO_TRIG, LOW);

  conectarWiFi();
  ultimaTentativaWiFiMs = millis();

  macEsp = WiFi.macAddress();
  Serial.printf("MAC deste ESP32: %s\n", macEsp.c_str());

  ultimoEnvioMs = millis() - INTERVALO_MS;
}

void loop() {
  if (millis() - ultimoEnvioMs >= INTERVALO_MS) {
    ultimoEnvioMs = millis();
    sincronizar();
  }
}