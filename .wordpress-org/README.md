# Assets for the WordPress.org plugin listing

This folder is **not part of the plugin** (`bin/build-zip.sh` excludes it from the ZIP): it's just where the files that need to be uploaded to the `/assets` folder of WordPress.org's **SVN** repo (separate from `/trunk`, where the plugin code lives) are kept in this Git repository.

## Screenshots

Ready — match, in order, the entries listed under `== Screenshots ==` in `readme.txt`. If you change the order or number of screenshots, update that section too.

- `screenshot-1.jpg` — the floating toggle button on the frontend, with the quick-access menu open.
- `screenshot-2.jpg` — the Settings → NanoBar page, with the live position/size/color preview.
- `screenshot-3.jpg` — the automatic bottom tab bar on narrow (phone-width) screens.

## Icon

Ready — generated to match NanoBar's own toggle icon (a gear, echoing the `dashicons-admin-generic` icon used throughout the plugin's UI) on the indigo-to-violet gradient used in the settings page.

- `icon-128x128.png`
- `icon-256x256.png` (retina)

## Banner

Ready — same gear/gradient style as the icon, with the "NanoBar" wordmark and a short tagline.

- `banner-772x250.png`
- `banner-1544x500.png` (retina)

## How to publish them (after the plugin is approved)

```bash
svn co https://plugins.svn.wordpress.org/nanobar
cp .wordpress-org/*.png .wordpress-org/*.jpg nanobar/assets/
cd nanobar
svn add assets/*
svn commit -m "Add plugin assets: screenshots, icon, banner"
```
