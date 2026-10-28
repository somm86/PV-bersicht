# PV Energiefluss für IP-Symcon

Responsive HTMLBox mit animierten Leistungsflüssen und eingebettetem Archivdiagramm (24 Stunden, Stundenmittel). Keine externen Bibliotheken, Webhooks oder Zugangsdaten. Die Flüsse zeigen die Richtung zwischen Komponenten und dem Hausbus, keine nachgewiesene Zuordnung einzelner Energiequellen zu Verbrauchern.

## Installation

1. [ZIP von `main` herunterladen](https://github.com/somm86/PV-bersicht/archive/refs/heads/main.zip), entpacken und den Ordner `PV-bersicht-main` in `PV-bersicht` umbenennen. Diesen Ordner in das Verzeichnis kopieren, in dem die Symcon-Skripte (`*.ips.php`) liegen. Auf dem hier verwendeten System ist das `/mnt/data/symcon/scripts/`. Die Dateien gehören auf den Symcon-Server. Bei Docker das entsprechende eingebundene Datenverzeichnis verwenden.
2. Ein **neues, ausschließlich dafür verwendetes PHP-Skript** in Symcon anlegen und folgenden Inhalt eintragen:

   ```php
   <?php
   include __DIR__ . '/PV-bersicht/tile.php';
   ```

3. In `tile.php` die Variablenzuordnung und Faktoren prüfen. Anschließend das neue Skript einmal ausführen. Es erstellt darunter die Stringvariable „PV Energiefluss“ mit Profil `~HTMLBox` und aktiviert seinen 30-Sekunden-Timer. Zum Abschalten den Skripttimer auf 0 setzen.
4. Die HTMLBox-Variable in die Kachelvisualisierung aufnehmen. Breite und Höhe in der Visualisierung einstellen; empfohlen mindestens 320 × 300 px. Das SVG passt sich beiden Abmessungen an und behält die Proportionen bei. Große oder schmale Kacheln erhalten freie Ränder, damit Beschriftungen lesbar bleiben.
5. Für IPSView dieselbe Variable als HTMLBox verwenden, sofern die eingesetzte IPSView-Version HTMLBox mit iframe/srcdoc und JavaScript unterstützt. Diese Unterstützung muss auf dem Zielclient geprüft werden; eine native IPSView-Komponente ist dies nicht.

## Wenn die Datei nicht gefunden wird

Die Einrichtung ist ein PHP-Skript mit HTMLBox, kein Modul für die Symcon-Modulverwaltung. Die Dateien werden durch `include` nicht heruntergeladen. Der folgende Aufbau muss auf dem Server vorhanden sein:

```text
/mnt/data/symcon/scripts/
├── 32867.ips.php             # das in Symcon angelegte aufrufende Skript
└── PV-bersicht/
    ├── tile.php
    └── assets/
        └── tile.html
```

Der Name `PV-bersicht-main` aus dem ZIP und ein zusätzlicher verschachtelter Ordner sind häufige Ursachen für „No such file or directory“. Der Ausdruck `__DIR__` im aufrufenden Skript verwendet dessen tatsächliches Verzeichnis, auch wenn `IPS_GetKernelDir()` einen anderen Pfad zurückgibt. Anschließend das Skript in Symcon erneut ausführen.

## Zuordnung aus den Screenshots

| Anzeige | ID | Bestätigter Rohwert |
| --- | --- | --- |
| PV | 22209 | W, momentane PV-Erzeugung |
| Speicherleistung | 18192 | W, positiv Laden |
| Netzleistung | 13288 | W, positiv Bezug / negativ Einspeisung |
| Wallbox | 31735 | kW → Faktor 1000 |
| Speicherstand | 45769 | % |
| Fahrzeugakku | 48784 | % |

**Die oben genannten Rohwerteinheiten sind vom Nutzer bestätigt:** PV, Speicher und Netz liefern Watt; die Wallbox liefert Kilowatt. Die voreingestellten Faktoren passen dazu. Bei einem späteren Wechsel der Quellen die Rohwerte erneut prüfen: Für W Faktor 1, für kW Faktor 1000 verwenden. Bei entgegengesetzter Zählrichtung den Faktor negieren. IDs lassen sich zentral am Anfang von `tile.php` ändern; nicht vorhandene Werte erscheinen als „—“.

Die PV-Quelle 22209 („mit Speicherberücksichtigung“) liefert laut Bestätigung des Nutzers die momentane PV-Erzeugung und ist die richtige Quelle. Ebenfalls bestätigt: Netz negativ = Einspeisung, positiv = Bezug; Speicher positiv = Laden, negativ = Entladen. Diese Richtungen sind bereits in der Kachel hinterlegt. Der Hausverbrauch ohne Wallbox wird als `PV + Netz − Batterieladung − Wallbox` berechnet. Negative Ergebnisse unter −50 W werden als unplausibel markiert. Verluste und zeitversetzte Messungen können die Bilanz beeinflussen. Eine positive Bilanz bestätigt die korrekte Zuordnung nicht.

## Archiv und Aktualisierung

Für die jeweilige Variable muss die Archivierung im Archive Control aktiv sein. Antippen eines Leistungswertes öffnet dessen Verlauf; beim Speicher und bei der Wallbox ist auch der jeweilige Akkustand erreichbar. Es wird die erste Archive-Control-Instanz verwendet. Bereits aufgezeichnete Daten müssen für Stundenaggregate verfügbar sein. Fehlende Archivierung, leere Zeitbereiche und Lesefehler werden separat angezeigt. Die Grafik verwendet die konfigurierten Faktoren einschließlich Vorzeichen; die aktuelle Leistungsanzeige zeigt den Betrag plus Richtung. Diagramme zeigen Stundenmittel, keine Spitzen oder Energiebilanzen.

Die gesamte HTMLBox wird alle 30 Sekunden erneuert; dabei kann ein geöffnetes Diagramm schließen. Dies vermeidet einen öffentlich erreichbaren Datenendpunkt. Keine automatische Aktivierung von Archivierung oder Steuerung von Wallbox/Speicher. Die eingebettete Seite benötigt JavaScript und moderne WebView-Unterstützung (`dialog`, SVG, srcdoc). Animationen stoppen bei aktivierter Systemeinstellung für reduzierte Bewegung.

## Entwicklung und Prüfung

Bestehenden Checkout verwenden; kein zusätzlicher Worktree nötig. Die Kachel selbst benötigt keine Node-Abhängigkeiten; die Entwicklungsprüfungen benötigen Node.js ab 22.13 und die fest versionierten Dev-Abhängigkeiten. JavaScript befindet sich in `assets/tile.html`.

```sh
npm ci
npm test
```

Acht automatisierte Prüfungen bestanden: PHP-Syntax mit PHP-8-Parser, Hausbilanz, Lade-/Entlade- und Netzrichtungen, Nullfluss, Diagrammöffnung und Archivpunkte, SOC-Umschaltung, leeres/deaktiviertes/fehlerhaftes Archiv sowie fehlende und unplausible Eingangsdaten. Die Interaktionen werden mit jsdom geprüft; native Dialogmethoden werden dort simuliert. Das ersetzt keinen visuellen Browsertest.

Optional mit lokal installiertem PHP und Chromium:

```sh
php -l tile.php
python3 tests/smoke.py
```

Der Chromium-Smoke-Test prüft synthetische Daten im echten Headless-Browser. Er konnte in dieser Cloud-Instanz wegen eines Chromium-Start-Timeouts nicht abgeschlossen werden. PHP CLI ist hier nicht verfügbar; die PHP-Syntax wurde per Parser geprüft, die Symcon-API und PHP-Ausführung bislang nicht. Installation auf dem echten Symcon-System, Kachel Visu und IPSView bleiben notwendige Integrationstests.
