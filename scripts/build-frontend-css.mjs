// PB-DIST-4B — frontend stylesheet pipeline.
//
// The public frontend component stylesheet (`.pb-*`) is hand-authored, self-contained CSS (no @import,
// no Vue/Tailwind), so it needs no bundler transform. This step is its tracked build: it emits the
// COMMITTED source `resources/frontend/page-builder.css` to the runtime path
// `public/vendor/page-builder/frontend/page-builder.css` that `PageBuilderFrontendAssets` links on every
// public page (and that the PB-DIST-4B PackageBuilder ships under the package `public/`). It is a
// byte-faithful copy so two builds are deterministic and the served asset never drifts from source.
import { mkdirSync, copyFileSync } from 'node:fs';
import { dirname } from 'node:path';
import { fileURLToPath, URL } from 'node:url';

const source = fileURLToPath(new URL('../resources/frontend/page-builder.css', import.meta.url));
const destination = fileURLToPath(new URL('../../../public/vendor/page-builder/frontend/page-builder.css', import.meta.url));

mkdirSync(dirname(destination), { recursive: true });
copyFileSync(source, destination);
console.log('[page-builder] frontend css -> public/vendor/page-builder/frontend/page-builder.css');
