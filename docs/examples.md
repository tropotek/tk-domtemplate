# Example site

The repository's `example/` folder is a small Bootstrap 5 site. It shows each feature on its own page, with
the PHP and HTML behind it at the bottom. The site's layout and navigation are also DomTemplate templates.

| Page | Shows |
| --- | --- |
| Vars | `text()` vs `html()`, append/prepend, attributes, classes, shared names, `hide()` and `?` nodes |
| Repeats | a table built with `each()`, per-row optional badges, nested repeats, empty states |
| Assets | inline, head and end scripts, URLs, a keyed script added by every row, a standalone fragment |
| Builder | components from a library file, dotted fallback, `data-opt-*` settings |
| HTMX + Alpine | an Alpine toggle, and a fragment loaded with `hx-get` that brings its own script |

## Run it

It runs in a FrankenPHP container, so you only need Docker:

```bash
git clone https://github.com/tropotek/tk-domtemplate.git
cd tk-domtemplate
cp .env.example .env
docker compose up -d --build
```

Open <http://127.0.0.1:8080>. On first start the container runs `composer install` to create the autoloader.

## Settings

Edit `.env` to change them:

| Variable | Default | Purpose |
| --- | --- | --- |
| `APP_HOST` | `127.0.0.1` | Address the site is published on. Use your LAN IP to reach it from other devices, or `0.0.0.0` for every interface. |
| `HTTP_APP_PORT` | `8080` | Port on the host. |
| `APP_UID` / `APP_GID` | `1000` | Container user. Match your host user (`id -u`, `id -g`) so files the container writes stay editable. |

Changes to `example/` and `src/` show up on the next page load. After changing `.env`, run
`docker compose up -d` again.
