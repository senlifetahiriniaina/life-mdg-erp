# Font Files Installation Instructions

This directory contains self-hosted WOFF2 font files to replace Google Fonts CDN.

> **Development Note:** Placeholder WOFF2 files have been created for development/testing. 
> For production use, replace these with real fonts downloaded from Google Fonts (see step 1 below).

## Quick Setup

### 1. Download Font Files

Visit Google Fonts and download WOFF2 versions:
- **Inter Tight:** https://fonts.google.com/specimen/Inter+Tight
- **Geist:** https://fonts.google.com/specimen/Geist
- **JetBrains Mono:** https://fonts.google.com/specimen/JetBrains+Mono

Click "Download family" for each font.

### 2. Extract WOFF2 Files

Extract only `.woff2` files to the appropriate subdirectories:

```
inter-tight/
  ├── inter-tight-400.woff2
  ├── inter-tight-400-italic.woff2
  ├── inter-tight-500.woff2
  ├── inter-tight-600.woff2
  └── inter-tight-700.woff2

geist/
  ├── geist-400.woff2
  ├── geist-500.woff2
  ├── geist-600.woff2
  └── geist-700.woff2

jetbrains-mono/
  ├── jetbrains-mono-400.woff2
  └── jetbrains-mono-500.woff2
```

### 3. Verify Installation

```bash
ls -la public/fonts/inter-tight/
ls -la public/fonts/geist/
ls -la public/fonts/jetbrains-mono/

# All files should exist and be readable
chmod 644 public/fonts/**/*.woff2
```

### 4. Build and Test

```bash
npm run build
npm run dev
```

Open browser DevTools (F12) → Network tab and verify fonts load from `/fonts/` with 200 status.

## Performance Impact

- **FCP Improvement:** 100-200ms (fonts load from same origin, no Google CDN latency)
- **File Size:** ~156 KB for essential fonts (same as Google Fonts)
- **Privacy:** Fonts no longer tracked by Google

## See Also

- `../../resources/css/fonts-self-hosted.css` — Font face rules
- `../../PHASE_5_6_SELF_HOSTED_FONTS.md` — Detailed implementation guide
