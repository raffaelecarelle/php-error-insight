## PHP Error Explainer

### **1. Introduction**
PHP Error Explainer is a modular library that intercepts errors and exceptions in PHP applications and provides educational explanations and resolution suggestions, leveraging both a local database and AI models (LLM) either local or external APIs. The tool aims to improve error understanding and developer productivity, easily integrating with frameworks like **Symfony** and **Laravel**.

---

### **2. Objectives**
- Improve readability and understanding of PHP errors.
- Provide detailed, educational, and contextualized explanations.
- Leverage AI models for advanced suggestions.
- Easily integrate the tool into vanilla PHP, Symfony, and Laravel applications.

---

### **3. Architecture and Modules**
The system is divided into three main packages:

#### **3.1. Core**
- **Error Interception:** Management of errors, warnings, exceptions through custom handlers.
- **Explanation:** Analysis of error message and generation of textual explanation (basic and/or AI).
- **LLM Integration:** Interface to local AI models (e.g., Ollama, LocalAI) and external APIs (OpenAI, Anthropic).
- **Configuration:** Ability to configure AI backend, language, detail level, etc.

#### **3.2. Symfony Adapter**
- **Symfony Bundle:** Integration through bundle, hooking into error events (`kernel.exception`).
- **YAML/Env Configuration:** Allows configuring the tool through Symfony configuration files.
- **Custom Output:** Shows explanation within Symfony error pages.

#### **3.3. Laravel Adapter**
- **Service Provider:** Integration through provider, hooking into Laravel's global error handler.
- **Config Configuration:** Support for Laravel configuration files.
- **Custom Output:** Shows explanation within Laravel error pages.

---

### **4. Features**

- **Automatic Interception:** Errors and exceptions are intercepted and analyzed.
- **Basic Explanation:** Predefined educational messages for main PHP errors.
- **AI Explanation:** Sending error message to AI backend (local/API) for advanced explanation.
- **Framework Support:** Adapters for Symfony and Laravel.
- **Configurability:** Choice of AI backend, language, HTML/text output, verbose/synthetic mode.
- **Extensibility:** Ability to add new adapters for other frameworks or environments.

---

### **5. Technical Requirements**

- PHP >= 7.4
- Composer for dependency management
- (Optional) Connection to Ollama/LocalAI for local LLM
- (Optional) API key for OpenAI/Anthropic
- Symfony >= 5.0 (for adapter)
- Laravel >= 8.0 (for adapter)

---

### **6. Operation Flow**

1. Registration of handlers for errors and exceptions.
2. Upon receiving an error/exception:
    - The core analyzes the message.
    - If configured, sends the message to AI backend.
    - Receives a textual explanation.
    - Displays the explanation in error page or log.
3. For frameworks:
    - Adapters hook into error hooks/events and call the core.

---

### **7. Output**

- Custom HTML for error pages.
- Textual output for CLI/log.
- Ability to export explanation as JSON for other uses.

---

### **8. Security & Privacy**

- Ability to filter or anonymize data sent to external services.
- Configuration to avoid sending sensitive data.

---

### **9. Next Extensions **
- Statistics on most frequent errors.
- Web dashboard for error analysis.
- IDE plugins (VS Code, PhpStorm).
- Community for sharing solutions.

---

### **10. Usage Example**

**Vanilla PHP:**