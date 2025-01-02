[![IPS-Version](https://img.shields.io/badge/Symcon_Version-6.0+-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Code](https://img.shields.io/badge/Code-PHP-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Funktionsreferenz](#4-funktionsreferenz)
5. [Konfiguration](#5-konfiguration)
6. [Anhang](#6-anhang)
7. [Versions-Historie](#7-versions-historie)

## 1. Funktionsumfang

Der Funktionsumfang unterscheidet sich natürlich je nach Gerätetyp, grundsätzlich aber
- Übernahme des Status des Gerätes sowie der Umweltsensoren
- Steuerung aller Funktionen, wie z.b. Lüfter ein/aus, Stärke und Richtung des Luftstroms, Timer etc.

## 2. Voraussetzungen

- IP-Symcon ab Version 6.0
- ein Dyson-Produkt, das Wifi und MQTT unterstützt, das ist offensichtlich bei Lüftern und Staubsaugern der Fall, die mit der _Dyson Link App_ verknüpft werden können.

Getestet wurde das Modul bisher mit:

| Typ | Bezeichnung |
| :-- | :---------- |
| 358 | Dyson Pure Cool Turmventilator mit Luftreiniger und Luftbefeuchter (PH01) |
| 358E | Dyson Pure Cool Turmventilator mit Luftreiniger und Luftbefeuchter (PH03) |
| 358K | Dyson Luftreiniger Humidify+Cool Formaldehyd (PH04) |
| 438 | Dyson Pure Cool Turmventilator mit Luftreiniger (TP04) |
| 438E | Dyson Pure Cool Turmventilator mit Luftreiniger (TP07) |
| 438K | Dyson Purifier Cool Formaldehyde (TP09) |
| 455 | Dyson Pure Hot+Cool Turmventilator mit Heizung und Luftreiniger (HP02) |
| 469 | Dyson Pure Cool Tischventilator mit Luftreiniger (DP02) |
| 475 | Dyson Pure Cool Turmventilator mit Luftreiniger (TP02) |
| 520 | Dyson Pure Cool Tischventilator mit Luftreiniger (DP04) |
| 527 | Dyson Pure Hot+Cool Turmventilator mit Heizung und Luftreiniger (HP04) |
| 664 | Dyson Luftreiniger Big+Quiet Formaldehyd (BP03) |

Es sollten alle der genannten Geräte das grundlegende Protokoll beherrschen, jedoch gibt es einige kleinere Unterschiede.
Mit einigen Anpassungen sollte das auch bei anderen Modellen funktionieren. Bei Bedarf bitte an den Autor wenden.

Das Modul basiert unter anderem auf Informationen aus dem Projekt [libpurecool](https://github.com/CharlesBlonde/libpurecoollink).

## 3. Installation

### a. Laden des Moduls

**Installieren über den Module-Store**

Die Webconsole von IP-Symcon mit http://\<IP-Symcon IP>:3777/console/ öffnen.

Anschließend oben rechts auf das Symbol für den Modulstore (IP-Symcon > 5.1) klicken

Im Suchfeld nun Dyson eingeben, das Modul auswählen und auf Installieren drücken.

**Installieren über die Modules-Instanz**

Die Konsole von IP-Symcon öffnen. Im Objektbaum unter Kerninstanzen die Instanz __*Modules*__ durch einen doppelten Mausklick öffnen.

In der _Modules_ Instanz rechts oben auf den Button __*Hinzufügen*__ drücken.

In dem sich öffnenden Fenster folgende URL hinzufügen:

`https://github.com/demel42/IPSymconDyson.git`

und mit _OK_ bestätigen. Ggfs. auf anderen Branch wechseln (Modul-Eintrag editieren, _Zweig_ auswählen).

Anschließend erscheint ein Eintrag für das Modul in der Liste der Instanz _Modules_

### b. Einrichtung in IPS

#### DysoConfig

In IP-Symcon nun unterhalb von _Konfigurator Instanzen_ die Funktion _Instanz hinzufügen_ (_CTRL+1_) auswählen, als Hersteller _Dyson_ und als Gerät _Dyson Konfigurator_ auswählen.

In dem Konfigurationsdialog die Dyson-Zugangsdaten eintragen.

Hier werden alle Dyson-Produkte, die mit dem angegebenen Dyson-Konto verknüpft sind, angeboten; aus denen wählt man ein Produkt aus.

Mit den Schaltflächen _Erstellen_ bzw. _Alle erstellen_ werden das/die gewählte Produkt(e) anlegt. Dabei werden 3 Instanzen erzeugt:
- DysonDevice<br>
Die Daten des Dyson-Accounts werden von DysonConfig hierhin dupliziert, Änderungen hieran müssen manuelle nachgeführt werden.
Die Produkte werden aufgrund der _Seriennummer_ identifiziert; durch den _Produkt-Typ_ wird der Umfang der Variablen festgelegt.
- MQTT Client<br>
in der Instanz wird bei der Anlage der Instanz alles konfiguriert und kann nachträglich nicht geändert werden.
Tip: die erzeugte Instanz danach sinnvoll benennen, da für jedes Gerät eine solche Instanz angelegt wird.
- Client Socket<br>
in dieser Instanz muss noch der Hostname bzw. die IP-Adresse des Dyson-Gerätes eingetragen werden… die vorgegebenen Port-Nummer _1883_ darf nicht geändert werden

Zu den Geräte-Instanzen werden im Rahmen der Konfiguration Produkttyp-abhängig Variablen angelegt.

Die Instanzen können dann in gewohnter Weise im Objektbaum frei positioniert werden.

## 4. Funktionsreferenz

`Dyson_UpdateStatus(int $InstanzID)`<br>
Auslösen einer Aktualisierungs-Anforderug an das Gerät.

Alle auslösbaren Aktionen stehen per RequestAction zur Verfügung, z.B. Einschalten des Gerätes:
`RequestAction(<ID der Variable 'Power'>, true)`

## 5. Konfiguration

### DysonConfig

#### Properties

| Eigenschaft               | Typ      | Standardwert | Beschreibung |
| :------------------------ | :------  | :----------- | :----------- |
| Dyson-Zugangsdaten        | string   |              | Benutzername und Passwort des Dyson-Cloud sowie das Land |
|                           |          |              | |
| Kategorie                 | integer  | 0            | Kategorie im Objektbaum, unter dem die Instanzen angelegt werden _[1]_ |
| Produkt                   |          |              | Liste der mit dem Konto verknüpften Geräte |

_[1]_: nur bis IPS-Version 7 vorhanden, danach ist eine Einstellmöglichkeit Bestandteil des Standard-Konfigurators

#### Actions

| Bezeichnung                | Beschreibung |
| :------------------------- | :----------- |
| Anmeldung durchführen      | Anmeldung an der Dyson-Cloud |


Die 2FA-Anmeldung funktioniert unter Umständen nur zusammen mit der Dyson-App auf dem Mobilgerät.
Manchmal reicht es aus, einfach die Dyson-App zu starten, manchmal muss man etwas mehr machen:
- in der App vom Dyson-Konto abmelden
- erneut Anmeldung beginnen mit Eingabe vom Land un der Mail-Adresse - nicht abschliessen!
- in dem Konfigurator den Bestätigungs-Code anfordern (Schritt 1)
- Postfach prüfen auf Mail von Dyson mit Code
- Code eingeben und bestätigen (Schritt 2)
War das erfolgreich, kann man sich in der Dyson-App wieder vollständig anmelden.
**Ab der Modul-Version 2.16 sollte die Einbeziehung der Dyson-App nicht mehr zwingend erforderlich sein.**

### DysonDevice

#### Properties

| Eigenschaft               | Typ      | Standardwert | Beschreibung |
| :------------------------ | :------  | :----------- | :----------- |
| Instanz deaktivieren      | boolean  | false        | Instanz temporär deaktivieren |
|                           |          |              | |
| Dyson-Zugangsdaten        | string   |              | Benutzername und Passwort des Dyson-Cloud sowie das Land |
|                           |          |              | |
| Status abrufen            | integer  | 1            | Abruf des Geräte-Status alle X Minuten |

#### Actions

| Bezeichnung                | Beschreibung |
| :------------------------- | :----------- |
| Daten aktualisieren        | Geräte-Status abfragen |

### Konfiguration neu laden und ggf. neu anmelden

Das erneute Laden der Konfiguration ist nur im Bedarfsfall durchzuführen - bisher war das noch nie erforderlich.<br>
Es werden die Daten im MQTT-Client gesetzt, die auch bei der Anlage eine Geräte-Instanz aus dem Konfigurator gesetzt werden.

Eine erneute Anmeldung ist nur erforderlich, wenn es bei dem Abruf der Konfiguration ein Authetifizierungsfehler geben würden; Vorgehen siehe Konfigurator.

### Test-Bereich

alle schaltbaren Statusvariablen der Instanz können hier geschaltet werden.

### Experten-Bereich

Hier können zum Testen von Steuerfunktionen (für neue Modelle) die ensprechenden Kommandos geschickt werden
Das Eingabefeld _Kommando_ muss einen JSON-Encoded-String enthalten, z.B. `{"fpwr":"ON"}`

### Variablenprofile

Es werden folgende Variablenprofile angelegt:
* Boolean<br>
Dyson.AirflowDirection,
Dyson.AirflowDistribution

* Integer<br>
Dyson.AirflowRate,
Dyson.AQT,
Dyson.Dust-Index,
Dyson.HCHO,
Dyson.NOx,
Dyson.Percent,
Dyson.PM10,
Dyson.PM25,
Dyson.Rotation,
Dyson.RotationAngle,
Dyson.RotationMode2,
Dyson.RotationStart,
Dyson.SleepTimer,
Dyson.VOC,
Dyson.VOC-Index,
Dyson.Wifi

* Float<br>
Dyson.HCHO,
Dyson.HeatingTemperature,
Dyson.Humidity,
Dyson.Temperature

## 6. Anhang

Quellen
- https://github.com/CharlesBlonde/libpurecoollink
- https://github.com/JanJaapKo/DysonPureLink
- https://github.com/lukasroegner/homebridge-dyson-pure-cool

GUIDs
- Modul: `{20E34F64-7545-2129-AF26-58F098F3ECC5}`
- Instanzen:
  - DysonConfig: `{2D4C75B7-A445-9A25-6669-FB391DDF35CF}`
  - DysonDevice: `{D1A42861-0280-E373-A07E-EC51D3B43951}`

## 7. Versions-Historie

- 2.17 @ 02.01.2025 14:28
  - Fix: Type 438K (Dyson Purifier Cool Formaldehyde (TP09)) ist nicht mit einem Luftbefeuchter ausgestattet
  - update submodule CommonStubs

- 2.16 @ 26.07.2024 11:22
  - Neu: Modell 438K (Dyson Purifier Cool Formaldehyde (TP09)) hinzugefügt
  - Fix: Verbesserung der 2FA-Loginprozedur
  - Fix: ungenutze Funktion entfernt
  - update submodule CommonStubs

- 2.15 @ 18.03.2024 17:00
  - Neu: Modell 664 (Dyson Luftreiniger Big+Quiet Formaldehyd (BP03)) hinzugefügt

- 2.14 @ 05.03.2024 15:12
  - Fix: Darstellungsfehler im Variablenprofil "Dyson.Humidify" behoben
  - update submodule CommonStubs

- 2.13 @ 07.02.2024 17:38
  - Fix: Absicherung von Zugriffen auf andere Instanzen in Konfiguratoren

- 2.12 @ 05.02.2024 15:13
  - Fix: Darstellungsfehler im Variablenprofil "Dyson.AirflowRate" behoben
  - update submodule CommonStubs

- 2.11 @ 09.12.2023 17:23
  - Neu: ab IPS-Version 7 ist im Konfigurator die Angabe einer Import-Kategorie integriert, daher entfällt die bisher vorhandene separate Einstellmöglichkeit

- 2.10 @ 03.11.2023 11:06
  - Neu: Ermittlung von Speicherbedarf und Laufzeit (aktuell und für 31 Tage) und Anzeige im Panel "Information"
  - update submodule CommonStubs

- 2.9 @ 05.07.2023 17:02
  - Vorbereitung auf IPS 7 / PHP 8.2
  - Neu: Schalter, um die Meldung eines inaktiven Gateway zu steuern
  - update submodule CommonStubs
    - Absicherung bei Zugriff auf Objekte und Inhalte

- 2.8 @ 01.03.2023 08:50
  - Fix: Modell 358K (PH04) ergänzt um die Übernahme des Formaldehyd
  - update submodule CommonStubs

- 2.7.1 @ 21.11.2022 19:35
  - Fix: Korrektur zu 2.6

- 2.7 @ 19.11.2022 16:38
  - Modell 358K (Dyson Luftreiniger Humidify+Cool Formaldehyd (PH04)) hinzugefügt
  - update submodule CommonStubs

- 2.6 @ 31.10.2022 15:48
  - es gibt Versionen von Dyson-Modellen, bei denen die Oscillation ('oson') per 'OION'/'OIOF' statt 'ON'/'OFF' gesteuert wird. Das wird nun vom Modul selbsttätig ermittelt.

- 2.5.4 @ 12.10.2022 14:44
  - Konfigurator betrachtet nun nur noch Geräte, die entweder noch nicht angelegt wurden oder mit dem gleichen I/O verbunden sind
  - update submodule CommonStubs

- 2.5.3 @ 07.10.2022 13:59
  - update submodule CommonStubs
    Fix: Update-Prüfung wieder funktionsfähig

- 2.5.2 @ 16.08.2022 10:10
  - update submodule CommonStubs
    Fix: in den Konfiguratoren war es nicht möglich, eine Instanz direkt unter dem Wurzelverzeichnis "IP-Symcon" zu erzeugen

- 2.5.1 @ 26.07.2022 10:28
  - update submodule CommonStubs
    Fix: CheckModuleUpdate() nicht mehr aufrufen, wenn das erstmalig installiert wird

- 2.5 @ 07.07.2022 11:54
  - einige Funktionen (GetFormElements, GetFormActions) waren fehlerhafterweise "protected" und nicht "private"
  - interne Funktionen sind nun private und ggfs nur noch via IPS_RequestAction() erreichbar
  - Fix: Angabe der Kompatibilität auf 6.2 korrigiert
  - Fix: Übersetzung ergänzt (Variablenprofil 'Dyson.RotationMode2')
  - Verbesserung: IPS-Status wird nur noch gesetzt, wenn er sich ändert
  - update submodule CommonStubs
    Fix: Ausgabe des nächsten Timer-Zeitpunkts

- 2.4.4 @ 17.05.2022 15:38
  - update submodule CommonStubs
    Fix: Absicherung gegen fehlende Objekte

- 2.4.3 @ 10.05.2022 15:06
  - update submodule CommonStubs
  - SetLocation() -> GetConfiguratorLocation()
  - weitere Absicherung ungültiger ID's

- 2.4.2 @ 30.04.2022 15:45
  - Überlagerung von Translate und Aufteilung von locale.json in 3 translation.json (Modul, libs und CommonStubs)

- 2.4.1 @ 26.04.2022 12:33
  - Korrektur: self::$IS_DEACTIVATED wieder IS_INACTIVE
  - IPS-Version ist nun minimal 6.0

- 2.4 @ 24.04.2022 15:07
  - Übersetzung vervollständigt
  - Implememtierung einer Update-Logik
  - diverse interne Änderungen

- 2.3.1 @ 16.04.2022 12:04
  - potentieller Namenskonflikt behoben (trait CommonStubs)
  - Aktualisierung von submodule CommonStubs

- 2.3 @ 11.04.2022 11:36
  - Konfigurator zeigt nun auch Instanzen an, die nicht mehr zu den vorhandenen Geräten passen
  - CheckConfiguration etabliert
  - Ausgabe der Instanz-Timer unter "Referenzen"

- 2.2.5 @ 17.03.2022 17:53
  - libs/common.php -> CommonStubs
  - Möglichkeit der Anzeige der Instanz-Referenzen

- 2.2.4 @ 20.02.2022 18:15
  - MQTT-Subscription um zusätzliche Topics erweitert
  Zum aktivieren: "Konfiguration erneut laden" + Client-Socket neu starten (Inaktiv/Aktiv schalten)
  - neue Variablen "Warnungen" und "Fehler"
  Hinweis: bei unbekannten Fehler, bitte Info an mich
  - Variablenprofile für PM10, PM25, VOX, NOx überarbeitet
  zum Aktivieren: "Variablenprofile erneut einrichten" sowie Variablen PM10 und PM25 löschen und neu anlegen lassen
  - Berechnung NOx und VOC verbessert
  - Anpassungen an IPS 6.2 (Prüfung auf ungültige ID's)

- 2.2.3 @ 05.02.2022 12:49
  - Korrektur Luftfeuchte für Modell 358E
  - Korrektur Luftstromstärke = AUS

- 2.2.2 @ 01.02.2022 17:15
  - Korrektur Luftstromstärke "Auto"

- 2.2.1 @ 31.01.2022 10:16
  - Korrektur Drehmodus für Modell 358E

- 2.2 @ 04.01.2022 13:46
  - Modell 358E (Dyson Pure Cool Turmventilator mit Luftreiniger und Luftbefeuchter (PH03)) hinzugefügt
  - Anzeige der Modul/Bibliotheks-Informationen

- 2.1 @ 06.09.2021 14:05
  - Umstellung auf internen MQTT-Client
    Achtung: nach der Aktualisierung die Aktion "MQTT-Client akualisieren" auslösen
  - erneute Parametrierung des MQTT-Clients durch die Aktion "Konfiguration erneut laden"

- 2.0 @ 24.07.2021 17:46
  - Umstellung auf 2FA-Anmeldung (Bestätigungs-Code per Mail)
  - Schalter "Instanz ist deaktiviert" umbenannt in "Instanz deaktivieren"

- 1.6 @ 19.04.2021 20:19
  - Summary in der Geräteinstanz auf Typ + Seriennummer setzen
  - Absicherung des Konfigurators

- 1.5 @ 31.03.2021 17:18
  - Modell 438E (Dyson Pure Cool Turmventilator mit Luftreiniger (TP07)) hinzugefügt

- 1.4 @ 28.02.2021 20:53
  - Länderauswahl ergänzt um Österreich, Schweiz, Niederlande, Frankreich
  - Fix, um den HTTP-Error 429 (too many requests) zu verhindern, der kommt, wenn zu häufig Login-Versuch fehlgeschlagen sind
    Das Modul wartet nun 5 min, bis es erneut ein Login versucht.
  - Korrektur nach Änderung der Login-API
  - fix für Typ 455

- 1.3 @ 29.01.2021 17:30
  - Modell 358 (Dyson Pure Cool Turmventilator mit Luftreiniger und Luftbefeuchter (PH01)) hinzugefügt
  - Modell 527 (Dyson Pure Hot+Cool Turmventilator mit Heizung und Luftreiniger (HP04)) hinzugefügt
  - PHP_CS_FIXER_IGNORE_ENV=1 in github/workflows/style.yml eingefügt
  - in den HTTP-Requests wird nun zusätzliche die Angabe des "User-Agent" erwartet, das Fehler wird mit einem HTTP-Error 403 quittiert

- 1.2 @ 26.08.2020 10:31
  - interne Funktionen sind nun "private"
  - library.php in local.php umbenannt
  - Traits des Moduls haben nun Postfix "Lib"
  - Modell 520 (Dyson Pure Cool Tischventilator mit Luftreiniger (DP04)) hinzugefügt
  - Modell 469 (Dyson Pure Cool Tischventilator mit Luftreiniger (DP02)) hinzugefügt
  - Modell 475 (Dyson Pure Cool Turmventilator mit Luftreiniger (TP02)) hinzugefügt
  - Schalter "Instanz ist deaktiviert" hinzugefügt

- 1.1 @ 21.07.2020 17:39
  - Modell 455 (Dyson Pure Hot+Cool Turmventilator mit Luftreiniger (HP02)) hinzugefügt mit Unterstützung durch [jbr27](https://www.symcon.de/forum/members/13374-jbr27)
  - fehlende Systemvoraussetzungen im Konfigurationsformular anzeigen
  - Nutzung von HasActiveParent(): Anzeige im Konfigurationsformular sowie entsprechende Absicherung von SendDataToParent()

- 1.0 @ 15.07.2020 10:08
  - Initiale Version
