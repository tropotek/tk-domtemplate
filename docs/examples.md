# Example site

The repository's `example/` folder is a small Bootstrap 5 site. It shows each feature on its own page, with
the PHP and HTML behind it at the bottom. The site's layout and navigation are also DomTemplate templates.

| Page | Shows |
| --- | --- |
| Vars | `setText()`, `setHtml()`, append/prepend, attributes, CSS classes, shared var names |
| Choices & repeats | table rows, per-row choices, empty states |
| Forms | `FormParser`: select options, multi-selects, refilling submitted values, validation errors |
| Head assets | meta tags, head CSS/JS, inline CSS/JS, sub-templates |

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
