/* #metapixelscript */
document.addEventListener('click', function (e) {
  var btn = e.target.closest('.add-to-cart, .add-to-wishlist');
  if (!btn) return;

  var isWishlist = btn.classList.contains('add-to-wishlist');
  var id = btn.getAttribute('data-id') || '';
  var name = btn.getAttribute('data-name') || '';
  var price = parseFloat(btn.getAttribute('data-price')) || 0;
  var currency = btn.getAttribute('data-currency') || 'CAD';

  console.log('CLICK DETECTED:', { id, name, price, currency, isWishlist });

  if (typeof fbq !== 'function') {
    console.error('❌ fbq NOT LOADED');
    return;
  }

  if (isWishlist) {
    console.log('🔥 Sending AddToWishlist');
    fbq('track', 'AddToWishlist', {
      content_type: 'product',
      content_ids: [String(id)],
      content_name: name,
      value: price,
      currency: currency
    });
  } else {
    console.log('🔥 Sending AddToCart');
    fbq('track', 'AddToCart', {
      content_type: 'product',
      content_ids: [String(id)],
      content_name: name,
      value: price,
      currency: currency,
      num_items: 1
    });
  }
});