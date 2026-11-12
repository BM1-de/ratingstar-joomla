# RatingStar für Joomla

RatingStar ist das Bewertungstool für Unternehmen: Kundenbewertungen sammeln,
Bewertungen aus Google und anderen Portalen bündeln und alles mit Siegeln auf
der eigenen Website zeigen.

Dieses Paket bringt die Siegel und Widgets von [RatingStar](https://ratingstar.de)
in Ihre Joomla-Website und liefert Google die strukturierten Daten für die
Sterne in der Suche.

| Erweiterung | Typ | Aufgabe |
|---|---|---|
| RatingStar (`mod_ratingstar`) | Site-Modul | zeigt ein Siegel oder Widget an einer Modulposition |
| System - RatingStar (`plg_system_ratingstar`) | System-Plugin | site-weite Einstellungen und die Sterne für Google |

Beide stecken in einem Installationspaket (`pkg_ratingstar.zip`).

## Voraussetzungen

- Joomla 6 (entwickelt und geprüft mit Joomla 6.2 unter PHP 8.4)
- ein RatingStar-Konto mit öffentlichem Profil
- für die Sterne in Google: ausgehende HTTPS-Verbindungen vom Webserver zu
  `ratingstar.de`

## Installation

1. Im Joomla-Backend **System → Installieren → Erweiterungen** öffnen und
   `pkg_ratingstar.zip` hochladen. Das Paket installiert Modul und Plugin in
   einem Schritt.
2. Unter **System → Verwalten → Plugins** das Plugin **System - RatingStar**
   öffnen, die Angaben eintragen (siehe unten) und es aktivieren.
3. Unter **Inhalt → Site-Module → Neu** ein Modul vom Typ **RatingStar**
   anlegen, Variante und Position wählen, veröffentlichen.
4. Die Domain Ihrer Website in RatingStar freigeben (siehe
   [Domain freigeben](#domain-freigeben)).

Updates meldet Joomla von selbst unter **System → Aktualisieren → Erweiterungen**:
Das Paket bringt einen Update-Server mit. Alternativ installieren Sie ein Update
genauso wie oben, indem Sie das neue Paket hochladen. Die Einstellungen bleiben
in beiden Fällen erhalten.

Download: [Releases auf GitHub](https://github.com/BM1-de/ratingstar-joomla/releases).

## Profil-Slug und API-Key

| Angabe | Wo Sie sie finden | Wofür |
|---|---|---|
| **Profil-Slug** | der Teil nach `/t/` in der Adresse Ihres Profils, z. B. `cafe-sonne` bei `ratingstar.de/t/cafe-sonne` | Siegel und Widgets einer Filiale |
| **Gruppe** | der Teil nach `/g/` in der Adresse des Gruppenprofils | Siegel einer ganzen Gruppe über alle aktiven Filialen |
| **API-Key** | in RatingStar auf der Seite „Google-Sterne“, Form `rs_live_` und 40 Zeichen | Sterne für Google |

Tragen Sie die Angaben einmal im Plugin **System - RatingStar** ein. Sie gelten
dann für die ganze Website. Jedes Modul kann davon abweichen: Was Sie im Modul
eintragen, hat Vorrang.

Nach dem Speichern prüfen Plugin und Modul die Angaben bei RatingStar und
melden, welches Profil sie gefunden haben. Schlägt die Prüfung fehl, erscheint
ein Hinweis, gespeichert wird trotzdem, damit Sie einen Tippfehler jederzeit
korrigieren können.

Der API-Key bleibt gültig, auch wenn sich der Profil-Slug ändert. Erzeugen Sie
ihn in RatingStar neu, verliert der alte sofort seine Gültigkeit: Tragen Sie
dann den neuen im Plugin ein.

## Varianten

| Variante | Art | Tarif |
|---|---|---|
| Rundsiegel | Siegel | alle Tarife |
| Banderole | Siegel | alle Tarife |
| Profil-Karte | Live-Widget, an Ort und Stelle oder schwebend in einer Bildschirmecke | ab 4-Star |
| Bewertungsleiste | Live-Widget | ab 4-Star |
| Gesamtnote | Live-Widget | ab 4-Star |
| Zitat | Live-Widget | ab 4-Star |
| Karussell | Live-Widget | ab 4-Star |
| Bewertungswand | Live-Widget | ab 4-Star |
| Fußleiste | Live-Widget | ab 4-Star |

In kleineren Tarifen zeigt RatingStar anstelle eines Live-Widgets automatisch
die Banderole. Das Modul gibt nur den Platzhalter aus; Aussehen, Inhalt und die
Hinweise zur Herkunft der Bewertungen kommen von RatingStar und sind damit
immer aktuell.

Mehrere Module auf einer Seite sind möglich. Das Skript `seal.js` wird dabei
nur einmal geladen.

## Darstellung je Modul

Im Reiter **Darstellung** stehen die Optionen der gewählten Variante, zum
Beispiel Größe, Breite, Anzahl der Bewertungen, Farbschema oder Farben der
Fußleiste.

Jede Option steht auf **Standard (Portal)**. Dann gilt, was Sie in RatingStar
unter „Siegel & Widgets“ eingestellt haben, und Änderungen dort wirken ohne
weiteres Zutun. Wählen Sie nur dort einen eigenen Wert, wo ein Modul abweichen
soll. So lässt sich dieselbe Variante mehrfach mit unterschiedlicher
Darstellung einbinden.

Das Feld **Zusätzliche data-Attribute** ist für Fachleute gedacht: Dort fügen
Sie Attribute aus dem Code ein, den RatingStar unter „Siegel & Widgets“
erzeugt, zum Beispiel `data-car-count="4" data-car-width="s"`. Sie können auch
den ganzen Code einfügen, übernommen werden nur `data-`Attribute. Diese Angaben
haben Vorrang vor den Einzeloptionen. Profil, Gruppe, Variante und Position
legen Sie immer über die Felder des Moduls fest.

## Siegel einer Gruppe

Tragen Sie im Feld **Gruppe** den Slug der Gruppe ein (im Plugin für die ganze
Website oder in einem einzelnen Modul). Das Siegel zeigt dann die Bewertungen
aller aktiven Filialen der Gruppe. Alle neun Varianten stehen auch für Gruppen
bereit. In der Variante „Gesamtnote“ lässt sich die Note je Filiale ein- oder
ausblenden.

Ist in einem Modul eine Gruppe eingetragen, hat sie Vorrang vor dem
Profil-Slug desselben Moduls.

## Sterne für Google

Mit einem API-Key holt das Plugin die strukturierten Daten Ihrer Bewertungen
(schema.org) serverseitig bei RatingStar ab und gibt sie
im Seitenkopf aus.

- Je Seite gibt es genau einen Block. Die Siegel-Module unterdrücken dann die
  Ausgabe durch `seal.js` von selbst.
- Eine Gesamtbewertung enthält der Block ab dem 4-Star-Tarif und sobald es
  Bewertungen gibt. Es zählen dieselben Bewertungen wie in Ihrem Siegel.
- Für die Sterne einer Gruppe tragen Sie den API-Key der Gruppe ein.
- **Standortseiten:** Geben Sie einem Siegel-Modul im Reiter „Erweitert“ den
  API-Key der Filiale mit. Die Seiten, auf denen dieses Modul erscheint,
  erhalten dann die Daten der Filiale statt der site-weiten, etwa der Gruppe.
- Ob und wann Google Sterne anzeigt, entscheidet Google. Das Plugin liefert die
  technischen Voraussetzungen.

Ist der RatingStar-Server einmal nicht erreichbar oder der Schlüssel ungültig,
lässt das Plugin den Block weg. Ihre Seite wird unverändert ausgeliefert.

## Statisches Bild

Für Rundsiegel, Banderole und Profil-Karte gibt es im Modul die Ausgabe
**Statisches Bild (ohne JavaScript)**: ein Bild des Siegels mit Link zum
Profil. Es zeigt die aktuelle Bewertung, die Optionen aus dem Reiter
„Darstellung“ gelten dafür nicht. Als Motiv lässt sich statt des Siegels eine
Auszeichnung der Filiale wählen (Angabe in der Form Typ-Stufe, zum Beispiel
`count_total-25`).

## Einbindung in Beiträge

Ein Siegel im Text eines Beitrags zeigen Sie mit Joomla-Bordmitteln: Modul
anlegen und im Beitrag `{loadmoduleid 123}` einfügen (123 = ID des Moduls).
Zuständig ist das Joomla-Plugin „Inhalt - Module laden“.

## Caching

- Die Daten für Google speichert das Plugin 6 Stunden zwischen (einstellbar im
  Reiter „Erweitert“). Das funktioniert unabhängig davon, ob der Joomla-Cache
  in der Konfiguration eingeschaltet ist.
- Einen fehlgeschlagenen Abruf versucht das Plugin nach 15 Minuten erneut.
- Nimmt der Cache keine Einträge an (Ordner `administrator/cache` ohne
  Schreibrecht, Redis oder Memcached nicht erreichbar), gibt das Plugin keine
  Daten für Google aus und fragt RatingStar nicht ab. Der Grund steht dann im
  Log-Ordner von Joomla in `plg_system_ratingstar.php`.
- Sofort neu abholen: das Plugin speichern oder unter **System → Cache leeren**
  den Eintrag `plg_system_ratingstar` leeren.
- Die Siegel selbst lädt der Browser direkt von RatingStar. Änderungen an
  Bewertungen und Darstellung erscheinen dort ohne Zutun.

## Domain freigeben

Tragen Sie die Domain Ihrer Website in RatingStar unter **Siegel & Widgets →
Domains & Statistik** im Abschnitt „Freigegebene Domains“ ein und bestätigen
Sie sie mit dem angezeigten DNS-Eintrag (`TXT _ratingstar.ihre-domain.de`).

Sobald dort erstmals eine Domain bestätigt ist, liefert RatingStar die Daten
für Live-Siegel nur noch an bestätigte Domains. Auf allen anderen Websites
bleibt das Siegel leer. Das schützt Ihr Siegel vor fremder Verwendung.

Für ein Gruppen-Siegel gilt die eigene Liste im Backend der Gruppe, ebenfalls
unter „Siegel & Widgets → Domains & Statistik“. Der Abruf für die Sterne in
Google läuft über den API-Key und ist davon nicht betroffen.

## Datenschutz und Einwilligung

- **Live-Siegel:** Der Browser Ihrer Besucher lädt `seal.js`, `seal.css`, die
  Bewertungsdaten und ein Zählpixel vom RatingStar-Server (`ratingstar.de`).
  Nennen Sie diesen Server in Ihrer Datenschutzerklärung und berücksichtigen
  Sie ihn in Ihrem Consent-Tool.
- **Sterne für Google:** Die Daten holt Ihr Webserver ab. Der Browser Ihrer
  Besucher baut dafür keine Verbindung zu RatingStar auf.
- **Statisches Bild:** Der Browser lädt nur eine Bilddatei von RatingStar.

RatingStar wird in Deutschland gehostet.

## Fehlersuche

| Beobachtung | Mögliche Ursache |
|---|---|
| Das Siegel bleibt leer | Domain nicht freigegeben oder nicht per DNS bestätigt; Profil-Slug falsch; ein Skript- oder Consent-Blocker hält `seal.js` zurück |
| Statt des Live-Widgets erscheint die Banderole | Live-Widgets gibt es ab dem 4-Star-Tarif |
| Kein Block für Google im Seitenquelltext | Plugin nicht aktiviert; API-Key fehlt oder wurde neu erzeugt; Webserver erreicht RatingStar nicht; Cache nicht beschreibbar (siehe [Caching](#caching)) |
| Geänderte Angaben wirken nicht | Cache leeren (siehe [Caching](#caching)) |
| Angemeldete Administratoren sehen einen gelben Hinweis statt des Siegels | Im Modul und im Plugin ist weder Profil-Slug noch Gruppe eingetragen |

## Für Entwickler

```
ratingstar-joomla/
├── pkg_ratingstar.xml          Paket-Manifest
├── language/                   Sprachdateien des Pakets
├── mod_ratingstar/             Site-Modul
├── plg_system_ratingstar/      System-Plugin
└── build.sh                    baut dist/pkg_ratingstar.zip
```

```bash
./build.sh            # erzeugt dist/pkg_ratingstar.zip
```

Das Skript prüft, dass alle Manifeste dieselbe Version tragen, und packt nur
die Dateien, die die Erweiterungen zur Laufzeit brauchen.

Oberfläche und Meldungen sind übersetzbar; mitgeliefert werden Deutsch (de-DE)
und Englisch (en-GB).

## Lizenz

GNU General Public License, Version 2 oder später (GPL-2.0-or-later), siehe `LICENSE.txt`.
