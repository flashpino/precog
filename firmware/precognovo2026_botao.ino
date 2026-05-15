/*********
  PrecogSystem — DHT22 + InfluxDB v2 + LCD 20x4 I2C
  Versão de Produção v2.2 — ESP32 DevKit V4

  NOVIDADES v2.2:
  - NTP sincronizado antes do envio ao InfluxDB
  - saveResetReason() salva motivo no NVS antes de cada ESP.restart()
  - sendResetReason() envia o motivo ao InfluxDB no boot com timestamp real
  - Correção: WiFi.SSID() → wm.getWiFiSSID() para detectar rede salva
  - Correção: WiFi.begin() → WiFi.begin(ssid, pass) com credenciais explícitas

  NOVIDADES v2.1:
  - Buzzer (GPIO 25): 1 bip no boot, 2 bips quando sem WiFi,
    bips de confirmação/alerta para cada ação do botão
  - Botão push multifunção (GPIO 26):
      Toque rápido  (< 2s)  → Reinicia
      Segurar >= 5s e < 10s → Entra em modo config WiFi
      Segurar >= 10s        → Reset de fábrica (apaga tudo)
  - LCD mostra barra de progresso e ação pendente enquanto botão é segurado
  - LCD não alterna páginas enquanto botão está pressionado

  CORREÇÕES v2.0:
  1. Reconexão WiFi automática não-bloqueante
  2. Watchdog de hardware (esp_task_wdt) — substitui reset por timer
  3. WiFiManager: tenta credenciais salvas após timeout do portal
  4. Contadores de falha com reinício controlado
  5. NVS protegido com verificação de retorno
  6. sendToInflux com retry (2 tentativas)
  7. Escape correto do Line Protocol do InfluxDB
  8. Credenciais web configuráveis pelo NVS
  9. Heartbeat LED onboard (GPIO2)

  PINAGEM:
  - GPIO 27 → DHT22 (data) + resistor 10kΩ entre VCC e DATA
  - GPIO 25 → Buzzer (ativo ou passivo)
  - GPIO 26 → Botão push (GND quando pressionado)
  - GPIO  2 → LED onboard (heartbeat)
  - GPIO  0 → BOOT (reset WiFi rápido só no setup)
  - GPIO 21 → SDA (LCD I2C)
  - GPIO 22 → SCL (LCD I2C)

  SENSOR DHT22:
  - VCC → 3.3V
  - GND → GND
  - DATA → GPIO 27
  - Resistor 10kΩ entre VCC e DATA obrigatório
*********/ 

#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiManager.h>
#include <Preferences.h>
#include <WebServer.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <esp_task_wdt.h>
#include <esp_system.h>
#include <DHT.h>
#include <time.h>

// ── Pinos ─────────────────────────────────────────────────────────────────────
#define DHTPIN       27
#define DHTTYPE      DHT22
#define BOOT_PIN      0    // só usado no setup para reset WiFi rápido
#define BUZZER_PIN   25    // buzzer ativo ou passivo
#define LED_PIN       2    // LED onboard (heartbeat)

// ── LCD I2C ───────────────────────────────────────────────────────────────────
#define LCD_ADDR  0x27
#define LCD_COLS  20
#define LCD_ROWS   4

// ── Watchdog ──────────────────────────────────────────────────────────────────
#define WDT_TIMEOUT  120   // segundos (deve ser >= timeout do portal WiFi)

// ── Tolerância de falhas ──────────────────────────────────────────────────────
#define MAX_WIFI_FAIL    10
#define MAX_INFLUX_FAIL   5
#define WIFI_RETRY_MS  30000UL

// ── Tempos do botão (ms) ──────────────────────────────────────────────────────
//#define BTN_DEBOUNCE_MS   50
//#define BTN_RESTART_MAX 2000   // < 2s  → reinicia
//#define BTN_WIFI_MS     5000   // >= 5s → config WiFi
//#define BTN_FACTORY_MS 10000   // >=10s → reset fábrica

// ── Objetos globais ───────────────────────────────────────────────────────────
LiquidCrystal_I2C lcd(LCD_ADDR, LCD_COLS, LCD_ROWS);
DHT               dht(DHTPIN, DHTTYPE);
Preferences        prefs;
WiFiManager        wm;
WebServer          server(80);

// ── Autenticação web ──────────────────────────────────────────────────────────
String WEB_USER = "admin";
String WEB_PASS = "Precog@4990!";

// ── Configurações NVS ─────────────────────────────────────────────────────────
String  influxUrl;
String  influxOrg;
String  influxBucket;
String  influxToken;
String  deviceId;
String  deviceLocation;
String  intervalStr;
unsigned long sendInterval = 10000;

// ── Rede ──────────────────────────────────────────────────────────────────────
String  ipMode;
String  staticIp;
String  staticGw;
String  staticMask;
String  staticDns;

// ── Estado geral ──────────────────────────────────────────────────────────────
unsigned long lastSend        = 0;
unsigned long lastWifiRetry   = 0;
unsigned long lastHeartbeat   = 0;
unsigned long lcdLastSwap     = 0;
unsigned long lcdLastWrite    = 0;
unsigned long bootTime        = 0;
unsigned long lastWifiCheck   = 0;
unsigned long lastBipWifi     = 0;

#define LCD_WDT_MS 60000UL
int           lcdPage         = 0;
int           wifiFailCount   = 0;
int           influxFailCount = 0;
bool          ledState        = false;
bool          portalAberto    = false;

float  ultimaTemp   = NAN;
float  ultimaUmid   = NAN;
String ultimoStatus = "Aguardando";

// ─────────────────────────────────────────────────────────────────────────────
// BUZZER
// ─────────────────────────────────────────────────────────────────────────────
void bip(int durMs) {
  ledcWriteTone(BUZZER_PIN, 2000);
  delay(durMs);
  ledcWriteTone(BUZZER_PIN, 0);
}

void bipBoot()     { bip(1200); }
void bipSemWifi()  { bip(100); delay(120); bip(100); }
void bipOk()       { bip(600); }
void bipAlerta()   { for (int i = 0; i < 3; i++) { bip(60); delay(80); } }
void bipReset()    { bip(400); delay(150); bip(400); }

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS LCD
// ─────────────────────────────────────────────────────────────────────────────
void lcdPrintCentered(uint8_t row, const String& text) {
  int len = (int)text.length();
  int pad = (LCD_COLS - len) / 2;
  if (pad < 0) pad = 0;
  lcd.setCursor(0, row);
  lcd.print("                    ");
  lcd.setCursor(pad, row);
  lcd.print(text.substring(0, LCD_COLS - pad));
}

void lcdSplash() {
  lcd.clear();
  lcdPrintCentered(0, "====================");
  lcdPrintCentered(1, "  PrecogSystem  ");
  lcdPrintCentered(2, "  Monitoramento  ");
  lcdPrintCentered(3, "====================");
  delay(2000);
  lcd.clear();
  lcdPrintCentered(0, "Inicializando...");
  lcdPrintCentered(2, "Aguarde...");
  lcdLastWrite = millis();
}

void lcdTestResult(const String& label, bool ok, const String& detail = "") {
  lcd.clear();
  lcdPrintCentered(0, "-- " + label + " --");
  lcdPrintCentered(1, ok ? "[  OK  ]" : "[ FALHA ]");
  if (detail.length() > 0) lcdPrintCentered(2, detail);
  delay(1500);
  lcdLastWrite = millis();
}

void lcdShowSensorData() {
  lcd.clear();
  lcdPrintCentered(0, "* PrecogSystem *");
  String tempStr = isnan(ultimaTemp) ? "Temp: ---.-C"
                 : "Temp: " + String(ultimaTemp, 1) + (char)223 + "C";
  lcdPrintCentered(1, tempStr);
  String humStr  = isnan(ultimaUmid) ? "Umid: ---.- %"
                 : "Umid: " + String(ultimaUmid, 1) + " %";
  lcdPrintCentered(2, humStr);
  String stStr = "DB:" + ultimoStatus;
  if ((int)stStr.length() > LCD_COLS) stStr = stStr.substring(0, LCD_COLS);
  lcdPrintCentered(3, stStr);
  lcdLastWrite = millis();
}

void lcdShowNetInfo() {
  lcd.clear();
  lcdPrintCentered(0, "-- Rede --");
  lcdPrintCentered(1, "IP:" + WiFi.localIP().toString());
  String mac = WiFi.macAddress(); mac.replace(":", "");
  lcdPrintCentered(2, "MAC:" + mac.substring(0,4)+"."+mac.substring(4,8)+"."+mac.substring(8,12));
  String devStr = deviceId;
  if ((int)devStr.length() > LCD_COLS - 4) devStr = devStr.substring(0, LCD_COLS - 4);
  lcdPrintCentered(3, "Dev:" + devStr);
  lcdLastWrite = millis();
}

void lcdRefresh() {
  if (lcdPage == 0) lcdShowSensorData();
  else              lcdShowNetInfo();
  lcdLastWrite = millis();
}

// ─────────────────────────────────────────────────────────────────────────────
// REINIT FORÇADO DO LCD
// ─────────────────────────────────────────────────────────────────────────────
void lcdHardReset() {
  Serial.println("🖥️ LCD: reinit forçado...");
  Wire.end();
  delay(100);
  Wire.begin();
  Wire.setClock(100000);
  delay(100);
  lcd.init();
  delay(50);
  lcd.init();
  delay(50);
  lcd.backlight();
  delay(50);
  lcdLastWrite = millis();
  lcdRefresh();
  Serial.println("🖥️ LCD: reinit concluído");
}

// ─────────────────────────────────────────────────────────────────────────────
// Uptime
// ─────────────────────────────────────────────────────────────────────────────
String uptimeStr() {
  unsigned long s = millis() / 1000;
  unsigned long d = s / 86400; s %= 86400;
  unsigned long h = s / 3600;  s %= 3600;
  unsigned long m = s / 60;    s %= 60;
  char buf[24];
  if (d > 0) snprintf(buf, sizeof(buf), "%lud %02lu:%02lu:%02lu", d, h, m, s);
  else        snprintf(buf, sizeof(buf), "%02lu:%02lu:%02lu", h, m, s);
  return String(buf);
}

// ─────────────────────────────────────────────────────────────────────────────
// Reset reason (sistema)
// ─────────────────────────────────────────────────────────────────────────────
String resetReasonStr() {
  switch (esp_reset_reason()) {
    case ESP_RST_POWERON:   return "Power-on";
    case ESP_RST_EXT:       return "Reset externo";
    case ESP_RST_SW:        return "Software (restart)";
    case ESP_RST_PANIC:     return "Panic / Exception";
    case ESP_RST_INT_WDT:   return "Watchdog interrupcao";
    case ESP_RST_TASK_WDT:  return "Watchdog tarefa";
    case ESP_RST_WDT:       return "Watchdog outro";
    case ESP_RST_DEEPSLEEP: return "Deep sleep";
    case ESP_RST_BROWNOUT:  return "Brownout (sub-tensao)";
    default:                return "Desconhecido";
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// NVS — salva motivo do reset antes de reiniciar
// ─────────────────────────────────────────────────────────────────────────────
void saveResetReason(const String& motivo) {
  if (prefs.begin("config", false)) {
    prefs.putString("last_reset", motivo);
    prefs.end();
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// NTP — sincronização de hora (UTC-3 Brasília)
// ─────────────────────────────────────────────────────────────────────────────
bool syncNTP() {
  Serial.println("🕐 Sincronizando NTP...");
  lcd.clear();
  lcdPrintCentered(0, "Sincronizando NTP");
  lcdPrintCentered(1, "Aguarde...");

  configTime(-3 * 3600, 0, "pool.ntp.org", "time.google.com");

  struct tm timeinfo;
  int tentativas = 0;
  while (!getLocalTime(&timeinfo, 2000) && tentativas < 5) {
    esp_task_wdt_reset();
    tentativas++;
    Serial.printf("⏳ NTP tentativa %d/5...\n", tentativas);
  }

  if (tentativas >= 5) {
    Serial.println("❌ NTP: timeout");
    lcdTestResult("NTP", false, "Sem hora!");
    return false;
  }

  char buf[32];
  strftime(buf, sizeof(buf), "%d/%m/%Y %H:%M:%S", &timeinfo);
  Serial.printf("✅ NTP OK: %s\n", buf);
  lcdTestResult("NTP", true, String(buf).substring(0, LCD_COLS));
  return true;
}

// ─────────────────────────────────────────────────────────────────────────────
// Timestamp Unix em milissegundos (requer NTP sincronizado)
// ─────────────────────────────────────────────────────────────────────────────
unsigned long long getEpochMs() {
  struct tm timeinfo;
  if (!getLocalTime(&timeinfo, 5000)) {
    Serial.println("⚠️ NTP: falha ao obter hora");
    return 0;
  }
  time_t now;
  time(&now);
  return (unsigned long long)now * 1000ULL;
}

// ─────────────────────────────────────────────────────────────────────────────
// Envia motivo do reset ao InfluxDB com timestamp real
// Lê do NVS se disponível, senão usa motivo do sistema (Power-on, Watchdog...)
// ─────────────────────────────────────────────────────────────────────────────
void sendResetReason() {
  String reason = "";

  // Tenta ler motivo customizado salvo antes do restart
  if (prefs.begin("config", true)) {
    reason = prefs.getString("last_reset", "");
    prefs.end();
  }

  if (reason.length() == 0) {
    // Sem motivo customizado — usa o motivo de hardware
    reason = resetReasonStr();
  } else {
    // Limpa após ler para não reenviar no próximo boot
    if (prefs.begin("config", false)) {
      prefs.remove("last_reset");
      prefs.end();
    }
  }

  Serial.printf("📤 Enviando motivo do reset: %s\n", reason.c_str());

  unsigned long long ts = getEpochMs();
  if (ts == 0) {
    Serial.println("⚠️ Timestamp inválido — abortando envio de reset_event");
    return;
  }

  String mac     = WiFi.macAddress(); mac.replace(":", "");
  String safeId  = deviceId;  safeId.replace(" ", "\\ ");  safeId.replace(",", "\\,");
  String safeLoc = deviceLocation; safeLoc.replace(" ", "\\ "); safeLoc.replace(",", "\\,");

  String body = "reset_event,device=" + safeId +
                ",location=" + safeLoc +
                ",mac=" + mac +
                " reason=\"" + reason + "\" " +
                String((uint64_t)ts);

  HTTPClient http;
  String url = influxUrl + "/api/v2/write?org=" + influxOrg +
               "&bucket=" + influxBucket + "&precision=ms";

  http.begin(url);
  http.setTimeout(5000);
  http.addHeader("Authorization", "Token " + influxToken);
  http.addHeader("Content-Type", "text/plain; charset=utf-8");

  int code = http.POST(body);
  Serial.printf("InfluxDB reset_event: HTTP %d\n", code);
  http.end();
}

// ─────────────────────────────────────────────────────────────────────────────
// NVS
// ─────────────────────────────────────────────────────────────────────────────
bool loadPrefs() {
  if (!prefs.begin("config", true)) {
    Serial.println("⚠️ NVS: falha ao abrir (read)");
    return false;
  }
  influxUrl      = prefs.getString("influx_url",    "https://webhook.precogsystem.com.br");
  influxOrg      = prefs.getString("influx_org",    "organizacao");
  influxBucket   = prefs.getString("influx_bucket", "precog");
  influxToken    = prefs.getString("influx_token",  "SEU-TOKEN-AQUI");
  deviceId       = prefs.getString("device_id",     "precog_001");
  deviceLocation = prefs.getString("device_loc",    "CPD");
  intervalStr    = prefs.getString("interval_s",    "10");
  ipMode         = prefs.getString("ip_mode",       "dhcp");
  staticIp       = prefs.getString("static_ip",     "192.168.1.100");
  staticGw       = prefs.getString("static_gw",     "192.168.1.1");
  staticMask     = prefs.getString("static_mask",   "255.255.255.0");
  staticDns      = prefs.getString("static_dns",    "8.8.8.8");
  WEB_USER       = prefs.getString("web_user",      "admin");
  WEB_PASS       = prefs.getString("web_pass",      "Precog@4990!");
  prefs.end();
  sendInterval = (unsigned long)(intervalStr.toInt()) * 1000UL;
  if (sendInterval < 10000) sendInterval = 10000;
  return true;
}

bool savePrefs() {
  if (!prefs.begin("config", false)) {
    Serial.println("⚠️ NVS: falha ao abrir (write)");
    return false;
  }
  prefs.putString("influx_url",    influxUrl);
  prefs.putString("influx_org",    influxOrg);
  prefs.putString("influx_bucket", influxBucket);
  prefs.putString("influx_token",  influxToken);
  prefs.putString("device_id",     deviceId);
  prefs.putString("device_loc",    deviceLocation);
  prefs.putString("interval_s",    intervalStr);
  prefs.putString("ip_mode",       ipMode);
  prefs.putString("static_ip",     staticIp);
  prefs.putString("static_gw",     staticGw);
  prefs.putString("static_mask",   staticMask);
  prefs.putString("static_dns",    staticDns);
  prefs.putString("web_user",      WEB_USER);
  prefs.putString("web_pass",      WEB_PASS);
  prefs.end();
  return true;
}

// ─────────────────────────────────────────────────────────────────────────────
// IP Fixo
// ─────────────────────────────────────────────────────────────────────────────
bool applyStaticIP() {
  if (ipMode != "static") return false;
  IPAddress ip, gw, mask, dns;
  if (!ip.fromString(staticIp)   || !gw.fromString(staticGw) ||
      !mask.fromString(staticMask) || !dns.fromString(staticDns)) {
    Serial.println("⚠️ IP fixo invalido — forcando DHCP");
    ipMode = "dhcp"; savePrefs(); return false;
  }
  WiFi.config(ip, gw, mask, dns);
  Serial.printf("🔒 IP fixo: %s\n", staticIp.c_str());
  return true;
}

void checkStaticIPFallback() {
  if (ipMode != "static") return;
  IPAddress got = WiFi.localIP();
  if (got[0] == 0) {
    lcd.clear();
    lcdPrintCentered(0, "AVISO: IP Fixo");
    lcdPrintCentered(1, "nao atribuido!");
    lcdPrintCentered(2, "Revertendo DHCP...");
    delay(2000);
    saveResetReason("IP fixo invalido - revertendo DHCP");
    ipMode = "dhcp"; savePrefs(); ESP.restart();
  }
  IPAddress wanted; wanted.fromString(staticIp);
  if (got != wanted) {
    Serial.printf("⚠️ IP obtido (%s) difere do configurado (%s) — revertendo DHCP\n",
                  got.toString().c_str(), staticIp.c_str());
    lcd.clear();
    lcdPrintCentered(0, "AVISO: IP diverge");
    lcdPrintCentered(1, "Obtido: " + got.toString());
    lcdPrintCentered(2, "Revertendo DHCP...");
    bipAlerta();
    delay(2000);
    saveResetReason("IP diverge do configurado");
    ipMode = "dhcp"; savePrefs(); ESP.restart();
  }
  Serial.printf("✅ IP fixo confirmado: %s\n", got.toString().c_str());
}

// ─────────────────────────────────────────────────────────────────────────────
// BOTÃO MULTIFUNÇÃO (comentado — mantido para referência)
// ─────────────────────────────────────────────────────────────────────────────


// ─────────────────────────────────────────────────────────────────────────────
// WiFi — reconexão não-bloqueante
// ─────────────────────────────────────────────────────────────────────────────
bool ensureWiFi() {
  if (WiFi.status() == WL_CONNECTED) {
    wifiFailCount = 0;
    return true;
  }

  unsigned long now = millis();
  if (now - lastWifiRetry < WIFI_RETRY_MS) {
    if (now - lastBipWifi >= 60000UL) {
      bipSemWifi();
      lastBipWifi = now;
    }
    return false;
  }
  lastWifiRetry = now;
  wifiFailCount++;

  Serial.printf("🔄 WiFi desconectado — tentativa %d/%d\n", wifiFailCount, MAX_WIFI_FAIL);

  if (wifiFailCount >= MAX_WIFI_FAIL) {
    lcd.clear();
    lcdPrintCentered(0, "ERRO: WiFi perdido");
    lcdPrintCentered(1, "Reiniciando...");
    bipAlerta();
    delay(2000);
    saveResetReason("WiFi perdido - 10 falhas consecutivas");
    ESP.restart();
  }

  lcd.clear();
  lcdPrintCentered(0, "WiFi: Reconectando");
  lcdPrintCentered(1, "Tentativa " + String(wifiFailCount));
  bipSemWifi();

  applyStaticIP();
  WiFi.disconnect(false);
  delay(500);
  WiFi.reconnect();

  for (int i = 0; i < 10; i++) {
    esp_task_wdt_reset();
    if (WiFi.status() == WL_CONNECTED) {
      wifiFailCount = 0;
      Serial.printf("✅ Reconectado: %s\n", WiFi.localIP().toString().c_str());
      lcd.clear();
      lcdPrintCentered(0, "WiFi Reconectado!");
      lcdPrintCentered(1, WiFi.localIP().toString());
      lcdLastWrite = millis();
      bipOk();
      esp_task_wdt_reset();
      delay(1500);
      esp_task_wdt_reset();
      lcdRefresh();
      return true;
    }
    esp_task_wdt_reset();
    delay(1000);
    esp_task_wdt_reset();
  }

  ultimoStatus = "WiFi off";
  return false;
}

// ─────────────────────────────────────────────────────────────────────────────
// Teste de internet
// ─────────────────────────────────────────────────────────────────────────────
bool testInternet() {
  HTTPClient http;
  http.begin("http://clients3.google.com/generate_204");
  http.setTimeout(5000);
  int code = http.GET();
  http.end();
  return (code == 204 || code == 200);
}

// ─────────────────────────────────────────────────────────────────────────────
// Escape Line Protocol InfluxDB
// ─────────────────────────────────────────────────────────────────────────────
String escapeTag(const String& s) {
  String r = s;
  r.replace(",", "\\,");
  r.replace("=", "\\=");
  r.replace(" ", "\\ ");
  return r;
}

// ─────────────────────────────────────────────────────────────────────────────
// Envio ao InfluxDB — com retry (2 tentativas)
// ─────────────────────────────────────────────────────────────────────────────
void sendToInflux(float temp, float hum) {
  String mac = WiFi.macAddress();
  String ip  = WiFi.localIP().toString();
  String safeId  = escapeTag(deviceId);
  String safeLoc = escapeTag(deviceLocation);
  String safeMac = mac; safeMac.replace(":", "");

  String lineAmb =
    "ambiente,"
    "device_id=" + safeId  + ","
    "location="  + safeLoc + ","
    "mac="       + safeMac + " "
    "temperatura=" + String(temp, 2) + ","
    "umidade="     + String(hum,  2) + ","
    "ip=\""        + ip + "\"";

  String lineDevice =
    "dispositivos,"
    "mac=" + safeMac + " "
    "device_id=\"" + deviceId       + "\","
    "location=\""  + deviceLocation + "\","
    "ip=\""        + ip + "\"";

  String payload = lineAmb + "\n" + lineDevice;
  String url = influxUrl + "/api/v2/write?org=" + influxOrg +
               "&bucket=" + influxBucket + "&precision=s";

  int code = -1;
  for (int attempt = 1; attempt <= 2; attempt++) {
    esp_task_wdt_reset();
    HTTPClient http;
    http.begin(url);
    http.setTimeout(5000);
    http.addHeader("Authorization",          "Token " + influxToken);
    http.addHeader("Content-Type",           "text/plain; charset=utf-8");
    http.addHeader("X-Content-Type-Options", "nosniff");
    code = http.POST(payload);
    http.end();
    esp_task_wdt_reset();
    Serial.printf("📨 InfluxDB tentativa %d — HTTP %d\n", attempt, code);
    if (code == 204) {
      ultimoStatus    = "OK";
      influxFailCount = 0;
      Serial.println("✅ Gravado!");
      return;
    }
    if (attempt < 2) delay(2000);
  }

  influxFailCount++;
  ultimoStatus = (code == -1) ? "Sem conexao" : "Err " + String(code);
  Serial.printf("❌ Falha InfluxDB (%d) — consecutivas: %d\n", code, influxFailCount);

  if (influxFailCount >= MAX_INFLUX_FAIL) {
    lcd.clear();
    lcdPrintCentered(0, "ERRO: InfluxDB");
    lcdPrintCentered(1, String(MAX_INFLUX_FAIL) + " falhas seguidas");
    lcdPrintCentered(2, "Reiniciando...");
    bipAlerta();
    delay(3000);
    saveResetReason("InfluxDB - 5 falhas consecutivas");
    ESP.restart();
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// CSS / NAV (interface web)
// ─────────────────────────────────────────────────────────────────────────────
String htmlEscape(const String& s) {
  String r = s;
  r.replace("&", "&amp;");
  r.replace("<", "&lt;");
  r.replace(">", "&gt;");
  r.replace("\"", "&quot;");
  r.replace("'", "&#39;");
  return r;
}

String getCSS() {
  return "<style>"
    "*{box-sizing:border-box;margin:0;padding:0}"
    "body{font-family:verdana,sans-serif;background:#0f1923;color:#e0e6ed;min-height:100vh;padding:20px}"
    ".wrap{max-width:480px;margin:0 auto}"
    ".card{background:#1a2535;border:1px solid #2a3a50;border-radius:12px;padding:20px;margin-bottom:16px}"
    ".logo{text-align:center;padding:10px 0 4px}"
    ".logo h1{color:#39d0d8;font-size:1.4em;letter-spacing:1px}"
    ".logo p{color:#5a7a9a;font-size:0.78em;margin-top:4px}"
    ".nav{display:flex;gap:8px;margin-bottom:4px}"
    ".nav a{flex:1;text-align:center;padding:9px;border-radius:8px;text-decoration:none;"
    "font-size:0.82em;color:#8aaabf;border:1px solid #2a3a50;transition:all .2s}"
    ".nav a:hover{background:#223044;color:#39d0d8}"
    ".nav a.ativo{background:#223044;color:#39d0d8;border-color:#39d0d8}"
    ".row{display:flex;justify-content:space-between;gap:12px;margin-bottom:12px}"
    ".metric{flex:1;background:#121d2b;border-radius:10px;padding:14px;text-align:center}"
    ".metric .lbl{font-size:0.72em;color:#5a7a9a;margin-bottom:6px}"
    ".metric .val{font-size:1.9em;font-weight:bold;color:#39d0d8}"
    ".metric .unit{font-size:0.8em;color:#8aaabf}"
    ".info-row{display:flex;justify-content:space-between;align-items:center;"
    "padding:7px 0;border-bottom:1px solid #1e2e40;font-size:0.82em}"
    ".info-row:last-child{border-bottom:none}"
    ".info-row .k{color:#5a7a9a}"
    ".info-row .v{color:#c0d0e0;text-align:right;word-break:break-all;max-width:70%}"
    ".badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:0.75em;font-weight:bold}"
    ".ok{background:#0d3320;color:#2ea043;border:1px solid #196127}"
    ".err{background:#3a1010;color:#f85149;border:1px solid #6e1c1c}"
    "label{display:block;font-size:0.8em;color:#5a7a9a;margin-bottom:4px;margin-top:12px}"
    "input[type=text],input[type=password],input[type=number]{"
    "width:100%;padding:9px 12px;border-radius:7px;border:1px solid #2a3a50;"
    "background:#121d2b;color:#e0e6ed;font-size:0.88em}"
    "input:focus{outline:none;border-color:#39d0d8}"
    ".btn{display:block;width:100%;padding:11px;margin-top:16px;border-radius:8px;"
    "border:none;font-size:0.9em;font-weight:bold;cursor:pointer;transition:all .2s}"
    ".btn-save{background:#0d4a4e;color:#39d0d8;border:1px solid #39d0d8}"
    ".btn-save:hover{background:#39d0d8;color:#0d1923}"
    ".btn-reset{background:#3a1010;color:#f85149;border:1px solid #6e1c1c;margin-top:8px}"
    ".btn-reset:hover{background:#f85149;color:#fff}"
    ".btn-danger{background:#4a1a00;color:#ff8c42;border:1px solid #8c3a00;margin-top:8px}"
    ".btn-danger:hover{background:#ff8c42;color:#fff}"
    ".divider{border:none;border-top:1px solid #1e2e40;margin:16px 0}"
    ".section-title{font-size:0.75em;color:#3a5a7a;text-transform:uppercase;"
    "letter-spacing:1px;margin-bottom:8px;margin-top:4px}"
    ".msg-ok{background:#0d3320;color:#2ea043;border:1px solid #196127;"
    "border-radius:8px;padding:10px;text-align:center;margin-bottom:12px;font-size:0.85em}"
    ".msg-err{background:#3a1010;color:#f85149;border:1px solid #6e1c1c;"
    "border-radius:8px;padding:10px;text-align:center;margin-bottom:12px;font-size:0.85em}"
    ".hint{font-size:0.72em;color:#3a5a7a;margin-top:3px}"
    ".toggle-row{display:flex;align-items:center;gap:10px;margin-top:14px;margin-bottom:4px}"
    ".toggle-row label{margin:0;font-size:0.85em;color:#c0d0e0}"
    ".switch{position:relative;display:inline-block;width:42px;height:24px;flex-shrink:0}"
    ".switch input{opacity:0;width:0;height:0}"
    ".slider{position:absolute;cursor:pointer;inset:0;background:#1e2e40;"
    "border-radius:24px;border:1px solid #2a3a50;transition:.3s}"
    ".slider:before{position:absolute;content:'';height:16px;width:16px;left:3px;bottom:3px;"
    "background:#5a7a9a;border-radius:50%;transition:.3s}"
    "input:checked+.slider{background:#0d4a4e;border-color:#39d0d8}"
    "input:checked+.slider:before{transform:translateX(18px);background:#39d0d8}"
    "#ip-static-fields{margin-top:8px}"
    "</style>";
}

String getNav(String ativo) {
  return "<div class='nav'>"
    "<a href='/' class='"      + String(ativo=="status"?"ativo":"") + "'>Status</a>"
    "<a href='/config' class='" + String(ativo=="config"?"ativo":"") + "'>Configurar</a>"
    "</div>";
}

// ─────────────────────────────────────────────────────────────────────────────
// ROTAS WEB
// ─────────────────────────────────────────────────────────────────────────────
void handleRoot() {
  if (!server.authenticate(WEB_USER.c_str(), WEB_PASS.c_str()))
    return server.requestAuthentication(DIGEST_AUTH, "Precog", "Acesso restrito");

  String badge = (ultimoStatus == "OK")
    ? "<span class='badge ok'>Gravando</span>"
    : "<span class='badge err'>" + ultimoStatus + "</span>";

  String html = "<!DOCTYPE html><html><head>"
    "<meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'>"
    "<meta http-equiv='refresh' content='10'>"
    "<title>Precog | Status</title>" + getCSS() +
    "</head><body><div class='wrap'>"
    "<div class='logo'><h1>PrecogSystem</h1><p>Monitor de Temperatura e Umidade</p></div>" +
    getNav("status") +
    "<div class='card'><div class='row'>"
    "<div class='metric'><div class='lbl'>Temperatura</div>"
    "<div class='val'>" + (isnan(ultimaTemp)?"---":String(ultimaTemp,1)) + "<span class='unit'>°C</span></div></div>"
    "<div class='metric'><div class='lbl'>Umidade</div>"
    "<div class='val'>" + (isnan(ultimaUmid)?"---":String(ultimaUmid,1)) + "<span class='unit'>%</span></div></div>"
    "</div>"
    "<div class='info-row'><span class='k'>Status InfluxDB</span><span class='v'>" + badge + "</span></div>"
    "<div class='info-row'><span class='k'>Sensor</span><span class='v'>DHT22</span></div>"
    "<div class='info-row'><span class='k'>Device ID</span><span class='v'>" + deviceId + "</span></div>"
    "<div class='info-row'><span class='k'>Localização</span><span class='v'>" + deviceLocation + "</span></div>"
    "<div class='info-row'><span class='k'>IP</span><span class='v'>" + WiFi.localIP().toString() + "</span></div>"
    "<div class='info-row'><span class='k'>RSSI</span><span class='v'>" + String(WiFi.RSSI()) + " dBm</span></div>"
    "<div class='info-row'><span class='k'>Modo IP</span><span class='v'>" + (ipMode=="static"?"Fixo":"DHCP") + "</span></div>"
    "<div class='info-row'><span class='k'>MAC</span><span class='v'>" + WiFi.macAddress() + "</span></div>"
    "<div class='info-row'><span class='k'>Intervalo</span><span class='v'>" + intervalStr + "s</span></div>"
    "<div class='info-row'><span class='k'>Bucket</span><span class='v'>" + influxBucket + "</span></div>"
    "<div class='info-row'><span class='k'>Org</span><span class='v'>" + influxOrg + "</span></div>"
    "<div class='info-row'><span class='k'>Uptime</span><span class='v'>" + uptimeStr() + "</span></div>"
    "<div class='info-row'><span class='k'>Último reset</span><span class='v'>" + resetReasonStr() + "</span></div>"
    "<div class='info-row'><span class='k'>Falhas WiFi</span><span class='v'>" + String(wifiFailCount) + "</span></div>"
    "<div class='info-row'><span class='k'>Falhas InfluxDB</span><span class='v'>" + String(influxFailCount) + "</span></div>"
    "</div>"
    "<p style='text-align:center;font-size:0.72em;color:#3a5a7a'>Atualiza a cada 10s</p>"
    "</div></body></html>";

  server.send(200, "text/html", html);
}

void handleConfig() {
  if (!server.authenticate(WEB_USER.c_str(), WEB_PASS.c_str()))
    return server.requestAuthentication(DIGEST_AUTH, "Precog", "Acesso restrito");

  String html = "<!DOCTYPE html><html><head>"
    "<meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'>"
    "<title>Precog | Configurar</title>" + getCSS() +
    "</head><body><div class='wrap'>"
    "<div class='logo'><h1>PrecogSystem</h1><p>Configurações do Dispositivo</p></div>" +
    getNav("config") +
    "<div class='card'><form method='POST' action='/salvar'>"
    "<label>URL do InfluxDB</label>"
    "<input type='text' name='influx_url' value='" + htmlEscape(influxUrl) + "'>"
    "<p class='hint'>ex: https://meu-influx.easypanel.host</p>"
    "<label>Organização</label>"
    "<input type='text' name='influx_org' value='" + htmlEscape(influxOrg) + "'>"
    "<label>Bucket</label>"
    "<input type='text' name='influx_bucket' value='" + htmlEscape(influxBucket) + "'>"
    "<label>Token</label>"
    "<input type='password' name='influx_token' value='" + htmlEscape(influxToken) + "'>"
    "<label>ID do Dispositivo</label>"
    "<input type='text' name='device_id' value='" + htmlEscape(deviceId) + "'>"
    "<p class='hint'>ex: precog_001 (sem espaços)</p>"
    "<label>Localização</label>"
    "<input type='text' name='device_loc' value='" + htmlEscape(deviceLocation) + "'>"
    "<label>Intervalo de envio (segundos, mínimo 10)</label>"
    "<input type='number' name='interval_s' value='" + htmlEscape(intervalStr) + "' min='10' max='3600'>"
    "<hr class='divider'>"
    "<p class='section-title'>Autenticação Web</p>"
    "<label>Usuário</label>"
    "<input type='text' name='web_user' value='" + htmlEscape(WEB_USER) + "'>"
    "<label>Senha</label>"
    "<input type='password' name='web_pass' value='" + htmlEscape(WEB_PASS) + "'>"
    "<p class='hint'>Credenciais de acesso a esta interface.</p>"
    "<hr class='divider'>"
    "<p class='section-title'>Configuração de Rede</p>"
    "<div class='toggle-row'>"
    "<label class='switch'><input type='checkbox' id='ip-toggle' name='ip_mode' value='static'"
    + String(ipMode=="static"?" checked":"") + " onchange='toggleIp(this)'>"
    "<span class='slider'></span></label>"
    "<label for='ip-toggle'>IP Fixo (desativado = DHCP)</label></div>"
    "<div id='ip-static-fields' style='display:" + String(ipMode=="static"?"block":"none") + "'>"
    "<label>Endereço IP</label>"
    "<input type='text' name='static_ip' value='" + htmlEscape(staticIp) + "' placeholder='192.168.1.100'>"
    "<label>Gateway</label>"
    "<input type='text' name='static_gw' value='" + htmlEscape(staticGw) + "' placeholder='192.168.1.1'>"
    "<label>Máscara</label>"
    "<input type='text' name='static_mask' value='" + htmlEscape(staticMask) + "' placeholder='255.255.255.0'>"
    "<label>DNS</label>"
    "<input type='text' name='static_dns' value='" + htmlEscape(staticDns) + "' placeholder='8.8.8.8'>"
    "<p class='hint'>⚠️ O dispositivo reiniciará para aplicar o novo IP.</p>"
    "</div>"
    "<script>function toggleIp(cb){"
    "document.getElementById('ip-static-fields').style.display=cb.checked?'block':'none';}"
    "</script>"
    "<button type='submit' class='btn btn-save'>💾 Salvar configurações</button>"
    "</form></div>"
    "<div class='card'><p class='section-title'>Zona de reset</p>"
    "<form method='POST' action='/reset-wifi'>"
    "<button type='submit' class='btn btn-reset'>🔄 Resetar WiFi</button>"
    "</form>"
    "<p class='hint' style='margin-top:6px'>Apaga a rede salva e abre o portal. Configurações mantidas.</p>"
    "<hr class='divider'>"
    "<form method='POST' action='/reset-config'>"
    "<button type='submit' class='btn btn-danger' "
    "onclick=\"return confirm('Apagar TODAS as configurações?\\nAção irreversível.')\">"
    "🗑️ Reset de fábrica</button>"
    "</form>"
    "<p class='hint' style='margin-top:6px'>Apaga WiFi e todas as configurações. Retorna ao padrão de fábrica.</p>"
    "</div></div></body></html>";

  server.send(200, "text/html", html);
}

void handleSalvar() {
  if (!server.authenticate(WEB_USER.c_str(), WEB_PASS.c_str()))
    return server.requestAuthentication(DIGEST_AUTH, "Precog", "Acesso restrito");

  if (server.hasArg("influx_url"))    influxUrl      = server.arg("influx_url");
  if (server.hasArg("influx_org"))    influxOrg      = server.arg("influx_org");
  if (server.hasArg("influx_bucket")) influxBucket   = server.arg("influx_bucket");
  if (server.hasArg("influx_token"))  influxToken    = server.arg("influx_token");
  if (server.hasArg("device_id"))     deviceId       = server.arg("device_id");
  if (server.hasArg("device_loc"))    deviceLocation = server.arg("device_loc");
  if (server.hasArg("interval_s"))    intervalStr    = server.arg("interval_s");
  if (server.hasArg("web_user") && server.arg("web_user").length() > 0)
    WEB_USER = server.arg("web_user");
  if (server.hasArg("web_pass") && server.arg("web_pass").length() > 0)
    WEB_PASS = server.arg("web_pass");

  String novoIpMode  = server.hasArg("ip_mode") ? "static" : "dhcp";
  bool ipModeChanged = (novoIpMode != ipMode);
  ipMode = novoIpMode;
  if (server.hasArg("static_ip"))   staticIp   = server.arg("static_ip");
  if (server.hasArg("static_gw"))   staticGw   = server.arg("static_gw");
  if (server.hasArg("static_mask")) staticMask = server.arg("static_mask");
  if (server.hasArg("static_dns"))  staticDns  = server.arg("static_dns");

  int novoInterval = intervalStr.toInt();
  if (novoInterval < 10) { novoInterval = 10; intervalStr = "10"; }
  sendInterval = (unsigned long)novoInterval * 1000UL;

  savePrefs();
  Serial.println("⚙️ Configurações salvas via web!");

  String msgExtra = ipModeChanged
    ? "<br><span style='color:#ff8c42'>⚠️ IP alterado — reiniciando em 3s...</span>" : "";

  String html = "<!DOCTYPE html><html><head>"
    "<meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'>"
    "<meta http-equiv='refresh' content='" + String(ipModeChanged?"4":"2") + ";url=/config'>"
    "<title>Salvo!</title>" + getCSS() +
    "</head><body><div class='wrap'>"
    "<div class='logo'><h1>PrecogSystem</h1></div>" + getNav("config") +
    "<div class='card'><div class='msg-ok'>✅ Configurações salvas!" + msgExtra + "</div>"
    "</div></div></body></html>";

  server.send(200, "text/html", html);
  if (ipModeChanged) {
    saveResetReason("IP alterado via interface web");
    delay(3000);
    ESP.restart();
  }
}

void handleResetWifi() {
  if (!server.authenticate(WEB_USER.c_str(), WEB_PASS.c_str()))
    return server.requestAuthentication(DIGEST_AUTH, "Precog", "Acesso restrito");

  server.send(200, "text/html",
    "<!DOCTYPE html><html><head><meta charset='UTF-8'>"
    "<meta name='viewport' content='width=device-width,initial-scale=1'>"
    "<title>Resetando WiFi...</title>" + getCSS() +
    "</head><body><div class='wrap'><div class='logo'><h1>PrecogSystem</h1></div>"
    "<div class='card'><div class='msg-err'>🔄 Abrindo portal WiFi...<br>"
    "Conecte na rede <b>PrecogSetup</b> em alguns segundos.</div>"
    "</div></div></body></html>");
  delay(2000);
  saveResetReason("Reset WiFi solicitado via web");
  wm.resetSettings();
  ESP.restart();
}

void handleResetConfig() {
  if (!server.authenticate(WEB_USER.c_str(), WEB_PASS.c_str()))
    return server.requestAuthentication(DIGEST_AUTH, "Precog", "Acesso restrito");

  server.send(200, "text/html",
    "<!DOCTYPE html><html><head><meta charset='UTF-8'>"
    "<meta name='viewport' content='width=device-width,initial-scale=1'>"
    "<title>Reset de fábrica...</title>" + getCSS() +
    "</head><body><div class='wrap'><div class='logo'><h1>PrecogSystem</h1></div>"
    "<div class='card'><div class='msg-err'>🗑️ Apagando todas as configurações...<br>"
    "Conecte na rede <b>PrecogSetup</b> para reconfigurar.</div>"
    "</div></div></body></html>");
  delay(2000);
  saveResetReason("Reset de fabrica solicitado via web");
  if (prefs.begin("config", false)) { prefs.clear(); prefs.end(); }
  wm.resetSettings();
  ESP.restart();
}

void handle404() {
  server.send(404, "text/plain", "Pagina nao encontrada");
}

// ─────────────────────────────────────────────────────────────────────────────
// SETUP
// ─────────────────────────────────────────────────────────────────────────────
void setup() {
  Serial.begin(115200);
  delay(500);
  Serial.println("\n\n🚀 PrecogSystem v2.2");
  Serial.printf("Motivo do reset: %s\n", resetReasonStr().c_str());

  // ── Pinos de saída ──────────────────────────────────────────────────────────
  pinMode(LED_PIN, OUTPUT); digitalWrite(LED_PIN, LOW);
  ledcAttach(BUZZER_PIN, 2000, 8);

  // ── Pinos de entrada ────────────────────────────────────────────────────────
  pinMode(BOOT_PIN, INPUT_PULLUP);

  // ── Watchdog ────────────────────────────────────────────────────────────────
  const esp_task_wdt_config_t wdt_config = {
    .timeout_ms = WDT_TIMEOUT * 1000,
    .idle_core_mask = 0,
    .trigger_panic = true
  };
  esp_task_wdt_reconfigure(&wdt_config);
  esp_task_wdt_add(NULL);
  Serial.printf("🐕 Watchdog: %ds\n", WDT_TIMEOUT);

  // ── LCD ─────────────────────────────────────────────────────────────────────
  delay(300);
  Wire.begin();           // SDA=21, SCL=22
  Wire.setClock(100000);  // 100kHz — mais estável para I2C
  delay(100);
  lcd.init();
  delay(50);
  lcd.init();
  delay(50);
  lcd.backlight();
  lcdLastWrite = millis();
  lcdSplash();

  // ── Bip de boot ─────────────────────────────────────────────────────────────
  bipBoot();

  // ── WiFi ────────────────────────────────────────────────────────────────────
  WiFi.mode(WIFI_STA);
  WiFi.setAutoReconnect(false);

  String macAddr = WiFi.macAddress();
  Serial.printf("🔌 MAC: %s\n", macAddr.c_str());

  // ── DHT22 ───────────────────────────────────────────────────────────────────
  lcd.clear();
  lcdPrintCentered(0, "Testando sensor...");
  dht.begin();
  esp_task_wdt_reset(); delay(1500); esp_task_wdt_reset();

  float testTemp = dht.readTemperature();
  float testHum  = dht.readHumidity();
  bool  sht31Ok  = (!isnan(testTemp) && !isnan(testHum));

  Serial.printf("Sensor — Temp: %.1f | Umid: %.1f | OK: %s\n",
                testTemp, testHum, sht31Ok ? "SIM" : "NAO");

  if (sht31Ok) {
    lcdTestResult("Sensor", true, String(testTemp,1)+"C  "+String(testHum,1)+"%");
    ultimaTemp = testTemp;
    ultimaUmid = testHum;
  } else {
    lcdTestResult("Sensor", false, "Verifique conexao");
  }

  // ── Prefs ───────────────────────────────────────────────────────────────────
  loadPrefs();

  // ── BOOT pressionado no setup → reset WiFi rápido ───────────────────────────
  if (digitalRead(BOOT_PIN) == LOW) {
    Serial.println("🔄 BOOT: resetando WiFi...");
    lcd.clear();
    lcdPrintCentered(0, "BOOT pressionado!");
    lcdPrintCentered(1, "Resetando WiFi...");
    lcdPrintCentered(3, "Reiniciando...");
    bipOk();
    saveResetReason("BOOT pressionado - reset WiFi");
    wm.resetSettings();
    delay(2000);
    ESP.restart();
  }

  // ── Portal WiFiManager ──────────────────────────────────────────────────────
  String macStr = "MAC: " + macAddr;
  WiFiManagerParameter p_mac          ("mac_info",      macStr.c_str(),           "",                    1);
  WiFiManagerParameter p_influx_url   ("influx_url",    "URL do banco",           influxUrl.c_str(),     80);
  WiFiManagerParameter p_influx_org   ("influx_org",    "Org",                    influxOrg.c_str(),     40);
  WiFiManagerParameter p_influx_bucket("influx_bucket", "Bucket",                 influxBucket.c_str(),  40);
  WiFiManagerParameter p_influx_token ("influx_token",  "Token",                  influxToken.c_str(),  128);
  WiFiManagerParameter p_device_id    ("device_id",     "ID do Dispositivo",      deviceId.c_str(),      40);
  WiFiManagerParameter p_device_loc   ("device_loc",    "Localizacao",            deviceLocation.c_str(),60);
  WiFiManagerParameter p_interval     ("interval_s",    "Intervalo (segundos)",   intervalStr.c_str(),   10);
  WiFiManagerParameter p_ip_mode      ("ip_mode",       "IP Fixo? (static/dhcp)", ipMode.c_str(),        10);
  WiFiManagerParameter p_static_ip    ("static_ip",     "IP Fixo",                staticIp.c_str(),      20);
  WiFiManagerParameter p_static_gw    ("static_gw",     "Gateway",                staticGw.c_str(),      20);
  WiFiManagerParameter p_static_mask  ("static_mask",   "Mascara",                staticMask.c_str(),    20);
  WiFiManagerParameter p_static_dns   ("static_dns",    "DNS",                    staticDns.c_str(),     20);

  wm.addParameter(&p_mac);
  wm.addParameter(&p_influx_url);
  wm.addParameter(&p_influx_org);
  wm.addParameter(&p_influx_bucket);
  wm.addParameter(&p_influx_token);
  wm.addParameter(&p_device_id);
  wm.addParameter(&p_device_loc);
  wm.addParameter(&p_interval);
  wm.addParameter(&p_ip_mode);
  wm.addParameter(&p_static_ip);
  wm.addParameter(&p_static_gw);
  wm.addParameter(&p_static_mask);
  wm.addParameter(&p_static_dns);

  wm.setConfigPortalTimeout(120);
  wm.setSaveConfigCallback([]() {
    Serial.println("📥 Config salva pelo portal");
  });

  // ── Reseta o watchdog enquanto o portal está aberto (evita reboot ao digitar senha) ──
  wm.setWebServerCallback([]() {
    esp_task_wdt_reset();
  });

  wm.setAPCallback([](WiFiManager* wm) {
    portalAberto = true;
    bipSemWifi();
    lcd.clear();
    lcdPrintCentered(0, "WiFi nao conectado");
    lcdPrintCentered(1, "Modo config ativo!");
    lcdPrintCentered(2, "WiFi: PrecogSetup");
    lcdPrintCentered(3, "IP: 192.168.4.1");
  });

  lcd.clear();
  lcdPrintCentered(0, "Conectando WiFi...");
  lcdPrintCentered(2, "Portal: PrecogSetup");
  lcdPrintCentered(3, "Reinicia em: 120s");

  Serial.println("🛜 WiFiManager...");
  esp_task_wdt_reset();

  // ── Tenta credenciais salvas (CORRIGIDO: usa wm.getWiFiSSID()) ───────────────
  bool wifiConectado = false;

  if (wm.getWiFiSSID().length() > 0) {
    Serial.printf("🔄 Rede salva: %s\n", wm.getWiFiSSID().c_str());
    applyStaticIP();
    WiFi.begin(wm.getWiFiSSID().c_str(), wm.getWiFiPass().c_str());

    for (int i = 0; i < 20; i++) {
      esp_task_wdt_reset();
      lcd.clear();
      lcdPrintCentered(0, "Conectando WiFi...");
      lcdPrintCentered(1, wm.getWiFiSSID().substring(0, LCD_COLS));
      lcdPrintCentered(2, "Tentativa " + String(i + 1) + "/20");
      lcdPrintCentered(3, "Aguarde...");
      if (WiFi.status() == WL_CONNECTED) { wifiConectado = true; break; }
      delay(1000);
    }
  }

  // ── Se não conectou, abre portal ─────────────────────────────────────────────
  if (!wifiConectado) {
    Serial.println("⚠️ Sem WiFi — abrindo portal de configuração...");
    lcd.clear();
    lcdPrintCentered(0, "Sem WiFi!");
    lcdPrintCentered(1, "Abrindo portal...");
    lcdPrintCentered(2, "Rede: PrecogSetup");
    lcdPrintCentered(3, "IP: 192.168.4.1");
    bipSemWifi();
    delay(1500);

    wm.setConfigPortalTimeout(120);
    bool wmOk = wm.startConfigPortal("PrecogSetup", "Precog@4990!");

    if (!wmOk || WiFi.status() != WL_CONNECTED) {
      Serial.println("❌ Portal expirou sem configurar — reiniciando...");
      lcd.clear();
      lcdPrintCentered(0, "Timeout portal");
      lcdPrintCentered(1, "Reiniciando...");
      bipAlerta();
      delay(3000);
      saveResetReason("Timeout portal WiFi - sem configuracao");
      ESP.restart();
    }
  }

  portalAberto = false;
  bool wifiOk  = (WiFi.status() == WL_CONNECTED);
  Serial.printf("📶 IP: %s\n", WiFi.localIP().toString().c_str());
  lcdTestResult("WiFi", wifiOk, wifiOk ? WiFi.localIP().toString() : "Falhou!");
  if (wifiOk) bipOk();

  checkStaticIPFallback();

  esp_task_wdt_reset();

  // ── NTP ─────────────────────────────────────────────────────────────────────
  bool ntpOk = syncNTP();

  // ── Envia motivo do reset ao InfluxDB ────────────────────────────────────────
  if (wifiOk) {
    sendResetReason();
  }

  // ── Teste de internet ────────────────────────────────────────────────────────
  lcd.clear();
  lcdPrintCentered(0, "Testando internet");
  lcdPrintCentered(1, "Aguarde...");

  bool netOk = testInternet();
  Serial.printf("🌐 Internet: %s\n", netOk ? "OK" : "FALHOU");
  lcdTestResult("Internet", netOk, netOk ? "Acesso OK" : "Sem acesso!");

  // Salva configs do portal (só sobrescreve se não vazio)
  auto setIfNotEmpty = [](String& target, const char* val) {
    if (val && strlen(val) > 0) target = String(val);
  };
  setIfNotEmpty(influxUrl,      p_influx_url.getValue());
  setIfNotEmpty(influxOrg,      p_influx_org.getValue());
  setIfNotEmpty(influxBucket,   p_influx_bucket.getValue());
  setIfNotEmpty(influxToken,    p_influx_token.getValue());
  setIfNotEmpty(deviceId,       p_device_id.getValue());
  setIfNotEmpty(deviceLocation, p_device_loc.getValue());
  setIfNotEmpty(intervalStr,    p_interval.getValue());
  String pmIpMode = p_ip_mode.getValue();
  if (pmIpMode.length() > 0) ipMode = (pmIpMode == "static") ? "static" : "dhcp";
  setIfNotEmpty(staticIp,   p_static_ip.getValue());
  setIfNotEmpty(staticGw,   p_static_gw.getValue());
  setIfNotEmpty(staticMask, p_static_mask.getValue());
  setIfNotEmpty(staticDns,  p_static_dns.getValue());
  savePrefs();

  sendInterval = (unsigned long)(intervalStr.toInt()) * 1000UL;
  if (sendInterval < 10000) sendInterval = 10000;

  Serial.println("=====================================");
  Serial.printf("📡 Device  : %s\n", deviceId.c_str());
  Serial.printf("📍 Local   : %s\n", deviceLocation.c_str());
  Serial.printf("🗄️  DB: %s / %s\n", influxUrl.c_str(), influxBucket.c_str());
  Serial.printf("⏱️  Intervalo: %lus\n", sendInterval / 1000);
  Serial.println("=====================================");

  server.on("/",             HTTP_GET,  handleRoot);
  server.on("/config",       HTTP_GET,  handleConfig);
  server.on("/salvar",       HTTP_POST, handleSalvar);
  server.on("/reset-wifi",   HTTP_POST, handleResetWifi);
  server.on("/reset-config", HTTP_POST, handleResetConfig);
  server.onNotFound(handle404);
  server.begin();
  Serial.println("🌐 Servidor web iniciado!");

  lcd.clear();
  lcdPrintCentered(0, "Sistema OK! v2.2");
  lcdPrintCentered(1, sht31Ok ? "Sensor:  [OK]"    : "Sensor:  [ERRO]");
  lcdPrintCentered(2, wifiOk  ? "WiFi:   [OK]"     : "WiFi:   [ERRO]");
  lcdPrintCentered(3, netOk   ? "Internet:[OK]"    : "Internet:[OFF]");
  esp_task_wdt_reset();
  delay(3000);
  esp_task_wdt_reset();

  lcdShowSensorData();
  lcdLastSwap = millis();
  bootTime    = millis();
}

// ─────────────────────────────────────────────────────────────────────────────
// LOOP
// ─────────────────────────────────────────────────────────────────────────────
void loop() {
  esp_task_wdt_reset();

  unsigned long now = millis();

  // ── Heartbeat LED (pisca 1x/s) ───────────────────────────────────────────────
  if (now - lastHeartbeat >= 500) {
    ledState = !ledState;
    digitalWrite(LED_PIN, ledState);
    lastHeartbeat = now;
  }

  // ── Botão multifunção ────────────────────────────────────────────────────────
  //handleButton();

  // ── Servidor web ─────────────────────────────────────────────────────────────
  server.handleClient();
  esp_task_wdt_reset();

  // ── Checagem periódica de WiFi ────────────────────────────────────────────────
  if (now - lastWifiCheck >= 15000UL) {
    lastWifiCheck = now;
    if (WiFi.status() != WL_CONNECTED) {
      Serial.println("⚠️ WiFi check: desconectado — tentando reconectar...");
      ensureWiFi();
    }
  }

  // ── Alternância LCD ──────────────────────────────────────────────────────────
  if (now - lcdLastSwap >= 8000) {
    lcdPage = (lcdPage + 1) % 2;
    lcdRefresh();
    lcdLastSwap = now;
  }

  // ── Watchdog do LCD ──────────────────────────────────────────────────────────
  if (now - lcdLastWrite >= LCD_WDT_MS) {
    lcdHardReset();
  }

  // ── Leitura DHT22 e envio ao InfluxDB ────────────────────────────────────────
  if (now - lastSend >= sendInterval) {
    lastSend = now;

    float temp = dht.readTemperature();
    float hum  = dht.readHumidity();

    Serial.printf("Temp: %.1f°C | Umid: %.1f%% | Uptime: %s\n",
                  temp, hum, uptimeStr().c_str());

    if (!isnan(temp) && !isnan(hum)) {
      ultimaTemp = temp;
      ultimaUmid = hum;
    } else {
      Serial.println("⚠️ Leitura invalida — usando ultima valida");
      ultimoStatus = "Erro sensor";
    }

    if (ensureWiFi()) {
      esp_task_wdt_reset();
      if (!isnan(ultimaTemp) && !isnan(ultimaUmid)) {
        sendToInflux(ultimaTemp, ultimaUmid);
      }
    }
    esp_task_wdt_reset();

    lcdRefresh();
    lcdLastSwap = now;

    Serial.println("=====================================");
  }

  delay(10);
}
