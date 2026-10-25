# Rankavia

Free, GPL-2.0-or-later WordPress plugin by Bixisoft.

**Development preview — plugin version 0.1.0; release v0.1.0-preview.2.** Test on a disposable copy before using it on a live site. This repository is public source distribution, not a WordPress.org listing or a stable compatibility certification.

Metadata and social tags, dashboard visibility guidance, sitemap/robots/crawler controls, optional llms.txt, basic JSON-LD, breadcrumbs, HTML sitemap, FAQ blocks and exact-path redirects. Yoast/Rank Math migration supports selected post/page fields in signed batches of 20, with preservation and conflict-aware undo; it is not whole-site migration.

## Download and install

Download **rankavia.zip** from [the preview release](https://github.com/bixisoft/rankavia/releases/tag/v0.1.0-preview.2). Upload that asset through WordPress Plugins → Add New → Upload Plugin, then activate. The GitHub-generated source archives contain development material and are not the install ZIP. Keep a database and file backup, especially before migration or changing asset loading.

Requires WordPress 6.5+ and PHP 7.4+. The exact runtime passed activation/coexistence and homepage checks on WordPress 6.5/PHP 7.4.33. Plugin Check 2.1 plugin_repo reported 0 errors and 34 warnings. Multisite, broad theme/builder acceptance and real source-plugin migration matrices remain incomplete. Read [VALIDATION.md](VALIDATION.md) for precise limits and hashes.

## Source and building

Editable admin/editor JavaScript and CSS, shared UI/scorer sources, package manifests and lockfile are in `_development/`. With Node 22 and npm, run:

```sh
cd _development
npm ci --ignore-scripts
npm run build
```

The build writes `_development/plugins/rankavia/build/`. Review generated changes before replacing runtime `build/`; update release hashes only after validation. PHP, the bundled core and FAQ editor asset are editable directly in this repository.

All production PHP dependencies are included in `vendor/`, with a plugin-specific core namespace so both free plugins can coexist. Do **not** run `composer install` in this extracted distribution: the historical composer.json describes a monorepo path dependency, not a standalone dependency setup.

Build the install ZIP from the verified runtime manifest with Python 3:

```sh
python tools/package.py
```

This checks every packaged file and creates `artifacts/rankavia.zip`. It excludes repository docs, CI and development dependencies. Runtime changes require refreshed validation and a new manifest/release; this script intentionally rejects unreviewed changes.

## Privacy and support

See [readme.txt](readme.txt) for external service disclosures and current limitations. No paid activation, telemetry, external publishing, auto updates or billing is enabled by this preview. Report reproducible issues in this repository; never include credentials or private customer data.

## License

Rankavia code is GPL-2.0-or-later; full GPL v2 text is in [LICENSE](LICENSE). Composer's bundled loader retains its MIT license in `vendor/composer/LICENSE`. Build dependencies retain their own package licenses, recorded in the editable-source dependency manifests/lockfile where applicable.
