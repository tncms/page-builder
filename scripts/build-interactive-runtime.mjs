// PB-XREG-3 — Interactive Runtime pipeline.
//
// The runtime is hand-authored, dependency-free ES module source (`resources/frontend/interactive-runtime.js`), so
// it needs no bundler transform. This step is its tracked build: a byte-faithful copy to the runtime path
// `public/vendor/page-builder/interactive/runtime.js` that InteractiveRuntime resolves through Core's published
// asset namespace (and that the PackageBuilder ships under the package `public/`). Two builds are deterministic.
import { mkdirSync, copyFileSync } from 'node:fs';
import { dirname } from 'node:path';
import { fileURLToPath, URL } from 'node:url';

const source = fileURLToPath(new URL('../resources/frontend/interactive-runtime.js', import.meta.url));
const destination = fileURLToPath(new URL('../../../public/vendor/page-builder/interactive/runtime.js', import.meta.url));

mkdirSync(dirname(destination), { recursive: true });
copyFileSync(source, destination);
console.log('[page-builder] interactive runtime -> public/vendor/page-builder/interactive/runtime.js');
