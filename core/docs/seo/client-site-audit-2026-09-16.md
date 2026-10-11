# Client audit triage — 2026-09-16 screenshots

SEMrush used a 20,000-page crawl limit on mobile with JavaScript rendering disabled. It reported site health 73%, AI search health 60%, 2,653 errors and 211,546 warnings. Issue counts are not counts of distinct pages and may include assets or repeat occurrences. The crawl stopped at its configured limit, so this is a sample of the site, not a complete inventory.

Confirmed locally or on the public site:

- `/sitemap.xml` returned 404 although public `/robots.txt` referred to it. This branch adds the endpoint and numbered product sitemaps; it is not yet deployed.
- A live product page and catalog page each had zero H1 tags in server-rendered HTML. This branch adds one meaningful H1 to each template.
- The sampled product's canonical omitted `item_id` although its URL included it. This branch aligns product links, canonicals and new sitemaps.

Still needs issue URL exports and targeted diagnosis:

- 848 duplicate-content pages, 848 duplicate-title issues and 954 duplicate descriptions: these may overlap, but the screenshots do not reveal which pages or whether Google selected a sensible canonical. Export the affected URL lists and compare pairs before rewriting titles or suppressing pages.
- 79,688 temporary-redirect URL warnings and 79,688 unminified JS/CSS warnings: get sample source and destination URLs and asset paths. The counts exceed the 20,000-page crawl cap because they are occurrences, not necessarily distinct pages. Do not replace redirects or change all assets from aggregate counts alone.
- 11,384 broken external-link issues and 30,533 links without anchor text: obtain samples and inspect the shared templates first. A few repeated links can generate many occurrences.
- 8,598 blocked pages: compare URLs with intentional `/admin/`, `/user/`, `/checkout/`, `/cart/` and `/compare/` rules in robots.txt. The blocked count is not automatically an indexing defect. The AI crawler warning requires the same URL-level review.
- 4,271 pages with only one internal incoming link: export the URLs; improve links to important products/categories, and avoid expanding links to duplicate or low-value URLs.
- 9,569 pages said to lack a doctype, 10 pages with multiple H1, one 4xx and one crawl failure: the sampled live catalog/product HTML had a doctype, so identify the exact affected response types. The one reported 4xx here is not the full Search Console 9,541 historic 404 set.
- Core Web Vitals 0%, site performance 87%, crawlability 61% and internal linking 71% are audit summaries. Review their underlying page URLs and field data before attributing root causes.

Search Console separately reported 72.4K excluded and 19.5K indexed, including 26,260 crawled-not-indexed and 9,541 404 URLs. Its property and time window differ from the SEMrush sample. A correct canonical alternate is normally excluded by design. Export representative URLs and use URL Inspection plus server responses to decide which exclusions need code or content changes.

Hermes is optional. It cannot force Google indexing. Staging can be used for testing, but should be private or otherwise kept out of search results.
