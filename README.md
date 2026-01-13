# AmirRAG - EGroupware RAG Application

A complete Retrieval-Augmented Generation (RAG) system for EGroupware with semantic search and natural language question answering across Addressbook, Calendar, and InfoLog.

## Features

- **Multi-Application Support**: Index and search across Addressbook, Calendar, and InfoLog
- **Semantic Search**: Natural language search using state-of-the-art embeddings
- **User Isolation**: Complete data isolation per user
- **Flexible Embeddings**: Support for OpenAI and IONOS embedding models
- **LLM Integration**: Natural language responses using OpenAI or IONOS LLMs
- **MariaDB Backend**: Reliable database with vector search capabilities

## Installation

### Prerequisites

- EGroupware 23.1 or higher
- PHP 7.4 or higher
- MariaDB 10.2 or higher
- OpenAI or IONOS API key

### Setup Steps

1. **Copy the app to EGroupware**
   ```bash
   cd /path/to/egroupware
   cp -r amirrag ./
   ```

2. **Install PHP dependencies**
   ```bash
   cd amirrag
   composer install
   ```

3. **Install the app in EGroupware**
   - Login as admin
   - Go to Admin → Applications
   - Find "AmirRAG" and click Install
   - Run the database setup

4. **Configure the app**
   - Go to Admin → Applications → AmirRAG → Configuration
   - Set your API keys for embeddings and LLM
   - Choose your provider (OpenAI or IONOS)
   - Configure model names and parameters

5. **Index your data**
   - Open AmirRAG application
   - Click "Index Data"
   - Select which applications to index
   - Click "Start Indexing"

## Configuration

### Embedding Providers

**OpenAI:**
- Provider: `openai`
- Model: `text-embedding-ada-002` or `text-embedding-3-small`
- API Key: Your OpenAI API key

**IONOS:**
- Provider: `ionos`
- Model: `BAAI/bge-m3`
- API URL: `https://openai.inference.de-txl.ionos.com/v1`
- API Key: Your IONOS API key

### LLM Providers

**OpenAI:**
- Provider: `openai`
- Model: `gpt-3.5-turbo` or `gpt-4`
- API Key: Your OpenAI API key

**IONOS:**
- Provider: `ionos`
- Model: `meta-llama/Llama-3.3-70B-Instruct`
- API URL: `https://openai.inference.de-txl.ionos.com/v1`
- API Key: Your IONOS API key

## Usage

### Searching

1. Open the AmirRAG application
2. Enter your natural language query
3. Optionally filter by application (Addressbook, Calendar, InfoLog)
4. Enable "Use AI Response" for natural language answers
5. Click "Search"

### Indexing

The app automatically indexes new/modified data through hooks. You can also manually index:

1. Click "Index Data" in the menu
2. Select the application(s) to index
3. Set a limit (0 = all records)
4. Click "Start Indexing"

## Architecture

```
┌─────────────────┐
│  Web Interface  │
│  (ETemplate2)   │
└────────┬────────┘
         │
┌────────▼────────┐
│   Ui (Frontend) │
└────────┬────────┘
         │
┌────────▼────────┐
│  Bo (Business)  │
│  - Indexing     │
│  - Search       │
└─┬─────┬────┬───┘
  │     │    │
  │     │    └──────────────┐
  │     │                   │
┌─▼─────▼────┐    ┌────────▼────────┐
│ Embedding  │    │   Database      │
│ & LLM      │    │   Service       │
│ Services   │    │  (Vector DB)    │
└────────────┘    └─────────────────┘
```

## Components

### PHP Classes

- **Bo.php**: Business logic layer
- **Ui.php**: User interface controller
- **Hooks.php**: EGroupware hooks integration
- **EmbeddingService.php**: Vector embedding generation
- **LLMService.php**: Natural language response generation
- **DatabaseService.php**: Vector storage and similarity search

### Database Tables

- **egw_amirrag_documents**: Stores document vectors and metadata
- **egw_amirrag_index_queue**: Queue for async indexing

## License

GPL-3.0-or-later

## Author

Amir - amir@egroupware.org

## Support

For issues and support, please contact the EGroupware community.
