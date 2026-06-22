@extends('layouts.app')

@section('title_full', __('Send a secret'))

@section('content')
<div class="section-heading">
    <i class="glyphicon glyphicon-lock"></i> {{ __('Send a secret') }}
</div>

<div class="col-xs-12">
    <p class="text-help">{{ __('Paste a password or configuration below. It is encrypted in your browser; the server only ever stores an unreadable blob, and the key travels only inside the link. Send the link to your customer.') }}</p>

    <div id="secrets-create-app"
         data-csrf="{{ csrf_token() }}"
         data-iterations="{{ $iterations }}"
         data-store-url="{{ route('secrets.store_outbound') }}"
         data-sms-url="{{ route('secrets.sms') }}"
         data-t-unsupported="{{ __('Your browser does not support the required cryptography.') }}"
         data-t-required="{{ __('Enter the secret to share.') }}"
         data-t-encrypting="{{ __('Encrypting…') }}"
         data-t-done="{{ __('Link ready. The secret can only be decrypted with this exact link.') }}"
         data-t-error="{{ __('Could not create the secret. Please try again.') }}"
         data-t-copied="{{ __('Copied') }}"
         data-t-sms_sending="{{ __('Sending…') }}"
         data-t-sms_sent="{{ __('Passphrase sent by SMS.') }}"
         data-t-sms_error="{{ __('SMS failed.') }}">

        <div class="form-group">
            <label>{{ __('Secret') }}</label>
            <textarea id="sc-secret" class="form-control" rows="6" spellcheck="false"
                      placeholder="{{ __('Paste the password or configuration here') }}"></textarea>
        </div>

        <div class="row">
            <div class="col-sm-4 form-group">
                <label>{{ __('Expires after') }}</label>
                <select id="sc-ttl" class="form-control">
                    <option value="1">{{ __('1 hour') }}</option>
                    <option value="24">{{ __('1 day') }}</option>
                    <option value="72">{{ __('3 days') }}</option>
                    <option value="168" {{ $defaultTtlHours == 168 ? 'selected' : '' }}>{{ __('7 days') }}</option>
                    <option value="720">{{ __('30 days') }}</option>
                </select>
            </div>
            <div class="col-sm-4 form-group">
                <label>{{ __('Maximum views') }}</label>
                <input id="sc-views" type="number" class="form-control" min="1" max="{{ $maxViewsCap }}" value="{{ $defaultMaxViews }}">
            </div>
            <div class="col-sm-4 form-group">
                <label>{{ __('Passphrase (optional)') }}</label>
                <div class="input-group">
                    <input id="sc-pass" type="text" class="form-control" autocomplete="off" spellcheck="false"
                           placeholder="{{ __('Extra protection') }}">
                    <span class="input-group-btn">
                        <button id="sc-gen" type="button" class="btn btn-default">{{ __('Generate') }}</button>
                    </span>
                </div>
                <small class="text-help">{{ __('If set, share it on a separate channel (e.g. SMS).') }}</small>
            </div>
        </div>

        <button id="sc-create" type="button" class="btn btn-primary">{{ __('Create secure link') }}</button>
        <div id="sc-status" class="secrets-status" style="margin-top:10px"></div>

        <div id="sc-result" style="display:none; margin-top:15px">
            <div class="form-group">
                <label>{{ __('Secure link') }}</label>
                <div class="input-group">
                    <input id="sc-link" type="text" class="form-control" readonly spellcheck="false">
                    <span class="input-group-btn">
                        <button id="sc-copy" type="button" class="btn btn-default">{{ __('Copy') }}</button>
                    </span>
                </div>
                <small class="text-help">{{ __('Anyone with this exact link can open the secret (subject to passphrase, views and expiry).') }}</small>
            </div>

            <div id="sc-sms-wrap" style="display:none">
                <label>{{ __('Send the passphrase by SMS') }}</label>
                <div class="input-group">
                    <input id="sc-sms-phone" type="tel" class="form-control" placeholder="+41 79 …">
                    <span class="input-group-btn">
                        <button id="sc-sms-btn" type="button" class="btn btn-default"
                                {{ $smsConfigured ? '' : 'disabled' }}>{{ __('Send SMS') }}</button>
                    </span>
                </div>
                @if(!$smsConfigured)
                    <small class="text-danger">{{ __('SMSeagle is not configured in the module settings.') }}</small>
                @endif
                <div id="sc-sms-status" class="text-help" style="margin-top:5px"></div>
            </div>
        </div>
    </div>
</div>
@endsection
