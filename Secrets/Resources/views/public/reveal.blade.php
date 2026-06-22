@extends('secrets::public.layout')

@section('title', __('View a secret'))

@section('content')
<div id="secrets-app" class="secrets-card"
     data-iterations="{{ $iterations }}"
     data-peek-url="{{ \Helper::getSubdirectory(true) }}api/secrets/peek/{{ $id }}"
     data-consume-url="{{ \Helper::getSubdirectory(true) }}api/secrets/consume/{{ $id }}"
     data-t-unsupported="{{ __('Your browser does not support the cryptography required to open this secret.') }}"
     data-t-missing_key="{{ __('This link is incomplete — the decryption key is missing. Make sure you copied the whole URL.') }}"
     data-t-gone="{{ __('This secret is no longer available. It may have already been viewed, or it has expired.') }}"
     data-t-ready="{{ __('A secret is waiting for you.') }}"
     data-t-views_left="{{ __('Views remaining:') }}"
     data-t-decrypting="{{ __('Decrypting…') }}"
     data-t-destroyed="{{ __('This secret has now been destroyed. Save it somewhere safe — it cannot be shown again.') }}"
     data-t-wrong="{{ __('Could not decrypt. The passphrase may be wrong — and this attempt used up one of the allowed views.') }}"
     data-t-error="{{ __('Something went wrong. Please try again.') }}"
     data-t-copied="{{ __('Copied') }}">

    <h1 class="secrets-title">{{ __('A secret has been shared with you') }}</h1>

    <div id="secrets-status" class="secrets-status">{{ __('Loading…') }}</div>
    <div id="secrets-meta" class="secrets-meta"></div>

    <div id="secrets-action" class="secrets-action" style="display:none">
        <div id="secrets-pass-wrap" class="secrets-field" style="display:none">
            <label for="secrets-pass">{{ __('Passphrase') }}</label>
            <input type="password" id="secrets-pass" autocomplete="off" spellcheck="false"
                   placeholder="{{ __('Enter the passphrase you were given') }}">
        </div>
        <button id="secrets-reveal-btn" type="button" class="secrets-btn secrets-btn-primary">
            {{ __('Reveal the secret') }}
        </button>
        <p class="secrets-warn">{{ __('Heads up: viewing this secret consumes one view and may destroy it. Have somewhere ready to paste it.') }}</p>
    </div>

    <div id="secrets-result" class="secrets-result" style="display:none">
        <textarea id="secrets-output" class="secrets-output" readonly rows="6" spellcheck="false"></textarea>
        <button id="secrets-copy-btn" type="button" class="secrets-btn">{{ __('Copy to clipboard') }}</button>
    </div>
</div>
@endsection

@section('page_scripts')
<script src="{{ \Module::getPublicPath('secrets') }}/js/reveal.js"></script>
@endsection
