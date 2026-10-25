# Preview 3 validation — local release candidate

Release target: `v0.1.0-preview.3`. Plugin header: `0.1.0`. This document describes a private local candidate until the tag, asset and public CI are published and verified.

The change from published preview2 is confined to educational wording in both JSON catalogues, the TypeScript scorer messages and the PHP page-analyzer messages. IDs, predicates, statuses, weights and character bands are unchanged. `SOURCE.txt` now points to the intended preview3 source tag. All other runtime files remain byte-identical to published preview2. The local candidate package and release manifest enforce exact content and archive hashes; candidate ZIP SHA256: `2b80b75aa72368c7c6b3a2cf9461ac74ce365795f51ef900eb52ec5ee01e92ac`. The corresponding Plugin Check result is recorded in the candidate receipt.

The candidate was checked on local WordPress 6.5/PHP 7.4.33 with Plugin Check 2.1.0 `plugin_repo`: exit 0, 0 errors and 34 existing migration SQL warnings (18 direct queries and 16 uncached queries). Changed PHP syntax passed. R43 private-source typecheck and targeted scorer tests passed; unchanged runtime/floor evidence from preview2 is reused. No new customer install, real source-plugin migration matrix, multisite or broad theme/builder acceptance was performed.

## Preview 2 validation (published baseline)

Release tag: v0.1.0-preview.2. Plugin header: 0.1.0. Development preview, not WordPress.org listed or stable certification.

Normalized install ZIP SHA256: `39cb01f582757584853c4ae40cbb119487624eabd049dd45a12731aad5bc08d4`. tools/package.py reconstructs it with stored entries and fixed metadata, then enforces artifactSha256 and every runtime file hash.

Reviewed file-identical ZIP: `1bba211c0a7d827febe9fd787ed459e57b8ad4678d3346420795c3c383521959`. It used DEFLATE and different ZIP timestamps; that packaging metadata is normalized for cross-platform byte reproduction. The executable/content bytes are identical.

- Exact predecessor Plugin Check 2.1.0 plugin_repo: 0 errors, 34 warnings. Repository CI separately packages/checks the normalized artifact.
- WordPress6.5/PHP7.4.33 executable acceptance: both free plugins active, homepageHTTP200, no PHP errors; Rankavia analyzer23 and NBSP-only link warning passed.
- Multisite, broad themes/builders and real competitor-version matrices remain open.

## Prior preview evidence (historical)

# Preview validation

Release: v0.1.0-preview.1. Runtime version: 0.1.0. Public development preview; release asset is the manifest-verified install ZIP.

Install ZIP SHA256: `1d874530b15a6c3ac9ad14d9016c4dd66eee5f6fbd5357ca68a40919b7b5dd0b`.
Reviewed predecessor ZIP SHA256: `7fa5cc7face1cbd8d8b702c001016982f52190d2c38574bc5a459944f3370d7d`.
All predecessor runtime files are byte-identical. Only full GPL LICENSE and SOURCE.txt were added. ZIP metadata is normalized and entries are stored without compression for platform-independent byte reproduction. `release-manifest.json` records every packaged byte hash; `python tools/package.py` verifies them before packaging.

## Existing evidence reused for identical runtime

- WordPress Plugin Check 2.1, plugin_repo category: 0 errors; 34 warnings (18 direct database query, 16 uncached query) in bounded migration transaction/state handling. They are retained, not suppressed. Authoritative state is read uncached with prepared values and atomic writes.
- Packaged PHP syntax: PHP7.4.33 passed (90 files across both plugins).
- Exact-runtime WordPress7.1.3/PHP8.2/MariaDB coexistence and plugin bootstrap passed with separate bundled cores and no development autoloader.
- Rankavia migration engine: synthetic Yoast/Rank Math storage fixtures, signed preview, apply/rollback, stale/tamper/replay rejection, SQL failure, transaction locks and physical connection loss recovery passed. This is synthetic source-storage testing, not an actual source-plugin release matrix.
- Public-payload review: all predecessor110 files scanned across both plugins; no private/credential signatures, missing Composer classmap targets or unsafe archive paths found. Editable source closure and license text verified.

## Final publication checks

Final license/source-notice ZIP: Plugin Check2.1 plugin_repo on owned local WordPress7.1.3/PHP8.2 fixture exited0 with 0 errors and34 migration warnings. Every packaged file matched the extracted test payload; the original PHP/JS runtime was unchanged. The temporary unexpected-markdown warning was fixed by using SOURCE.txt.

[GitHub exact-preview checks](https://github.com/bixisoft/rankavia/actions/runs/39723952019) validate archive SHA256 reproduction, PHP7.4/8.3 syntax and Plugin Check with the plugin_repo error gate. Warnings remain visible, with no ignored warning codes. Rankavia also passed a fresh Node22 npm ci/build and byte comparison of every generated admin/editor asset against shipped build/. A local clean dependency install passed as additional source-install evidence.


## Open acceptance

Actual WordPress6.5/PHP7.4 runtime; multisite; theme/builder/commerce coexistence; native activation/upgrade/uninstall retention matrix; actual Yoast/Rank Math version matrix; directory metadata/screenshots and WordPress.org submission remain open. No claim of WordPress.org listing, stable production readiness, ranking gain, AI citation, active install count, paid launch or revenue is made. Cache/Heartbeat and unfinished import code are excluded from these packages.

No production migration, real customer transaction, paid activation, scanner/Hub deployment or marketplace submission is part of this source preview.
