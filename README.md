# Documento tecnico/funzionale
## PHP Error Explainer

### **1. Introduzione**
PHP Error Explainer è una libreria modulare che intercetta errori ed eccezioni negli applicativi PHP e fornisce spiegazioni didattiche e suggerimenti di risoluzione, sfruttando sia una base locale che modelli AI (LLM) locali o API esterne. Il tool punta a migliorare la comprensione degli errori e la produttività degli sviluppatori, integrandosi facilmente con framework come **Symfony** e **Laravel**.

---

### **2. Obiettivi**
- Migliorare la leggibilità e la comprensione degli errori PHP.
- Fornire spiegazioni dettagliate, didattiche e contestualizzate.
- Sfruttare modelli AI per suggerimenti avanzati.
- Integrare facilmente il tool in applicativi vanilla PHP, Symfony e Laravel.

---

### **3. Architettura e Moduli**
Il sistema è suddiviso in tre pacchetti principali:

#### **3.1. Core**
- **Intercettazione Errori:** Gestione di errori, warning, eccezioni tramite handler personalizzati.
- **Spiegazione:** Analisi del messaggio d’errore e generazione di una spiegazione testuale (base e/o AI).
- **LLM Integration:** Interfaccia verso modelli AI locali (es. Ollama, LocalAI) e API esterne (OpenAI, Anthropic).
- **Configurazione:** Possibilità di configurare backend AI, lingua, livello di dettaglio, ecc.

#### **3.2. Symfony Adapter**
- **Bundle Symfony:** Integrazione tramite bundle, aggancio agli eventi di errore (`kernel.exception`).
- **Configurazione via YAML/Env:** Permette di configurare il tool tramite file di configurazione Symfony.
- **Output personalizzato:** Mostra la spiegazione all’interno delle pagine di errore Symfony.

#### **3.3. Laravel Adapter**
- **Service Provider:** Integrazione tramite provider, aggancio ai global error handler di Laravel.
- **Configurazione via config:** Supporto per file di configurazione Laravel.
- **Output personalizzato:** Mostra la spiegazione all’interno delle pagine di errore Laravel.

---

### **4. Funzionalità**

- **Intercettazione automatica:** Errori e eccezioni vengono intercettati e analizzati.
- **Spiegazione base:** Messaggi didattici predefiniti per i principali errori PHP.
- **Spiegazione AI:** Invio del messaggio d’errore al backend AI (locale/API) per una spiegazione avanzata.
- **Supporto framework:** Adattatori per Symfony e Laravel.
- **Configurabilità:** Scelta del backend AI, lingua, output HTML/text, modalità verbose/sintetica.
- **Estendibilità:** Possibilità di aggiungere nuovi adapter per altri framework o ambienti.

---

### **5. Requisiti Tecnici**

- PHP >= 7.4
- Composer per la gestione delle dipendenze
- (Opzionale) Connessione a Ollama/LocalAI per LLM locale
- (Opzionale) API key per OpenAI/Anthropic
- Symfony >= 5.0 (per adapter)
- Laravel >= 8.0 (per adapter)

---

### **6. Flusso di funzionamento**

1. Registrazione degli handler per errori ed eccezioni.
2. Alla ricezione di un errore/exception:
    - Il core analizza il messaggio.
    - Se configurato, invia il messaggio al backend AI.
    - Riceve una spiegazione testuale.
    - Visualizza la spiegazione nella pagina di errore o log.
3. Nel caso di framework:
    - Gli adapter si agganciano agli hook/eventi di errore e chiamano il core.

---

### **7. Output**

- HTML personalizzato per pagine di errore.
- Output testuale per CLI/log.
- Possibilità di esportare la spiegazione come JSON per altri utilizzi.

---

### **8. Sicurezza & Privacy**

- Possibilità di filtrare o anonimizzare i dati inviati ai servizi esterni.
- Configurazione per evitare invio di dati sensibili.

---

### **9. Estensioni**
- Statistiche sugli errori più frequenti.
- Dashboard web di analisi errori.
- Plugin per IDE (VS Code, PhpStorm).
- Community per la condivisione di soluzioni.

---

### **10. Esempio di utilizzo**

**Vanilla PHP:**
```php
use ErrorExplainer\ErrorExplainer;
ErrorExplainer::register();
```

**Symfony:**
```yaml
# config/packages/error_explainer.yaml
error_explainer:
    enabled: true
    backend: 'local'
    model: 'llama2'
```

**Laravel:**
```php
// config/error_explainer.php
return [
    'enabled' => true,
    'backend' => 'api',
    'api_key' => env('ERROR_EXPLAINER_API_KEY'),
];
```

---

### AI e Configurazione

Variabili d'ambiente supportate (possono essere override da opzioni passate a ErrorExplainer::register):
- ERROR_EXPLAINER_ENABLED: 1|0 abilita/disabilita il tool (default: 1)
- ERROR_EXPLAINER_BACKEND: none|local|api
  - none: solo spiegazioni base locali (default)
  - local: invia la richiesta ad un LLM locale (es. Ollama/LocalAI)
  - api: usa un provider API compatibile con OpenAI (es. OpenAI)
- ERROR_EXPLAINER_MODEL: nome modello (es. "llama2", "gpt-4o-mini")
- ERROR_EXPLAINER_API_URL: URL dell'endpoint
  - Local (Ollama): http://localhost:11434 (verrà usato /api/generate)
  - OpenAI: https://api.openai.com/v1/chat/completions
- ERROR_EXPLAINER_API_KEY: chiave API per provider esterni (es. OpenAI)
- ERROR_EXPLAINER_LANG: lingua delle spiegazioni (default: it)
- ERROR_EXPLAINER_OUTPUT: auto|html|text|json (default: auto)
- ERROR_EXPLAINER_VERBOSE: 1|0 include trace dettagliato e messaggi extra (default: 0)

Esempi rapidi
- Solo locale (senza AI):
  ERROR_EXPLAINER_BACKEND=none php examples/vanilla/index.php

- LLM locale (Ollama):
  ERROR_EXPLAINER_BACKEND=local \
  ERROR_EXPLAINER_MODEL=llama2 \
  ERROR_EXPLAINER_API_URL=http://localhost:11434 \
  php examples/vanilla/index.php

- OpenAI:
  ERROR_EXPLAINER_BACKEND=api \
  ERROR_EXPLAINER_MODEL=gpt-4o-mini \
  ERROR_EXPLAINER_API_URL=https://api.openai.com/v1/chat/completions \
  ERROR_EXPLAINER_API_KEY=sk-... \
  php examples/vanilla/index.php

Note tecniche
- L'integrazione AI usa cURL con timeout e fallback silenzioso in caso di errore.
- Per Ollama viene chiamato /api/generate con stream=false; per endpoint compatibili OpenAI si usa /v1/chat/completions.
- Il testo AI viene aggregato ai dettagli e, se rilevati, i punti elenco vengono aggiunti ai suggerimenti.

### **11. Licenza**
MIT (proposta)

---