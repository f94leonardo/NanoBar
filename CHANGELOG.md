# Changelog

Tutte le modifiche rilevanti a questo progetto sono documentate in questo file.
Formato: [Keep a Changelog](https://keepachangelog.com/it/1.0.0/)
Versionamento: [SemVer](https://semver.org/lang/it/)

## [Unreleased]

## [1.0.0] - 2026-09-08

Prima release del plugin (in sostituzione della vecchia versione mu-plugin, conservata sul branch `mu-plugin`).

### Aggiunto

- Tab-bar automatica per telefono: sotto i 600px il pulsante flottante + menu a comparsa diventa una barra fissa in basso, sempre visibile, con icona ed etichetta per ogni voce (Bacheca, Modifica sito, Nuovo, Esci) — sostituisce le righe "solo icone" del vecchio menu a comparsa, illeggibili a quella larghezza.
- Sotto i 480px la tab-bar nasconde le etichette testuali (solo icone); per le voci con sottomenu (Bacheca, Nuovo) la freccina di apertura si sposta sopra l'icona principale invece che in un badge d'angolo, e resta allineata sulla stessa riga delle altre icone.
- `bin/build-zip.sh`: script per il pacchetto ZIP di produzione (lint PHPCS, build asset SCSS, `composer install --no-dev`, zip) — legge la versione dall'header di `nanobar.php`.

### Corretto

- Il pannello frontend non si apriva al click/tocco del pulsante: lo script veniva eseguito prima che il markup del pannello fosse stampato nel DOM (`render_panel()` è agganciato a `wp_footer` priorità 999, mentre WordPress stampa gli script di footer a priorità 20 di default). L'inizializzazione ora attende `DOMContentLoaded`.
- `phpcs.xml.dist` non escludeva `.phpstan-cache/`: la cache di PHPStan (file `.php` generati contenenti array serializzati di grandi dimensioni) veniva tokenizzata da PHPCS come fosse codice sorgente, esaurendo il memory_limit di PHP.
