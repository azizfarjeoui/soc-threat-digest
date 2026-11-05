# Weekly SOC Threat Intelligence Digest App

A production-quality Symfony 7.x application designed for Security Operations Centers (SOC) to automatically collect, aggregate, normalize, deduplicate, and display threat intelligence.

It fetches threat data every Monday at 08:00 AM (configured timezone Africa/Tunis) from multiple security sources:
1. **CISA KEV (Known Exploited Vulnerabilities)** (Exploited CVEs catalog)
2. **NVD (National Vulnerability Database)** (New published CVEs)
3. **MalwareBazaar** (Recent malware samples/signatures)
4. **AlienVault OTX** (Subscribed threat indicators/pulses)

---

## Architecture Diagram

```text
               +--------------------------------------+
               |          Symfony Scheduler           |
               |       (Cron: Mon 08:00 Tunis)        |
               +------------------+-------------------+
                                  |
                   [TriggerScheduledFetchMessage]
                                  v
               +------------------+-------------------+
               |      TriggerScheduledFetchHandler    |
               | (Create FetchRun + Dispatch Subjobs) |
               +------------------+-------------------+
                                  |
           +----------------------+----------------------+
           | [FetchThreatSourceMessage] (Async)          |
           v                                             v
+----------+------------+                    +-----------+-----------+
| FetchThreatSource     |                    | FetchThreatSource     |
| Handler (CISA_KEV)    |                    | Handler (NVD)         |
+----------+------------+                    +-----------+-----------+
           |                                             |
           +----------------------+----------------------+
                                  |
                                  v
               +------------------+-------------------+
               |       ThreatDigestManager Service    |
               |  - Fetch via HttpClient              |
               |  - Normalize to common shape         |
               |  - Deduplicate against database      |
               |  - Persist normalized ThreatReport   |
               |  - Record errors/status in FetchRun  |
               +------------------+-------------------+
                                  |
                                  v
                       +----------+----------+
                       |    SQLite / MySQL   |
                       +----------+----------+
                                  ^
                                  |
                       +----------+----------+
                       |    Twig Dashboard   |
                       | (Analyze / Filter)  |
                       +---------------------+
```

---

## Setup & Local Running

The application is pre-configured to use **SQLite** out-of-the-box for easy local development, eliminating any database dependency setup.

### Prerequisites
- PHP 8.2+
- Composer 2.x
- SQLite PHP extension enabled (`extension=pdo_sqlite` and `extension=sqlite3` in `php.ini`)

### Steps
1. **Navigate** to project directory:
   ```bash
   cd C:\Users\zak\.gemini\antigravity\scratch\soc-threat-digest
   ```
2. **Install Dependencies**:
   ```bash
   composer install
   ```
3. **Configure API Keys** (Optional):
   Copy `.env.example` to `.env` or edit the existing `.env` to configure API keys:
   ```bash
   NVD_API_KEY=your_nvd_key_here
   OTX_API_KEY=your_otx_key_here
   MALWARE_BAZAAR_API_KEY=your_key_here
   ```
4. **Run Migrations** (creates SQLite database and tables):
   ```bash
   php bin/console doctrine:migrations:migrate --no-interaction
   ```
5. **Start Dev Server**:
   ```bash
   php -S localhost:8000 -t public
   ```
   *Access the dashboard at: `http://localhost:8000`*

### Manually Running the Fetch Pipeline
You can trigger the threat intelligence ingestion synchronously at any time via:
```bash
php bin/console app:fetch-threat-digest
```

**Options:**
- `--days=X`: Fetch threats published/updated in the last X days (Default is 7).
- `--source=NAME`: Run for a specific source only (e.g., `CISA_KEV`, `NVD`, `MALWARE_BAZAAR`, `OTX`).

---

## Containerized Run (Docker)

To run the full stack (PHP 8.2 + Apache, MySQL 8, and an asynchronous Messenger queue consumer) in Docker:

1. **Start containers**:
   ```bash
   docker-compose up -d --build
   ```
2. **Run database migrations** inside the app container:
   ```bash
   docker-compose exec app php bin/console doctrine:migrations:migrate --no-interaction
   ```
3. Access the application at `http://localhost:8000`

---

## Scheduling & Async Processing Details

1. **Triggering**: The `ThreatScheduler` schedules a weekly run every Monday at 08:00 AM Tunis Time.
2. **Queueing**: A `TriggerScheduledFetchHandler` starts a new `FetchRun` log record, then splits the task into individual source-specific message items (`FetchThreatSourceMessage`).
3. **Async Workers**: The messages are routed to the Doctrine transport (`async`). A background daemon or docker container runs:
   ```bash
   php bin/console messenger:consume async
   ```
   It processes the fetching jobs concurrently, isolates failures (so one API crash doesn't block another source), and updates the shared `FetchRun` statistics and errors array.
4. **Final Status**: When all scheduled sources have finished, the `FetchRun` status is updated to `SUCCESS`, `PARTIAL_FAILURE`, or `FAILED`.
