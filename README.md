# RatingStar für Joomla

Bindet das RatingStar-Siegel und die Google-Sterne (Rich Snippets) einer
RatingStar-Filiale (https://ratingstar.de) in eine Joomla-Website ein.

**Status: in Entwicklung** — Funktionsumfang und Roadmap stehen in den
Issues dieses Repos.

## Geplanter Funktionsumfang

- Modul-Parameter: RatingStar-Profil-Slug + Embed-Key, Varianten-Auswahl
- Siegel-Widget (Rundsiegel, Banderole, Floating-Badge) als Site-Modul
- Google-Sterne: serverseitiges JSON-LD (System-Plugin) über den
  offiziellen RatingStar-Endpunkt (`/seal/k/<key>.json`)

## Entwicklung

Dieses Repo enthält **nur das Modul**. Die lokale Test-Instanz liegt
drumherum: DDEV-Projekt `ratingstar-plugin-joomla`
(https://ratingstar-plugin-joomla.ddev.site, Administrator
`/administrator`, User `admin`), das Modul ist dort unter
`modules/mod_ratingstar` ausgecheckt — Installation über
System → Entdecken (Discover).

```bash
cd ~/Sites/Dev/ratingstar-plugin-joomla
ddev start
```
