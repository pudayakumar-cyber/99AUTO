# Storefront asset build

The storefront previously served expanded source from several files, including
files named `.min.js` and `.min.css`. Keep editing the original sources in
`assets/front/`; the shared Blade layout loads the generated `.optimized.min`
files. Generated files are committed, so production needs no Node installation.

From this directory, with Node installed:

```sh
npm ci
npm run build
npm run check
```

The pinned Terser version formats JavaScript with compression and mangling
disabled; esbuild compacts CSS whitespace. There is no bundling or script
reordering. License comments are
preserved. Outputs remain beside their source files so CSS relative font and
image URLs keep resolving to the same locations.

Five existing CSS parse warnings are explicitly allowed and preserved, rather
than activating previously ignored styles as part of this change.

When changing generated assets after this release, bump `v=20260927` on their
references in `core/resources/views/master/front.blade.php` to invalidate browser
and CDN caches. Commit source changes and regenerated files together.

Before deployment, smoke-test catalogue filters, product variation selection,
add to cart, cart updates, checkout validation, navigation, and lazy images.
Reverting the layout references restores the original assets without requiring
a database change.
