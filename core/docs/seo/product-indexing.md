# Product URL indexing repair

The public product route accepts `/product/{slug}?item_id={id}`. The controller uses `item_id` to select the requested item when multiple products have the same slug. The product sitemap and feed already include the ID, but the shared page canonical previously used `url()->current()`, which omitted the query. Many storefront cards also linked to the slug alone, selecting the first active product with that slug.

This branch uses one `ProductUrl::for($item)` URL for product cards and the rendered product canonical. The URL keeps `item_id`, so pages sharing a slug identify distinct products. Other page canonicals keep their existing behavior. Existing slug-only URLs remain readable; the first matching product will identify its own canonical URL in the page.

If a bookmarked or indexed URL has a valid active `item_id` but its slug has changed, the product route now returns a permanent redirect to the current ID-specific URL. Unknown or inactive IDs remain 404. Malformed IDs no longer silently resolve to a different product with the same slug. No redirect is attempted for an old slug that lacks a trustworthy item ID.

Validation: three focused tests and eight assertions pass for duplicate slugs, an old slug redirect, and invalid/inactive IDs using an isolated in-memory SQLite database. All 18 changed Blade templates compile and parse, PHP syntax checks pass, and `git diff --check` is clean. No database migration is needed. No production or Search Console changes were made.

Before release, test two real products with the same slug. Confirm each catalog card opens its intended product, each page has one matching canonical, and the product sitemap URL matches the canonical. Also change one test product slug and confirm its old `?item_id=` URL redirects to the new slug, while a nonexistent or inactive product remains 404. Then inspect sample Search Console URLs for duplicate, crawled-not-indexed, and 404 groups. This repair cannot establish how many exclusions it will resolve without those examples or a later crawl.

Hermes is optional for the investigation. It cannot force Google indexing. A staging clone can be useful for broader releases but is not required to develop and test this local repair. Do not submit staging pages for indexing.

## Local catalog audit

The local database has 2,925 active products, one active product without a slug, and zero duplicate active-slug groups. This does not establish the production catalog state; the Search Console screenshots concern the live site and show a much larger URL set. The missing local slug is excluded from the current product sitemap generator and should be corrected in catalog data before expecting that product to appear there.

## Public sitemap and headings

The client audit reported `Sitemap.xml not found`. A public HEAD request on 2026-09-19 confirmed that `https://99autoparts.ca/sitemap.xml` returned 404 while `/robots.txt` returned 200 and pointed to that missing sitemap. This branch adds a public `/sitemap.xml` index, a page sitemap, and numbered product sitemaps with at most 10,000 products each. They contain only active products with a slug, and product URLs match the page canonicals. The sitemap response is XML; no customer session or admin authentication is needed. No sitemap is yet live until this branch is merged and deployed.

The audit also reported 11,372 pages missing H1 headings. Read-only checks of the live catalog and one live product page both found no H1 in their server-rendered HTML. The product title is now an H1 with its existing visual class. The catalog has a visible H1 using its existing page-title text. These are confirmed examples, not proof that every reported page is fixed.

The isolated sitemap tests parse the XML, check active/canonical product URLs, and verify numbered sitemap entries. A read-only run against the local database produced valid XML with 2,924 product URLs in one 429,664-byte file. This does not establish the live catalog size or response time.

After deployment, verify HTTP 200 and XML validity for `/sitemap.xml`, `/sitemaps/pages.xml`, and each numbered product sitemap; compare a product `<loc>` with its canonical, and submit the sitemap index in Search Console. Monitor the index and errors after Google recrawls. The sitemap helps discovery; it does not guarantee indexing.
