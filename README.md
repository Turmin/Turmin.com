# turmin.com

Landing page for [turmin.com](https://turmin.com): a simple one-screen
business card. Plain HTML and CSS, no JavaScript, no build step.

## Structure

```
index.html          the page
css/style.css       styles (mobile first)
img/                logo, social preview image, touch icon
fonts/              self-hosted variable fonts (no Google Fonts requests)
favicon.svg / .ico  favicons
```

## Local preview

```sh
python3 -m http.server 8000
```

Then open http://localhost:8000.

## Deploy

Pushing to `main` deploys to production, pushing to `development` deploys to
the development environment, both via FTPS (`.github/workflows/deploy.yml`).
The workflow uses the `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD` and
`FTP_REMOTE_DIR` secrets of the matching GitHub environment.

## Licence

Code: MIT, see [LICENSE](LICENSE). The Turmin name and logo are excluded.
Fonts: SIL Open Font License 1.1.
