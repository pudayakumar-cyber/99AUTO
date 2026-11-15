"""Source contract for the shared product Blade view after both handoff patches.

Run: python3 -m unittest core.tests.Support.test_product_seo_fitment_integration
"""
import pathlib
import re
import unittest


VIEW = pathlib.Path(__file__).resolve().parents[2] / "resources/views/front/catalog/product.blade.php"


class ProductSeoFitmentIntegrationTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.view = VIEW.read_text()

    def test_seo_schema_is_in_existing_meta_section_with_truthful_price(self):
        meta = self.view.split("@section('meta')", 1)[1].split("@endsection", 1)[0]
        self.assertIn("ProductStructuredData::supports($item)", meta)
        self.assertIn("PriceHelper::grandCurrencyPriceValue($item)", meta)
        self.assertIn("PriceHelper::setCurrencyName()", meta)
        self.assertIn('type="application/ld+json"', meta)
        self.assertIn("ProductMetaDescription::for($item)", self.view)
        self.assertIn("ProductMetaTitle::for($item)", self.view)
        self.assertIn("$productShareUrl", meta)

    def test_application_check_is_visible_in_both_status_locations(self):
        self.assertIn("$requiresApplicationCheck = $item->requiresApplicationCheck();", self.view)
        for status_id in ("product_fitment_status_top", "product_fitment_status"):
            line = next(line for line in self.view.splitlines() if f'id="{status_id}"' in line)
            self.assertIn("$isUniversalFit || $requiresApplicationCheck", line)
        self.assertEqual(self.view.count("@elseif($requiresApplicationCheck)"), 2)
        self.assertIn("No verified year/make/model fitment table is available.", self.view)

    def test_application_check_blocks_client_side_vehicle_match_and_preserves_scripts(self):
        self.assertEqual(len(re.findall(r"@section\('script'\)", self.view)), 1)
        self.assertEqual(len(re.findall(r"@section\('styleplugins'\)", self.view)), 1)
        content = self.view.split("@section('content')", 1)[1].split("@section('script')", 1)[0]
        script = self.view.split("@section('script')", 1)[1]
        self.assertIn("var requiresApplicationCheck = @json($requiresApplicationCheck);", content)
        self.assertLess(content.index("if (requiresApplicationCheck) {"), content.index("if (!hasSelection) {"))
        self.assertIn("currentFitMatched = null;", content)
        self.assertIn("function evaluateFitment()", content)
        self.assertIn("product_ymm_shop_link", content)
        self.assertIn("product_compare", content)
        self.assertIn("window.paTrack('ViewContent'", script)
        self.assertIn("window.paKlaviyoTrack('ViewContent'", script)

    def test_existing_stock_and_currency_display_survive(self):
        self.assertIn("PriceHelper::grandCurrencyPrice($item)", self.view)
        self.assertIn("__('Out of stock')", self.view)
        self.assertIn("$item->stock", self.view)


if __name__ == "__main__":
    unittest.main()
