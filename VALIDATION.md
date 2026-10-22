# Preview validation

Release: v0.1.0-preview.1. Runtime version: 0.1.0. Public source preparation; release asset publication waits for final checks.

Install ZIP SHA256: `522a520cdf65248972cf6d1f6041f4deb223744124db5daa8612651b050ba379`.
Reviewed predecessor ZIP SHA256: `7fa5cc7face1cbd8d8b702c001016982f52190d2c38574bc5a459944f3370d7d`.
All predecessor runtime files are byte-identical. Only full GPL LICENSE and SOURCE.md were added. ZIP creation metadata is normalized across platforms. `release-manifest.json` records every packaged byte hash; `python tools/package.py` verifies them before packaging.

## Existing evidence reused for identical runtime

- WordPress Plugin Check 2.1, plugin_repo category: 0 errors; 34 warnings (18 direct database query, 16 uncached query) in bounded migration transaction/state handling. They are retained, not suppressed. Authoritative state is read uncached with prepared values and atomic writes.
- Packaged PHP syntax: PHP7.4.33 passed (90 files across both plugins).
- Exact-runtime WordPress7.1.3/PHP8.2/MariaDB coexistence and plugin bootstrap passed with separate bundled cores and no development autoloader.
- Rankavia migration engine: synthetic Yoast/Rank Math storage fixtures, signed preview, apply/rollback, stale/tamper/replay rejection, SQL failure, transaction locks and physical connection loss recovery passed. This is synthetic source-storage testing, not an actual source-plugin release matrix.
- Public-payload review: all predecessor110 files scanned across both plugins; no private/credential signatures, missing Composer classmap targets or unsafe archive paths found. Editable source closure and license text verified.

## Final publication checks

A fresh Plugin Check against the license/source-notice additions and a source build are in progress. See repository Actions for remote results; this document will be updated before release assets are published. CI checks exact manifest/package bytes and PHP7.4/8.3 syntax. Plugin Check uses the existing plugin_repo error gate; warnings remain visible, with no ignored warning codes.

## Open acceptance

Actual WordPress6.5/PHP7.4 runtime; multisite; theme/builder/commerce coexistence; native activation/upgrade/uninstall retention matrix; actual Yoast/Rank Math version matrix; directory metadata/screenshots and WordPress.org submission remain open. No claim of WordPress.org listing, stable production readiness, ranking gain, AI citation, active install count, paid launch or revenue is made. Cache/Heartbeat and unfinished import code are excluded from these packages.

No production migration, real customer transaction, paid activation, scanner/Hub deployment or marketplace submission is part of this source preview.
