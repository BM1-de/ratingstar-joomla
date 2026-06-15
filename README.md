# RatingStar für Joomla

Bindet das RatingStar-Siegel bzw. Bewertungs-Widget einer RatingStar-Filiale
(https://ratingstar.de) als **Site-Modul** in eine Joomla-Website ein. Das
Rendering übernimmt RatingStars `seal.js`; das Modul gibt nur das offizielle
`.rs-seal`-Markup aus und lädt das Script einmal pro Seite.

**Status:** Siegel-Modul implementiert (Joomla 6). Server-seitiges
Google-Sterne-JSON-LD (System-Plugin) folgt als nächster Schritt.

## Funktionsumfang

- Modul-Parameter:
  - **Profil-Slug** — der `/t/<slug>`-Handle der Filiale.
  - **Widget-Variante** — voller Satz: Rundsiegel, Banderole (statisch) sowie
    Profil-Karte, Trust-Bar, Floating-Badge, Hero-Snippet, Featured Quote,
    Carousel, Wall of Love, Footer-Bar (live, ab 4-Star-Tarif).
  - **Position** — Bildschirm-Ecke für Floating-Badge / Footer-Bar
    (überschreibt die RatingStar-Konfiguration; nur diese beiden Varianten).
  - **Basis-Origin** (erweitert) — Standard `https://ratingstar.de`; für
    Staging / Self-Hosting anpassbar. Alle URLs werden daraus gebaut.
  - **Client-Rich-Snippet unterdrücken** (erweitert) — setzt
    `data-no-richsnippet="1"`, sobald das JSON-LD server-seitig ausgeliefert
    wird (genau ein JSON-LD-Block pro Seite).
- Lädt `seal.js` per WebAssetManager **einmal pro Seite**, auch bei mehreren
  Modul-Instanzen.
- Mehrsprachig (de-DE / en-GB), alle Attributwerte HTML-escaped.

## Installation

Modul-Dateien nach `modules/mod_ratingstar` legen, dann im Backend unter
**System → Verwalten → Entdecken (Discover)** das Modul `RatingStar` finden und
installieren. Anschließend unter **Inhalt → Site-Module** eine Modul-Instanz
anlegen, den Profil-Slug und die Variante setzen und einer Modul-Position bzw.
Seite zuweisen.

> **Auslieferung:** Sobald im RatingStar-Admin (Tab „Auslieferung“) eine
> Liefer-Domain eingetragen ist, werden die Siegel-Daten nur an DNS-verifizierte
> Domains ausgeliefert — die Domain dieser Website dort eintragen und
> verifizieren.
>
> **Datenschutz:** `seal.js`, `seal.css`, die Widget-Daten und das
> Tracking-Pixel laden von der RatingStar-Origin (Dritt-Host). In der
> Datenschutzerklärung nennen und im Consent-Tool behandeln.

## Entwicklung

Dieses Repo enthält **nur das Modul** (moderne Joomla-6-Struktur:
`services/provider.php`, `src/Dispatcher`, `src/Helper`, `tmpl`). Die lokale
Test-Instanz liegt drumherum: DDEV-Projekt `ratingstar-plugin-joomla`
(https://ratingstar-plugin-joomla.ddev.site, Administrator `/administrator`).

```bash
cd ~/Sites/Dev/ratingstar-plugin-joomla
ddev start
```
