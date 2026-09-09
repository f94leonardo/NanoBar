# Changelog

Tutte le modifiche rilevanti a questo progetto sono documentate in questo file.
Formato: [Keep a Changelog](https://keepachangelog.com/it/1.0.0/)
Versionamento: [SemVer](https://semver.org/lang/it/)

## [Unreleased]

## [1.0.1] - 2026-09-09

Preparazione alla pubblicazione sul repository ufficiale dei plugin di WordPress.org.

### Modificato

- `nanobar.php` non richiede più `composer install`: l'autoloader Composer è stato sostituito da un piccolo autoloader PSR-4 proprio (nessuna dipendenza a runtime), così il plugin funziona subito dopo l'attivazione da zip, senza alcuno step di build lato utente.
- `Requires PHP` abbassato da `8.4` a `8.0`: è il minimo effettivamente richiesto dal codice (tipo `mixed`, `str_starts_with()`); un minimo così alto come 8.4 avrebbe escluso inutilmente gran parte degli hosting. Aggiornato in `nanobar.php`, `readme.txt`, `composer.json` e `README.md`.
- `bin/build-zip.sh`: rimosso lo step `composer install --no-dev` (non più necessario); `composer.json`/`composer.lock` sono ora esclusi dal pacchetto fin dall'inizio invece di essere copiati e poi cancellati; lo ZIP viene ora scritto in `dist/` invece che nella root del progetto.

### Aggiunto

- `readme.txt`, nel formato standard richiesto da WordPress.org (header, `== Description ==`, `== Installation ==`, `== FAQ ==`, `== Changelog ==`, ecc.).
- `LICENSE`: testo completo della GPLv2.
- `.wordpress-org/`: cartella (esclusa dallo ZIP del plugin) dove tenere screenshot, icona e banner da caricare a parte nella cartella `assets/` dell'SVN di WordPress.org; vedi `.wordpress-org/README.md` (in inglese). Contiene `icon-128x128.png`/`icon-256x256.png` e `banner-772x250.png`/`banner-1544x500.png` (generati: ingranaggio bianco, coerente con l'icona `dashicons-admin-generic` usata nel toggle del pannello, su sfondo sfumato indaco→viola come nella pagina impostazioni; il banner aggiunge la wordmark "NanoBar" e un breve tagline) e `screenshot-1.png`/`-2.png`/`-3.png`.
- `README.md`: nuova sezione "Building a release" che documenta `bin/build-zip.sh` (flag `--skip-npm`/`--skip-lint`, dove finisce lo ZIP, cosa viene escluso).

## [1.0.0] - 2026-09-08

Prima release del plugin (in sostituzione della vecchia versione mu-plugin, conservata sul branch `mu-plugin`).

### Aggiunto

- Tab-bar automatica per telefono: sotto i 600px il pulsante flottante + menu a comparsa diventa una barra fissa in basso, sempre visibile, con icona ed etichetta per ogni voce (Bacheca, Modifica sito, Nuovo, Esci) — sostituisce le righe "solo icone" del vecchio menu a comparsa, illeggibili a quella larghezza.
- Sotto i 480px la tab-bar nasconde le etichette testuali (solo icone); per le voci con sottomenu (Bacheca, Nuovo) la freccina di apertura si sposta sopra l'icona principale invece che in un badge d'angolo, e resta allineata sulla stessa riga delle altre icone.
- `bin/build-zip.sh`: script per il pacchetto ZIP di produzione (lint PHPCS, build asset SCSS, `composer install --no-dev`, zip) — legge la versione dall'header di `nanobar.php`.

### Corretto

- Il pannello frontend non si apriva al click/tocco del pulsante: lo script veniva eseguito prima che il markup del pannello fosse stampato nel DOM (`render_panel()` è agganciato a `wp_footer` priorità 999, mentre WordPress stampa gli script di footer a priorità 20 di default). L'inizializzazione ora attende `DOMContentLoaded`.
- `phpcs.xml.dist` non escludeva `.phpstan-cache/`: la cache di PHPStan (file `.php` generati contenenti array serializzati di grandi dimensioni) veniva tokenizzata da PHPCS come fosse codice sorgente, esaurendo il memory_limit di PHP.
