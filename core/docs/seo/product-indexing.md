# Product URL indexing repair

The public product route accepts `/product/{slug}?item_id={id}`. The controller uses `item_id` to select the requested item when multiple products have the same slug. The product sitemap and feed already include the ID, but the shared page canonical previously used `url()->current()`, which omitted the query. Many storefront cards also linked to the slug alone, selecting the first active product with that slug.

This branch uses one `ProductUrl::for($item)` URL for product cards and the rendered product canonical. The URL keeps `item_id`, so pages sharing a slug identify distinct products. Other page canonicals keep their existing behavior. Existing slug-only URLs remain readable; the first matching product will identify its own canonical URL in the page.

Validation: the URL helper test passes for two products with the same slug, all 18 changed Blade templates compile and parse, PHP syntax checks pass, and `git diff --check` is clean. No database migration is needed. No production or Search Console changes were made.

Before release, test two real products with the same slug. Confirm each catalog card opens its intended product, each page has one matching canonical, and the product sitemap URL matches the canonical. Then inspect sample Search Console URLs for duplicate, crawled-not-indexed, and 404 groups. This repair cannot establish how many exclusions it will resolve without those examples or a later crawl.

Hermes is optional for the investigation. It cannot force Google indexing. A staging clone can be useful for broader releases but is not required to develop and test this local repair. Do not submit staging pages for indexing.
