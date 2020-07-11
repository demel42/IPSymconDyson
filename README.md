# IPSymconDyson

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
| 438 | Dyson Pure Cool Turmventilator mit Luftreiniger |

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
Aktualiseren des Status des Gerätes.

Alle auslösbaren Aktionen stehen per _RequestAction_ zur Verfügung, z.B.<br>
`RequestAction(<ID der Variable 'Power'>, true)` aktiviert das Gerät.

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
| Erneut anmelden            | Test der Anmeldung an der Dyson-Cloud |

### DysonDevice

#### Properties

| Eigenschaft               | Typ      | Standardwert | Beschreibung |
| :------------------------ | :------  | :----------- | :----------- |
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

## 6. Anhang

Quellen
- https://github.com/CharlesBlonde/libpurecoollink

GUIDs
- Modul: `{20E34F64-7545-2129-AF26-58F098F3ECC5}`
- Instanzen:
  - DysonConfig: `{2D4C75B7-A445-9A25-6669-FB391DDF35CF}`
  - DysonDevice: `{D1A42861-0280-E373-A07E-EC51D3B43951}`

## 7. Versions-Historie

- 1.0 @ 11.07.2020 16:19
  - Initiale Version
