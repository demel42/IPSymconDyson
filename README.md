[![IPS-Version](https://img.shields.io/badge/Symcon_Version-5.3+-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
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

- IP-Symcon ab Version 5.3
- ein Dyson-Produkt, das Wifi und MQTT unterstützt, das ist offensichtlich bei Lüftern und Staubsaugern der Fall, die mit der _Dyson Link App_ verknüpft werden können.
- das installierte Modul _MQTTClient_ von **Kai Schnittcher**

Getestet wurde das Modul bisher mit:

| Typ | Bezeichnung |
| :-- | :---------- |
| 358 | Dyson Pure Cool Turmventilator mit Luftreiniger und Luftbefeuchter (PH01) |
| 438 | Dyson Pure Cool Turmventilator mit Luftreiniger (TP04) |
| 438E | Dyson Pure Cool Turmventilator mit Luftreiniger (TP07) |
| 455 | Dyson Pure Hot+Cool Turmventilator mit Heizung und Luftreiniger (HP02) |
| 469 | Dyson Pure Cool Tischventilator mit Luftreiniger (DP02) |
| 475 | Dyson Pure Cool Turmventilator mit Luftreiniger (TP02) |
| 520 | Dyson Pure Cool Tischventilator mit Luftreiniger (DP04) |
| 527 | Dyson Pure Hot+Cool Turmventilator mit Heizung und Luftreiniger (HP04) |

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
- DysonDevice
Die Daten des Dyson-Accounts werden von DysonConfig hierhin dupliziert, Änderungen hieran müssen manuelle nachgeführt werden.
Die Produkte werden aufgrund der _Seriennummer_ identifiziert; durch den _Produkt-Typ_ wird der Umfang der Variablen festgelegt.
- MQTTClient
in der Instanz wird bei der Anlage der Instanz alles konfiguriert und kann nachträglich nicht geändert werden.
Tip: die erzeugte Instanz danach sinnvoll benennen, da für jedes Gerät eine solche Instanz angelegt wird.
- Client Socket
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
| Kategorie                 | integer  | 0            | Kategorie im Objektbaum, unter dem die Instanzen angelegt werden |
| Produkt                   |          |              | Liste der mit dem Konto verknüpften Geräte |

#### Actions

| Bezeichnung                | Beschreibung |
| :------------------------- | :----------- |
| Anmeldung durchführen      | Anmeldung an der Dyson-Cloud |


Die 2FA-Anmeldung funktioniert nur zusammen mit der Dyson-App
- zuerst in der App vom Dyson-Konto abmelden
- erneut Anmeldung beginnen mit Eingabe vom Land un der Mail-Adresse - nicht abschliessen!
- in dem Konfigurator den Bestätigungs-Code anfordern (Schritt 1)
- Postfach prüfen auf Mail von Dyson mit Code
- Code eingeben und bestätigen (Schritt 2)
War das erfolgreich kann man sich in der Dyson-App wieder vollständig anmelden.

### DysonDevice

#### Properties

| Eigenschaft               | Typ      | Standardwert | Beschreibung |
| :------------------------ | :------  | :----------- | :----------- |
| Instanz ist deaktiviert   | boolean  | false        | Instanz temporär deaktivieren |
|                           |          |              | |
| Dyson-Zugangsdaten        | string   |              | Benutzername und Passwort des Dyson-Cloud sowie das Land |
|                           |          |              | |
| Konfiguration abrufen     | integer  | 60           | Abrufen der Daten aus der Dyson-Cloud alle X Minuten |
| Status abrufen            | integer  | 0            | Abruf des Geräte-Status alle X Minuten |

Der Abruf der Daten aus der Dyson-Cloud ist erforderlich, weil in der Antwort das Passwort zur lokalen MQTT-Kommunikation mit dem Gerät geliefert wird.

#### Actions

| Bezeichnung                | Beschreibung |
| :------------------------- | :----------- |
| Erneut anmelden            | Test der Anmeldung an der Dyson-Cloud |
| Konfiguration erneut laden | Geräte-Konfiguration aus der Dyson-Cloud laden |
| Daten aktualisieren        | Geräte-Status abfragen |

### Test-Bereich

alle schaltbaren Statusvariablen der Instanz können hier geschaltet werden.

### Experten-Bereich

Hier können zum Testen von Steuerfunktionen (für neue Modelle) die ensprechenden Kommandos geschickt werden
Das Eingabefeld _Kommando_ muss einen JSON-Encoded-String enthalten, z.B. `{"fpwr":"ON"}`

### Variablenprofile

Es werden folgende Variablenprofile angelegt:
* Boolean<br>
Dyson.AirflowDirection, Dyson.AirflowDistribution

* Integer<br>
Dyson.AirflowRate, Dyson.AQT, Dyson.NOx, Dyson.Percent, Dyson.PM, Dyson.Dust-Index, Dyson.RotationAngle, Dyson.RotationStart, Dyson.SleepTimer, Dyson.VOC, Dyson.VOC-Index, Dyson.Wifi

* Float<br>
Dyson.HeatingTemperature, Dyson.Humidity, Dyson.Temperature

## 6. Anhang

Quellen
- https://github.com/CharlesBlonde/libpurecoollink

GUIDs
- Modul: `{20E34F64-7545-2129-AF26-58F098F3ECC5}`
- Instanzen:
  - DysonConfig: `{2D4C75B7-A445-9A25-6669-FB391DDF35CF}`
  - DysonDevice: `{D1A42861-0280-E373-A07E-EC51D3B43951}`

## 7. Versions-Historie

- 2.0 @ 14.07.2021 18:17 (beta)
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
