<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manuale utente</title>
    <meta name="description" content="Manuale operativo di UnicoAgent: ingresso WhatsApp, preventivo, perfezionamento e consegna al CRM dell'azienda.">
    <link rel="icon" href="/unicoagent_logo.png">
    <style>
        :root { --bg:#f6f8f7; --fg:#14211c; --muted:#55655e; --card:#fff; --line:#dbe4df; --brand:#0f7b5f; --soft:#e3f4ee; }
        @media (prefers-color-scheme: dark) { :root { --bg:#0e1512; --fg:#e8f0ec; --muted:#9bada5; --card:#16201b; --line:#26352e; --brand:#2bb48e; --soft:#14302a; } }
        * { box-sizing: border-box; }
        body { margin:0; background:var(--bg); color:var(--fg); font:16.5px/1.65 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        a { color:var(--brand); }
        .wrap { max-width:900px; margin:0 auto; padding:0 20px 60px; }
        header.top { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:16px 0; flex-wrap:wrap; }
        header.top img { height:40px; }
        h1 { font-size:clamp(1.7rem,4vw,2.3rem); line-height:1.2; margin:10px 0 6px; }
        h2 { font-size:1.35rem; margin:42px 0 8px; padding-top:8px; border-top:1px solid var(--line); }
        h3 { font-size:1.08rem; margin:26px 0 6px; }
        .meta { color:var(--muted); margin:0 0 20px; }
        nav.toc { background:var(--card); border:1px solid var(--line); border-radius:14px; padding:14px 22px; margin:18px 0; }
        nav.toc ol { margin:6px 0; padding-left:20px; columns:2; column-gap:30px; }
        @media (max-width:640px) { nav.toc ol { columns:1; } }
        .box { background:var(--soft); border:1px solid var(--line); border-radius:12px; padding:12px 18px; margin:14px 0; }
        .warn { background:#fff4e0; border-color:#e8c27a; color:#3d2600; }
        @media (prefers-color-scheme: dark) { .warn { background:#3a2c10; color:#f5ddb0; border-color:#6b5320; } }
        table { width:100%; border-collapse:collapse; margin:12px 0; font-size:.96rem; }
        th, td { text-align:left; padding:8px 10px; border-bottom:1px solid var(--line); vertical-align:top; }
        th { color:var(--muted); font-weight:600; }
        code, kbd { background:var(--soft); border-radius:6px; padding:1px 6px; font-size:.92em; }
        .chat { background:var(--card); border:1px solid var(--line); border-left:4px solid var(--brand); border-radius:10px; padding:10px 16px; margin:10px 0; font-size:.96rem; }
        ol.steps li, ul li { margin:4px 0; }
        @media print { nav.toc, header.top a { display:none; } body { background:#fff; color:#000; } a { color:#000; } }
    </style>
</head>
<body>
<div class="wrap">
    <header class="top">
        <img src="/unicoagent_banner.png" alt="UnicoAgent">
        <span><a href="/admin">Pannello</a> · <a href="/">Home</a></span>
    </header>

    <h1>Manuale operativo</h1>
    <p class="meta">Manuale utente per chi raccoglie le pratiche su WhatsApp e per chi collega il CRM dell'azienda. UnicoAgent non sostituisce il gestionale: raccoglie e consegna.</p>

    <nav class="toc">
        <strong>Indice</strong>
        <ol>
            <li><a href="#cose">A chi serve</a></li>
            <li><a href="#aziende">L'azienda dal numero WhatsApp</a></li>
            <li><a href="#agente">Menu e comandi</a></li>
            <li><a href="#richiedi">Preventivo di finanziamento</a></li>
            <li><a href="#fornitura">Nuova offerta di fornitura</a></li>
            <li><a href="#perfeziona">Perfezionamento</a></li>
            <li><a href="#stato">Stato Pratiche</a></li>
            <li><a href="#consegna">Consegna al CRM</a></li>
            <li><a href="#pannello">Il pannello</a></li>
            <li><a href="#pratiche">Pratiche e documenti</a></li>
            <li><a href="#config">Percorsi di configurazione</a></li>
            <li><a href="#anagrafiche">Anagrafiche</a></li>
            <li><a href="#dati">Dati personali</a></li>
            <li><a href="#problemi">Se qualcosa non funziona</a></li>
        </ol>
    </nav>

    <h2 id="cose">1. A chi serve</h2>
    <p>UnicoAgent è l'ingresso su WhatsApp con cui un agente o un installatore raccoglie i dati e i documenti di una pratica e li consegna al CRM dell'azienda, via API o per email. Il lavoro nel gestionale (istruttoria, delibera, contratto) resta nel CRM. Il servizio non valuta il merito creditizio e non decide l'esito.</p>
    <p>Il dialogo ha tre passaggi:</p>
    <ol class="steps">
        <li><strong>Menu WhatsApp</strong>: la persona sceglie cosa fare. Le voci dipendono dall'azienda a cui appartiene il suo numero.</li>
        <li><strong>Preventivazione</strong>: per il finanziamento, un profilo a fasce senza nome né codice fiscale; per la fornitura, una nuova offerta a partire dalla spesa dichiarata.</li>
        <li><strong>Perfezionamento</strong>: codice pratica, documenti, controlli, dati che mancano. Alla conferma la pratica parte verso il CRM di quell'azienda, oppure per email se l'azienda non ha un CRM.</li>
    </ol>
    <p>L'intelligenza artificiale legge i documenti e propone dei dati. L'agente li conferma. Un istruttore, nel CRM o nel pannello, decide sul merito.</p>

    <h2 id="aziende">2. L'azienda dal numero WhatsApp</h2>
    <p>Ogni messaggio è di una sola azienda. Il programma cerca il cellulare tra i fornitori attivi del core. Se lo trova, la conversazione è dell'azienda di UnicoAgent collegata a quel fornitore: prima per la società del core (<code>core_company_id</code>), altrimenti per l'identificativo dell'azienda in UnicoAgent. Se il numero non c'è, o il fornitore non è attivo, la conversazione è di <strong>Mediatore DEMO</strong>.</p>
    <p>Un fornitore riconosciuto è salutato per nome. Un numero sconosciuto che completa un preventivo di finanziamento viene registrato come <em>Segnalatore occasionale</em>, non attivo: non vede gli importi e riceve telefono ed email del customer care.</p>
    <table>
        <tr><th>Azienda</th><th>Cosa fa su WhatsApp</th><th>Dove arriva la pratica</th></tr>
        <tr>
            <td><strong>Races Finance S.r.l.</strong></td>
            <td>Solo finanziamento. Menu predefinito: Richiedi Finanziamento, Modifica Preventivo, Perfeziona Finanziamento, Stato Pratiche. I percorsi caricati sono richiesta, perfezionamento e documenti.</td>
            <td>CRM <code>unicofinance</code>, API <code>/api/agente/v1</code>. Non ha un URL di preventivatore: il preventivo, per un produttore, parte per email all'istruttoria.</td>
        </tr>
        <tr>
            <td><strong>GBD Energy</strong></td>
            <td>Solo fornitura. Il menu ha due voci: Nuova offerta e Sottoscrivi contratto. Aprono i percorsi <code>richiesta</code> e <code>perfezionamento</code> di questa azienda.</td>
            <td>Nessun driver CRM: alla conferma la pratica va per email all'istruttoria, con gli allegati. Non produce i moduli dal core (manca il collegamento al tipo pratica).</td>
        </tr>
        <tr>
            <td><strong>Mediatore DEMO</strong></td>
            <td>Finanziamento e fornitura insieme. Il menu ha Richiedi Finanziamento, Modifica Preventivo, Perfeziona Finanziamento, Stato Pratiche e, in più, Nuova offerta e Sottoscrivi contratto. Queste due ultime voci sono i codici di percorso <code>fornitura</code> e <code>sottoscrizione</code>.</td>
            <td>Nessun driver CRM: la pratica perfezionata va per email. È l'azienda di chi scrive da un numero non in anagrafica.</td>
        </tr>
    </table>
    <div class="box warn">Su GBD Energy i percorsi «Nuova offerta» e «Sottoscrivi contratto» sono nel menu, ma nel database non hanno ancora le domande. Le domande della fornitura sono caricate su Mediatore DEMO (codici <code>fornitura</code> e <code>sottoscrizione</code>) e sono descritte in <code>config/energy.php</code>. Finché non si pubblicano anche su GBD, l'installatore di GBD non percorre quell'albero.</div>

    <h2 id="agente">3. Menu e comandi</h2>
    <p>Al primo messaggio, e quando si torna al menu, WhatsApp mostra le voci dell'azienda. Si risponde con i pulsanti o scrivendo il titolo della voce. Per il menu di finanziamento valgono anche i numeri e le parole brevi: 1 o «richiedi», 2 o «modifica», 3 o «perfeziona», 4 o «stato». Un codice pratica scritto nel menu (forma <code>SIGLA-MMGG-HHmm</code>) apre il perfezionamento di quella pratica.</p>
    <p>Il primo contatto riceve anche il link alla sintesi dei comandi (<a href="/comandi">/comandi</a>). Nel profilo WhatsApp i messaggi per rompere il ghiaccio ripetono i titoli del menu di finanziamento, così il bot li tratta come una scelta.</p>
    <h3>Comandi, in qualsiasi momento</h3>
    <p>Si scrivono da soli o con la barra (<kbd>/menu</kbd>, <kbd>/help</kbd>). Sono gli stessi registrati nel profilo.</p>
    <table>
        <tr><th>Comando</th><th>Effetto</th></tr>
        <tr><td><kbd>menu</kbd></td><td>Chiude la conversazione in corso e mostra il menu. Il pulsante «Vai al menu» in fondo al preventivo fa la stessa cosa.</td></tr>
        <tr><td><kbd>annulla</kbd></td><td>Chiude la conversazione, scrive «Operazione annullata» e mostra il menu.</td></tr>
        <tr><td><kbd>help</kbd> o <kbd>aiuto</kbd></td><td>Elenco dei comandi, poi ripropone la domanda in corso (o il menu, se non c'è un dialogo aperto).</td></tr>
        <tr><td><kbd>indietro</kbd></td><td>Torna alla domanda precedente.</td></tr>
        <tr><td><kbd>salta</kbd></td><td>Salta la domanda, solo se il bot lo consente: lo scrive in fondo al testo. Non salva una risposta.</td></tr>
        <tr><td><kbd>avanti</kbd></td><td>Durante l'attesa dei controlli sui documenti: prosegue senza aspettare. Vale anche «continua».</td></tr>
    </table>
    <p>Una conversazione lasciata sulla prima domanda per più di un'ora, senza risposte, viene chiusa: il messaggio successivo trova il menu. Se il dialogo è fermo da più di 24 ore, il bot chiede se continuare o ricominciare. Un saluto («ciao», «buongiorno» e simili) a una conversazione appena aperta, a cui non si è ancora risposto, riporta al menu.</p>
    <p>Chi ha il numero WhatsApp associato a un utente del pannello, e solo se un percorso ha una copia di prova, vede in più le voci «Prova: Richiedi», «Prova: Perfeziona» e «Prova: Stato Pratiche». Le pratiche di quelle prove sono segnate come prova e il codice inizia con <code>TST-</code>.</p>

    <h2 id="richiedi">4. Preventivo di finanziamento</h2>
    <p>Voce <strong>Richiedi Finanziamento</strong>. Vale per Races Finance e per Mediatore DEMO. La fase non chiede dati identificativi: se nel testo compaiono, il bot li rifiuta e ripete la domanda.</p>
    <ol class="steps">
        <li>Tipo di finanziamento: prestito personale, cessione del quinto, finalizzato, mutuo, leasing, finanziamento aziendale.</li>
        <li>Domande a scelta, diverse secondo il prodotto (importo, durata, età, lavoro, reddito a fasce, e le domande proprie di mutuo, leasing o azienda). Importo e durata si possono modificare in seguito; le altre solo se in configurazione sono segnate come modificabili.</li>
        <li>Riepilogo: Conferma, Ricomincia o Annulla.</li>
    </ol>
    <div class="chat">✅ Richiesta registrata. Codice pratica: <strong>PM-1007-1435</strong>. Conservalo: ti servirà per perfezionare il finanziamento.</div>
    <p>Il codice ha la forma <code>SIGLA-MMGG-HHmm</code>: sigla del fornitore (se manca, le iniziali del nome; senza nome, <code>SEG</code>), mese e giorno, ora e minuti. Due preventivi dello stesso fornitore nello stesso minuto: il secondo aggiunge una lettera (<code>PM-1007-1435A</code>).</p>
    <p>Subito dopo il codice, per un produttore:</p>
    <ul>
        <li>Se l'azienda ha l'URL del preventivatore, il bot mostra l'importo minimo e massimo. Se il servizio non risponde, i dati del preventivo partono per email.</li>
        <li>Se l'URL del preventivatore è vuoto, come oggi per tutte e tre le aziende, i dati del preventivo vanno per email. La casella è quella dell'istruttoria della prima azienda in archivio (oggi Races Finance), non quella dell'azienda della conversazione. Il messaggio dice che l'istruttoria ricontatterà. Se la mail non parte: «Non sono riuscito a inoltrare la richiesta all'istruttoria: contattala indicando il codice pratica.»</li>
    </ul>
    <p>Un numero che non è un fornitore attivo non vede gli importi: viene registrato come Segnalatore occasionale e invitato a chiamare il customer care.</p>
    <p><strong>Modifica Preventivo</strong> elenca gli ultimi 5 preventivi non perfezionati. Il bot ne crea uno nuovo copiando le risposte e chiede solo i dati modificabili, mostrando il valore attuale. Si può tenere con «Mantieni attuale» o scrivendo <kbd>ok</kbd>. Il preventivo di partenza non cambia. Se per quel prodotto non c'è nulla di modificabile, il bot lo dice e torna al menu. Il pulsante «Modifica preventivo» sotto il messaggio di fine richiesta fa la stessa cosa, e compare solo se ci sono dati modificabili.</p>

    <h2 id="fornitura">5. Nuova offerta di fornitura</h2>
    <p>È il percorso di preventivazione per chi vende energia. Su Mediatore DEMO si chiama <strong>Nuova offerta</strong> e il codice del percorso è <code>fornitura</code>. Le domande, le stesse descritte per GBD in configurazione, sono queste:</p>
    <ol class="steps">
        <li>Profilo: abitazione privata oppure azienda o partita IVA.</li>
        <li>Fornitura: luce, gas, oppure luce e gas.</li>
        <li>Per un privato, se la fornitura è la residenza principale o una seconda casa. Per un'azienda, bassa o media tensione.</li>
        <li>Spesa media mensile in euro (per luce e gas insieme, il totale).</li>
        <li>Riepilogo e conferma.</li>
    </ol>
    <p>Alla conferma il programma non chiama un CRM di preventivazione. Stima il consumo dalla spesa e, tra i prodotti energia attivi dell'azienda, sceglie per ogni fornitura il più conveniente adatto a profilo, residenza o tensione e consumo. Il messaggio indica il nome dell'offerta, una spesa mensile indicativa e, se c'è, il risparmio annuo stimato. Per la media tensione aggiunge che il prezzo finale si definisce con una consulenza. Se nessun prodotto è adatto, rimanda al customer care. La stima è dichiarata indicativa. Anche qui nasce un codice pratica, da usare per la sottoscrizione.</p>
    <p>Su GBD Energy la voce di menu ha lo stesso titolo, «Nuova offerta», ma apre il percorso <code>richiesta</code> di GBD, oggi senza domande (vedi la sezione aziende).</p>

    <h2 id="perfeziona">6. Perfezionamento</h2>
    <h3>Finanziamento</h3>
    <p>Voce <strong>Perfeziona Finanziamento</strong> (Races e Mediatore DEMO). L'elenco propone gli ultimi 5 preventivi da perfezionare e le pratiche già inviate a cui manca ancora un documento obbligatorio. Si può anche scrivere il codice.</p>
    <ol class="steps">
        <li>Conferma che è la pratica giusta. Il bot mostra il riepilogo del preventivo. Se la pratica è già perfezionata ma manca un documento obbligatorio, si passa al caricamento, senza rifare i dati.</li>
        <li>Elenco dei documenti del prodotto e link dell'informativa privacy da far firmare.</li>
        <li>Informativa firmata (foto o PDF). Finché non risulta verificata, gli altri documenti restano in attesa e non vengono letti.</li>
        <li>Documento d'identità e codice fiscale. Il documento di reddito si può saltare. Un documento già ricevuto e non respinto non viene richiesto di nuovo.</li>
        <li>Attesa dei controlli. Il bot riparte da solo a controlli finiti. Con <kbd>avanti</kbd> si prosegue prima.</li>
        <li>Conferma dei dati letti, se ce ne sono: «Sì, confermo» li tratta come risposte; «No, li inserisco io» li scarta e le domande si compilano a mano. Un dato letto non valido (formato, opzione, controllo) viene rifiutato e richiesto di nuovo, con il motivo.</li>
        <li>Domande rimaste: codice fiscale (da cui il bot ricava data di nascita, sesso e luogo, e rifiuta un minorenne), cognome e nome, coerenza col codice fiscale, residenza, stato civile, documento, telefono, email, contatto diretto, IBAN, datore di lavoro oppure ragione sociale e partita IVA.</li>
        <li>Riepilogo finale e <em>Invia in istruttoria</em>. Se qualche documento è ancora in controllo, l'invio non parte: il bot chiede di aspettare l'esito.</li>
    </ol>
    <p>Nel riepilogo lo stato dei documenti è: ✅ a posto, 📎 ricevuto e in verifica, ⚠️ da correggere, ➖ mancante.</p>

    <h3>Se un controllo non passa</h3>
    <p>Alla ricezione di un file il bot risponde subito «Documento ricevuto» e, se la lettura automatica è attiva, che scriverà l'esito. Il controllo gira dopo, e il risultato arriva come messaggio WhatsApp sulla stessa chat.</p>
    <ul>
        <li><strong>Informativa accettata</strong>: «è il nostro modulo ed è firmato.» Da quel momento partono i documenti che erano in attesa.</li>
        <li><strong>Informativa rifiutata</strong>: «non la posso accettare», con il motivo (non è il modulo, non è firmata, oppure non è leggibile). Gli altri documenti restano in attesa. Si invia di nuovo il file.</li>
        <li><strong>Altro documento a posto</strong>: «controllo completato, nessuna differenza con i dati inseriti.»</li>
        <li><strong>Altro documento con differenze</strong>: elenco dei problemi e l'invito a inviarne uno corretto da Stato Pratiche → Carica documenti. Sul documento della pratica lo stato diventa rifiutato; l'allegato risulta difforme o non leggibile.</li>
        <li><strong>Lettura non riuscita</strong>: il file è stato ricevuto, il controllo automatico non è stato fatto, lo vedrà l'istruttore (allegato «non analizzato»).</li>
        <li><strong>In attesa dell'informativa</strong>: il file è ricevuto e sarà controllato quando l'informativa risulta verificata.</li>
    </ul>
    <p>I controlli predefiniti sono, per identità e codice fiscale, tipo di documento, coerenza con i dati noti ed estrazione; per il reddito, tipo e coerenza; per l'informativa, che sia il modulo firmato. Un passo del percorso può averne di propri. Il primo controllo che fallisce ferma la catena: non si propongono dati da quel documento.</p>
    <p>Se la lettura automatica è spenta, il file viene solo archiviato: non parte l'analisi e non arriva l'esito su WhatsApp. Dal pannello l'operatore approva o rifiuta a mano. Approvare l'informativa sblocca i documenti in attesa.</p>
    <p>Una risposta di testo che non supera il controllo (codice fiscale, IBAN, email, età, e gli altri agganciati alla domanda) non viene salvata: il bot ripete la domanda con il messaggio di errore di quel controllo.</p>

    <h3>Sottoscrizione di un contratto di fornitura</h3>
    <p>Su Mediatore DEMO la voce è <strong>Sottoscrivi contratto</strong> (percorso <code>sottoscrizione</code>). Chiede il codice, l'informativa firmata, poi bolletta, documento d'identità, visura camerale se il preventivo è di un'azienda, ed estratto conto o IBAN. Questi documenti, tranne l'informativa, si possono saltare. Dopo l'attesa e la conferma dei dati letti, il bot chiede solo ciò che manca: per un'azienda parte dalla partita IVA e, se il Registro Imprese risponde, compila ragione sociale e sede; poi codice fiscale e generalità di chi firma, POD e PDR secondo la fornitura, fornitore attuale, pagamento (addebito in conto o bollettino), recapiti e il consenso a essere ricontattati. Il riepilogo chiede «Invia per la firma».</p>
    <p>Quella conferma usa lo stesso invio del finanziamento: CRM se l'azienda ne ha uno, altrimenti email con dati e allegati. L'invio dei moduli al cliente per la firma con OTP avviene solo se l'azienda è impostata per produrre i documenti dal core (flag attivo, società del core e tipo pratica). Oggi nessuna delle tre aziende lo è, quindi la sottoscrizione non manda l'SMS di firma: la pratica risulta perfezionata e inviata in istruttoria, e parte l'avviso verso UnicoBPM descritto più sotto. Per un privato di un'azienda energia, dopo l'invio riuscito, il recupero dei dati catastali parte in background: se non si avvia, la pratica resta comunque perfezionata.</p>
    <div class="chat">✅ Pratica PM-1007-1435 perfezionata e inviata in istruttoria al mediatore creditizio.</div>
    <div class="box warn"><strong>Invio pratica fallito, riprovare o contattare Istruttoria</strong>: l'invio non è andato a buon fine. La pratica non è stata segnata come perfezionata e i dati restano nel riepilogo. Si tocca di nuovo <em>Invia in istruttoria</em> (o <em>Invia per la firma</em>, nella fornitura). Se continua, si controlla il CRM o la casella dell'istruttoria, come nella sezione consegna.</div>

    <h2 id="stato">7. Stato Pratiche</h2>
    <p>Voce presente per Races e per Mediatore DEMO (percorso documenti). Non è nel menu di GBD Energy.</p>
    <ol class="steps">
        <li>Elenco delle ultime 10 pratiche di chi scrive.</li>
        <li>Documenti in Obbligatori, Facoltativi e Integrazioni richieste, con lo stato e l'ultima nota.</li>
        <li>Carica documenti: si sceglie il tipo e si invia foto o PDF. Un file nuovo sostituisce il precedente nella verifica. Si può tornare in giorni diversi.</li>
    </ol>
    <p>Per caricare serve l'informativa già ricevuta: altrimenti il bot non apre il caricamento. Se dal pannello un operatore rifiuta un documento o chiede un'integrazione, l'agente riceve un messaggio WhatsApp con la nota.</p>

    <h2 id="consegna">8. Consegna al CRM</h2>
    <p>Ogni azienda ha un solo CRM, scelto in Aziende → CRM dell'istruttoria. Alla conferma del perfezionamento il programma chiama quel CRM. La risposta attesa è il codice HTTP 200. Qualsiasi altro esito, o un servizio irraggiungibile, è un fallimento: l'agente vede il messaggio della sezione precedente e può riprovare dallo stesso riepilogo. Nei log restano il tipo di errore e il codice, non i dati del cliente.</p>
    <div class="box">Se nella configurazione dell'applicazione il driver globale del CRM è <code>simulated</code>, la conferma risponde con la simulazione (di norma 200) e <strong>non chiama</strong> il CRM scelto in scheda, per nessuna azienda. È l'impostazione di sviluppo e test. In esercizio quel valore non deve essere <code>simulated</code>.</div>
    <table>
        <tr><th>Scelta in scheda</th><th>Cosa succede all'invio</th></tr>
        <tr><td>Vuota</td><td>Mediafacile se c'è l'URL dell'istruttoria; altrimenti email.</td></tr>
        <tr><td>Solo email</td><td>Nessun CRM. Dati e allegati alla casella dell'istruttoria dell'azienda (se manca, alla casella indicata in configurazione). La pratica è inviata solo se la mail parte. In scheda pratica, la colonna <em>Inviata per email</em> mostra l'orario.</td></tr>
        <tr><td>Mediafacile</td><td>Caricamento del lead con una chiamata al servizio (risposta XML). Vale 200 solo se lo stato inizia con OK. L'identificativo restituito, se c'è, finisce sulla pratica. I file non partono con questa chiamata: l'email, quando è il canale usato, è quella che porta gli allegati.</td></tr>
        <tr><td>CRM generico (REST)</td><td>Una chiamata HTTPS configurata in scheda: indirizzo, metodo, formato JSON o form, autenticazione, modello del corpo, codici di successo e dove leggere l'esito e l'id. Senza modello si invia il formato unico (riferimento, prodotto, risposte, cliente, agente, consenso, elenco documenti senza il contenuto dei file). L'id letto nella risposta è salvato sulla pratica.</td></tr>
        <tr><td>unicofinance</td><td>Il contratto descritto sotto. È il driver di Races Finance.</td></tr>
    </table>

    <h3>unicofinance, a livello operativo</h3>
    <p>La scheda azienda contiene l'indirizzo di base (https), il token e il segreto. Si ottengono in unicofinance con il comando che registra il client <code>unicoagent</code>; i valori si inseriscono una volta e non si riportano nei messaggi. L'autenticazione è un token Bearer e una firma HMAC: il dettaglio è in <code>docs/agent-api.md</code> di unicofinance.</p>
    <p>Alla conferma, in modo sincrono, UnicoAgent crea cliente e pratica con <code>POST /api/agente/v1/richieste</code>. L'operazione è idempotente sul codice pratica di UnicoAgent: un nuovo tentativo non duplica la richiesta. Sulla pratica viene salvato il codice restituito dal CRM. Mancano il codice fiscale del cliente o la partita IVA dell'agente, oppure l'indirizzo, il token o il segreto: l'invio non parte e l'agente vede il fallimento. Un rifiuto del CRM (per esempio cliente già di un altro agente, o agente sconosciuto) è lo stesso fallimento, con il codice di errore solo nei log.</p>
    <p>Se questa prima chiamata riesce, la pratica è perfezionata e parte un job che consegna i file non ancora inviati, uno alla volta, con <code>POST /api/agente/v1/richieste/{codice}/documenti</code>. Si saltano i documenti rifiutati e quelli senza file. Un file già consegnato non viene rimandato. Insieme al file parte l'esito dell'analisi già fatta qui (solo esito, tipo e differenze; i dati letti si aggiungono se in scheda l'analisi è impostata su «anche i dati letti»).</p>
    <p>Lo stesso driver sa anche, senza che la chat WhatsApp li chiami ancora:</p>
    <ul>
        <li>elencare i moduli della pratica e scaricare il PDF vuoto;</li>
        <li>compilare un modulo con i dati della pratica (stampa sul core) ;</li>
        <li>chiedere la firma con OTP e leggerne lo stato: inviata, firmata, rifiutata, scaduta o errore;</li>
        <li>scaricare il PDF firmato, se c'è, altrimenti l'originale.</li>
    </ul>
    <p>Il codice del modulo, in queste chiamate, è l'identificativo del modulo in unicofinance. Il riferimento della firma è l'identificativo del documento lì.</p>

    <h3>Cosa vede l'operatore se l'invio fallisce, e come si riprova</h3>
    <ul>
        <li><strong>Fallisce la pratica</strong> (CRM o email): su WhatsApp l'agente ha «Invio pratica fallito, riprovare o contattare Istruttoria» e il riepilogo è ancora lì. Nel pannello la pratica non risulta perfezionata. Si riprova dalla chat. In alternativa, dall'elenco pratiche, si selezionano le righe e si usa <em>Invia per email (forza)</em>: rimanda dati e allegati all'istruttoria anche se un invio email c'era già. Non richiama il CRM.</li>
        <li><strong>La pratica è arrivata e falliscono i documenti</strong> (solo un CRM che li accetta, oggi unicofinance): la pratica è già perfezionata. Il job <code>SendDocumentsToCrm</code> riprova da solo fino a 5 volte, con attese di 1 minuto, 5 minuti, 15 minuti e 1 ora. In più, ogni ora, il comando <code>crm:send-documents</code> rimette in coda le pratiche perfezionate che hanno ancora allegati non consegnati. A mano: <code>php artisan crm:send-documents --loan=CODICE</code>. I file già segnati come consegnati non ripartono.</li>
        <li><strong>UnicoBPM</strong>: a perfezionamento riuscito, se l'azienda non produce i documenti per la firma, parte l'evento <code>pratica.perfezionata</code> (prodotto, codice pratica del CRM, nome e partita IVA dell'agente; niente dati del cliente né file). Il job ritenta dopo 1, 5 e 15 minuti, poi dopo 1 e 4 ore. Senza indirizzo e chiave del bridge, o per una pratica di prova, non si invia nulla. Un secondo invio dello stesso codice non apre due processi.</li>
    </ul>

    <h2 id="pannello">9. Il pannello</h2>
    <p>Si apre da <a href="/admin">/admin</a> con email e password. Il menu laterale ha Pratiche, Conversazioni, il gruppo Settings (Percorsi di configurazione, Documenti per il finanziamento, Controlli sulle risposte) e il gruppo Anagrafiche (Azienda, Produttori, Utenti). Lo switch azienda filtra le tabelle sulla company scelta.</p>
    <h3>Dashboard</h3>
    <ul>
        <li><strong>Da fare adesso</strong>: documenti rifiutati, documenti non letti dall'AI, informative da verificare, pratiche perfezionate negli ultimi 7 giorni.</li>
        <li><strong>Integrazioni</strong>: stato di WhatsApp, analisi AI e pulizia automatica.</li>
        <li><strong>Ultimi 7 giorni</strong>: pratiche nuove per stato, pratiche vicine alla cancellazione, conversazioni attive e ferme.</li>
        <li><em>Verifica WhatsApp</em> legge i dati del numero e non manda messaggi. <em>Verifica AI</em> fa una richiesta minima: dice se la chiave è valida e se il credito è esaurito. L'importo residuo non è disponibile via API.</li>
    </ul>

    <h2 id="pratiche">10. Pratiche e documenti</h2>
    <p><strong>Pratiche</strong> elenca le richieste, con filtri per stato, origine (reale o di prova) e prodotto. La scheda mostra codice, agente, stato, quando è stata ricevuta e verificata l'informativa, le risposte del preventivo, i dati personali e le difformità segnalate dal bot.</p>
    <ul>
        <li><strong>Recapiti del cliente</strong>: cellulare, email e il flag di contatto diretto. Sono cifrati come gli altri dati personali.</li>
        <li><strong>Documenti della pratica</strong>: stato Da ricevere, Ricevuto, OK, Rifiutato, Integrazione richiesta, e le note di AI e operatore. Azioni: Approva, Rifiuta (la nota arriva su WhatsApp), Chiedi integrazione, Richiedi un documento integrativo.</li>
        <li><strong>Dati letti dai documenti</strong>: Proposto, Confermato o Rifiutato. Sola lettura.</li>
        <li><strong>Allegati</strong>: esito Verificato, Difforme, Non leggibile, Non analizzato, In attesa informativa, e le differenze trovate.</li>
    </ul>
    <div class="box"><strong>Informativa</strong>: finché non è verificata, i documenti della pratica non vanno all'AI. Se la lettura la rifiuta, Approva sul documento Informativa firmata fa partire l'analisi di quelli in attesa.</div>
    <p><strong>Conversazioni</strong> mostra numero, fornitore, percorso, domanda corrente e stato. I dati in corso non sono visibili: si cancellano quando la conversazione si chiude. I dati definitivi stanno sulla pratica.</p>

    <h2 id="config">11. Percorsi di configurazione</h2>
    <p>Le domande stanno nelle tabelle e si modificano senza programmare, per ogni azienda. Per ogni percorso si cambiano intestazione, testi, opzioni, salti, domande saltabili e controlli. Il pannello rifiuta i salvataggi che bloccherebbero gli agenti (salti mancanti, troppe opzioni per WhatsApp, testi troppo lunghi).</p>
    <ol class="steps">
        <li><strong>Crea copia di prova</strong> e lavoraci senza toccare la produzione.</li>
        <li>Chi ha il numero associato al proprio utente vede le voci «Prova: …» e le pratiche nascono come prova.</li>
        <li><strong>Pubblica in produzione</strong>. <em>Rifai la copia di prova</em> riparte dalla produzione.</li>
    </ol>
    <p>Altri pulsanti: <em>Grafo delle domande</em> ed <em>Esporta configurazione</em>. Su una domanda a scelta, <em>Modificabile nel preventivo</em> la include nella modifica del preventivo. Sulle domande di file si agganciano i controlli documento; sulle altre, i controlli sulla risposta.</p>
    <p><strong>Documenti per il finanziamento</strong>: catalogo per tipo di finanziamento. Il nome sta nella lista WhatsApp (massimo 24 caratteri). Le modifiche valgono per le pratiche nuove.</p>
    <p><strong>Controlli sulle risposte</strong>: elenco, con dove sono usati. <em>Cerca nuovi controlli</em> registra le classi aggiunte al programma. Un controllo usato da una domanda non si disattiva.</p>
    <p>Una voce di menu può essere il codice di un percorso di quell'azienda, non solo le quattro voci standard. È così che Mediatore DEMO aggiunge <code>fornitura</code> e <code>sottoscrizione</code> accanto a Richiedi, Modifica, Perfeziona e Stato.</p>

    <h2 id="anagrafiche">12. Anagrafiche</h2>
    <h3>Azienda</h3>
    <p>Una scheda per company. I dati del titolare compaiono nell'informativa pubblica. In sintesi operativa:</p>
    <table>
        <tr><th>Campo</th><th>Effetto</th></tr>
        <tr><td>Ragione sociale, sede, email privacy, DPO</td><td>Titolare nell'informativa e nella pagina di trasparenza.</td></tr>
        <tr><td>Settore e prodotti attivi</td><td>Finance, call center, hotel o energia, e i prodotti del catalogo. Per l'energia, i prodotti attivi sono quelli tra cui si sceglie l'offerta.</td></tr>
        <tr><td>Telefono ed email del customer care</td><td>Indicati al segnalatore occasionale e quando non si calcola un'offerta.</td></tr>
        <tr><td>Email dell'istruttoria</td><td>Per la pratica perfezionata, la casella dell'azienda del numero WhatsApp. Per il solo preventivo di finanziamento, la casella della prima azienda in archivio. Se la casella manca, si usa quella indicata in configurazione.</td></tr>
        <tr><td>URL del preventivatore</td><td>Se compilato, il preventivo di finanziamento chiede gli importi al CRM. Se vuoto, parte l'email.</td></tr>
        <tr><td>CRM dell'istruttoria</td><td>Un solo driver per azienda, come nella sezione consegna. La configurazione (indirizzi, token, segreti) resta in scheda e non va copiata altrove.</td></tr>
        <tr><td>Voci del menu WhatsApp</td><td>Se compilate, sostituiscono del tutto il menu predefinito. Vuote, resta Richiedi / Modifica / Perfeziona / Stato.</td></tr>
        <tr><td>Società del core e tipo pratica</td><td>Insieme al flag di produzione documenti, abilitano moduli e firma OTP. Senza questi tre elementi la firma dalla chat non parte.</td></tr>
    </table>
    <h3>Produttori</h3>
    <p>Anagrafica dei fornitori attivi, sul core. La sigla apre il codice dei preventivi: se è vuota, il programma la ricava dal nome e la salva. Il cellulare è quello con cui <em>forWhatsApp</em> riconosce chi scrive. I numeri sconosciuti che completano un preventivo compaiono come Segnalatore occasionale, non attivi: per convenzionarne uno si correggono i dati, il tipo e il flag attivo, e si indica l'azienda.</p>
    <h3>Utenti</h3>
    <p>Chi accede al pannello. Il numero WhatsApp dell'utente abilita le voci di prova nel menu del bot.</p>

    <h2 id="dati">13. Dati personali</h2>
    <ul>
        <li>Il preventivo di finanziamento è anonimo. I dati personali si raccolgono dopo l'informativa firmata, con data e ora.</li>
        <li>I dati personali sono cifrati. I file stanno in un archivio privato, non raggiungibile da internet.</li>
        <li>Le pratiche non perfezionate sono cancellate, con i file, dopo il periodo di conservazione (30 giorni se non diversamente impostato). Eliminare una pratica dal pannello cancella documenti e conversazioni collegati.</li>
        <li>Per la cancellazione chiesta da un cliente: <a href="/cancellazione-dati">Cancellazione dei dati</a>. Per il trattamento: <a href="/privacy">Informativa privacy</a> e <a href="/compliance">Trasparenza e conformità</a>.</li>
    </ul>

    <h2 id="problemi">14. Se qualcosa non funziona</h2>
    <table>
        <tr><th>Sintomo</th><th>Cosa controllare</th></tr>
        <tr><td>Il bot non risponde</td><td>Dashboard → Verifica WhatsApp. Un token scaduto è la causa più comune: va rigenerato e aggiornato nella configurazione del server, non in questo manuale.</td></tr>
        <tr><td>Menu di un'altra azienda</td><td>Il cellulare è di un fornitore attivo di quell'azienda, oppure non è in anagrafica e quindi vale Mediatore DEMO.</td></tr>
        <tr><td>«Questo servizio non è ancora attivo»</td><td>Il percorso scelto non ha un albero pubblicato per quell'azienda. È il caso, oggi, di Nuova offerta e Sottoscrivi contratto su GBD Energy.</td></tr>
        <tr><td>I documenti restano in attesa</td><td>L'informativa non è verificata. In pratica, documento Informativa firmata: Approva, oppure chiedi un nuovo invio.</td></tr>
        <tr><td>I documenti non vengono letti</td><td>Dashboard → Verifica AI. Nel frattempo si controllano a mano.</td></tr>
        <tr><td>Invio pratica fallito</td><td>Scheda azienda: driver, e per unicofinance indirizzo, token e segreto; per l'email, la casella e la posta. Se il driver globale è <code>simulated</code>, il CRM reale non viene chiamato. L'agente riprova dal riepilogo, oppure si usa Invia per email (forza).</td></tr>
        <tr><td>La pratica c'è nel CRM ma mancano i file</td><td>Solo unicofinance consegna i file dopo. Il job ritenta da solo; altrimenti <code>php artisan crm:send-documents --loan=CODICE</code>.</td></tr>
        <tr><td>Le pratiche scadute non si cancellano</td><td>In dashboard la pulizia risulta mai eseguita: manca la pianificazione sul server (il comando previsto è giornaliero).</td></tr>
        <tr><td>Una domanda del bot è sbagliata</td><td>Percorsi di configurazione della company → copia di prova → prova dal menu → pubblica.</td></tr>
    </table>

    <p class="meta" style="margin-top:40px"><a href="#cose">↑ Torna all'inizio</a></p>
</div>
</body>
</html>
