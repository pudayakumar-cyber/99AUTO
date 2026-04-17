<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Fcategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FaqContentSeeder extends Seeder
{
    public function run(): void
    {
        $category = Fcategory::updateOrCreate(
            ['slug' => 'frequently-asked-questions'],
            [
                'name' => 'Frequently Asked Questions',
                'text' => "Welcome to our FAQ section! We've compiled answers to some of the most common questions our customers ask. If you can't find what you're looking for here, please don't hesitate to contact our customer service team.",
                'status' => 1,
                'meta_keywords' => 'faq, auto parts faq, shipping faq, returns faq, payment faq',
                'meta_descriptions' => 'Answers to common questions about ordering parts, shipping, returns, refunds, payments, and account support.',
            ]
        );

        $faqs = [
            [
                'section' => 'Finding and Ordering Parts',
                'title' => "How do I make sure the part I'm ordering will fit my vehicle?",
                'details' => "We understand how crucial it is to get the right part the first time. Our website features a comprehensive vehicle selector tool. Simply enter your vehicle's year, make, model, and trim level, and we'll show you only the parts that are guaranteed to fit. If you're ever unsure, our expert customer service team is just a call or click away to help you confirm compatibility.",
            ],
            [
                'section' => 'Finding and Ordering Parts',
                'title' => 'What kind of auto parts do you sell?',
                'details' => "We offer a vast selection of auto parts for nearly every vehicle make and model on the road in North America. Whether you're looking for OEM (Original Equipment Manufacturer) parts or high-quality aftermarket alternatives, we've got you covered. Our inventory includes everything from routine maintenance items like oil filters and brake pads to specialized performance parts and body components.",
            ],
            [
                'section' => 'Finding and Ordering Parts',
                'title' => 'Do you have a physical catalog I can browse?',
                'details' => "Given the immense and constantly updated inventory we carry, maintaining a physical catalog isn't feasible. All our available parts are listed and regularly updated in our online catalog, which you can easily search by part name, number, or even your vehicle's specifications.",
            ],
            [
                'section' => 'Finding and Ordering Parts',
                'title' => 'The product image looks slightly different from what I received. Is this normal?',
                'details' => 'Yes, it can be. Product images on our website are often generic or close-like representations. The actual part you receive might have minor variations in appearance, shape, or size, but rest assured, it will be the correct part for your vehicle as per your order specifications.',
            ],
            [
                'section' => 'Shipping and Delivery',
                'title' => 'Do you offer free shipping?',
                'details' => "We offer free standard shipping on most orders over \$100 within Canada. For orders under this amount, or for expedited shipping options, shipping costs will be calculated and displayed clearly in your cart before you complete your purchase. We believe in transparent pricing, so you'll always know the full cost upfront.",
            ],
            [
                'section' => 'Shipping and Delivery',
                'title' => 'What shipping carriers do you use?',
                'details' => 'We partner with trusted carriers such as Canada Post, Purolator, FedEx, and Loomis-Express to ensure your parts arrive safely and efficiently. The choice of carrier depends on your location and the size and weight of your order.',
            ],
            [
                'section' => 'Shipping and Delivery',
                'title' => 'How long will it take for my order to arrive?',
                'details' => "Estimated delivery times vary based on your shipping address and the shipping method selected. You'll see an estimated delivery timeframe during checkout. Once your order ships, we'll send you a tracking number so you can monitor its journey.",
            ],
            [
                'section' => 'Shipping and Delivery',
                'title' => "What if my order hasn't arrived within the estimated delivery time?",
                'details' => "If your order is delayed beyond the estimated delivery window, please contact our customer service team immediately with your order number. We'll investigate the issue with the shipping carrier and work to resolve it as quickly as possible.",
            ],
            [
                'section' => 'Shipping and Delivery',
                'title' => 'Do you ship internationally (outside of Canada)?',
                'details' => 'Currently, we only ship within Canada.',
            ],
            [
                'section' => 'Returns and Refunds',
                'title' => 'What is your return policy?',
                'details' => 'We want you to be completely satisfied with your purchase. You can return most new, unused, and uninstalled parts in their original packaging within 30 days of delivery for a refund or exchange. Please refer to our detailed Return Policy page for specific conditions and instructions.',
            ],
            [
                'section' => 'Returns and Refunds',
                'title' => 'What should I do if I receive a damaged or incorrect part?',
                'details' => 'In the rare event that you receive a damaged, defective, or incorrect part, please contact us within 7 days of delivery. We will arrange for a replacement or refund and guide you through the return process. We may ask for photos of the damaged item or the incorrect part to help us resolve the issue efficiently.',
            ],
            [
                'section' => 'Returns and Refunds',
                'title' => 'Who pays for return shipping?',
                'details' => 'If the return is due to our error, such as a damaged or incorrect part, we will cover the return shipping costs. For returns due to other reasons, such as ordering the wrong part or changing your mind, the customer is typically responsible for return shipping fees.',
            ],
            [
                'section' => 'Returns and Refunds',
                'title' => 'How long does it take to process a refund?',
                'details' => "Once we receive and inspect your returned item, refunds are typically processed within 3 to 5 business days. You'll receive a confirmation email once your refund has been issued. Please note that it may take additional time for the funds to appear in your bank or credit card statement, depending on your financial institution.",
            ],
            [
                'section' => 'Returns and Refunds',
                'title' => "Can I cancel an order after it's been placed?",
                'details' => 'We process orders very quickly to ensure fast delivery. Therefore, there is a very limited window to cancel an order before it ships. If you need to cancel, please contact us immediately. If the order has already shipped, you may need to initiate a return once you receive it.',
            ],
            [
                'section' => 'Payment and Account',
                'title' => 'What payment methods do you accept?',
                'details' => 'We accept all major credit cards, including Visa, MasterCard, American Express, and Discover. We also offer convenient payment options like PayPal and Sezzle for eligible purchases.',
            ],
            [
                'section' => 'Payment and Account',
                'title' => 'Is it safe to use my credit card on your website?',
                'details' => 'Absolutely. Your online security is our top priority. Our website uses industry-standard 256-bit SSL encryption to protect your personal and payment information. All transactions are processed through secure gateways, ensuring your data is safe.',
            ],
            [
                'section' => 'Payment and Account',
                'title' => "I'm having trouble with my credit card at checkout. What should I do?",
                'details' => 'If your credit card is being declined, please double-check that your billing address matches the address on file with your credit card company and that all card details, including number, expiry date, and CVV, are entered correctly. If the issue persists, please contact your bank or credit card provider, or try an alternative payment method.',
            ],
            [
                'section' => 'Payment and Account',
                'title' => 'Do I need to create an account to place an order?',
                'details' => 'No, you can check out as a guest without creating an account. However, creating an account offers several benefits, including faster checkout on future orders, the ability to track your order history, and access to exclusive promotions.',
            ],
            [
                'section' => 'Payment and Account',
                'title' => 'How can I track my order status?',
                'details' => "Once your order has shipped, you'll receive an email with a tracking number and a link to the carrier's website. You can use this to monitor the progress of your delivery. If you have an account, you can also log in and view your order status directly from your order history.",
            ],
        ];

        foreach ($faqs as $faqData) {
            Faq::updateOrCreate(
                [
                    'category_id' => $category->id,
                    'title' => $faqData['title'],
                ],
                [
                    'details' => $faqData['details'],
                ]
            );
        }
    }
}
