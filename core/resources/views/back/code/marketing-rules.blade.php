<div class="form-group">
    <label for="expires_at">{{ __('Offer expires at (optional)') }}</label>
    <input type="datetime-local" name="expires_at" id="expires_at" class="form-control"
        value="{{ old('expires_at', isset($code) ? $code->expires_at?->format('Y-m-d\TH:i') : '') }}">
    <small>{{ __('Store timezone:') }} {{ config('app.timezone') }}</small>
</div>
<div class="form-group">
    <label for="recipient_email">{{ __('Restrict to customer email (optional)') }}</label>
    <input type="email" name="recipient_email" id="recipient_email" maxlength="255" class="form-control"
        value="{{ old('recipient_email', $code->recipient_email ?? '') }}">
    <small>{{ __('The customer must sign in with this email to use the offer.') }}</small>
</div>
<div class="form-group">
    <input type="hidden" name="first_order_only" value="0">
    <label for="first_order_only">
        <input type="checkbox" name="first_order_only" id="first_order_only" value="1"
            {{ old('first_order_only', $code->first_order_only ?? false) ? 'checked' : '' }}>
        {{ __('First order only (requires customer sign-in)') }}
    </label>
</div>
