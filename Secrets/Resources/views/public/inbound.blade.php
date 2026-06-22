@extends('secrets::public.layout')

@section('title', __('Send us a secret'))

@section('content')
<div id="secrets-app" class="secrets-card"
     data-iterations="{{ $iterations }}"
     data-pubkey-url="{{ \Helper::getSubdirectory(true) }}api/secrets/pubkey"
     data-inbound-url="{{ \Helper::getSubdirectory(true) }}api/secrets/inbound"
     data-t-unsupported="{{ __('Your browser does not support the cryptography required by this form.') }}"
     data-t-required="{{ __('Please provide your e-mail and the information to send.') }}"
     data-t-encrypting="{{ __('Encrypting in your browser…') }}"
     data-t-error="{{ __('Could not send the secret. Please try again.') }}">

    <h1 class="secrets-title">{{ __('Send us sensitive information securely') }}</h1>
    <p class="secrets-lead">{{ __('Your information is encrypted in your browser before it leaves your device. We only ever receive an encrypted blob and open it inside our support tool.') }}</p>

    <div id="secrets-status" class="secrets-status"></div>

    <div id="secrets-form-wrap">
        <form id="secrets-form" autocomplete="off">
            {{-- Honeypot: must stay empty. --}}
            <div class="secrets-hp" aria-hidden="true">
                <label>{{ __('Leave this field empty') }}<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <div class="secrets-field">
                <label for="sc-in-email">{{ __('Your e-mail') }} *</label>
                <input type="email" id="sc-in-email" name="email" required placeholder="you@example.com">
            </div>

            <div class="secrets-field">
                <label for="sc-in-subject">{{ __('Subject') }}</label>
                <input type="text" id="sc-in-subject" name="subject" maxlength="180"
                       placeholder="{{ __('e.g. Server credentials') }}">
            </div>

            <div class="secrets-field">
                <label for="sc-in-secret">{{ __('The information to send') }} *</label>
                <textarea id="sc-in-secret" name="secret" rows="6" required spellcheck="false"
                          placeholder="{{ __('Paste the password or configuration here') }}"></textarea>
            </div>

            <div class="secrets-field">
                <label for="sc-in-note">{{ __('Note for our team') }}</label>
                <textarea id="sc-in-note" name="note" rows="2" maxlength="1000"
                          placeholder="{{ __('Optional context (this part is not encrypted)') }}"></textarea>
            </div>

            <div class="secrets-field">
                <label for="sc-in-pass">{{ __('Optional passphrase') }}</label>
                <input type="text" id="sc-in-pass" name="passphrase" autocomplete="off" spellcheck="false"
                       placeholder="{{ __('Extra protection — share it with us by phone or SMS') }}">
                <small class="secrets-hint">{{ __('If you set a passphrase, tell your contact separately. We cannot read the secret without it.') }}</small>
            </div>

            <button id="secrets-submit" type="submit" class="secrets-btn secrets-btn-primary">
                {{ __('Encrypt & send') }}
            </button>
        </form>
    </div>

    <div id="secrets-success" class="secrets-success" style="display:none">
        <h2>{{ __('Sent securely ✔') }}</h2>
        <p>{{ __('Thank you. Your information reached our team encrypted and a support ticket has been opened.') }}</p>
        <p class="secrets-hint">{{ __('You can close this page now.') }}</p>
    </div>
</div>
@endsection

@section('page_scripts')
<script src="{{ \Module::getPublicPath('secrets') }}/js/inbound.js"></script>
@endsection
