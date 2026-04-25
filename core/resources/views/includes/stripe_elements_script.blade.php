<!-- Stripe Elements JavaScript -->
<script src="https://js.stripe.com/v3/"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if Stripe modal exists
    const stripeModal = document.getElementById('stripe');
    if (!stripeModal) return;

    let stripe = null;
    let elements = null;
    let cardElement = null;
    let clientSecret = null;
    const appBasePath = window.location.pathname.startsWith('/core/') ? '/core' : '';
    const stripeCreateIntentUrl = `${window.location.origin}${appBasePath}/stripe/create-intent`;
    const stripeConfirmPaymentUrl = `${window.location.origin}${appBasePath}/stripe/confirm-payment`;
    const stripeReturnUrl = `${window.location.origin}${appBasePath}/checkout/success`;

    // Initialize Stripe when modal is shown
    stripeModal.addEventListener('shown.bs.modal', function() {
        if (stripe) return; // Already initialized

        // Get shipping and state IDs
        const shippingId = document.querySelector('.shipping_id_setup')?.value || '';
        const stateId = document.querySelector('.state_id_setup')?.value || '';

        // Update hidden fields in Stripe form
        document.getElementById('stripe_shipping_id').value = shippingId;
        document.getElementById('stripe_state_id').value = stateId;

        // Create Payment Intent
        fetch(stripeCreateIntentUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                shipping_id: shippingId,
                state_id: stateId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.status) {
                showError(data.message || 'Failed to initialize payment');
                return;
            }

            clientSecret = data.clientSecret;

            // Initialize Stripe
            stripe = Stripe(data.publishableKey);
            elements = stripe.elements({
                clientSecret: clientSecret,
                appearance: {
                    theme: 'stripe',
                    variables: {
                        colorPrimary: '{{ $setting->primary_color ?? "#0570de" }}',
                    }
                }
            });

            // Create and mount Card Element
            cardElement = elements.create('payment');
            cardElement.mount('#card-element');

            // Handle real-time validation errors
            cardElement.on('change', function(event) {
                const displayError = document.getElementById('card-errors');
                if (event.error) {
                    displayError.textContent = event.error.message;
                } else {
                    displayError.textContent = '';
                }
            });
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Failed to initialize payment. Please try again.');
        });
    });

    // Handle form submission
    const submitButton = document.getElementById('stripe-submit-button');
    const buttonText = document.getElementById('stripe-button-text');
    const spinner = document.getElementById('stripe-spinner');

    submitButton.addEventListener('click', async function(e) {
        e.preventDefault();
        if (typeof fbq !== 'undefined') {
            let rawPrice = $(".grand_total_set").text();
            let numericValue = parseFloat(rawPrice.replace(/[^0-9.-]+/g, '')) || 0;

            fbq('track', 'Purchase', {
                content_type: 'product',
                value: numericValue,
                currency: 'CAD' 
            });
        }
        if (!stripe || !clientSecret) {
            showError('Payment not initialized. Please close and reopen the payment form.');
            return;
        }

        // Disable button and show spinner
        submitButton.disabled = true;
        buttonText.classList.add('d-none');
        spinner.classList.remove('d-none');
        hideError();

        try {
            // Confirm payment
            const {error, paymentIntent} = await stripe.confirmPayment({
                elements,
                confirmParams: {
                    return_url: stripeReturnUrl,
                },
                redirect: 'if_required'
            });

            if (error) {
                // Payment failed
                showError(error.message);
                submitButton.disabled = false;
                buttonText.classList.remove('d-none');
                spinner.classList.add('d-none');
            } else if (paymentIntent && ['succeeded', 'processing', 'requires_capture'].includes(paymentIntent.status)) {
                // Payment succeeded or is processing - confirm with backend
                const response = await fetch(stripeConfirmPaymentUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        payment_intent_id: paymentIntent.id
                    })
                });

                const result = await response.json();

                if (result.status) {
                    // Success - redirect to success page
                    window.location.href = result.redirect;
                } else {
                    showError(result.message || 'Payment confirmation failed');
                    submitButton.disabled = false;
                    buttonText.classList.remove('d-none');
                    spinner.classList.add('d-none');
                }
            } else {
                // Unexpected state - show generic error
                showError('A processing error occurred. Please contact support if your card was charged.');
                submitButton.disabled = false;
                buttonText.classList.remove('d-none');
                spinner.classList.add('d-none');
            }
        } catch (err) {
            console.error('Payment error:', err);
            showError('An unexpected error occurred. Please try again.');
            submitButton.disabled = false;
            buttonText.classList.remove('d-none');
            spinner.classList.add('d-none');
        }
    });

    function showError(message) {
        const errorDiv = document.getElementById('stripe-payment-message');
        errorDiv.textContent = message;
        errorDiv.classList.remove('d-none');
    }

    function hideError() {
        const errorDiv = document.getElementById('stripe-payment-message');
        errorDiv.classList.add('d-none');
    }

    // Reset when modal is closed
    stripeModal.addEventListener('hidden.bs.modal', function() {
        if (cardElement) {
            cardElement.unmount();
        }
        stripe = null;
        elements = null;
        cardElement = null;
        clientSecret = null;
        hideError();

        // Reset button
        submitButton.disabled = false;
        buttonText.classList.remove('d-none');
        spinner.classList.add('d-none');
    });
});
</script>
