<?php

namespace App\Helpers;

use App\Models\Item;
use App\Models\Setting;
use App\Models\ShippingService;
use App\Services\EShipperService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use RuntimeException;

class CheckoutShippingHelper
{
    const SESSION_KEY = 'checkout_shipping_options';

    public static function loadCheckoutOptions($shippingAddress = null, $cart = null)
    {
        $cart = $cart ?: Session::get('cart', []);
        $shippingAddress = $shippingAddress ?: Session::get('shipping_address', []);

        if (!PriceHelper::CheckDigital()) {
            Session::forget(self::SESSION_KEY);

            return [
                'options' => [],
                'message' => null,
                'error' => null,
            ];
        }

        $summary = self::cartSummary($cart);
        $service = app(EShipperService::class);

        if ($summary['subtotal'] >= (float) config('services.eshipper.free_shipping_threshold', 150)) {
            $options = [
                self::makeOption('free_shipping', 'Free Shipping', 0, [
                    'source' => 'free_shipping',
                    'provider' => 'internal',
                    'remarks' => 'Free shipping threshold applied.',
                ]),
            ];

            self::storeOptions($options);

            return [
                'options' => $options,
                'message' => null,
                'error' => null,
            ];
        }

        if ($summary['has_missing_package_data']) {
            $amount = (float) config('services.eshipper.fallback_flat_rate', 25);
            $options = [
                self::makeOption('flat_rate_standard', 'Standard Shipping', $amount, [
                    'source' => 'flat_rate',
                    'provider' => 'internal',
                    'remarks' => 'Flat shipping applied because one or more product categories are missing package dimensions.',
                ]),
            ];

            self::storeOptions($options);

            return [
                'options' => $options,
                'message' => __('Standard shipping is being used because one or more item categories are missing package dimensions.'),
                'error' => null,
            ];
        }

        if (!self::hasQuotableAddress($shippingAddress)) {
            Session::forget(self::SESSION_KEY);

            return [
                'options' => [],
                'message' => __('Enter a complete Canadian shipping address to view shipping options.'),
                'error' => null,
            ];
        }

        if (!$service->isEnabled()) {
            $options = self::legacyShippingOptions($summary['subtotal']);
            self::storeOptions($options);

            return [
                'options' => $options,
                'message' => __('Standard store shipping rates are being used because live carrier rates are not enabled.'),
                'error' => empty($options) ? __('No shipping services are currently available.') : null,
            ];
        }

        try {
            $payload = self::buildQuotePayload($shippingAddress, $summary['packages']);
            $response = $service->getQuotes($payload);
            $quotes = $response['quotes'] ?? [];
            $options = [];

            foreach (array_values($quotes) as $index => $quote) {
                $serviceName = trim((string) ($quote['serviceName'] ?? 'Standard Shipping'));
                $carrierName = trim((string) ($quote['carrierName'] ?? ''));
                $title = $carrierName !== '' ? $serviceName . ' - ' . $carrierName : $serviceName;

                $options[] = self::makeOption(
                    'eshipper_' . $index,
                    $title,
                    (float) ($quote['totalCharge'] ?? 0),
                    [
                        'source' => 'eshipper',
                        'provider' => 'carrier',
                        'quote_uuid' => $response['uuid'] ?? null,
                        'service_id' => $quote['serviceId'] ?? null,
                        'rate_id' => $quote['id'] ?? null,
                        'service_name' => $quote['serviceName'] ?? null,
                        'carrier_name' => $quote['carrierName'] ?? null,
                        'transit_days' => $quote['transitDays'] ?? null,
                        'currency' => $quote['currency'] ?? 'CAD',
                        'remarks' => $quote['remarks'] ?? null,
                        'raw' => $quote,
                    ]
                );
            }

            $options = array_slice($options, 0, (int) config('services.eshipper.top_rates_limit', 4));

            if (empty($options)) {
                Session::forget(self::SESSION_KEY);

                return [
                    'options' => [],
                    'message' => null,
                    'error' => __('No shipping services are currently available for this address.'),
                ];
            }

            self::storeOptions($options);

            return [
                'options' => $options,
                'message' => null,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Unable to load checkout shipping options.', [
                'message' => $e->getMessage(),
            ]);

            $options = self::legacyShippingOptions($summary['subtotal']);
            self::storeOptions($options);

            return [
                'options' => $options,
                'message' => empty($options) ? null : __('Standard store shipping rates are being used because carrier rates are temporarily unavailable.'),
                'error' => empty($options) ? __('Shipping rates are temporarily unavailable. Please try again in a moment.') : null,
            ];
        }
    }

    public static function getStoredOptions()
    {
        return array_values(Session::get(self::SESSION_KEY, []));
    }

    public static function resolveSelectedOption($shippingId)
    {
        if (!$shippingId) {
            return null;
        }

        if (is_numeric($shippingId) && ShippingService::where('id', $shippingId)->exists()) {
            $shipping = ShippingService::findOrFail($shippingId);

            return [
                'id' => (string) $shipping->id,
                'title' => $shipping->title,
                'price' => (float) $shipping->price,
                'source' => 'legacy',
                'provider' => 'internal',
            ];
        }

        $options = Session::get(self::SESSION_KEY, []);

        return $options[$shippingId] ?? null;
    }

    public static function selectedPrice($shippingId)
    {
        $option = self::resolveSelectedOption($shippingId);

        return $option ? (float) ($option['price'] ?? 0) : 0.0;
    }

    public static function orderShippingPayload($shippingId)
    {
        $option = self::resolveSelectedOption($shippingId);

        if (!$option) {
            return null;
        }

        return [
            'id' => $option['id'],
            'title' => $option['title'],
            'price' => (float) ($option['price'] ?? 0),
            'source' => $option['source'] ?? 'internal',
            'provider' => $option['provider'] ?? 'internal',
            'service_id' => $option['service_id'] ?? null,
            'rate_id' => $option['rate_id'] ?? null,
            'service_name' => $option['service_name'] ?? null,
            'carrier_name' => $option['carrier_name'] ?? null,
            'transit_days' => $option['transit_days'] ?? null,
            'quote_uuid' => $option['quote_uuid'] ?? null,
            'currency' => $option['currency'] ?? 'CAD',
            'remarks' => $option['remarks'] ?? null,
        ];
    }

    public static function customerProvince($value)
    {
        return self::normalizeProvince($value);
    }

    public static function mapBillingToShipping($request)
    {
        return [
            'ship_first_name' => $request->bill_first_name,
            'ship_last_name' => $request->bill_last_name,
            'ship_email' => $request->bill_email,
            'ship_phone' => $request->bill_phone,
            'ship_company' => $request->bill_company,
            'ship_address1' => $request->bill_address1,
            'ship_address2' => $request->bill_address2,
            'ship_zip' => $request->bill_zip,
            'ship_city' => $request->bill_city,
            'ship_province' => $request->bill_province,
            'ship_country' => $request->bill_country,
        ];
    }

    protected static function cartSummary($cart)
    {
        $subtotal = 0;
        $packages = [];
        $hasMissingPackageData = false;

        foreach ($cart as $itemId => $line) {
            $subtotal += (($line['main_price'] ?? 0) + ($line['attribute_price'] ?? 0)) * ($line['qty'] ?? 0);

            $item = Item::find($itemId);
            if (!$item || ($line['item_type'] ?? null) !== 'normal') {
                continue;
            }

            $packageData = self::packageDataForItem($item);

            if (!$packageData) {
                $hasMissingPackageData = true;
                continue;
            }

            for ($i = 0; $i < (int) ($line['qty'] ?? 0); $i++) {
                $packages[] = [
                    'length' => $packageData['length'],
                    'width' => $packageData['width'],
                    'height' => $packageData['height'],
                    'weight' => $packageData['weight'],
                    'description' => $item->name . ' (' . ($item->category->name ?: 'Category') . ')',
                ];
            }
        }

        return [
            'subtotal' => $subtotal,
            'packages' => $packages,
            'has_missing_package_data' => $hasMissingPackageData,
        ];
    }

    protected static function hasQuotableAddress($shippingAddress)
    {
        if (empty($shippingAddress)) {
            return false;
        }

        if (!self::isCanada($shippingAddress['ship_country'] ?? null)) {
            return false;
        }

        return
            trim((string) ($shippingAddress['ship_first_name'] ?? '')) !== '' &&
            trim((string) ($shippingAddress['ship_last_name'] ?? '')) !== '' &&
            trim((string) ($shippingAddress['ship_email'] ?? '')) !== '' &&
            trim((string) ($shippingAddress['ship_phone'] ?? '')) !== '' &&
            trim((string) ($shippingAddress['ship_address1'] ?? '')) !== '' &&
            trim((string) ($shippingAddress['ship_city'] ?? '')) !== '' &&
            trim((string) ($shippingAddress['ship_zip'] ?? '')) !== '' &&
            trim((string) ($shippingAddress['ship_province'] ?? '')) !== '';
    }

    protected static function buildQuotePayload($shippingAddress, $packages)
    {
        if (empty($packages)) {
            throw new RuntimeException('No shippable packages found for quote request.');
        }

        $setting = Setting::first();
        $originCompany = config('services.eshipper.origin_company') ?: ($setting->title ?? 'Store');
        $originEmail = config('services.eshipper.origin_email') ?: ($setting->email_from ?: $setting->contact_email);
        $originPhone = config('services.eshipper.origin_phone') ?: $setting->footer_phone;
        $originAttention = config('services.eshipper.origin_contact') ?: $originCompany;

        return [
            'from' => [
                'attention' => $originAttention,
                'company' => $originCompany,
                'address1' => config('services.eshipper.origin_address1'),
                'address2' => config('services.eshipper.origin_address2'),
                'city' => config('services.eshipper.origin_city'),
                'province' => self::normalizeProvince(config('services.eshipper.origin_province')),
                'country' => config('services.eshipper.origin_country', 'CA'),
                'zip' => self::normalizePostalCode(config('services.eshipper.origin_postal')),
                'email' => $originEmail,
                'phone' => $originPhone,
            ],
            'to' => [
                'attention' => trim(($shippingAddress['ship_first_name'] ?? '') . ' ' . ($shippingAddress['ship_last_name'] ?? '')),
                'company' => $shippingAddress['ship_company'] ?? trim(($shippingAddress['ship_first_name'] ?? '') . ' ' . ($shippingAddress['ship_last_name'] ?? '')),
                'address1' => $shippingAddress['ship_address1'],
                'address2' => $shippingAddress['ship_address2'] ?? null,
                'city' => $shippingAddress['ship_city'],
                'province' => self::normalizeProvince($shippingAddress['ship_province']),
                'country' => 'CA',
                'zip' => self::normalizePostalCode($shippingAddress['ship_zip']),
                'email' => $shippingAddress['ship_email'],
                'phone' => $shippingAddress['ship_phone'],
            ],
            'scheduledShipDate' => Carbon::now()->format('Y-m-d H:i'),
            'packagingUnit' => config('services.eshipper.packaging_unit', 'Imperial'),
            'packages' => [
                'type' => 'Package',
                'packages' => array_map(function ($package) {
                    return [
                        'length' => $package['length'],
                        'width' => $package['width'],
                        'height' => $package['height'],
                        'weight' => $package['weight'],
                        'description' => $package['description'],
                    ];
                }, $packages),
            ],
            'currency' => config('services.eshipper.currency', 'CAD'),
            'insidePickup' => false,
            'insuranceType' => 'NONE',
            'saturdayPickupRequired' => false,
        ];
    }

    protected static function storeOptions($options)
    {
        $indexed = [];

        foreach ($options as $option) {
            $indexed[$option['id']] = $option;
        }

        Session::put(self::SESSION_KEY, $indexed);
    }

    protected static function legacyShippingOptions($subtotal)
    {
        return ShippingService::whereStatus(1)
            ->orderBy('id')
            ->get()
            ->filter(function ($shipping) use ($subtotal) {
                if ((int) $shipping->id !== 1) {
                    return true;
                }

                if (!$shipping->is_condition) {
                    return true;
                }

                return (float) $shipping->minimum_price <= (float) $subtotal;
            })
            ->map(function ($shipping) {
                return self::makeOption((string) $shipping->id, $shipping->title, (float) $shipping->price, [
                    'source' => 'legacy',
                    'provider' => 'internal',
                ]);
            })
            ->values()
            ->all();
    }

    protected static function packageDataForItem($item)
    {
        $source = $item;

        if (
            !$source->package_length ||
            !$source->package_width ||
            !$source->package_height ||
            !$source->package_weight
        ) {
            $source = $item->category;
        }

        if (
            !$source ||
            !$source->package_length ||
            !$source->package_width ||
            !$source->package_height ||
            !$source->package_weight
        ) {
            return null;
        }

        return [
            'length' => (float) $source->package_length,
            'width' => (float) $source->package_width,
            'height' => (float) $source->package_height,
            'weight' => (float) $source->package_weight,
        ];
    }

    protected static function makeOption($id, $title, $price, $meta = [])
    {
        return array_merge([
            'id' => $id,
            'title' => $title,
            'price' => round((float) $price, 2),
        ], $meta);
    }

    protected static function isCanada($country)
    {
        $country = strtoupper(trim((string) $country));

        return in_array($country, ['CA', 'CANADA'], true);
    }

    protected static function normalizePostalCode($postalCode)
    {
        return strtoupper(str_replace(' ', '', trim((string) $postalCode)));
    }

    protected static function normalizeProvince($province)
    {
        $province = strtoupper(trim((string) $province));
        $province = str_replace(['.', '_'], '', $province);

        $map = [
            'AB' => 'CA-AB',
            'ALBERTA' => 'CA-AB',
            'BC' => 'CA-BC',
            'BRITISH COLUMBIA' => 'CA-BC',
            'MB' => 'CA-MB',
            'MANITOBA' => 'CA-MB',
            'NB' => 'CA-NB',
            'NEW BRUNSWICK' => 'CA-NB',
            'NL' => 'CA-NL',
            'NEWFOUNDLAND AND LABRADOR' => 'CA-NL',
            'NS' => 'CA-NS',
            'NOVA SCOTIA' => 'CA-NS',
            'NT' => 'CA-NT',
            'NORTHWEST TERRITORIES' => 'CA-NT',
            'NU' => 'CA-NU',
            'NUNAVUT' => 'CA-NU',
            'ON' => 'CA-ON',
            'ONTARIO' => 'CA-ON',
            'PE' => 'CA-PE',
            'PRINCE EDWARD ISLAND' => 'CA-PE',
            'QC' => 'CA-QC',
            'QUEBEC' => 'CA-QC',
            'SK' => 'CA-SK',
            'SASKATCHEWAN' => 'CA-SK',
            'YT' => 'CA-YT',
            'YUKON' => 'CA-YT',
            'CA-AB' => 'CA-AB',
            'CA-BC' => 'CA-BC',
            'CA-MB' => 'CA-MB',
            'CA-NB' => 'CA-NB',
            'CA-NL' => 'CA-NL',
            'CA-NS' => 'CA-NS',
            'CA-NT' => 'CA-NT',
            'CA-NU' => 'CA-NU',
            'CA-ON' => 'CA-ON',
            'CA-PE' => 'CA-PE',
            'CA-QC' => 'CA-QC',
            'CA-SK' => 'CA-SK',
            'CA-YT' => 'CA-YT',
        ];

        return $map[$province] ?? $province;
    }
}
