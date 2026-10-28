# PV Energiefluss für IP-Symcon

Responsive HTMLBox mit animierten Leistungsflüssen und eingebettetem Archivdiagramm (24 Stunden, Stundenmittel). Keine externen Bibliotheken, Webhooks oder Zugangsdaten. Die Flüsse zeigen die Richtung zwischen Komponenten und dem Hausbus, keine nachgewiesene Zuordnung einzelner Energiequellen zu Verbrauchern.

## Installation als Symcon-Modul (empfohlen)

Voraussetzung: IP-Symcon ab 7.0. Dieses Repository enthält jetzt eine `library.json` und das Modul **PV Energiefluss**.

1. In der Symcon-Modulverwaltung das Repository `https://github.com/somm86/PV-bersicht.git` hinzufügen bzw. den vorhandenen Eintrag aktualisieren. Als Branch **main** auswählen.
2. Eine neue Instanz **PV Energiefluss** anlegen.
3. In der Instanzkonfiguration die Leistungs- und Ladestandsvariablen auswählen. Die IDs aus den Screenshots sind voreingestellt. Zusätzlich können weitere Detailwerte zugeordnet werden (0 = nicht zugeordnet). PV, Speicher und Netz müssen W liefern; die Wallbox kW. Netz positiv = Bezug, Speicher positiv = Laden.
4. Änderungen übernehmen. Unter der Instanz wird die HTMLBox-Variable **PV Energiefluss** erstellt und regelmäßig aktualisiert. Mit **Jetzt aktualisieren** lässt sie sich sofort erneuern.
5. Für die native PHP/HTML-Kachel in der Kachel Visu die **Instanz „PV Energiefluss“ selbst** in die Visualisierung aufnehmen. Das Modul stellt die HTML-Ausgabe über `GetVisualizationTile()` bereit und aktualisiert sie über `UpdateVisualizationValue()`. Für IPSView bleibt alternativ die darunterliegende HTMLBox-Variable verfügbar. Es müssen keine Dateien manuell in das Skriptverzeichnis kopiert werden.

Die native Darstellung setzt eine Symcon-Version mit HTML-Instanzvisualisierung (`SetVisualizationType`) voraus. Nach dem Modulupdate die Instanzkonfiguration einmal übernehmen. Falls die Instanz auf deiner Version nicht als Kachel angeboten wird, Symcon-Version nennen; bis dahin die HTMLBox verwenden. Die native API-Anbindung ist lokal geprüft, ihre Darstellung auf dem Zielsystem noch nicht.

Wenn bereits die Skriptversion läuft, deren Skripttimer auf 0 setzen und die neue HTMLBox verwenden, um doppelte Aktualisierungen zu vermeiden. Die Modulinstallation wurde hier anhand der Metadaten und PHP-Syntax geprüft; der Test in einer echten Symcon-Laufzeit steht noch aus.

## Alternative: Installation als Skript

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

Dieser Abschnitt betrifft ausschließlich die alternative Skriptinstallation. Bei der Modulinstallation sind keine manuellen Kopiervorgänge nötig. Die Dateien werden durch `include` nicht heruntergeladen. Der folgende Aufbau muss auf dem Server vorhanden sein:

```text
/mnt/data/symcon/scripts/
├── 32867.ips.php             # das in Symcon angelegte aufrufende Skript
└── PV-bersicht/
    ├── tile.php
    └── PVEnergyFlow/
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
| PV-Erzeugung gesamt | 54550 | kWh laut Screenshot |
| Netzbezug gesamt | 30181 | kWh laut Screenshot |
| Netzeinspeisung gesamt | 29267 | kWh laut Screenshot |
| Wallbox Ladezyklus | 17564 | kWh laut Screenshot |
| PV / Netzbezug / Einspeisung heute | 0 | Optional: jeweilige Tagesvariable in kWh auswählen |

**Die oben genannten Rohwerteinheiten sind vom Nutzer bestätigt:** PV, Speicher und Netz liefern Watt; die Wallbox liefert Kilowatt. Die voreingestellten Faktoren passen dazu. Bei einem späteren Wechsel der Quellen die Rohwerte erneut prüfen: Für W Faktor 1, für kW Faktor 1000 verwenden. Bei entgegengesetzter Zählrichtung den Faktor negieren. IDs lassen sich zentral am Anfang von `tile.php` ändern; nicht vorhandene Werte erscheinen als „—“.

Die PV-Quelle 22209 („mit Speicherberücksichtigung“) liefert laut Bestätigung des Nutzers die momentane PV-Erzeugung und ist die richtige Quelle. Ebenfalls bestätigt: Netz negativ = Einspeisung, positiv = Bezug; Speicher positiv = Laden, negativ = Entladen. Diese Richtungen sind bereits in der Kachel hinterlegt. Der Hausverbrauch ohne Wallbox wird als `PV + Netz − Batterieladung − Wallbox` berechnet. Negative Ergebnisse unter −50 W werden als unplausibel markiert. Verluste und zeitversetzte Messungen können die Bilanz beeinflussen. Eine positive Bilanz bestätigt die korrekte Zuordnung nicht.

## Darstellung

Dunkle Verlaufskarten mit Lichtkanten, Farbakzenten und Schatten sowie räumliche SVG-Illustrationen mit perspektivischen Seitenflächen, Glas- und Metallverläufen sowie Bodenschatten für Solarmodule, Batterie, Haus, Strommast und Wallbox mit Fahrzeug sowie animierte Leitungen zeigen die Anlage. Die zentrale Hauskarte ist ohne umlaufende Kreise gestaltet und zeigt ein Haussymbol und den berechneten Leistungswert ohne Textbeschriftung; die Berechnung bleibt unverändert. Speicher- und Fahrzeugladestand erscheinen zusätzlich als Balken. PV-Gesamterzeugung, Netzenergiezähler und Energie im Wallbox-Ladezyklus ergänzen die Leistungswerte. Die Gesamtwerte sind reine Zählerstände in kWh; sie und der Ladezyklus haben keinen Diagrammaufruf und verursachen keine Archivabfragen. Klicks auf diese Zahlen öffnen auch nicht versehentlich den Leistungsgraphen der übergeordneten Karte. Die untere Viererzeile und die Speichertemperatur wurden entfernt. Drei optionale Tageswerte (PV, Netzbezug, Einspeisung) werden erst sichtbar, wenn die jeweiligen kWh-Variablen in der Instanz gewählt sind. Die Screenshots zeigen dafür VerbrauchZeitspanne-Instanzen, aber keine sicher zuordenbaren Ergebnisvariablen; deshalb sind diese IDs mit 0 vorbelegt. Es handelt sich bei den Gesamtwerten ausdrücklich nicht um Tageswerte. Leistungswerte, Akkustände und optional konfigurierte Tageswerte sind für den Archivverlauf anklickbar; nicht zugeordnete Werte erscheinen als „—“.

## Archiv und Aktualisierung

Für die jeweilige Variable muss die Archivierung im Archive Control aktiv sein. Antippen eines Leistungswertes öffnet dessen Verlauf; beim Speicher und bei der Wallbox ist auch der jeweilige Akkustand erreichbar. Es wird die erste Archive-Control-Instanz verwendet. Bereits aufgezeichnete Daten müssen für Stundenaggregate verfügbar sein. Fehlende Archivierung, leere Zeitbereiche und Lesefehler werden separat angezeigt. Die Grafik verwendet die konfigurierten Faktoren einschließlich Vorzeichen; die aktuelle Leistungsanzeige zeigt den Betrag plus Richtung. Diagramme zeigen Stundenmittel, keine Spitzen oder Energiebilanzen.

Die gesamte HTMLBox wird standardmäßig alle 30 Sekunden erneuert (im Modul konfigurierbar); dabei kann ein geöffnetes Diagramm schließen. Dies vermeidet einen öffentlich erreichbaren Datenendpunkt. Keine automatische Aktivierung von Archivierung oder Steuerung von Wallbox/Speicher. Die eingebettete Seite benötigt JavaScript und moderne WebView-Unterstützung (`dialog`, SVG, srcdoc). Animationen stoppen bei aktivierter Systemeinstellung für reduzierte Bewegung.

## Entwicklung und Prüfung

Bestehenden Checkout verwenden; kein zusätzlicher Worktree nötig. Die Kachel selbst benötigt keine Node-Abhängigkeiten; die Entwicklungsprüfungen benötigen Node.js ab 22.13 und die fest versionierten Dev-Abhängigkeiten. JavaScript befindet sich in `PVEnergyFlow/assets/tile.html`. Auf Repository-Ebene liegt nur der Modulordner `PVEnergyFlow`; Ressourcen und Tests liegen darin, damit Symcon sie nicht als weitere Module interpretiert.

```sh
npm ci
npm test
```

Vierzehn automatisierte Prüfungen bestanden (einschließlich Modulmetadaten, Konfigurationszuordnung und Prüfung der Modulordnerstruktur): PHP-Syntax mit PHP-8-Parser, Hausbilanz, Lade-/Entlade- und Netzrichtungen, Nullfluss, Diagrammöffnung und Archivpunkte, SOC-Umschaltung, leeres/deaktiviertes/fehlerhaftes Archiv sowie fehlende und unplausible Eingangsdaten. Die Interaktionen werden mit jsdom geprüft; native Dialogmethoden werden dort simuliert. Das ersetzt keinen visuellen Browsertest.

Optional mit lokal installiertem PHP und Chromium:

```sh
php -l tile.php
python3 PVEnergyFlow/tests/smoke.py
```

Der Chromium-Smoke-Test prüft synthetische Daten im echten Headless-Browser. Er konnte in dieser Cloud-Instanz wegen eines Chromium-Start-Timeouts nicht abgeschlossen werden. PHP CLI ist hier nicht verfügbar; die PHP-Syntax wurde per Parser geprüft, die Symcon-API und PHP-Ausführung bislang nicht. Installation auf dem echten Symcon-System, Kachel Visu und IPSView bleiben notwendige Integrationstests.
