# OpsPilot

An AI layer for an ordinary support and sales dashboard. Tickets and leads arrive as usual, the AI does the repetitive work (triage, reply drafts, lead scoring, follow-up emails, a daily brief), and a person accepts, edits or rejects every suggestion. Nothing is sent or changed without human approval, and every AI decision is logged.

It ships with **Oakline Home**, a fictional furniture retailer with 40 tickets, 25 leads and two weeks of AI history, so the demo looks alive before a single API call. Every person, company and email address in the sample data is invented.

Built with Laravel 13, Inertia v3 + React 19, Tailwind CSS v4 and MySQL. AI calls go through a small provider layer with Anthropic (Claude) and OpenAI drivers.

---

## Contents

1. [Quick start](#quick-start)
2. [Demo accounts and demo mode](#demo-accounts-and-demo-mode)
3. [Environment variables](#environment-variables)
4. [Switching AI providers](#switching-ai-providers)
5. [Cost notes](#cost-notes)
6. [How it works](#how-it-works)
7. [Testing](#testing)
8. [Deploying to a small VPS](#deploying-to-a-small-vps)
9. [Re-skinning for a client](#re-skinning-for-a-client)

---

## Quick start

Requirements: PHP 8.4 (with `mbstring`, `xml`, `curl`, `mysql`, `intl`, `zip`, `bcmath`, `gd`), Composer 2, Node 22, MySQL 8.

```bash
git clone <your-repo-url> opspilot && cd opspilot
cp .env.example .env              # then edit the DB_* and AI keys (see below)

mysql -u root -p -e "CREATE DATABASE opspilot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'opspilot'@'localhost' IDENTIFIED BY 'choose-a-password';
  GRANT ALL ON opspilot.* TO 'opspilot'@'localhost';"

composer setup                     # install, key, migrate, link storage, seed Oakline Home, build assets
composer run dev                   # app + queue worker + logs + Vite, at http://localhost:8000
php artisan schedule:work          # optional, in another terminal: daily briefs and demo cleanup
```

Sign in as `maya@oaklinehome.example` / `password` (admin) or `theo@oaklinehome.example` / `password` (agent).

> AI features need a key. Without one, everything else works and AI actions show a friendly "AI isn't set up yet" message. The seeded sample data never needs a key.

---

## Demo accounts and demo mode

There are two ways to show the app:

|               | Showcase workspace                               | Visitor sandboxes                                                               |
| ------------- | ------------------------------------------------ | ------------------------------------------------------------------------------- |
| Who uses it   | You, during presentations                        | Prospects you send the link to                                                  |
| How to get in | Sign in with the showcase email and password     | "Try the demo as an Admin / Agent" on the landing page (needs `DEMO_MODE=true`) |
| Data          | Oakline Home sample data                         | A private copy of Oakline Home per visitor                                      |
| Lifetime      | Restored to the sample data every night at 03:00 | Deleted after 24 hours                                                          |

With `DEMO_MODE=true`:

- the landing page offers one-click sandboxes and public registration is closed;
- **no email is ever sent** (visitors can type any address into the public forms), and replies are saved to the conversation instead;
- AI spending has a **daily global cap** (`DEMO_DAILY_AI_SPEND_CAP_USD`, measured from the AI usage log). When it is reached, AI features pause with a friendly banner until midnight and everything else keeps working;
- per-user and per-IP rate limits apply to AI actions, sandbox creation and the public forms;
- CSV uploads are limited in size, rows and number of files per person.

### `demo:reset`

```bash
php artisan demo:reset                    # remove expired sandboxes + restore the showcase workspace
php artisan demo:reset --sandboxes-only   # only remove expired sandboxes
php artisan demo:reset --all-sandboxes    # remove every sandbox, even active ones
php artisan demo:reset --force            # run even when DEMO_MODE is off (it deletes data!)
```

The scheduler runs `demo:reset --sandboxes-only` hourly and the full `demo:reset` nightly at 03:00, only while demo mode is on.

---

## Environment variables

Everything not listed here is a standard Laravel setting (`APP_*`, `DB_*`, `MAIL_*`, `QUEUE_CONNECTION=database`).

### AI provider

| Variable                                               | Default                  | Purpose                                                                                                                                           |
| ------------------------------------------------------ | ------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| `AI_PROVIDER`                                          | `anthropic`              | `anthropic` or `openai`, used for all chat-style calls                                                                                            |
| `AI_MODEL`                                             | `claude-opus-5-5`        | Model for triage, drafts, scoring, follow-ups, briefs and rewrites. Never hard-coded in the app                                                   |
| `ANTHROPIC_API_KEY`                                    |                          | Needed when `AI_PROVIDER=anthropic`                                                                                                               |
| `ANTHROPIC_EFFORT`                                     | `low`                    | Claude's effort level (`low` … `max`). `low` keeps routine work fast and cheap                                                                    |
| `ANTHROPIC_REFUSAL_FALLBACK`                           | `true`                   | If Claude declines a request for safety reasons, Anthropic retries it on another model inside the same call. Only sent for models that support it |
| `OPENAI_API_KEY`                                       |                          | Needed for `AI_PROVIDER=openai` or OpenAI embeddings                                                                                              |
| `AI_EMBEDDING_PROVIDER`                                | `openai`                 | Optional. Ranks knowledge snippets by relevance. Leave empty to send all snippets instead (fine for up to ~25)                                    |
| `AI_EMBEDDING_MODEL`                                   | `text-embedding-3-small` | Embedding model                                                                                                                                   |
| `AI_TIMEOUT_SECONDS`                                   | `60`                     | Per-request timeout                                                                                                                               |
| `AI_MAX_ATTEMPTS`                                      | `3`                      | Attempts per call. Rate limits (429), overloads (529) and 5xx errors are retried with exponential backoff, honouring `retry-after`                |
| `AI_PRICE_INPUT_PER_MTOK` / `AI_PRICE_OUTPUT_PER_MTOK` |                          | Override the price of `AI_MODEL` (USD per million tokens) used for cost estimates                                                                 |

### Demo mode and limits

| Variable                                                   | Default                        | Purpose                                                        |
| ---------------------------------------------------------- | ------------------------------ | -------------------------------------------------------------- |
| `DEMO_MODE`                                                | `false`                        | Turns on sandboxes and all the guards above                    |
| `DEMO_DAILY_AI_SPEND_CAP_USD`                              | `2.00`                         | Global daily AI budget across all sandboxes                    |
| `DEMO_SANDBOX_LIFETIME_HOURS`                              | `24`                           | How long a visitor's sandbox lives                             |
| `DEMO_SANDBOXES_PER_IP_PER_HOUR`                           | `5`                            | Sandbox creation limit                                         |
| `DEMO_AI_REQUESTS_PER_USER_PER_MINUTE`                     | `10`                           | AI actions per person                                          |
| `DEMO_AI_REQUESTS_PER_IP_PER_HOUR`                         | `60`                           | AI actions per IP address                                      |
| `DEMO_MAX_UPLOAD_KB`                                       | `512`                          | CSV upload size                                                |
| `DEMO_MAX_FILES_PER_USER`                                  | `5`                            | CSV imports per person                                         |
| `DEMO_MAX_IMPORT_ROWS`                                     | `50`                           | Rows per CSV import                                            |
| `DEMO_SHOWCASE_ADMIN_EMAIL` / `_AGENT_EMAIL` / `_PASSWORD` | `maya@…`, `theo@…`, `password` | Showcase sign-ins. **Change the password before going public** |

### Branding defaults

`BRAND_NAME`, `BRAND_PRIMARY_COLOR` (quote it: `"#4f46e5"`, an unquoted `#` starts a comment in `.env`), `BRAND_SUPPORT_EMAIL` and `BRAND_LOGO_MAX_KB` set the defaults for the landing and sign-in pages and for new workspaces. Each workspace can then re-skin itself in **Settings → Branding**.

---

## Switching AI providers

Only `.env` changes; there is no code to touch.

**Claude (default)**

```dotenv
AI_PROVIDER=anthropic
AI_MODEL=claude-opus-5-5          # or claude-sonnet-5-5, claude-haiku-5-5
ANTHROPIC_API_KEY=sk-ant-...
AI_EMBEDDING_PROVIDER=openai      # Anthropic has no embeddings API; or leave empty
OPENAI_API_KEY=sk-...             # only needed for embeddings
```

**OpenAI**

```dotenv
AI_PROVIDER=openai
AI_MODEL=gpt-4.1-mini
OPENAI_API_KEY=sk-...
AI_EMBEDDING_PROVIDER=openai
```

Then run `php artisan config:clear` (or `config:cache` in production) and restart the queue worker. **Settings → AI features → Run test** streams a short reply, so you can confirm the provider, model and key work.

Adding another provider means writing one class that implements `App\Services\AI\AiClient` and registering it in `App\Services\AI\AiManager`. Everything else (logging, cost estimates, budgets, retries, tests) works unchanged, because the app only talks to `App\Services\AI\AiGateway`.

---

## Cost notes

Every AI call is logged in `ai_usage_logs` with tokens, estimated cost, duration and status. **Settings → AI usage** shows today's spend, the last 7 days, cost by feature and every recent call. The figures below are rough estimates (they include a little thinking at `low` effort). Use the AI usage page for real numbers.

| Work                               | Calls                   | Claude Opus 5.5 ($4 / $20 per M tokens)       | Claude Haiku 5.5 ($0.10 / $0.50) |
| ---------------------------------- | ----------------------- | --------------------------------------------- | -------------------------------- |
| New ticket (triage + reply draft)  | 2                       | about $0.03                                   | about $0.0007                    |
| New lead (score + follow-up draft) | 2                       | about $0.02                                   | about $0.0005                    |
| Rewrite a reply                    | 1                       | about $0.01                                   | about $0.0002                    |
| Daily brief                        | 1 per workspace per day | about $0.03 (quiet days are free, no AI call) | under $0.001                     |
| Knowledge-snippet embedding        | 1 per snippet save      | negligible                                    | negligible                       |

Ways to keep costs down:

- For a public demo, `AI_MODEL=claude-haiku-5-5` is roughly 40× cheaper than Opus 5.5 and fine for triage and short drafts.
- Keep `ANTHROPIC_EFFORT=low`.
- Leave the demo budget cap on. At $2 a day it covers roughly 60 tickets on Opus 5.5 or a few thousand on Haiku.
- Switch off AI features a client doesn't need in **Settings → AI features**.
- Sample data never costs anything: seeded "AI results" are logged with provider `sample-data` at $0 and don't count toward the cap.

Prices for unknown models fall back to a deliberately high rate, so the cap errs on the safe side. Check `config/ai.php` against your provider's pricing page when you change models.

---

## How it works

```
Contact form ─┐
Pasted email ─┼─► CreateTicket ─► queue ─► TriageTicket ───► pending suggestions ─► agent accepts / edits / rejects
Tickets API  ─┘                       └──► DraftTicketReply ─► reply draft ─────────► agent edits, rewrites, sends

Lead form ──┬─► CreateLead ─► queue ─► ScoreLead ──────────► hot / warm / cold + reason ─► accept / change / reject
CSV import ─┘                     └──► DraftLeadFollowUp ──► follow-up email ─────────────► edit, then mark as sent

Scheduler (06:00) ─► briefs:generate ─► GenerateDailyBrief ─► headline, summary, themes, urgent items
```

- **One gateway for all AI.** `App\Services\AI\AiGateway` checks the feature switch and the budget, adds the workspace's custom instructions, calls the driver, and writes one `ai_usage_logs` row per call, whether it succeeds or fails.
- **Humans stay in control.** Suggestions are stored in `ai_suggestions` and only change a ticket or lead when someone accepts or edits them. Optional _auto-apply_ exists for ticket triage only and is off by default. The **AI Activity** page shows every decision with its input, output and acceptance rate.
- **Queues for everything except streaming.** Triage, drafts, scoring, follow-ups, imports, embeddings and briefs run on the database queue with retries and backoff. Only the reply rewrite toolbar and "regenerate draft" stream directly to the browser (Server-Sent Events).
- **Untrusted input stays data.** Customer text is passed to the AI inside tags and the prompts say to ignore instructions inside it. Structured outputs (JSON schemas) constrain answers, and the app re-checks every reference before storing it.
- **Multi-tenant by workspace.** Records from another workspace return 404, never 403.

Code layout: `app/Actions` (business operations), `app/Services` (AI layer, insights, tickets, leads), `app/Jobs`, `app/Http/Requests`, `app/Policies`, `app/Enums`, and `resources/js/pages` (Inertia pages).

---

## Testing

```bash
php artisan test --compact     # Pest feature tests; the AI is always faked
vendor/bin/phpstan analyse     # static analysis
vendor/bin/pint                # code style
npm run types:check && npm run check
```

Tests never hit a real AI provider. They use `App\Services\AI\Testing\FakeAiClient::install()` for app-level tests and `Http::fake()` (with stray requests blocked) for the driver tests.

---

## Deploying to a small VPS

A 2 vCPU / 2–4 GB VPS on Ubuntu 24.04 is plenty. Replace `opspilot.example.com` with your domain.

### 1. Install the stack

```bash
sudo apt update && sudo apt install -y nginx mysql-server supervisor unzip git \
  php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl php8.4-intl php8.4-zip php8.4-bcmath php8.4-gd
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - && sudo apt install -y nodejs   # only needed to build assets
```

Create the database and user as in [Quick start](#quick-start).

### 2. Get the code and configure it

```bash
sudo mkdir -p /var/www/opspilot && sudo chown $USER:www-data /var/www/opspilot
git clone <your-repo-url> /var/www/opspilot && cd /var/www/opspilot
cp .env.example .env
```

Edit `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://opspilot.example.com
DB_PASSWORD=...
ANTHROPIC_API_KEY=...
DEMO_MODE=true                    # for a public demo
DEMO_SHOWCASE_PASSWORD=...        # something long
MAIL_MAILER=log                   # or real SMTP settings if DEMO_MODE=false
```

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force       # creates the Oakline Home showcase
php artisan storage:link
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo chown -R $USER:www-data storage bootstrap/cache && sudo chmod -R ug+rwx storage bootstrap/cache
```

### 3. Nginx

`/etc/nginx/sites-available/opspilot`:

```nginx
server {
    listen 80;
    server_name opspilot.example.com;
    root /var/www/opspilot/public;
    index index.php;
    client_max_body_size 6m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_read_timeout 120s;    # streamed AI rewrites can take a while
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/opspilot /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo apt install -y certbot python3-certbot-nginx && sudo certbot --nginx -d opspilot.example.com
```

Streaming responses already send `X-Accel-Buffering: no`, so Nginx passes them through without buffering.

### 4. Queue worker (Supervisor)

`/etc/supervisor/conf.d/opspilot-worker.conf`:

```ini
[program:opspilot-worker]
command=php /var/www/opspilot/artisan queue:work --sleep=3 --max-time=3600
user=www-data
numprocs=2
process_name=%(program_name)s_%(process_num)02d
autostart=true
autorestart=true
stopwaitsecs=330
redirect_stderr=true
stdout_logfile=/var/www/opspilot/storage/logs/worker.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
```

Jobs set their own retries and backoff, so the worker needs no `--tries` flag.

### 5. Scheduler (cron)

```bash
( sudo crontab -u www-data -l 2>/dev/null; echo "* * * * * cd /var/www/opspilot && php artisan schedule:run >> /dev/null 2>&1" ) | sudo crontab -u www-data -
```

This runs the 06:00 daily briefs and, in demo mode, the hourly sandbox cleanup and nightly `demo:reset`.

### 6. Updating

```bash
cd /var/www/opspilot
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
php artisan up
```

Optional: server-side rendering. Build it with `npm run build:ssr` and run `php artisan inertia:start-ssr` under Supervisor. Without it, pages render in the browser as normal.

---

## Re-skinning for a client

1. Sign in as an admin and open **Settings → Branding**: brand name, logo, primary color and support email. The live preview updates as you type, and the whole app, the sign-in pages and the public forms switch over on save.
2. **Settings → Ticket categories**, **Knowledge snippets** and **Lead scoring**: put in the client's own categories, policies and ideal customer. The AI uses them immediately.
3. **Settings → AI features**: turn features on or off and add custom instructions, such as a sign-off or tone of voice.
4. Share the public forms from **Settings → API & channels**: the contact form, the lead form and the tickets API.
