# Product URL indexing repair

The public product route accepts `/product/{slug}?item_id={id}`. The controller uses `item_id` to select the requested item when multiple products have the same slug. The product sitemap and feed already include the ID, but the shared page canonical previously used `url()->current()`, which omitted the query. Many storefront cards also linked to the slug alone, selecting the first active product with that slug.

This branch uses one `ProductUrl::for($item)` URL for product cards and the rendered product canonical. The URL keeps `item_id`, so pages sharing a slug identify distinct products. Other page canonicals keep their existing behavior. Existing slug-only URLs remain readable; the first matching product will identify its own canonical URL in the page.

If a bookmarked or indexed URL has a valid active `item_id` but its slug has changed, the product route now returns a permanent redirect to the current ID-specific URL. Unknown or inactive IDs remain 404. Malformed IDs no longer silently resolve to a different product with the same slug. No redirect is attempted for an old slug that lacks a trustworthy item ID.

Validation: three focused tests and eight assertions pass for duplicate slugs, an old slug redirect, and invalid/inactive IDs using an isolated in-memory SQLite database. All 18 changed Blade templates compile and parse, PHP syntax checks pass, and `git diff --check` is clean. No database migration is needed. No production or Search Console changes were made.

Before release, test two real products with the same slug. Confirm each catalog card opens its intended product, each page has one matching canonical, and the product sitemap URL matches the canonical. Also change one test product slug and confirm its old `?item_id=` URL redirects to the new slug, while a nonexistent or inactive product remains 404. Then inspect sample Search Console URLs for duplicate, crawled-not-indexed, and 404 groups. This repair cannot establish how many exclusions it will resolve without those examples or a later crawl.

Hermes is optional for the investigation. It cannot force Google indexing. A staging clone can be useful for broader releases but is not required to develop and test this local repair. Do not submit staging pages for indexing.

## Local catalog audit

The local database has 2,925 active products, one active product without a slug, and zero duplicate active-slug groups. This does not establish the production catalog state; the Search Console screenshots concern the live site and show a much larger URL set. The missing local slug is excluded from the current product sitemap generator and should be corrected in catalog data before expecting that product to appear there.
