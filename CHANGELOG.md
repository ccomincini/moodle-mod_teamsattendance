# Changelog

All notable changes to the Teams Attendance module will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.0] - 2025-07-24

### 🎉 MAJOR RELEASE - Sistema di Filtri Definitivo

### Added
- **Sistema di Filtri Avanzato**: Implementazione completa del sistema di filtri per la gestione dei record non assegnati
  - Filtro "Suggerimenti desunti dal nome": Mostra solo record con suggerimenti basati su similarità del nome
  - Filtro "Suggerimenti desunti dall'email": Mostra solo record con suggerimenti basati su pattern email
  - Filtro "Senza suggerimenti": Mostra solo record che non hanno suggerimenti automatici
  - Filtro "Tutti i record": Visualizzazione completa senza filtri
- **Sincronizzazione URL-Interfaccia**: I filtri vengono riflessi nell'URL e viceversa per bookmarking e condivisione
- **Performance Handler Modulare**: Nuova classe `performance_data_handler` per gestione ottimizzata dei dati
  - Caching intelligente dei suggerimenti
  - Paginazione efficiente con filtri server-side
  - Gestione bulk operations con progress tracking
- **JavaScript Modulare**: Completa riscrittura del frontend JavaScript
  - Lettura automatica dei parametri URL all'inizializzazione
  - Aggiornamento dinamico dell'URL senza ricaricamento pagina
  - Gestione state management avanzata
  - Caching client-side per performance ottimali

### Changed
- **Architettura Modulare**: Completo refactoring dell'architettura del codice
  - Separazione delle responsabilità tra performance, suggestion engine e UI
  - Template system modulare per migliore manutenibilità
  - Namespace PHP corretto per tutte le classi
- **Interfaccia Utente Migliorata**: 
  - Contatori in tempo reale per ogni tipo di filtro
  - Feedback visivo immediato per tutte le operazioni
  - Gestione errori più robusta con messaggi informativi
- **Algoritmo di Filtri Ottimizzato**: 
  - Conversione corretta tra formati URL e backend
  - Mappatura precisa dei tipi di suggerimento
  - Validazione rigorosa dei parametri

### Technical Improvements
- **Codice Pulito e Documentato**: Tutti i commenti tradotti in italiano con spiegazioni dettagliate
- **Gestione Errori Avanzata**: Sistema robusto per la gestione di errori PHP e JavaScript
- **Cache Management**: Sistema di cache intelligente per ottimizzazione delle performance
- **AJAX Optimizations**: Chiamate AJAX ottimizzate con gestione JSON corretta
- **Parameter Validation**: Validazione rigorosa di tutti i parametri con whitelist di sicurezza

### Fixed
- **Problema Filtri Risolto**: Completa risoluzione del bug che impediva il funzionamento dei filtri
- **Namespace Issues**: Risolti tutti i problemi di namespace tra classi PHP
- **URL Parameter Handling**: Corretto il problema con `PARAM_ALPHA` che rimuoveva gli underscore
- **JavaScript State Sync**: Risolto il mismatch tra stato JavaScript e parametri URL
- **Template Data Flow**: Corretto il flusso dati tra backend e frontend

### Security
- **Parameter Sanitization**: Validazione e sanitizzazione completa di tutti i parametri
- **AJAX Security**: Protezione robusta per tutte le chiamate AJAX con sesskey validation
- **Input Validation**: Whitelist rigorosa per tutti gli input utente

### Performance
- **Database Query Optimization**: Ottimizzazione delle query per migliori performance
- **Client-side Caching**: Sistema di cache browser per ridurre le chiamate server
- **Lazy Loading**: Caricamento intelligente dei dati solo quando necessario
- **Memory Management**: Gestione ottimizzata della memoria per grandi dataset

### Documentation
- **Inline Documentation**: Documentazione completa di tutte le funzioni e classi
- **Code Comments**: Commenti dettagliati in italiano per migliore comprensione
- **Architecture Notes**: Documentazione dell'architettura modulare implementata

## [1.1.0] - 2025-07-14

### Added
- **Intelligent Name-Based Matching**: Automatic matching suggestions between Teams users and Moodle users based on name similarity
  - Supports multiple name formats: "LastName, FirstName", "FirstName LastName", etc.
  - Uses Levenshtein distance algorithm with 80% minimum similarity threshold
  - Handles edge cases with compound names and special characters
- **Visual Styling for Unassigned Records Page**:
  - Light green background for rows with suggested matches
  - Light orange background for rows without automatic matches  
  - Color legend to explain visual coding
  - Left border indicators for better visual distinction
  - Hover effects and visual feedback
- **Smart Record Sorting**: Suggested matches displayed first, followed by non-suggested records
- **Filtered User Lists**: Dropdown menus now exclude users already assigned to prevent duplicates
- **Bulk Operations**: Apply multiple suggested matches simultaneously with checkbox selection
- **Persistent Suggestion Tracking**: Applied suggestions are not shown again on page reload
- **Enhanced JavaScript Interactions**:
  - Confirmation dialogs for single and bulk operations
  - Visual feedback when suggestions are selected/deselected
  - Real-time button state management
- **Comprehensive Language Support**:
  - New English strings for all improved features
  - Complete Italian translations for all new functionality
  - User-friendly messages and notifications

### Changed
- **Improved manage_unassigned.php Interface**: Complete redesign with better UX and visual hierarchy
- **Enhanced User Assignment Workflow**: Streamlined process with intelligent suggestions
- **Optimized Database Queries**: More efficient retrieval of available users
- **Better Error Handling**: More informative messages for edge cases

### Technical Improvements
- **Algorithm Implementation**: Sophisticated name parsing and similarity calculation
- **CSS Styling**: Custom styles for improved visual distinction
- **Performance Optimization**: Reduced database queries and improved loading times
- **Code Organization**: Better separation of concerns and modular functions

### Security
- **Enhanced Validation**: Improved sesskey validation for all operations
- **Input Sanitization**: Better protection against malicious inputs
- **Capability Checks**: Proper permission verification for all actions

## [1.0.7] - 2025-06-23

### Fixed
- Various bug fixes and stability improvements
- Enhanced error handling for API connections

### Security
- Updated dependencies and security patches

## [1.0.6] - Previous Release

### Added
- Basic user assignment functionality
- Manual assignment capabilities
- Core attendance tracking features

---

## Version History Format

- **Added** for new features
- **Changed** for changes in existing functionality  
- **Deprecated** for soon-to-be removed features
- **Removed** for now removed features
- **Fixed** for any bug fixes
- **Security** in case of vulnerabilities
- **Technical Improvements** for code quality and performance enhancements
