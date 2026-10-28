# Duplicate product SEO audit

Use the read-only audit to identify products that may be producing duplicate titles,
descriptions, or page content. The command does not update, merge, redirect, disable,
or delete any product.

```bash
cd /var/www/html/core
php artisan seo:audit-duplicate-products --limit=50
```

For a reviewable machine-readable export:

```bash
php artisan seo:audit-duplicate-products --limit=500 --json > duplicate-product-audit.json
```

Only active products are inspected by default. Add `--include-inactive` when historical
records are relevant.

## Confidence levels

- `high`: products under the same brand share at least one normalized SKU, product part
  number, or supplier product number.
- `review`: products under the same brand share a normalized title but not an identifier.

Every candidate still needs inventory review. Compare supplier, fitment, price, stock,
photos, category, and order history before deciding whether records are duplicates,
variants, or separate products. Canonicals and redirects should be chosen only after the
surviving product URL is approved.
