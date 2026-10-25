# RatingStar für Joomla

Bindet das RatingStar-Bewertungssiegel und die Google-Sterne (Rich Snippets)
einer RatingStar-Filiale (https://ratingstar.de) in eine Joomla-Website ein.

Das Produkt besteht aus **zwei Extensions**, gebündelt in einem
Installations-Paket (`pkg_ratingstar`):

| Extension | Typ | Aufgabe |
|---|---|---|
| `mod_ratingstar` | Site-Modul | Siegel-/Widget-Embed (`.rs-seal` + `seal.js`) |
| `plg_system_ratingstar` | System-Plugin | server-seitiges `LocalBusiness`-JSON-LD im Seitenkopf |

**Status:** Beide Extensions implementiert (Joomla 6, moderne Struktur).

## Siegel-Modul (`mod_ratingstar`)

Gibt das offizielle `.rs-seal`-Markup aus und lädt `seal.js` einmal pro Seite;
das Rendering übernimmt RatingStar. Parameter pro Modul-Instanz:

- **Profil-Slug** — der `/t/<slug>`-Handle der Filiale.
- **Widget-Variante** — voller Satz: Rundsiegel, Banderole (statisch) sowie
  Profil-Karte, Bewertungsleiste, Gesamtnote, Zitat, Karussell,
  Bewertungswand, Fußleiste (live, ab 4-Star-Tarif). Früher gespeicherte
  `floating`-Module laufen als Profil-Karte weiter.
- **Position** — Profil-Karte im Inhalt (inline) oder schwebend in einer
  Bildschirm-Ecke.
- **Farbschema** — hell oder dunkel für Bewertungsleiste, Gesamtnote, Zitat,
  Karussell und Bewertungswand (`data-theme`).
- **Darstellung** — Live-Widget (seal.js) oder **statisches Bild** ohne
  JavaScript (`<img>` des Siegel-SVG: Banderole/Rundsiegel/Karte, oder ein
  Auszeichnungs-Badge) — für E-Mail/PDF/AMP/JS-aus (CONTRACT §12).
- **Basis-Origin** (erweitert) — Standard `https://ratingstar.de`.
- **Client-Rich-Snippet unterdrücken** (erweitert) — setzt
  `data-no-richsnippet="1"`, wenn das JSON-LD-Plugin aktiv ist.

## JSON-LD-Plugin (`plg_system_ratingstar`)

Holt das fertige `LocalBusiness`-JSON-LD vom key-basierten Endpunkt
(`<origin>/seal/k/<key>.jsonld`), cacht es server-seitig und injiziert **genau
einen** `<script type="application/ld+json">`-Block in den Seitenkopf — ohne
Client-Verbindung. Reicht das Schema unverändert durch (nacktes
`LocalBusiness` ohne Bewertungen unter dem 4-Star-Tarif / bei 0 Bewertungen,
`aggregateRating` + `review[]` darüber). Setzt `data-rs-jsonld`, wodurch
`seal.js` seine eigene Client-Injektion unterdrückt → ein Block pro Seite.

Parameter: **API-Key** (`rs_live_…`), **Basis-Origin**, **Cache-Dauer**.

Beim Speichern einer Siegel-Modul-Instanz prüft das Plugin zusätzlich
**nicht-blockierend** den Slug (`GET <origin>/t/<slug>`) und bestätigt den
Profilnamen aus dem `<title>` bzw. warnt — der Wert wird immer gespeichert
(CONTRACT §3).

> **Freigegebene Domains:** Sobald im RatingStar-Admin unter „Siegel & Widgets →
> Domains & Statistik“ eine Domain DNS-verifiziert ist, werden die
> *slug-basierten* Siegel-Daten nur noch an verifizierte Domains ausgeliefert —
> die Domain dort eintragen und verifizieren. Der key-basierte JSON-LD-Weg ist auf allen Tarifen offen.
>
> **Datenschutz:** `seal.js` & Co. laden von der RatingStar-Origin (Dritt-Host)
> — in Datenschutzerklärung/Consent nennen. Der server-seitige JSON-LD-Weg
> braucht keine Client-Verbindung.

## Build & Installation

```bash
./build.sh            # erzeugt dist/pkg_ratingstar.zip
```

Das `dist/pkg_ratingstar.zip` im Joomla-Backend unter **System → Installieren →
Erweiterungen** hochladen — installiert Modul + Plugin in einem Schritt.
Anschließend: Modul-Instanz unter **Inhalt → Site-Module** anlegen
(Slug + Variante), Plugin unter **System → Plugins** aktivieren (API-Key).

## Repo-Struktur

```
ratingstar-joomla/
├── pkg_ratingstar.xml          Paket-Manifest
├── mod_ratingstar/             Site-Modul (moderne J6-Struktur)
├── plg_system_ratingstar/      System-Plugin
├── language/                   Paket-Sprachdateien
└── build.sh                    baut dist/pkg_ratingstar.zip
```
