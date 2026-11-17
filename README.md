# AssistantSupport

A small, self-hosted memory and knowledge base for voice assistants (like Grok in a Tesla) that start every session fresh and can only read web pages with GET requests.

Single user, PHP, no database server, no JavaScript. Runs as one Docker container with an Unraid-friendly `/data` + `/config` layout.

## How it works

1. You tell the assistant: *"Read https://assistant.example.com"*.
2. That public welcome page tells the assistant to ask you for the passphrase: a sentence of at least three words.
3. You say the sentence. The assistant opens `https://assistant.example.com/?pass=the-purple-elephant-sings-loudly`.
4. That one private page holds everything: who the assistant is (backstory, tone, language), its enabled skills, your links and the 5 most recent memories. Older memories are one link away, organised per week.
5. The assistant searches older memories and saves new ones, all with GET requests:
   - `/?pass=…&page=history` (memories per week)
   - `/?pass=…&page=memories&q=invoice` (search)
   - `/?pass=…&page=remember&title=Short+title&summary=What+happened&points=First|Second`

You manage all of it in the admin panel.

## Two ports

| Container port | What | Who reaches it |
|---|---|---|
| `80` | Assistant side (the URLs below) | The internet, through your reverse proxy with HTTPS |
| `8080` | Admin panel | Only your local network. Never forward this port. |

The admin panel is a separate site with its own document root, so nothing of it can be reached through port 80.

## Admin panel (port 8080)

Sign in with `ADMIN_USER` (default `admin`) and `ADMIN_PASSWORD`. Without `ADMIN_PASSWORD` the admin panel stays disabled.

- **Backstory**: assistant name, role, tone, default language and a Markdown backstory.
- **Skills**: what the assistant can do and how, in Markdown; only enabled skills are sent.
- **Links**: dashboards, docs and tools you use often.
- **Public page**: the welcome page the assistant reads first, with a preview that shows it exactly as the assistant sees it before you save.
- **Memories**: browse, search, edit and delete what the assistant saved. Summaries are Markdown.
- **Skill usage**: every time the assistant opened a skill link, per skill (total, last 7 days, last used) and as a list of recent uses.
- **Security**: browsers seen in the last 30 days, a browser allowlist and a geo lock, each with an on/off switch, and the recently blocked requests (see below).
- **Settings**: the passphrase (with "Suggest one"), lockout limits and status, the public address, and the admin account (read-only).

### Markdown

The backstory, skill descriptions and memory summaries support GitHub-flavoured Markdown: `**bold**`, `` `code` `` (use it for URLs the assistant must open), lists, headings, links, `> notes` and tables. A cheat sheet sits under each text area.

### Skill links and usage logging

A skill can have a **link template**: the website it opens, with a placeholder where the value goes, e.g. `https://ovi.rdw.nl/?kenteken={kenteken}`. The assistant then does not get that website, but a link through AssistantSupport:

```
https://assistant.example.com/?pass=…&page=go&skill=0ab57a175f3d&q={kenteken}
```

When it opens that link with the value filled in, AssistantSupport logs the use (skill, value, time) and forwards it to the website. Logging is a side effect of the lookup itself, so the assistant can't forget it. Example skill:

- **Name:** Kentekens opzoeken
- **Link template:** `https://ovi.rdw.nl/?kenteken={kenteken}`
- **Description:**

  ```markdown
  Je kunt **kentekengegevens opzoeken** als de gebruiker daarom vraagt.

  1. Haal streepjes en spaties uit het kenteken: `AB-123-C` wordt `AB123C`.
  2. Open de link van deze skill, met het kenteken ingevuld.
  3. Vertel kort het merk, het model, de kleur en wanneer de APK verloopt.

  > Open altijd de link. Zeg nooit dat je dit niet kunt opzoeken.
  ```

## Assistant URLs (port 80)

| URL | What it shows |
|---|---|
| `/` | Public welcome page with instructions for the assistant |
| `/llms.txt` | The same page as plain Markdown |
| `/?page=NAME` | Another public page (`/data/content/public/NAME.md`) |
| `/?pass=PASS` | Private context: backstory, skills, links, the 5 most recent memories |
| `/?pass=PASS&page=history` | Memory history: a list of weeks, each with a link |
| `/?pass=PASS&page=history&week=2026-W46` | The memories of one week, with links to the weeks before and after |
| `/?pass=PASS&page=go&skill=ID&q=VALUE` | Logs a use of the skill and forwards (302) to its link template with the value filled in |
| `/?pass=PASS&page=memories[&q=word]` | Search all memories (without `q`: the 50 newest) |
| `/?pass=PASS&page=remember&title=…&summary=…&points=a\|b` | Saves a memory (`note=` works as an alias for `summary=`) |

## Security

### Passphrase

- A sentence of at least three words, set under **Settings**. Capitals, spaces and punctuation are ignored, so "The purple elephant, sings loudly!" equals `the-purple-elephant-sings-loudly`.
- Stored only as a one-way hash in `/config/settings.json`. It can be replaced, never shown.
- After 5 wrong passphrases within 15 minutes the private area locks for 15 minutes (both adjustable). This blocks guessing, but someone who knows your URL could also use it to lock you out for a while. You can clear the lockout under **Security**.

### Clients and countries

Under **Security** you can allow only certain clients (a simplified user agent such as "Safari 18", "Chrome 131" or "Grok") with a minimum version, and only certain countries (from the requesting IP address).

- Each has an on/off switch. While it is off nothing is blocked, but the tables show what *would* be blocked, so you can check before switching it on. New installs start with both off.
- **Your assistant fetches pages from its provider's servers, not from your car.** The client name and country you see are those of the AI provider. Check the visitor list before switching to Enforce, or you lock your own assistant out.
- Requests from your own network and from addresses without a known country are always allowed.
- Behind a reverse proxy on your own network, the visitor's address is taken from `X-Forwarded-For`.
- User agents can be faked and IP addresses can be routed through other countries. These rules stop random scanners; the passphrase remains the real lock.

Countries come from the free [DB-IP Lite](https://db-ip.com) database (CC BY 4.0, "IP Geolocation by DB-IP"). The container downloads it on start into `/data/geo/` and refreshes it every 30 days. Set `GEO_DOWNLOAD=false` to turn that off; country checks are then skipped.

### Other measures

- **The passphrase is part of the URL.** That is unavoidable with GET-only clients. This container leaves query strings out of its access log, but **your reverse proxy (Nginx Proxy Manager, SWAG, Traefik, Cloudflare, …) may still log full URLs**. Check that, or disable its access logs for this host.
- Use HTTPS, so the passphrase is encrypted in transit. Don't store anything here you would not want leaked (no passwords, no bank details).
- The admin panel uses a session login with CSRF tokens on every form. Keep its port on your own network.
- Pages are sent with `noindex`, `no-store`, `no-referrer` and `DENY` framing.

## Settings and environment variables

Everything you can change in the admin panel is stored in `/config/settings.json`. Only these are set on the container:

| Variable | Meaning |
|---|---|
| `ADMIN_PASSWORD` | **Required for the admin panel.** Mask it in Unraid. |
| `ADMIN_USER` | Admin user name. Default `admin`. |
| `PUID` / `PGID` | User/group that owns `/data` and `/config`. Default `99`/`100` (Unraid nobody:users). |
| `TZ` | Time zone for timestamps, e.g. `Europe/Amsterdam`. |
| `GEO_DOWNLOAD` | `false` disables the DB-IP download. Default `true`. |

## Files (container paths)

```
/config/settings.json         passphrase hash, public address, lockout, security rules
/data/context.json            backstory, skills and links
/data/memories/*.json         one file per memory
/data/content/public/*.md     public pages (index.md = welcome page)
/data/visitors.json           visitor overview for the Security page
/data/skill-usage.json        skill usage log (the newest 1000 uses)
/data/failures.json           wrong-passphrase counter (lockout)
/data/geo/                    DB-IP Lite country database
```

On first start the container copies a default welcome page and example context into `/data`. It never overwrites files that already exist, so after an update that changes the default welcome page, edit yours under **Public page** if you want the new text.

### How the welcome page is styled

It is plain Markdown; the stylesheet in `src/layout.php` gives it its look:

- A short line directly above the `# Title` becomes a small uppercase label.
- A `>` blockquote becomes a highlighted card with a lock icon.
- A numbered list (`1.`) becomes numbered steps; start each step with `### Step title`.
- Fenced code blocks become code boxes. Use them for URLs the assistant must open.
- `{{base}}` becomes the public address.

## Running it

### Image

CI publishes the image to GitHub Container Registry for `amd64` and `arm64`:

- `ghcr.io/rubenkremer/assistantsupport:latest`: the latest build of `main`
- `ghcr.io/rubenkremer/assistantsupport:1.2.3`: a release, when a git tag `v1.2.3` is pushed

Every push and pull request is linted, built and smoke-tested first (`.github/workflows/ci.yml`). Run the same smoke test locally with:

```sh
docker build -t assistant-support:ci . && sh docker/smoke-test.sh assistant-support:ci
```

### Docker Compose

```sh
cp .env.example .env      # then set ADMIN_PASSWORD in .env
docker compose pull && docker compose up -d     # or: docker compose up -d --build
```

Open the admin panel at http://localhost:8081, sign in, and set a passphrase under **Settings**. The assistant side is at http://localhost:8080.

### Unraid

Add a container with:

- Repository: `ghcr.io/rubenkremer/assistantsupport:latest`
- Icon URL: `https://raw.githubusercontent.com/RubenKremer/AssistantSupport/main/assets/brand/unraid-icon.png`
- WebUI: `http://[IP]:[PORT:8080]/` (opens the admin panel)
- Port: container `80` → host port of your choice (assistant side)
- Port: container `8080` → another host port (admin panel)
- Path: `/data` → `/mnt/user/appdata/assistant-support/data`
- Path: `/config` → `/mnt/user/appdata/assistant-support/config`
- Variables: `ADMIN_PASSWORD` (masked), `PUID=99`, `PGID=100`, `TZ=Europe/Amsterdam`

Put **only the assistant port** behind your reverse proxy with HTTPS.

## Project layout

```
public/index.php          assistant side (port 80)
admin/index.php           admin panel router (port 8080)
src/functions.php         shared bootstrap and helpers
src/storage.php           JSON files (atomic writes, locking)
src/settings.php          settings, passphrase, lockout
src/context.php           backstory, skills, links
src/memories.php          memories
src/security.php          client/country rules, geo lookup, visitor log
src/assistant.php         assistant-side pages
src/layout.php            assistant-side layout and stylesheet
src/admin/                admin pages, layout and stylesheet
src/cli/update-geo.php    DB-IP Lite downloader
public/fonts/             Inter and JetBrains Mono (SIL Open Font License)
public/favicon.*          favicons (also copied into the admin site)
assets/brand/             brand mark (icon.svg) and the 512 px Unraid app icon
src/countries.php         country names for the geo lock
defaults/                 first-run content for /data
docker/                   Apache config and entrypoint
```
