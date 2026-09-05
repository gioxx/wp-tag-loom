# Tag Loom - Taxonomy Manager

*[README also available in English](README.md)*

Un plugin WordPress per gestire i tag e i termini di tassonomia dei tuoi articoli con o senza IA: ottieni suggerimenti di unione/pulizia tramite l'AI Client di WordPress core, oppure cerca, unisci ed elimina i tag manualmente. Nulla viene modificato automaticamente: ogni suggerimento IA richiede approvazione manuale.

## Requisiti

- WordPress 7.0 o superiore (include l'AI Client di core)
- PHP 7.4 o superiore
- Un provider IA configurato nell'AI Client di WordPress core (solo per la tab "AI Analysis")

## Installazione

1. Copia l'intera cartella del plugin in `wp-content/plugins/tag-loom/` sul tuo sito WordPress.
2. Vai su **Plugin** nella bacheca di WordPress e attiva "Tag Loom".

## Lingua

L'interfaccia del plugin è in inglese di default. Le traduzioni (italiano incluso) sono gestite tramite [translate.wordpress.org](https://translate.wordpress.org/) invece di essere incluse nel plugin. Questo è indipendente dalla lingua che l'IA usa per scrivere il "motivo" di ogni suggerimento, configurabile separatamente nelle Impostazioni.

## Configurazione

Prima configura un provider IA nelle impostazioni di WordPress (AI Client di WordPress 7.0). Poi vai su **Strumenti → Tag Loom: Settings** e, se serve, regola:

| Campo | Descrizione |
|---|---|
| **AI provider** | Usa il bottone **"Check AI provider"** per verificare che un provider sia configurato. Le credenziali stanno in WordPress core, non nel plugin. |
| **Preferred model(s)** | Opzionale. ID di modelli separati da virgola in ordine di priorità (es. `claude-haiku-4-5, gpt-5.4`); l'AI Client usa il primo supportato dal tuo provider. Vuoto = modello di default del provider. |
| **Batch size** | Numero di tag inviati per ogni richiesta IA (10-500). |
| **AI response language** | Lingua che l'IA deve usare per il testo "motivo" di ogni suggerimento. Lascia vuoto per farla corrispondere automaticamente alla lingua dei nomi dei tag. |
| **Full cleanup on uninstall** | Se attivo (default), l'eliminazione del plugin rimuove impostazioni e storico di suggerimenti/batch dal database. |

## Avviare un'analisi

1. Vai su **Articoli → Tag Loom**, nella tab **"AI Analysis"**.
2. Clicca **"Start analysis"**. I tag vengono elaborati a batch, con un log di elaborazione live e un indicatore di avanzamento; usa **"Stop analysis"** per interromperla.
3. Al termine dei batch, i suggerimenti compaiono raggruppati per tipo:
   - **Near-duplicates** — quasi-duplicati testuali (refusi, plurali, maiuscole/minuscole, trattini/spazi)
   - **Semantic overlaps** — formulazione diversa, significato sovrapposto
   - **Low-usage tags** — tag poco usati che potrebbero confluire in un tag più ampio già presente

## Revisionare i suggerimenti

Ogni suggerimento può essere **Approvato** (unisce il/i tag sorgente nel tag target), **Rifiutato**, oppure in seguito **Ripristinato** dalla lista "Rejected suggestions" per tornare in sospeso. Ogni tabella supporta anche la selezione multipla con checkbox "seleziona tutto" e azioni bulk **Approve selected / Reject selected / Restore selected** — selezione e azioni bulk sono indipendenti per ciascuna tabella.

## Gestire i tag senza IA

La tab **"Manage Tags"** (accanto a "AI Analysis" sulla stessa pagina) raccoglie tutta la gestione manuale, non-IA, dei tag:

- Un istogramma di distribuzione dell'utilizzo per un colpo d'occhio immediato sulla tua tassonomia — clicca su una barra per filtrare la tabella sottostante esattamente a quel range.
- La tabella **"Unused tags (0 posts)"**, con un'opzione di cancellazione multipla; usa **"Recount tag counts"** se i conteggi sembrano sbagliati (es. dopo un'importazione).
- Una tabella di tutti i tag in uso, cercabile, ordinabile e paginata, con ogni tag collegato direttamente alla lista articoli filtrata. Usa il pannello opzioni schermata (in alto a destra) per cambiare quanti tag mostrare per pagina.
- **Cancella** qualsiasi tag singolarmente (azione di riga) o in blocco.
- **Quick Edit** su ogni tag (azione di riga) per rinominarlo o cambiarne lo slug senza uscire dalla pagina.
- **Unisci** 2 o più tag indipendentemente da quanto i loro nomi differiscano, anche se li trovi con ricerche separate: seleziona i tag con le checkbox e usa **"Add to merge selection"** per costruire una selezione persistente (mostrata in una barra sopra la tabella, che sopravvive a ricerche/pagine/ordinamenti diversi), poi **"Prepare merge"**, scegli quale tag deve sopravvivere da una dropdown "Merge into" e conferma. Non serve più dare ai tag un termine di ricerca comune solo per trovarli insieme, come richiede invece la schermata Tag nativa di WordPress — e qui non è coinvolta l'IA.

## Aggiornamenti

Il plugin è compatibile con [Git Updater](https://git-updater.com/), quindi può essere mantenuto aggiornato direttamente dal [repository GitHub](https://github.com/gioxx/wp-tag-loom) senza passare da WordPress.org.
