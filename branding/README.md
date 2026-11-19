# Turmin brand assets

Logos, profile pictures and banners for social media, chat apps and press.
Not part of the website: the deploy workflow skips this folder.

Every PNG has an SVG next to it. Text is converted to outlines, so the SVGs
look the same everywhere, even without the fonts installed.

## Which file for what

| Where | File | Size |
|---|---|---|
| X / Twitter profile picture | `avatar/turmin-avatar-400.png` | 400×400 |
| X / Twitter header | `banner/turmin-x-header-1500x500.png` | 1500×500 |
| Telegram profile / channel picture | `avatar/turmin-avatar-1024.png` | 1024×1024 |
| WhatsApp profile / channel picture | `avatar/turmin-avatar-1024.png` | 1024×1024 |
| Discord server icon, bot avatar | `avatar/turmin-avatar-512.png` | 512×512 |
| Discord server banner | `banner/turmin-discord-server-banner-960x540.png` | 960×540 |
| Discord profile banner | `banner/turmin-discord-profile-banner-680x240.png` | 680×240 |
| LinkedIn page banner | `banner/turmin-linkedin-banner-1584x396.png` | 1584×396 |
| Link preview (Open Graph), slides | `banner/turmin-social-preview-1200x630.png` | 1200×630 |
| Wallpaper, video, Discord invite background | `banner/turmin-wallpaper-1920x1080.png` | 1920×1080 |

The avatar fills 78% of the square, so it survives the circle crop that X,
Telegram, WhatsApp and Discord apply. The green dot, the mark's outermost
point, stays well inside the circle.

Headers and profile banners sit next to the avatar, which already shows the
logo, so they don't repeat it. The text is top-left, clear of the avatar in
the bottom-left corner, with a large crop of the ring on the right. The link
preview and the wallpaper are used on their own, so those carry the full logo.

## Logos

| File | Use on |
|---|---|
| `logo/turmin-logo-on-dark` | dark backgrounds (cream wordmark) |
| `logo/turmin-logo-on-light` | light backgrounds (navy wordmark) |
| `logo/turmin-mark` | the symbol alone, any background |
| `logo/turmin-wordmark-on-dark` / `-on-light` | the name alone |

Logo PNGs are transparent: 2400 px wide, the mark 1024×1024.
Keep some empty space around the logo, at least the height of the T in the mark.
Don't recolour, stretch or rotate it.

## Colours

| Name | Hex | Use |
|---|---|---|
| Navy | `#1E1A44` | background |
| Cream | `#FFF3DC` | wordmark and text on navy |
| Orange | `#FFBA4C` | the mark, highlights |
| Green | `#3DDC97` | the dot in the mark only |

## Fonts

- **Sora** Bold, uppercase, letter-spacing −2%: wordmark and headings
- **Instrument Sans**: body text, e.g. "EST. 2006 - The Netherlands"

Both are free (SIL Open Font License) and are also in `/fonts/` in this repository.

The Turmin name and logo are not covered by the MIT licence of this repository.
