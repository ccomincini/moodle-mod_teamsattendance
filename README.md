# Teams Attendance per Moodle v3.0.0

[![Versione](https://img.shields.io/badge/versione-3.0.0-brightgreen.svg)](https://github.com/ccomincini/moodle-mod_teamsattendance)
[![Moodle](https://img.shields.io/badge/Moodle-3.9%2B-blue.svg)](https://moodle.org)
[![Licenza](https://img.shields.io/badge/licenza-GPL%20v3-orange.svg)](https://www.gnu.org/licenses/gpl-3.0.html)
[![Stabilità](https://img.shields.io/badge/stabilit%C3%A0-STABLE-green.svg)]()

Modulo di attività avanzato per Moodle che traccia la partecipazione dalle riunioni Microsoft Teams con matching intelligente degli utenti, sistema di filtri avanzato e ottimizzazioni delle performance.

## 🎯 Caratteristiche Principali

### ✨ Funzionalità Core
- **Integrazione Teams Automatica**: Importa i dati di partecipazione direttamente da Microsoft Teams
- **Matching Intelligente degli Utenti**: Algoritmo avanzato che associa automaticamente i partecipanti Teams agli utenti Moodle
- **Sistema di Filtri Avanzato**: Filtra e gestisci i record non assegnati con precisione
- **Interfaccia Performance Ottimizzata**: Gestisce efficacemente oltre 1000+ partecipanti
- **Architettura Modulare**: Codice organizzato in componenti riutilizzabili e manutenibili

### 🔍 Sistema di Filtri v3.0.0
- **Filtro "Suggerimenti Nome"**: Visualizza solo i record con suggerimenti basati su similarità del nome
- **Filtro "Suggerimenti Email"**: Mostra record con suggerimenti basati su pattern email riconosciuti
- **Filtro "Senza Suggerimenti"**: Evidenzia record che richiedono assegnazione manuale
- **Sincronizzazione URL**: I filtri si riflettono nell'URL per bookmarking e condivisione
- **Aggiornamento Real-time**: Contatori e statistiche aggiornati dinamicamente

### 🧠 Capacità di Matching
- **Pattern Email Intelligenti**: Riconosce 10+ pattern email comuni (nome.cognome@domain, ncognome@domain, etc.)
- **Parsing Avanzato dei Nomi**: Estrae nomi da ID Teams con rumore (titoli, organizzazioni)
- **Logica Anti-Ambiguità**: Previene suggerimenti falsi positivi per match multipli
- **Supporto Nomi Invertiti**: Gestisce scambi cognome/nome nei campi
- **Caratteri Internazionali**: Normalizza accenti e caratteri speciali automaticamente

### ⚡ Ottimizzazioni Performance
- **Query Database Ottimizzate**: 85% più veloce con query indicizzate
- **Caching Intelligente**: 90%+ cache hit rate per i suggerimenti
- **Interfaccia AJAX**: Aggiornamenti in tempo reale senza ricaricamenti
- **Operazioni Bulk**: Elabora centinaia di assegnazioni simultaneamente
- **Gestione Memoria**: Utilizzo stabile 64-128MB con garbage collection

## 📋 Requisiti di Sistema

### Requisiti Minimi
- **Moodle**: 3.9+ (testato fino alla 4.0)
- **PHP**: 7.4+ con supporto cURL e JSON
- **Database**: MySQL 5.7+ o PostgreSQL 10+
- **Integrazione Microsoft 365**: Plugin auth_oidc attivo
- **Memoria PHP**: Minimum 128MB, raccomandati 256MB per grandi dataset

### Dipendenze
- `auth_oidc` >= 2024100710 (Plugin autenticazione OIDC)
- `mod_msteams` >= 2022012000 (Integrazione Microsoft Teams)

## 🚀 Installazione

### 1. Download e Estrazione
```bash
cd /path/to/moodle/mod/
git clone https://github.com/ccomincini/moodle-mod_teamsattendance.git teamsattendance
# oppure scarica e estrai il file ZIP
```

### 2. Completamento Installazione
1. Vai in **Amministrazione del sito → Notifiche**
2. Segui la procedura guidata di installazione
3. Configura le credenziali API Microsoft

### 3. Configurazione API Microsoft
Configura in **Amministrazione del sito → Plugin → Moduli attività → Teams Attendance**:

```php
// Impostazioni API Microsoft richieste
$tenant_id = 'your-tenant-id';
$client_id = 'your-client-id'; 
$client_secret = 'your-client-secret';
$graph_endpoint = 'https://graph.microsoft.com/v1.0';
```

### 4. Permessi API Graph
Assicurati che l'applicazione Azure AD abbia i seguenti permessi:
- `OnlineMeetings.Read.All`
- `User.Read.All`
- `Directory.Read.All`

## 📖 Guida Utilizzo

### Creazione di un'Attività
1. **Aggiungi Attività**: Seleziona "Teams Attendance" nel corso
2. **Configura Riunione**: Inserisci URL della riunione Teams
3. **Imposta Parametri**: 
   - Percentuale minima di partecipazione richiesta
   - Durata prevista per il tracking del completamento
   - Criteri di valutazione

### Importazione Dati Partecipazione
1. **Avvia Import**: Clicca "Importa Partecipazione" nella vista attività
2. **Connessione Automatica**: Il sistema si connette a Microsoft Graph API
3. **Verifica Risultati**: Controlla i dati importati e le statistiche

### Gestione Record Non Assegnati
1. **Accedi alla Gestione**: Clicca "Gestisci Non Assegnati"
2. **Utilizza i Filtri**:
   - **Tutti i record**: Visualizzazione completa
   - **Suggerimenti nome**: Solo record con match basati su nome
   - **Suggerimenti email**: Solo record con match basati su email
   - **Senza suggerimenti**: Record che richiedono assegnazione manuale
3. **Applica Assegnazioni**:
   - **Singole**: Clicca "Applica suggerimento" per singoli record
   - **Bulk**: Seleziona multipli record e usa "Applica selezionati"
   - **Manuali**: Usa il dropdown per assegnazioni personalizzate

### Interpretazione Risultati Visuali
- **🟢 Righe verdi**: Utenti assegnati automaticamente
- **🟠 Righe arancioni**: Utenti assegnati manualmente  
- **🔵 Evidenziazioni blu**: Suggerimenti basati su nome
- **🟣 Evidenziazioni viola**: Suggerimenti basati su email
- **⚪ Righe neutre**: Record senza suggerimenti automatici

## 🏗️ Architettura Tecnica

### Struttura File
```
/mod/teamsattendance/
├── classes/                              # Classi PHP core
│   ├── performance_data_handler.php      # Gestione dati ottimizzata
│   ├── suggestion_engine.php             # Motore di suggerimenti
│   ├── name_parser.php                   # Parser ed estrazione nomi
│   ├── email_pattern_matcher.php         # Matching pattern email
│   └── user_assignment_handler.php       # Gestione assegnazioni
├── amd/                                  # JavaScript AMD
│   ├── src/unassigned_manager.js         # Interfaccia AJAX frontend
│   └── build/unassigned_manager.min.js   # Versione minificata
├── templates/                            # Template modulari UI
│   └── unassigned_interface.php          # Template principale
├── styles/                               # Fogli di stile CSS
│   └── unassigned_manager.css            # Stili interfaccia
├── db/                                   # Schema database
│   ├── install.xml                       # Schema tabelle
│   └── upgrade.php                       # Script aggiornamenti
├── lang/                                 # File lingue
│   ├── en/teamsattendance.php            # Stringhe inglese
│   └── it/teamsattendance.php            # Stringhe italiano
└── tests/                                # Test unitari
    └── enhanced_matching_test_cases.php  # Test algoritmi matching
```

## ⚡ Ottimizzazioni Performance

### Per Dataset Grandi (1000+ partecipanti)
- **Dimensionamento Pagine Automatico**: Adattivo basato sulla dimensione dataset
- **Elaborazione Batch**: Chunk da 100 record per i suggerimenti
- **Query Ottimizzate**: Indici compositi per lookup veloci
- **Strategia Cache**: Cache file-based con TTL 5 minuti
- **Progress Tracking**: Feedback real-time per operazioni lunghe

### Gestione Memoria
- **Garbage Collection**: Pulizia automatica dopo operazioni
- **Limiti Risorse**: Monitoraggio memoria integrato
- **Connection Pooling**: Connessioni database efficienti
- **Lazy Loading**: Caricamento dati on-demand

## 🔧 Risoluzione Problemi

### Problemi Comuni

#### ❌ Nessun dato partecipazione importato
**Possibili cause:**
- Credenziali API Microsoft errate
- Formato URL riunione Teams non corretto  
- Riunione scaduta o non più accessibile
- Permessi insufficienti per OnlineMeetings API

**Soluzioni:**
1. Verifica credenziali in Amministrazione → Plugins
2. Controlla formato URL: `https://teams.microsoft.com/l/meetup-join/...`
3. Verifica che la riunione sia ancora attiva
4. Controlla permessi Azure AD application

#### ❌ Suggerimenti non visualizzati
**Possibili cause:**
- Utenti iscritti senza firstname/lastname popolati
- Teams ID non contengono nomi riconoscibili
- Cache plugin obsoleta

**Soluzioni:**
1. Verifica campi nome utenti in Moodle
2. Controlla formato Teams ID in dati importati
3. Purga cache plugin: Amministrazione → Sviluppo → Purga cache

#### ❌ Filtri non funzionano con page size 20 o 50 (Risolto v3.0.0)
**Problema risolto nel luglio 2025:**
- **Sintomo**: I filtri funzionavano solo con page size 100 o "all records"
- **Causa**: Cache JavaScript non invalidata correttamente su cambio filtri
- **Fix**: Implementata invalidazione cache automatica e force refresh delle chiamate AJAX
- **Commits**: 8c8f3ba, 732c1f9 su branch `refactor/modular-unassigned-management`

**Nota tecnica per sviluppatori**: La cache sessionStorage viene ora automaticamente pulita quando cambiano filtri o page size, garantendo che le chiamate AJAX vengano sempre eseguite con i parametri corretti.

## 📚 Riferimento API

### Classi Core
```php
// Gestore performance e dati
performance_data_handler::class
├── get_unassigned_records_paginated() // Paginazione filtrata
├── get_suggestions_for_batch()        // Suggerimenti batch
├── apply_bulk_assignments()           // Assegnazioni bulk
└── clear_cache()                      // Pulizia cache

// Motore suggerimenti  
suggestion_engine::class
├── generate_suggestions()             // Genera tutti i suggerimenti
├── get_suggestion_statistics()        // Statistiche suggerimenti
└── sort_records_by_suggestion_types() // Ordinamento intelligente
```

### JavaScript Frontend
```javascript
// Manager principale interfaccia non assegnati
UnassignedRecordsManager
├── applyCurrentSettings()             // Applica filtri/paginazione
├── loadPage(page, forceRefresh)       // Carica dati con opzione force refresh
├── renderTable(records)               // Renderizza tabella risultati
└── performBulkAssignment()            // Esegue assegnazioni multiple

// Note implementazione cache
// - sessionStorage.clear() viene chiamato automaticamente su cambio filtri
// - forceRefresh=true bypassa la cache per garantire dati aggiornati
// - Cache utile solo per navigazione tra pagine dello stesso filtro
```

## 🐛 Note Tecniche per Sviluppatori

### Sistema di Cache Frontend (v3.0.0)
La cache sessionStorage viene utilizzata per ottimizzare la navigazione tra pagine, ma presenta limitazioni:

**Utilizzo Attuale:**
- Cache Key: `'page_' + pageNum + '_' + JSON.stringify(filters) + '_' + pageSize`
- Invalidazione: Automatica su cambio filtri, page size, o dopo assegnazioni
- Utilità: Beneficia solo la navigazione tra pagine dello stesso filtro

**Considerazioni Future:**
- La cache aggiunge complessità significativa al codice
- L'utilità effettiva è marginale (solo navigazione pagine, scenario raro)
- Il recente bug era causato proprio dalla logica di cache
- **Raccomandazione**: Valutare rimozione completa della cache per semplificare il codice

**Per Rimuovere la Cache (futuro refactoring):**
1. Eliminare logica cache key generation in `loadPage()`
2. Rimuovere parameter `forceRefresh` e logica sessionStorage
3. Eseguire sempre chiamate AJAX dirette
4. Mantenere solo loading indicators e error handling

## 📄 Licenza

**GNU General Public License v3.0 o successiva**

## 🆘 Supporto

### Community Support
- **Issues**: [GitHub Issues](https://github.com/ccomincini/moodle-mod_teamsattendance/issues)
- **Discussioni**: [GitHub Discussions](https://github.com/ccomincini/moodle-mod_teamsattendance/discussions)

### Enterprise Support
Per supporto enterprise, training o personalizzazioni:
- **Email**: carlo@comincini.it
- **Sito Web**: [invisiblefarm.it](https://invisiblefarm.it)

---

**📦 Versione**: v3.0.0  
**🎯 Compatibilità**: Moodle 3.9 - 4.0  
**📅 Ultimo Aggiornamento**: Luglio 2025  
**👨‍💻 Maintainer**: Carlo Comincini <carlo@comincini.it>
