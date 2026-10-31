// PB-PREVIEW-PARITY-R1 — committed static image pipeline.
//
// The Page Builder-owned starter/preview images are COMMITTED source under `public/img/**` and are referenced by
// `StarterAssets` as `/vendor/page-builder/img/…`. The PackageBuilder ships the package `public/` from the built
// `public/vendor/page-builder` directory only, so the images must be part of the build output. This step is their
// tracked build: a byte-faithful, sorted copy of `public/img/**` to `public/vendor/page-builder/img/**`. Stale files
// that no longer exist in source are removed so two builds are deterministic.
import { copyFileSync, mkdirSync, readdirSync, rmSync, statSync, existsSync } from 'node:fs';
import { dirname, join, relative } from 'node:path';
import { fileURLToPath, URL } from 'node:url';

const source = fileURLToPath(new URL('../public/img', import.meta.url));
const destination = fileURLToPath(new URL('../../../public/vendor/page-builder/img', import.meta.url));

function walk(dir) {
  const out = [];
  for (const entry of readdirSync(dir).sort()) {
    const abs = join(dir, entry);
    if (statSync(abs).isDirectory()) out.push(...walk(abs));
    else out.push(abs);
  }
  return out;
}

const files = walk(source).map((abs) => relative(source, abs));
if (existsSync(destination)) rmSync(destination, { recursive: true, force: true });
for (const rel of files) {
  const target = join(destination, rel);
  mkdirSync(dirname(target), { recursive: true });
  copyFileSync(join(source, rel), target);
}
console.log(`[page-builder] static images (${files.length}) -> public/vendor/page-builder/img`);
