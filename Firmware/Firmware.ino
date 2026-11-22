// ============================================================
//  Pest Detector — Arduino UNO R4 WiFi Edition
//  Features: DHT22, IR Sensor, LCD, Buzzer, SIM800L,
//            WiFi → PHP backend data push (ArduinoHttpClient)
// ============================================================

// ── Libraries ───────────────────────────────────────────────
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <SoftwareSerial.h>
#include <WiFiS3.h>
#include <ArduinoHttpClient.h>
#include "DHT.h"

// ── Pin Definitions ─────────────────────────────────────────
#define BUZZER_PIN_SIG      2
#define IROBJAVOID_PIN_OUT  4
#define DHT_PIN_DATA        3
#define SIM800L_TX          10
#define SIM800L_RX          11

// ── LCD ─────────────────────────────────────────────────────
#define LCD_ADDRESS 0x27
LiquidCrystal_I2C lcd(LCD_ADDRESS, 16, 2);

// ── GSM ─────────────────────────────────────────────────────
SoftwareSerial sim800l(SIM800L_TX, SIM800L_RX);

// ── DHT ─────────────────────────────────────────────────────
// FIX #1: Must pass sensor type (DHT22) as second argument
#define DHT_TYPE DHT22
DHT dht(DHT_PIN_DATA, DHT_TYPE);

// ── WiFi Credentials ────────────────────────────────────────
// ⚠️ CHANGE THESE to your actual WiFi hotspot SSID and password
const char* WIFI_SSID     = "HUAWEI-2.4G-u5Ve";
const char* WIFI_PASSWORD = "RqqDP8sf";

// ── PHP Server Settings ─────────────────────────────────────
// ⚠️  HOW TO FIND THE CORRECT IP:
//     1. On your PC, open Command Prompt and run: ipconfig
//     2. Find the adapter that is sharing the hotspot.
//        - If Arduino connects to your PC's mobile hotspot:
//          look for "Wireless LAN adapter Local Area Connection* X"
//          → IPv4 Address (usually 192.168.137.1)
//        - If Arduino connects to a router you are also on:
//          look for "Wireless LAN adapter Wi-Fi" → IPv4 Address
//     3. Paste that IPv4 address below.
//     4. Make sure your PHP files are placed at:
//          C:\xampp\htdocs\iot\   (Windows XAMPP)
//        so that http://<SERVER_HOST>/iot/receive.php works in a browser.
const char* SERVER_HOST = "192.168.100.46";  // ← replace with your PC's hotspot/LAN IPv4
const int   SERVER_PORT = 80;
const char* SERVER_PATH = "/iot/receive.php";

// ── GSM / SMS ───────────────────────────────────────────────
#define SMS_RECIPIENT "+639564267049"

// ── Timing ──────────────────────────────────────────────────
#define ALERT_COOLDOWN   30000   // 30 s between SMS alerts
#define WIFI_POST_EVERY   5000   // push data every 5 s

unsigned long lastAlertTime = 0;
unsigned long lastPostTime  = 0;
bool          gsmRegistered = false;

// ============================================================
void setup() {
    Serial.begin(9600);
    sim800l.begin(9600);

    // ── Buzzer pin ──────────────────────────────────────────
    pinMode(BUZZER_PIN_SIG, OUTPUT);
    digitalWrite(BUZZER_PIN_SIG, LOW);

    // ── LCD ─────────────────────────────────────────────────
    lcd.init();
    lcd.backlight();
    lcdMsg("  Pest Detect  ", "  Initializing ");
    delay(2000);

    // ── Sensors ─────────────────────────────────────────────
    pinMode(IROBJAVOID_PIN_OUT, INPUT);
    dht.begin();

    // ── WiFi ────────────────────────────────────────────────
    initWiFi();

    // ── GSM ─────────────────────────────────────────────────
    // Uncomment below to enable GSM/SMS on boot:
    // initGSM();

    lcd.clear();
    Serial.println("System Ready.");
}

// ============================================================
void loop() {
    float humidity = dht.readHumidity();
    float tempC    = dht.readTempC();

    // Validate DHT readings
    if (isnan(humidity) || isnan(tempC)) {
        Serial.println("DHT read failed — retrying...");
        lcdMsg("DHT Read Fail", "Retrying...");
        delay(2000);
        return;
    }

    // IR: LOW = object detected
    bool irTriggered = !digitalRead(IROBJAVOID_PIN_OUT);

    unsigned long now = millis();

    // ── Push data to server periodically ────────────────────
    if (now - lastPostTime >= WIFI_POST_EVERY) {
        postData(tempC, humidity, irTriggered);
        lastPostTime = now;
    }

    if (irTriggered) {
        // ── PEST DETECTED ───────────────────────────────────
        lcd.clear();
        lcd.setCursor(0, 0);
        lcd.print(" PEST DETECTED! ");
        lcd.setCursor(0, 1);
        lcd.print("T:");
        lcd.print(tempC, 1);
        lcd.print("C H:");
        lcd.print(humidity, 0);
        lcd.print("%");

        Serial.println("PEST DETECTED!");

        // ── Buzzer beep (direct digitalWrite — no library) ──
        for (int i = 0; i < 5; i++) {
            digitalWrite(BUZZER_PIN_SIG, HIGH);
            delay(100);
            digitalWrite(BUZZER_PIN_SIG, LOW);
            delay(100);
        }

        // ── SMS alert ───────────────────────────────────────
        if (now - lastAlertTime >= ALERT_COOLDOWN) {
            if (checkGSMRegistration()) {
                String msg = "Alert: Pest detected! Temp: "
                           + String(tempC, 1) + "C, Humidity: "
                           + String(humidity, 0) + "%";
                sendSMS(msg);
                lastAlertTime = now;
                Serial.println("SMS sent.");
            } else {
                Serial.println("GSM not registered — SMS skipped.");
                lcdMsg("GSM not ready", "SMS skipped");
                delay(1500);
            }
        }

        delay(2000);

    } else {
        // ── MONITORING ──────────────────────────────────────
        lcd.clear();
        lcd.setCursor(0, 0);
        lcd.print("T:");
        lcd.print(tempC, 1);
        lcd.print("C  H:");
        lcd.print(humidity, 0);
        lcd.print("%");
        lcd.setCursor(0, 1);
        lcd.print(" Monitoring...  ");

        Serial.print("Temp: "); Serial.print(tempC);
        Serial.print("C | Humidity: "); Serial.print(humidity);
        Serial.println("%");

        delay(2000);
    }
}

// ============================================================
//  WiFi
// ============================================================
void initWiFi() {
    lcdMsg("WiFi Connecting", WIFI_SSID);
    Serial.print("Connecting to WiFi: ");
    Serial.println(WIFI_SSID);

    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED) {
        delay(500);
        Serial.print(".");
        attempts++;

        if (attempts > 60) {
            Serial.println("\nRetrying WiFi...");
            WiFi.disconnect();
            delay(1000);
            WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
            attempts = 0;
        }
    }

    if (WiFi.status() == WL_CONNECTED) {
        Serial.println("\nWiFi connected!");
        Serial.print("Arduino IP : "); Serial.println(WiFi.localIP());
        Serial.print("Gateway IP : "); Serial.println(WiFi.gatewayIP());
        Serial.print("SERVER_HOST: "); Serial.println(SERVER_HOST);
        lcdMsg("WiFi Connected", WiFi.localIP().toString().c_str());
    } else {
        Serial.println("\nWiFi FAILED — continuing without it.");
        lcdMsg("WiFi FAILED", "No connection");
    }
    delay(2000);
}

// ── HTTP POST to PHP server ──────────────────────────────────
void postData(float temp, float humidity, bool pest) {
    if (WiFi.status() != WL_CONNECTED) {
        Serial.println("WiFi disconnected — skipping POST.");
        return;
    }

    // FIX #2: sender should be module identifier; recipient is the phone number
    String body = "temp="     + String(temp, 1)
                + "&humidity=" + String(humidity, 0)
                + "&pest="     + String(pest ? 1 : 0)
                + "&sender=SIM800L%20Module"           // URL-encoded space
                + "&recipient=" + String(SMS_RECIPIENT);

    Serial.println("Posting to server...");
    Serial.print("URL : http://"); Serial.print(SERVER_HOST); Serial.println(SERVER_PATH);
    Serial.print("Body: "); Serial.println(body);

    WiFiClient wifi;
    HttpClient client = HttpClient(wifi, SERVER_HOST, SERVER_PORT);
    client.setTimeout(5000);

    client.beginRequest();
    client.post(SERVER_PATH);
    client.sendHeader("Content-Type", "application/x-www-form-urlencoded");
    client.sendHeader("Content-Length", body.length());
    client.sendHeader("Connection", "close");
    client.beginBody();
    client.print(body);
    client.endRequest();

    int    statusCode = client.responseStatusCode();
    String response   = client.responseBody();

    Serial.print("Status Code: "); Serial.println(statusCode);
    Serial.print("Response   : "); Serial.println(response);

    if (statusCode == 200) {
        Serial.println("Data saved OK!");
    } else {
        Serial.print("POST failed. Status: "); Serial.println(statusCode);
    }

    client.stop();
}

// ============================================================
//  GSM / SIM800L
// ============================================================
void initGSM() {
    Serial.println("Initializing GSM...");
    lcdMsg("GSM Init...", "Please wait...");

    delay(8000); // SIM800L full boot time

    sendAT("AT",        1500);
    sendAT("AT+CSQ",    1500);   // signal quality
    sendAT("AT+CREG?",  1500);   // network registration
    sendAT("AT+CMGF=1", 1500);   // SMS text mode
    sendAT("AT+CNMI=1,2,0,0,0", 1500);

    gsmRegistered = checkGSMRegistration();

    if (gsmRegistered) {
        lcdMsg("GSM Ready", "Network OK");
        Serial.println("GSM registered on network.");
    } else {
        lcdMsg("GSM: No SIM/Net", "SMS disabled");
        Serial.println("GSM not registered — no SIM or no signal.");
    }
    delay(2000);
}

bool checkGSMRegistration() {
    sim800l.println("AT+CREG?");
    delay(1500);

    String response = "";
    unsigned long t = millis();
    while (millis() - t < 2000) {
        if (sim800l.available()) {
            response += (char)sim800l.read();
        }
    }

    Serial.println("CREG response: " + response);

    // +CREG: 0,1 = registered home | +CREG: 0,5 = roaming
    return (response.indexOf(",1") != -1 || response.indexOf(",5") != -1);
}

void sendSMS(String message) {
    Serial.println("Sending SMS to " + String(SMS_RECIPIENT) + "...");

    sendAT("AT+CMGF=1", 1500);

    sim800l.print("AT+CMGS=\"");
    sim800l.print(SMS_RECIPIENT);
    sim800l.println("\"");
    delay(3000); // wait for '>' prompt

    sim800l.print(message);
    delay(500);
    sim800l.write(26); // CTRL+Z to send
    delay(5000);

    String resp = "";
    while (sim800l.available()) {
        resp += (char)sim800l.read();
    }
    Serial.println("SMS response: " + resp);
}

void sendAT(const char* cmd, int waitMs) {
    sim800l.println(cmd);
    delay(waitMs);
    String resp = "";
    while (sim800l.available()) {
        resp += (char)sim800l.read();
    }
    if (resp.length() > 0) {
        Serial.println(String(cmd) + " → " + resp);
    }
}

// ── Utility: two-line LCD message ───────────────────────────
void lcdMsg(const char* line1, const char* line2) {
    lcd.clear();
    lcd.setCursor(0, 0); lcd.print(line1);
    lcd.setCursor(0, 1); lcd.print(line2);
}
