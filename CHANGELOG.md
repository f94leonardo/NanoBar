# Changelog

Tutte le modifiche rilevanti a questo progetto sono documentate in questo file.
Formato: [Keep a Changelog](https://keepachangelog.com/it/1.0.0/)
Versionamento: [SemVer](https://semver.org/lang/it/)

## [1.0.0] - 2026-09-25

### Modificato

- Preparazione all'invio su WordPress.org, dopo una verifica con Plugin Check (eseguita su una copia dello ZIP compilato):
  - `readme.txt`: `Tested up to` è ora `7.1` (Plugin Check accetta solo maggiore.minore, non la patch; copre anche la 7.1.2 su cui il plugin è stato provato). Aggiunti Backup (export/import) e FastCache all'elenco delle funzioni, il tag `block editor` è sostituito da `quick links` e c'è una nuova sezione «Source code» con il link al repository. `README.md` è allineato.
  - `bin/build-zip.sh`: lo ZIP non include più i file nascosti (`.stylelintrc.json`, `.prettierrc`, `.prettierignore`), `eslint.config.js` e `CHANGELOG.md`, che Plugin Check segnala come errori o file superflui. Include invece `assets/scss/`, i sorgenti del CSS minificato (linea guida 4 della directory).
- `Settings\Backup`: la lettura di `$_FILES['nanobar_import_file']` passa da `wp_unslash()` e `absint()` (warning `InputNotSanitized` di Plugin Check); nessun cambiamento di comportamento.

### Corretto

- Link rapidi: un URL senza `/`, `?` o `#` iniziale (es. `contatti/`, `esempio.com/pagina`) ora è segnalato come non valido lato JS; prima passava il controllo, ma `esc_url_raw()` lo trasformava in `http://contatti/` e il salvataggio lo scartava in silenzio.
- Selettore icone: `dashicons-before` (classe di supporto, non un'icona) non è più proposto.
- Cmd/Ctrl+S non genera più un `TypeError` con gli eventi di autofill di Chrome (senza `event.key`).
- «Annulla ripristino»: dopo un salvataggio il toast viene rimosso, invece di restare visibile senza effetto.
- Logout: il redirect mantiene la query string della pagina corrente.
- Import backup: un file oltre `upload_max_filesize` dà «file troppo grande» invece di «nessun file caricato»; un file senza alcune chiavi (es. `enabled`) non le spegne più, ma usa i valori predefiniti.
- Pulsanti Cache: la admin bar nativa (necessaria al click proxy) viene mantenuta attiva anche per gli amministratori che l'hanno disattivata dal profilo, quando la voce Cache è visibile e un plugin di cache è attivo.
- Tab bar telefono: frecce ed Esc funzionano anche quando il pannello non ha lo stato `is-open`.
- Command palette: le voci «Bacheca» e «Cache» eseguono davvero l'azione anche a ≤666px e in «Solo icone» (prima aprivano solo il sottomenu); le voci duplicate sono elencate una volta sola; il campo ha un nome accessibile, `aria-activedescendant`/`aria-selected`, il focus resta nel dialog e Cmd/Ctrl+K non viene intercettato mentre si scrive in un altro campo.
- Pagina opzioni, header sticky: la disposizione a riga unica (titolo, menu, pulsanti) ora parte da 1320px invece di 1000px. Con il menu di WordPress aperto l'header è circa 220px più stretto della finestra e il menu delle sezioni, che ha bisogno di circa 600px, veniva compresso e tagliato ai due lati (visibile intorno a 1108px). Sotto quella soglia resta l'header su due righe, dove il menu ha tutta la larghezza.
- Pagina opzioni, header sticky (≥1000px, riga unica): il sottotitolo, invisibile ma ancora largo quanto la sua riga di testo, occupava tutto lo spazio e schiacciava il menu delle sezioni a larghezza zero, spingendo i pulsanti fuori dall'header; ora nello stato sticky è tolto dal layout (`display: none`).
- Pagina opzioni, header sticky: con poco spazio orizzontale il menu delle sezioni tagliava le prime voci a sinistra (centratura di un contenitore scorrevole) senza modo di raggiungerle; ora parte dalla prima voce, è centrato solo quando entra tutto e scorre per tenere in vista la sezione corrente.
- Contrasto (pagina opzioni): il testo delle voci deselezionate passa da `#787c82` a `#646970` (4,9:1) e l'impostazione «Prefer Elementor» inattiva usa opacità 0,85 invece di 0,5.
- Documentazione e commenti: allineati i breakpoint reali (tab bar telefono ≤580px e voci nascoste sul telefono; «Bacheca» che apre il sottomenu ≤666px); aggiornata di conseguenza la stringa tradotta della card «Voci del pannello» (`.pot`, `.po`, `.mo`).
- Il riepilogo del file di backup non interpreta più `$&`/`$'` presenti nella versione del file.

### Rimosso

- `Plugin::load_textdomain()` e la chiamata a `load_plugin_textdomain()` (sconsigliata da WordPress 4.6: le traduzioni dei plugin della directory sono caricate da WordPress). Le traduzioni in `languages/` non vengono più caricate nelle installazioni manuali fuori dalla directory.

## [0.0.5] - 2026-09-24

### Modificato

- Pagina opzioni, «Ruoli abilitati» e «Voci del pannello»: il checkbox unico «Seleziona tutto» torna ai due pulsanti «Seleziona tutto» / «Seleziona nessuno» in un controllo segmentato (come prima della 2.0.0).
- Backup ridisegnato: la card unica diventa due card affiancate, «Esporta» (con l'elenco di cosa include) e «Importa». L'importazione ha un'area drag & drop cliccabile al posto del campo file del browser: mostra nome e dimensione del file scelto con un pulsante per rimuoverlo, controlla lato client che sia un export di NanoBar (mostrando versione e numero di link rapidi, oppure l'errore) e tiene disabilitato «Importa impostazioni» finché non c'è un file valido. Prima di sostituire le impostazioni compare una conferma inline (Importa/Annulla), come per «Ripristina i valori predefiniti». Nuovi pulsanti `.nanobar-btn` (44px, con icona, stati focus e disabilitato). «Backup» è anche nella navigazione dell'header. Senza JavaScript il form resta un normale file input con pulsante di invio. Nuovo modulo `assets/js/admin-backup.js`.

## [0.0.4] - 2026-09-24

### Corretto

- Pagina opzioni, salvataggio in place: dopo il salvataggio il riallineamento dei campi ai valori del server (introdotto in 2.0.1) scriveva lo stato dell'ultimo ruolo sul primo checkbox `roles[]`, quindi l'interfaccia poteva mostrare «Amministratore» deselezionato anche se il salvataggio era corretto (un secondo salvataggio l'avrebbe poi persistito). Ora ogni input viene abbinato per nome, tipo e valore.

### Modificato

- Refactoring interno, nessun cambiamento visibile: `Settings\Page` ora si occupa solo di hook, asset e struttura della pagina, mentre il markup delle card è in `Settings\View` (un metodo per card) e la card Backup e il suo avviso sono in `Settings\Backup`. Il markup generato è identico a prima (verificato confrontando l'HTML delle due versioni).
- `assets/js/admin-settings.js` (1600 righe in un'unica closure) è diviso in moduli con responsabilità singola — `admin-core.js` (stato, toast), `admin-layout.js`, `admin-preview.js`, `admin-items.js`, `admin-quick-links.js`, `admin-save.js`, `admin-defaults.js` — che si registrano su `window.NanoBarAdmin` e sono inizializzati da `admin-settings.js`. Le funzioni condivise passano dall'oggetto `app` e lo stato «modifiche non salvate» è in `app.state`.

## [0.0.3] - 2026-09-24

### Sicurezza

- Il JSON delle voci extra della command palette, stampato in un `<script type="application/json">`, ora è codificato con `JSON_HEX_TAG`/`JSON_HEX_AMP`/`JSON_HEX_APOS`/`JSON_HEX_QUOT`: un'etichetta o un URL proveniente dal filtro `nanobar_command_palette_items` non può più chiudere il tag `<script>` (XSS).
- Gli URL delle voci extra della command palette passano da `esc_url_raw()` (lato PHP) e sono controllati come http(s)/relativi prima della navigazione (lato JS), quindi uno schema `javascript:` non viene più seguito.
- Gli id dei nodi admin bar dei plugin di cache (filtro `nanobar_cache_plugins`) sono limitati a caratteri validi in un id DOM e cercati con `getElementById()` invece che con un selettore CSS costruito a stringa.
- `Sanitizer`, `QuickLinks`, `Page`, `Commands` e `Menus` verificano il tipo dei valori (array/scalari inattesi da form manipolati, DB modificato a mano o filtri) invece di castarli, evitando `TypeError`/warning; `nanobar_options` e `nanobar_logout_redirect` ricadono sul valore originale se il filtro restituisce un tipo sbagliato.
- `uninstall.php` rimuove anche il transient con l'elenco dei dashicons.

### Corretto

- Il sottomenu (flyout) del pannello non esce più dallo schermo su viewport bassi: viene riportato dentro con una `transform`, qualunque sia l'ancoraggio (in alto o in basso).
- `uninstall.php` in multisite: alla disinstallazione di un plugin attivato a livello di rete ora ripulisce le opzioni e i transient di ogni sito, non solo del sito corrente.
- Pagina opzioni, card Aspetto: le due colonne (dimensione | colore, «Solo icone» | schema colori) ora reagiscono alla larghezza della sotto-card (container query, una colonna sotto i 480px) e non a quella della finestra, quindi si impilano anche quando le card sono affiancate ma strette.
- `Options::clamp_toggle_size()`: un valore enorme in `px` (es. `1e999`) diventava 32 invece di 72, perché il cast a intero di `INF` dà 0; ora si limita prima e si arrotonda dopo (scoperto dai nuovi test).
- Modalità «Solo icone»: il gruppo Cache non ha più la freccia; il suo tile apre il sottomenu (con Impostazioni e svuota cache) invece di lanciare l'azione. Voci del sottomenu alte 40px come quelle del menu.
- Frontend: il pulsante di apertura poteva diventare un'ellisse quando il tema applicava padding o larghezze minime ai `<button>`; ora ha `box-sizing: border-box`, padding e margini azzerati e `aspect-ratio: 1`, quindi resta sempre un cerchio.
- Pagina opzioni, salvataggio in place: dopo il salvataggio il form si riallinea ai valori effettivamente salvati dal server (dimensione e unità limitate o arrotondate, colore, checkbox), invece di lasciare quelli digitati con la scritta «salvato».
- Pagina opzioni, «Annulla ripristino»: se si salva prima di annullare, l'annullamento non reinserisce più righe e valori obsoleti.
- Pagina opzioni: Cmd/Ctrl+S non salva più mentre il selettore icone è aperto.
- `Sanitizer`: se `mobile_items` manca del tutto (ad esempio un aggiornamento via WP-CLI) le voci restano visibili sulla barra mobile invece di essere nascoste tutte.
- Pagina opzioni: l'header sticky vibrava scorrendo lentamente fino al punto in cui si compatta. Il restringimento dell'header faceva intervenire lo scroll anchoring di Chrome, che riportava la pagina indietro e lo riespandeva in loop; ora `overflow-anchor` è disattivato per questa pagina.
- Pagina opzioni: i checkbox mostravano due segni di spunta (quello di WordPress core più quello personalizzato) perché le regole core `input[type="checkbox"]` sono più specifiche di una classe singola. Ora tutti i checkbox semplici usano `input[type="checkbox"].nanobar-checkbox` (un solo selettore al posto dei tre precedenti) e il segno di core è disattivato.

### Aggiunto

- Backup delle impostazioni: nuova card «Backup» in Impostazioni → NanoBar per scaricare le impostazioni salvate come JSON e reimportarle (anche su un altro sito). L'import accetta solo file di NanoBar fino a 256KB, richiede nonce e `manage_options` e passa dallo stesso `Sanitizer` del salvataggio normale (classe `Settings\Backup`, azioni `admin-post` `nanobar_export`/`nanobar_import`).
- Test PHPUnit anche sul percorso di lettura (`QuickLinks::get_visible`, filtri `nanobar_command_palette_items` e `nanobar_cache_plugins` con valori malformati) e su `Backup`; test Node (`npm run test:js`) per gli helper JS puri, spostati in `assets/js/admin-helpers.js` (URL interni/relativizzati, contrasto colore).
- Tastiera sul pannello: dal pulsante, Freccia giù apre il menu e porta il focus sulla prima voce; Su/Giù/Home/Fine scorrono le voci, Freccia destra apre il sottomenu di un gruppo e sinistra lo chiude; Esc chiude prima il sottomenu (riportando il focus sul suo tile) e poi il menu. Con `prefers-reduced-motion` le transizioni sono disattivate.
- Test PHPUnit (`composer run test`, cartella `tests/`) su `Options` (dimensione e unità, schema colori, fallback delle voci) e `Sanitizer` (URL dei link rapidi, tra cui `//`, `javascript:` e host esterni; icone, ruoli, limite di link, input non validi, `mobile_items`). Usano il WordPress del sito Local senza scrivere sul DB (`WP_LOAD_PATH` per cambiare percorso); esclusi dallo ZIP.
- Workflow GitHub Actions (`.github/workflows/ci.yml`): phpcs e phpstan, stylelint, eslint, prettier e verifica che il CSS compilato sia aggiornato.
- `Plugin::asset_version()`: in ambiente `local`/`development` (o con `SCRIPT_DEBUG`) gli asset usano la data di modifica del file come versione, così una build nuova non viene mai servita dalla cache del browser.
- Nuova impostazione «Unità di misura» per la dimensione del pulsante: `px` (32–72) o `rem` (2–4,5, a passi di 0,25), scelta con un controllo segmentato; cambiando unità il valore viene convertito (16px = 1rem) e l'intervallo/suggerimento si aggiornano. Opzione `toggle_size_unit`, resa sul frontend come `--nanobar-toggle-size`.
- Ogni voce del pannello ha un pulsante con lo smartphone per nasconderla dalla barra compatta sui telefoni (≤480px), come già avveniva per i link rapidi (opzione `mobile_items`, classe `nanobar__item--hide-mobile`); la voce resta comunque raggiungibile dalla command palette.
- Link rapidi: riordino con trascinamento (maniglia a sinistra di ogni riga, `jquery-ui-sortable`), stato vuoto con invito ad aggiungere il primo link, pulsante «Aggiungi link» tratteggiato a tutta larghezza.
- Dimensione icona: slider a tutta larghezza sotto il campo numerico, sincronizzato con esso e con i limiti dell'unità scelta.
- Colore di sfondo: barra con campione e codice esadecimale sul pulsante e palette di colori rapidi nel selettore.

### Modificato

- L'icona della Cache è ora `dashicons-database` (prima `dashicons-performance`, troppo simile a quella della Bacheca), sul pannello e nella pagina opzioni.
- Modalità «Solo icone»: Bacheca e Nuovo non mostrano più la freccia `>`; è l'icona stessa ad aprire il sottomenu (con `aria-expanded`), e nel sottomenu ricompare la voce Bacheca, altrimenti la sua destinazione non sarebbe più raggiungibile.
- Pagina opzioni, header: «Salva modifiche» e «Ripristina i valori predefiniti» sono ora pulsanti con testo (solo icona, con `title`, quando l'header è compatto o sotto i 782px), con accanto la scritta «Modifiche non salvate» (live region) oltre al pallino; una navigazione a sezioni (Generale, Ruoli abilitati, Aspetto, Voci del pannello, Link rapidi) sotto il titolo, che con l'header sticky (da 1000px in su) si dispone sulla stessa riga di titolo e pulsanti; evidenzia la sezione corrente e scorre senza animazione se l'utente preferisce meno movimento. Rimossi il secondo pulsante Salva/Ripristina in fondo e la nota «Le modifiche vengono applicate al salvataggio».
- Pagina opzioni, salvataggio in place: il form viene inviato con `fetch` a `options.php` (stesso endpoint e nonce), quindi scroll e stato restano dove sono; l'esito compare in un toast («Impostazioni salvate.») e le note del sanitizer diventano toast a loro volta. I link rapidi vengono sostituiti con la versione normalizzata dal server. Se qualcosa non va si ricade sull'invio normale del form (che è anche quello che funziona senza JS). Cmd/Ctrl+S salva.
- Pagina opzioni, «Ripristina i valori predefiniti»: niente più `window.confirm()`, ma una conferma inline (toast con Ripristina/Annulla) seguita da un toast con «Annulla ripristino» che riporta ogni campo, link rapidi compresi, allo stato precedente.
- Pagina opzioni, layout: Aspetto (che ora include anche la Posizione) e Anteprima stanno affiancati in una riga a sé; le due card hanno la stessa altezza (l'anteprima si adatta a quella di Aspetto, niente più sticky). Sotto i 1100px si impilano. Dimensione e colore stanno in due colonne; la card è divisa in due sotto-card, «Icona» (dimensione e colore, in due colonne) e «Pannello» («Solo icone», spostato qui da General, e schema colori, in due colonne); il suggerimento «Da 32px a 72px.» sta sotto il campo, e campo numero, px/rem e barra colore sono tutti alti 44px; «Dimensione» e «Colore di sfondo» si chiamano ora «Dimensione icona» e «Colore icona» e sono state tolte la nota sul contrasto automatico e il rapporto di contrasto.
- Pagina opzioni, posizione: al posto dei due select si sceglie direttamente sull'anteprima, cliccando uno dei 6 punti tratteggiati dove può stare il pulsante (radio group con frecce da tastiera; i due campi nascosti inviati sono gli stessi di prima). «Centro» è disabilitato, con la spiegazione nel `title` e sotto l'anteprima, fuori dal lato consentito da `Config`. Nella vista Mobile i punti non si mostrano.
- Pagina opzioni, anteprima: mostra il pannello aperto (voci selezionate con la loro icona, link rapidi, Esci separato), rispetta «Solo icone» e lo schema colori, e ha il selettore Desktop/Mobile (in Mobile la barra compatta in basso ≤480px con le sole voci spuntate per il telefono). Il pulsante apre/chiude il pannello. 
- Pagina opzioni, stato dei plugin: badge «Rilevato / Non rilevato» su Cache (con il nome del plugin), Query Monitor ed Elementor (sotto «Prefer Elementor when available»).
- Pagina opzioni, «Seleziona tutto»: un unico checkbox (con stato indeterminato) per Ruoli e Voci del pannello al posto dei due pulsanti Seleziona/Deseleziona. Le voci deselezionate sono grigie con bordo tratteggiato. Il pulsante smartphone di ogni voce ha ora uno stato inequivocabile (pieno con spunta se attivo, tratteggiato e barrato se spento) ed è 40px (44px su touch).
- Pagina opzioni, accessibilità: icon picker con frecce direzionali, tabindex «roving» e focus trap (non è più un `listbox` finto); `aria-live` sui contatori «N di M», `role="status"`/`role="alert"` sulle notifiche, che non spariscono più da sole; rispettato `prefers-reduced-motion`.
- Testi più brevi nelle descrizioni degli interruttori e coerenza dei termini italiani («Link rapidi», «Voci del pannello»; rimosse le etichette ridondanti «Scorciatoie personalizzate» e «Voci visibili»).
- Pagina opzioni, nuovo layout: riga 1 General | Enabled roles (ora una card a parte) | Appearance, riga 2 Position | Preview (la Preview riempie tutta l'altezza della card), poi Menu items e Quick links a tutta larghezza; sotto i 1100px due colonne, sotto i 782px una. Rimossa la preview «sticky», ormai inutile. «Icons only» e «Force admin bar for Query Monitor» stanno in General; nelle card le etichette, i suggerimenti (a destra dell'etichetta o sotto il campo) e le spaziature seguono ora una scala unica (`$space-*`). L'SCSS della pagina è suddiviso in `_cards`, `_fields`, `_menu-items` e `_quick-links` al posto del solo `_bento`. Le card non hanno più `overflow: hidden`, così il selettore colore e i tooltip non vengono tagliati.
- Link rapidi: righe ridisegnate (maniglia, icona quadrata, etichetta, URL, cestino; ruoli e «Visibile su mobile» come pillole attivabili).
- Codice JS/SCSS formattato con Prettier (`@wordpress/prettier-config`); riordinate alcune regole in `_header.scss`, `_bento.scss` e `_panel.scss` per rispettare `no-descending-specificity` (CSS compilato equivalente: stesse dichiarazioni, stesso ordine tra selettori di uguale specificità). La regola resta disattivata solo in `_panel.scss`, dove gli stati `.is-open` e i modificatori icons-only/tab-bar dipendono dall'ordine.
- L'`aria-label` del pulsante di chiusura delle notifiche in `admin-settings.js` ora è traducibile (`Chiudi la notifica`); aggiunte anche le traduzioni italiane mancanti di 4 stringhe dell'interfaccia (ripristino impostazioni, contatore link rapidi, «Visibile su mobile», «Posizione e aspetto»).
- Tooling di sviluppo allineato al tema Caine (ESLint 9, Stylelint 16, Prettier 3, `bin/local-wp.sh`, `composer check`); `phpcs.xml.dist` non usa più la sintassi deprecata delle proprietà array; corretti tutti i warning ESLint/Stylelint e PHPStan/PHPCS sono a zero.

## [0.0.2] - 2026-09-24

### Aggiunto

- Pagina opzioni: nuova card "Menu items" per scegliere quali voci principali del pannello mostrare (Bacheca, Modifica contenuto, Modifica sito, Nuovo, Query Monitor, Esci). Le voci disattivate restano nascoste indipendentemente dal contesto o dai permessi dell'utente; quelle attivate continuano comunque a rispettare i controlli esistenti (capability, contesto della pagina, plugin di terze parti attivi).
- Integrazione Elementor: se Elementor è attivo e l'opzione "Prefer Elementor when available" è abilitata, la voce "Modifica sito" viene sostituita da un link diretto a Elementor quando applicabile — al contenuto corrente se creato con l'editor di Elementor, oppure al Theme Builder di Elementor Pro se sta controllando il template corrente (header/footer/singolo/archivio). Altrimenti resta il comportamento esistente (Site Editor FSE o Aspetto → Temi).
- Nuova voce "Cache" nel pannello (visibile solo agli Amministratori, toggleabile come le altre dalla card "Menu items"): pulsante "svuota cache" con sottomenu che apre le impostazioni del plugin di cache attivo. Riconosce out-of-the-box WP Rocket, W3 Total Cache, WP Super Cache, LiteSpeed Cache e WP Fastest Cache (rilevazione best-effort, non verificata contro un'installazione reale di ciascuno); estendibile/correggibile via il filtro `nanobar_cache_plugins` senza modificare il plugin. Lo svuotamento riusa lo stesso meccanismo di "proxy click" sul nodo nascosto della admin bar nativa già usato per Query Monitor (generalizzato in `frontend.js` tramite l'attributo `data-proxy-node`, cosicché eredita gratis il nonce/la sicurezza del plugin di cache invece di reimplementarli).

### Modificato

- Pagina opzioni, card "Menu items": griglia dei toggle a 3 colonne (prima 2, condivisa con "Ruoli abilitati" che resta invece a 2 colonne), con un'icona per voce (lo stesso dashicon usato nel pannello frontend), un badge "N di 7 attive" aggiornato dal vivo via JS, e due pulsanti rapidi "Seleziona tutto"/"Deseleziona tutto". Il checkbox "Prefer Elementor when available" è ora visivamente collegato (bordo/rientro, più testo "Applies to 'Edit site', above") alla voce "Modifica sito" da cui dipende, e si attenua via CSS quando quella voce è disattivata — resta però un normale campo del form (mai `disabled`), cosicché il suo valore salvato non venga mai perso solo perché "Modifica sito" risulta momentaneamente spento.
- Traduzione italiana (`languages/nanobar-it_IT.po`/`.mo`) e catalogo sorgente (`languages/nanobar.pot`) rigenerati da zero con `wp i18n make-pot`/`update-po`/`make-mo`, per includere tutte le stringhe aggiunte nelle voci precedenti di questo changelog (Elementor, Cache, Menu items) — le traduzioni italiane già presenti sono state preservate.
- `nanobar.php`: rinominato il parametro `$class` dell'autoloader in `$class_name` (era una parola riservata usata come nome di parametro, segnalato da PHPCS). `Menus.php`: rimosso un controllo `is_string()` ridondante su `WP_Post_Type::$menu_icon`, già tipizzato `string` negli stub WordPress (segnalato da PHPStan). Nessun cambio di comportamento in entrambi i casi; il plugin ora passa PHPCS e PHPStan (livello 6) senza alcun errore o warning residuo.
- Pagina opzioni, card "Menu items": voci riordinate per rispecchiare l'ordine reale nel pannello (Bacheca, Nuovo, Modifica contenuto, Modifica sito, poi Cache/Query Monitor/Esci invariati). Il toggle "Prefer Elementor when available" non è più un blocco separato in fondo alla card, ma è annidato direttamente dentro la tile "Modifica sito" (che ora occupa un'intera riga della griglia) — resta comunque un campo normale del form, mai `disabled`, per non perdere il valore salvato quando "Modifica sito" è spento.
- I pulsanti "Seleziona tutto"/"Deseleziona tutto" sono ora un piccolo controllo segmentato con icone, invece di due link testuali separati da un punto; su schermi ≤480px diventano a piena larghezza, divisi equamente. Aggiornata anche la logica JS di conteggio/selezione per escludere correttamente il toggle "Prefer Elementor" (ora annidato nella stessa griglia) dal conteggio "N di 7 attive" e da "Seleziona tutto"/"Deseleziona tutto", che devono contare solo le 7 voci reali del pannello.
- Traduzioni italiane aggiornate di conseguenza (nuova stringa "Bulk select" per l'etichetta accessibile del gruppo di pulsanti, testo del toggle Elementor riformulato per il nuovo contesto annidato).

### Corretto

- Pagina opzioni, layout a ≤782px: la card "Menu items" (che ha sempre `grid-column: span 12`) non veniva più resettata a `grid-column: auto` come le altre card quando, in quel breakpoint, la griglia passa da 12 colonne esplicite a una sola (`1fr`). Il browser generava quindi 11 colonne implicite strettissime per soddisfare lo `span 12` residuo, schiacciando le card "Generale"/"Posizione"/"Aspetto"/"Anteprima" in strisce quasi illeggibili invece di impilarle su una colonna. Corretto includendo anche `--menu-items` nel reset del breakpoint.
- Sottomenu "Nuovo" del pannello: con "Prefer Elementor when available" disattivato, comparivano comunque le voci "Elemento fluttante" ed "Template" — i CPT pubblici che Elementor registra per i propri contenuti interni (`e-floating-buttons`, `elementor_library`), raccolti insieme a qualunque altro CPT pubblico dal codice esistente. Ora vengono esclusi quando l'opzione è disattivata, tramite un elenco filtrabile con `nanobar_elementor_post_types` (include anche `e-landing-page` di Elementor Pro).
- Il nodo admin bar usato dal pulsante principale "Cache" per LiteSpeed Cache era sbagliato: puntava al nodo di livello superiore `litespeed-menu`, che (verificato su un'installazione reale) si limita ad aprire la pagina impostazioni del plugin invece di svuotare la cache — il pulsante quindi non svuotava nulla per chi usa LiteSpeed Cache. Corretto puntando al nodo figlio effettivo `litespeed-purge-all`.
- Aggiunto FastCache by host.it al registro plugin di cache (rilevato tramite `FASTCACHE_VERSION`), ma senza pulsanti di svuotamento: verificato che le sue voci "CDN Purge" nella admin bar esistono solo con la CDN dedicata abilitata (non la cache di pagina locale, quella davvero attiva), e comunque il proprio script che gestisce il click cerca una classe CSS (`fastcache-confirm-purge`) assente dal markup generato sul frontend — un bug del plugin stesso, non qualcosa a cui NanoBar può agganciarsi in modo affidabile oggi. La chiave `purge_all_node_id` del registro è quindi ora nullable: quando è `null` (come per FastCache), la voce "Cache" nel pannello diventa un semplice link alle impostazioni del plugin invece del pulsante split con sottomenu di svuotamento.
- I node id per WP Fastest Cache erano sbagliati (stesso problema già trovato per LiteSpeed Cache): puntavano al nodo di primo livello `wp-fastest-cache`, che si limita ad aprire le impostazioni, invece dei nodi figli che eseguono davvero il purge. Corretto con `wpfc-toolbar-parent-delete-cache` (svuota tutto) e aggiunto `wpfc-toolbar-parent-clear-cache-of-this-page` (svuota la pagina corrente, prima assente) — entrambi verificati dal vivo: il click genera le chiamate AJAX reali del plugin (`wpfc_delete_cache`/`wpfc_delete_current_page_cache`) con esito positivo.
- WP Super Cache, caso diverso dagli altri: verificato dal vivo che sul frontend espone in admin bar **solo** un nodo "svuota la pagina corrente" (`delete-cache`, con link già firmato da nonce, niente JS separato) — il purge dell'intero sito esiste solo nella variante dell'admin bar mostrata in wp-admin, mai renderizzata sul frontend dove gira NanoBar. Il registro aveva erroneamente `purge_all_node_id => 'wp-super-cache'` (mai esistito) e nessun `purge_page_node_id`. Corretto: ora `purge_all_node_id` è `null` e `purge_page_node_id` è `delete-cache`. La logica del pulsante principale "Cache" in `Renderer.php` è stata generalizzata per usare qualunque dei due sia disponibile (preferendo lo svuotamento totale quando c'è), invece di richiedere sempre `purge_all_node_id` — prima, un plugin nella sola condizione di WP Super Cache sarebbe finito nel fallback "solo link alle impostazioni", perdendo la funzionalità di svuotamento della pagina corrente che invece funziona. Verificato con un click reale: round trip completo su wp-admin (elimina la cache, poi reindirizza alla pagina di partenza) senza errori di nonce.

### Modificato

- Pagina opzioni: testo portato a un minimo di 16px in tutte le card (etichette, opzioni, help text, badge, pulsanti, campi, notifiche) — prima variava tra 11px e 14px. Di conseguenza aumentati proporzionalmente anche i controlli associati (interruttori, checkbox delle voci, badge "N di 7 attive", pulsanti "Seleziona/Deseleziona tutto") e il padding/spaziatura di card, tile e righe, per non far risultare il testo più grande schiacciato. Il colore del testo secondario (help text, sottotitoli) è stato scurito da `#646970` a `#50575e` per mantenere un contrasto adeguato alla dimensione maggiore.
- Card "Menu items": corretti gli spazi tra gli elementi, diventati troppo stretti dopo l'aumento del font-size — il testo di aiuto non aveva margine inferiore, per cui toccava quasi direttamente i pulsanti "Seleziona tutto"/"Deseleziona tutto" sotto di esso (`.nanobar-help` ora ha `margin-bottom: 16px` invece di 0, applicato a tutte le card). Ulteriore giro di rifiniture su richiesta: padding interno del corpo delle card (22px → 26px), padding delle singole tile/righe della griglia voci (10px 12px → 12px 14px, min-height 46 → 50px) e gap della griglia (10px → 18px), oltre ai margini attorno a header/pulsanti della card — per un ritmo verticale più arioso e coerente con testo e controlli più grandi.

### Corretto

- Notifiche di salvataggio (es. "Impostazioni salvate."): la "×" per chiuderle era disallineata verticalmente verso l'alto invece di restare centrata rispetto a icona e testo — causata da `align-items: flex-start` sul contenitore flex della notice, diventato più evidente ora che le notice sono più alte per via del font-size maggiore. Corretto in `align-items: center`.

### Aggiunto

- Sottomenu "Cache": dopo "Impostazioni", aggiunte le voci "Elimina tutta la cache" (ripete l'azione del pulsante principale, ora accessibile anche dal sottomenu) ed "Elimina cache per la pagina corrente" — quest'ultima mostrata solo per i plugin di cache di cui è noto un nodo admin bar dedicato al purge della singola pagina (al momento solo LiteSpeed Cache, verificato dal vivo: nodo `litespeed-purge-single`). Il registro dei plugin di cache (`nanobar_cache_plugins`) usa ora due chiavi distinte, `purge_all_node_id` e `purge_page_node_id` (nullable), al posto della precedente `node_id` unica.

### Aggiunto

- Comandi rapidi (Cmd/Ctrl+K): apre una palette di comandi che legge le voci già presenti e già filtrate per permessi nel pannello (Bacheca, Nuovo, Modifica, Cache, Esci, ecc.), più alcune destinazioni comuni di wp-admin non altrimenti presenti (profilo, commenti, utenti, temi, strumenti) tramite la nuova classe `Frontend\Commands`. Attivabile/disattivabile dalla pagina impostazioni (opzione `command_palette_enabled`, attiva di default).
- Link rapidi personalizzati ("Quick links"): repeater nella pagina impostazioni per aggiungere fino a `Config::get()['quick_links']['max']` (20) link propri al pannello, ciascuno con etichetta, URL, icona e ruoli visibili opzionali. Gli URL sono validati con `wp_validate_redirect()` — lo stesso meccanismo che WordPress usa per i redirect di login — e possono puntare solo a pagine di questo sito: link esterni, protocol-relative (`//dominio/...`) o con schema diverso da http/https vengono scartati sia lato form che, in modo indipendente, al salvataggio.
- Validazione lato form per i link rapidi: etichetta e URL sono campi obbligatori per ogni riga compilata (una riga mai toccata non viene segnalata; i ruoli restano facoltativi come da nota già presente sotto le caselle — nessuna selezione continua a significare "stessi ruoli di NanoBar"). Un messaggio d'errore compare sotto il campo interessato, si aggiorna dal vivo mentre si scrive, e il salvataggio dell'intera pagina viene bloccato finché tutte le righe non sono valide. Resta comunque `Settings\Sanitizer` l'autorità finale: una riga non valida viene scartata silenziosamente anche con JavaScript disattivato.
- Selezione icona per i link rapidi tramite una griglia visiva con **tutte** le dashicon disponibili (non un sottoinsieme curato): un pulsante mostra l'icona e il nome correnti e apre un overlay condiviso con casella di ricerca e griglia cliccabile. L'elenco completo viene letto direttamente dal foglio di stile `dashicons.css` incluso nel core di WordPress (`Settings\Page::get_all_dashicons()`), così resta sempre coerente con la versione di WordPress in uso senza bisogno di mantenerlo a mano; il risultato viene messo in cache in un transient legato alla versione del core.
- Un URL assoluto incollato per un link rapido (es. `https://tuosito.test/wp-admin/font-library.php?p=%2Ffont-list`), se punta a questo sito, viene ripulito automaticamente nella sola parte relativa (`/wp-admin/font-library.php?p=%2Ffont-list`) — dal vivo nel campo appena si esce dal focus, e comunque in modo definitivo al salvataggio (`Settings\Sanitizer::relative_quick_link_url()`).
- Schema colori del pannello (Auto / Chiaro / Scuro, opzione `color_scheme`): "Auto" (default) segue il `prefers-color-scheme` del sistema operativo/browser di chi visita il sito. Interessa solo lo sfondo del menu a tendina e dei sottomenu — il pulsante di attivazione mantiene sempre il colore scelto dall'amministratore, indipendentemente dallo schema.

### Modificato

- Valori di default per le installazioni nuove (non toccano siti con impostazioni già salvate): "Forza la barra di amministrazione per Query Monitor" ora disattivato di default; le voci pannello "Query Monitor" e "Cache" deselezionate di default nella card "Menu items"; "Prefer Elementor when available" disattivato di default.

### Corretto

- `Menus.php`: ripristinato un controllo `is_string()` prima di `str_starts_with()` su `WP_Post_Type::$menu_icon`, rimosso per errore in una modifica precedente — un plugin di terze parti può registrare un CPT con `menu_icon` non-stringa (es. `true`), causando altrimenti un errore fatale nella build del sottomenu "Nuovo" per tutti gli utenti loggati.
- `Menus.php`: il valore restituito dal filtro `nanobar_elementor_post_types` viene ora validato come array prima dell'uso (`in_array()`), evitando un errore fatale se un plugin/tema lo aggancia restituendo un valore non-array.
- Allineato a 580px il breakpoint della modalità tab-bar automatica tra `frontend.js` (era rimasto a 600px) e il CSS corrispondente, oltre a due commenti nel SCSS rimasti disallineati dalla stessa modifica precedente.

### Corretto

- **Sicurezza (link rapidi)**: un URL come `https://questo-sito//evil.example.com/x` superava legittimamente `wp_validate_redirect()` (l'host combacia, il doppio slash è solo un segmento del percorso su un URL già assoluto), ma la successiva riduzione al solo percorso relativo produceva `//evil.example.com/x` — che, stampato da solo come `href`, il browser reinterpreta come URL protocol-relative verso un dominio *diverso*, vanificando la restrizione "solo link interni". `Settings\Sanitizer::relative_quick_link_url()` e l'equivalente `relativizeQuickLinkUrl()` in `admin-settings.js` ora collassano un `//` iniziale in un singolo `/`. In pratica il controllo duplicato in lettura (`Frontend\QuickLinks::get_visible()`) già scartava questi link prima che comparissero nel pannello, quindi non era sfruttabile end-to-end — ma il salvataggio sembrava riuscire e il link spariva silenziosamente.
- `assets/js/frontend.js`: la command palette costruiva il proprio dialog concatenando le stringhe tradotte (placeholder, etichetta accessibile) direttamente dentro una stringa HTML (`innerHTML`), anziché impostarle via API DOM come già fa correttamente l'icon picker in `admin-settings.js` — una traduzione contenente un carattere `"` avrebbe potuto corrompere il markup. Ora usa `setAttribute()`/`.placeholder`.
- `ContextLinks::get_site_editor_link()` e `Menus::get_new_content_items()`: il parametro `$use_elementor` aveva ancora un valore di default `true`, rimasto disallineato dopo che il default dell'opzione "Prefer Elementor when available" è stato portato a `false`. Innocuo (chiamato sempre esplicitamente da `Renderer.php`), ma un possibile trabocchetto per chi legge la firma in futuro — il default è stato rimosso anziché semplicemente invertito, cosicché non possa disallinearsi di nuovo.

### Corretto

- **Comandi rapidi**: `.nanobar-command-palette` non aveva una regola `[hidden] { display: none; }` — `closePalette()` chiude l'overlay impostando la proprietà DOM `hidden`, ma la regola `display: flex` del selettore di classe ha la stessa specificità della regola predefinita del browser per `[hidden]` e, a parità di specificità, vince quella dell'autore. Di fatto la palette non si chiudeva mai visivamente (Escape, click sullo sfondo): restava un overlay a schermo intero che bloccava ogni click sulla pagina. Aggiunta la regola mancante.
- **Plugin di cache**: `Menus::get_cache_plugin_registry()` non validava il valore restituito dal filtro `nanobar_cache_plugins` (un plugin/tema che lo aggancia restituendo un valore non-array avrebbe generato un warning silenzioso ma innocuo), e `get_active_cache_plugin()` non verificava che la chiave `is_active` di ogni voce fosse effettivamente richiamabile prima di passarla a `call_user_func()` — una voce malformata (chiave mancante, refuso) generava un `TypeError` fatale non gestito a ogni caricamento del pannello frontend per chiunque potesse vedere la voce "Cache". Entrambi ora validati difensivamente, con relative voci `ignoreErrors` documentate in `phpstan.neon.dist` per lo stesso motivo già usato per `nanobar_elementor_post_types`.
- **Link rapidi**: `Frontend\QuickLinks::get_visible()` validava l'icona di ogni riga solo con `is_string()`, non con lo stesso pattern `/^dashicons-[a-z0-9-]+$/` già usato in scrittura da `Settings\Sanitizer` — un valore non valido arrivato tramite il filtro `nanobar_options` o una riga di database modificata a mano finiva comunque nell'attributo `class` del markup del pannello (escapato, quindi non uno XSS, ma comunque una classe CSS non valida invece del fallback sicuro). Ora ri-validato allo stesso modo anche in lettura.
- **Voci del pannello**: `Options::get_visible_items()` considerava sempre `true` (visibile) una voce mancante dall'array `visible_items` salvato/filtrato — corretto per compatibilità in avanti quando si tratta di una voce futura non ancora esistente, ma sbagliato per una voce *esistente* col proprio default a `false` (Cache, Query Monitor): un array `visible_items` parziale, arrivato tramite il filtro `nanobar_options` o una riga di database modificata a mano, poteva riportarle visibili nonostante il default. Ora una chiave mancante usa il default di *quella specifica voce* (da `get_defaults()`), non più un `true` generico.
- **Pagina Impostazioni**: la griglia "Menu items" e la select "Color scheme" usavano elenchi di chiavi duplicati a mano invece di scorrere `Config::get()['menu_items']`/`['color_schemes']` — l'unica fonte di verità dichiarata dal progetto. Un'eventuale nuova voce aggiunta a `Config` in futuro sarebbe stata validata/conteggiata ovunque nel plugin ma non avrebbe mai avuto una checkbox/opzione in questa pagina per essere configurata. Ora entrambe scorrono `Config` direttamente, con un'etichetta/icona di fallback generica per una chiave non ancora mappata localmente.

### Aggiunto

- Nuovo filtro `nanobar_command_palette_items`: le destinazioni extra dei comandi rapidi (profilo, commenti, utenti, temi, strumenti) sono ora un registro filtrabile — stessa forma "registro + callable `is_visible` valutato pigramente" già usata per `nanobar_cache_plugins`, con la stessa validazione difensiva (una voce malformata da un filtro non manda in crash il pannello).

### Corretto

- `bin/build-zip.sh`: la copia `rsync` non escludeva `.DS_Store` (a differenza di `.gitignore`, che già lo fa) — un file del genere presente nella cartella del plugin (creato semplicemente aprendola in Finder) finiva quindi nello ZIP di produzione.
- `Tested up to` (in `readme.txt` e `README.md`) allineato alla versione di WordPress effettivamente installata sul sito di sviluppo, `7.1.2`.

### Aggiunto

- Link rapidi: nuova opzione "Visible on mobile" per ciascun link, indipendente dai ruoli — permette di nascondere un link specifico dalla tab-bar automatica sotto i 666px senza toccarne la visibilità per ruolo altrove nel pannello. Un link creato da zero (pulsante "+ Add link") parte nascosto su mobile di default; i link già salvati (anche quelli creati prima che questa opzione esistesse) restano visibili come prima, per non alterare configurazioni già in uso.
- Pagina impostazioni, header: due pulsanti a sola icona ("Restore defaults", "Save changes") sempre raggiungibili tramite `position: sticky`, con tooltip condiviso (`[data-tooltip]`, nuovo componente riutilizzabile) al posto del `title` nativo del browser. L'header si comprime (nasconde il sottotitolo, riduce icona/padding) non appena raggiunge la cima della viewport durante lo scroll, rilevato via `IntersectionObserver` su un elemento sentinella — `position: sticky` non espone un modo nativo per sapere quando un elemento è effettivamente "agganciato".
- Pagina impostazioni: indicatore di modifiche non salvate (pallino sul pulsante Save) e conferma prima di eseguire "Restore defaults" (azione distruttiva, ora meno auto-esplicativa essendo solo un'icona); avviso nativo del browser se si esce dalla pagina con modifiche non salvate.
- Pagina impostazioni: bulk-select "Seleziona tutto"/"Deseleziona tutto" anche per "Enabled roles" (prima presente solo per "Menu items"); badge "N di 20 usati" per i Quick links, aggiornato dal vivo via JS ad ogni link aggiunto/rimosso, sullo stesso modello del badge di "Menu items".

### Modificato

- Pagina impostazioni: le card "Position" e "Appearance" sono state unite in un'unica card "Position & Appearance" con due colonne interne — "Position" da sola (2 soli campi) restava visibilmente più corta di "Appearance" (4 campi) quando affiancate, uno sbilanciamento evidente nella bento grid. Il toggle "Force admin bar for Query Monitor" è stato spostato da "General" a questa card (è un'impostazione sul comportamento del pulsante/admin bar di NanoBar, non su chi vede il pannello).
- Pagina impostazioni: font-size degli hint/testo secondario riportato a 13px (era stato portato a 16px in una modifica precedente di questo stesso changelog) per una resa più compatta; padding e margini di card, gruppi di campi e righe dei quick link rivisti di conseguenza.
- Checkbox di ruoli/voci di menu con aspetto custom (quadratino con spunta bianca su sfondo blu accento, focus-visible) al posto dell'aspetto nativo del browser, coerente con lo stile a interruttore già usato per i toggle on/off della pagina.
- Pannello frontend, voce "Bacheca": sotto i 666px il tile non naviga più direttamente al tap ma apre il sottomenu, che ora include anche una voce "Bacheca" in cima (visibile solo sotto quella soglia, dove altrimenti diventerebbe l'unico modo per raggiungere la bacheca) — riduce i tap accidentali su schermi stretti, dove il tile e la piccola freccina che apre il sottomenu sono molto vicini tra loro.
- Pagina impostazioni, layout responsive: le card "General"/"Position & Appearance" passano da affiancate a impilate a piena larghezza a 1100px (prima 782px), evitando la fascia intermedia 782–1100px dove restavano affiancate ma troppo strette per il contenuto di "Position & Appearance". Le due colonne interne di "Position & Appearance" collassano a una sola alla stessa soglia. L'header non va più a capo su più righe a nessuna larghezza: i due pulsanti restano sempre sulla stessa riga del titolo, allineati a destra.

### Corretto

- Toggle "Prefer Elementor when available" (nella card "Menu items"/"Edit site"): il nuovo selettore CSS per lo stile custom dei checkbox (`.nanobar-role-option input[type="checkbox"]`, discendente generico) colpiva anche il checkbox nascosto dello switch "Prefer Elementor" annidato nella stessa tile di gruppo, annullandone il `position: absolute` e rompendo il layout a due colonne dello switch. Ristretto a un selettore di figlio diretto (`.nanobar-role-option > input[type="checkbox"]`), che non tocca più i checkbox annidati in sotto-componenti come lo switch-row.
- Indicatore di modifiche non salvate sul pulsante Save dell'header: condivideva lo stesso pseudo-elemento `::after` del componente tooltip (`[data-tooltip]`) sullo stesso bottone — le proprietà CSS delle due regole si sommavano invece di sostituirsi a vicenda, producendo un indicatore enorme e mal renderizzato (ne ereditava padding e opacità) invece di un piccolo pallino sempre visibile nell'angolo. Spostato su `::before`, non utilizzato da nessun'altra regola sullo stesso elemento.
- Pagina impostazioni, layout responsive: le card "Menu items"/"Quick links" tornavano ad affiancarsi (invece di restare impilate a piena larghezza) sotto i 782px, per un `grid-column: auto` residuo in quel breakpoint che annullava lo `span 12` già impostato di default su quelle card — bastava rimuovere la regola, mai stata necessaria.
- Hint dei ruoli per ciascun quick link: tentato come icona con tooltip al posto del testo sempre visibile, ma il tooltip veniva tagliato dal bordo della card quando il link si trovava vicino al margine destro o a schermi stretti; ripristinato come testo italico sempre visibile.

## [0.0.1] - 2026-09-09

Preparazione alla pubblicazione sul repository ufficiale dei plugin di WordPress.org.

### Modificato

- `nanobar.php` non richiede più `composer install`: l'autoloader Composer è stato sostituito da un piccolo autoloader PSR-4 proprio (nessuna dipendenza a runtime), così il plugin funziona subito dopo l'attivazione da zip, senza alcuno step di build lato utente.
- `Requires PHP` abbassato da `8.4` a `8.0`: è il minimo effettivamente richiesto dal codice (tipo `mixed`, `str_starts_with()`); un minimo così alto come 8.4 avrebbe escluso inutilmente gran parte degli hosting. Aggiornato in `nanobar.php`, `readme.txt`, `composer.json` e `README.md`.
- `bin/build-zip.sh`: rimosso lo step `composer install --no-dev` (non più necessario); `composer.json`/`composer.lock` sono ora esclusi dal pacchetto fin dall'inizio invece di essere copiati e poi cancellati; lo ZIP viene ora scritto in `dist/` invece che nella root del progetto.

### Aggiunto

- `readme.txt`, nel formato standard richiesto da WordPress.org (header, `== Description ==`, `== Installation ==`, `== FAQ ==`, `== Changelog ==`, ecc.).
- `LICENSE`: testo completo della GPLv2.
- `.wordpress-org/`: cartella (esclusa dallo ZIP del plugin) dove tenere screenshot, icona e banner da caricare a parte nella cartella `assets/` dell'SVN di WordPress.org; vedi `.wordpress-org/README.md` (in inglese). Contiene `icon-128x128.png`/`icon-256x256.png` e `banner-772x250.png`/`banner-1544x500.png` (generati: ingranaggio bianco, coerente con l'icona `dashicons-admin-generic` usata nel toggle del pannello, su sfondo sfumato indaco→viola come nella pagina impostazioni; il banner aggiunge la wordmark "NanoBar" e un breve tagline) e `screenshot-1.jpg`/`-2.png`/`-3.png`.
- `README.md`: nuova sezione "Building a release" che documenta `bin/build-zip.sh` (flag `--skip-npm`/`--skip-lint`, dove finisce lo ZIP, cosa viene escluso).

## [0.0.0] - 2026-09-08

Prima release del plugin (in sostituzione della vecchia versione mu-plugin, conservata sul branch `mu-plugin`).

### Aggiunto

- Tab-bar automatica per telefono: sotto i 600px il pulsante flottante + menu a comparsa diventa una barra fissa in basso, sempre visibile, con icona ed etichetta per ogni voce (Bacheca, Modifica sito, Nuovo, Esci) — sostituisce le righe "solo icone" del vecchio menu a comparsa, illeggibili a quella larghezza.
- Sotto i 480px la tab-bar nasconde le etichette testuali (solo icone); per le voci con sottomenu (Bacheca, Nuovo) la freccina di apertura si sposta sopra l'icona principale invece che in un badge d'angolo, e resta allineata sulla stessa riga delle altre icone.
- `bin/build-zip.sh`: script per il pacchetto ZIP di produzione (lint PHPCS, build asset SCSS, `composer install --no-dev`, zip) — legge la versione dall'header di `nanobar.php`.

### Corretto

- Il pannello frontend non si apriva al click/tocco del pulsante: lo script veniva eseguito prima che il markup del pannello fosse stampato nel DOM (`render_panel()` è agganciato a `wp_footer` priorità 999, mentre WordPress stampa gli script di footer a priorità 20 di default). L'inizializzazione ora attende `DOMContentLoaded`.
- `phpcs.xml.dist` non escludeva `.phpstan-cache/`: la cache di PHPStan (file `.php` generati contenenti array serializzati di grandi dimensioni) veniva tokenizzata da PHPCS come fosse codice sorgente, esaurendo il memory_limit di PHP.
