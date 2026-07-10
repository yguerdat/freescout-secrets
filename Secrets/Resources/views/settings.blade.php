@extends('layouts.app')

@section('title_full', __('Secrets') . ' - ' . __('Settings'))

@section('sidebar')
    @include('secrets::partials.sidebar', ['active' => 'settings'])
@endsection

@section('content')
<div class="section-heading">{{ __('Secrets') }}</div>

<div class="col-xs-12">
    <p class="text-help">{{ __('Securely share one-time secrets with customers and let them send secrets back to you. Everything is end-to-end encrypted in the browser.') }}</p>

    <form method="POST" action="{{ route('secrets.settings') }}" class="form-horizontal margin-top">
        {{ csrf_field() }}

        <div class="section-heading-sm">{{ __('Branding') }}</div>
        <p class="text-help col-sm-offset-3 col-sm-7">{{ __('Public pages use your company name (:name). Customize the tagline, colour and logo shown to customers.', ['name' => $brandName]) }}</p>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{ __('Tagline') }}</label>
            <div class="col-sm-7">
                <input type="text" name="tagline" class="form-control" maxlength="120" value="{{ $tagline }}"
                       placeholder="{{ __('Securely share secrets') }}">
                <p class="form-help">{{ __('Shown under your company name on the public pages.') }}</p>
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{ __('Accent colour') }}</label>
            <div class="col-sm-3">
                <div class="input-group">
                    <span class="input-group-addon" style="padding:2px 4px;">
                        <input type="color" value="{{ $accentColor }}" style="width:34px;height:28px;border:0;padding:0;background:none;"
                               onchange="document.getElementById('secrets-accent-hex').value=this.value">
                    </span>
                    <input type="text" id="secrets-accent-hex" name="accent_color" class="form-control" value="{{ $accentColor }}"
                           pattern="#[0-9a-fA-F]{3,8}" placeholder="#2563eb">
                </div>
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{ __('Logo URL') }}</label>
            <div class="col-sm-7">
                <input type="url" name="logo_url" class="form-control" value="{{ $logoUrl }}"
                       placeholder="https://cdn.example.com/logo.png">
                <p class="form-help">{{ __('Optional. Shown on the public pages instead of the lock icon. Leave empty to use the lock icon.') }}</p>
            </div>
        </div>

        <hr>
        <div class="section-heading-sm">{{ __('Public access') }}</div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{ __('Public base URL') }}</label>
            <div class="col-sm-7">
                <input type="url" name="public_base_url" class="form-control" value="{{ $publicBaseUrl }}" placeholder="https://secrets.example.com">
                <p class="form-help">{{ __('Where the reveal page and intake form are served. A dedicated sub-domain is recommended.') }}</p>
                <p class="form-help">
                    {{ __('Customer intake form:') }}
                    <a href="{{ $publicBaseUrl }}/secrets/new" target="_blank">{{ $publicBaseUrl }}/secrets/new</a>
                </p>
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{ __('Default expiry (hours)') }}</label>
            <div class="col-sm-3">
                <input type="number" name="default_ttl_hours" class="form-control" min="1" value="{{ $defaultTtlHours }}">
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{ __('Default maximum views') }}</label>
            <div class="col-sm-3">
                <input type="number" name="default_max_views" class="form-control" min="1" value="{{ $defaultMaxViews }}">
            </div>
        </div>

        <hr>
        <div class="section-heading-sm">{{ __('Customer intake') }}</div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{ __('Enable intake form') }}</label>
            <div class="col-sm-7">
                <div class="controls">
                    <div class="onoffswitch-wrap">
                        <div class="onoffswitch">
                            <input type="checkbox" name="inbound_enabled" value="1" id="inbound_enabled"
                                   class="onoffswitch-checkbox" {{ $inboundEnabled ? 'checked' : '' }}>
                            <label class="onoffswitch-label" for="inbound_enabled"></label>
                        </div>
                    </div>
                </div>
                <p class="form-help">{{ __('Allow customers to send you secrets, which open a ticket with a reveal button.') }}</p>
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{ __('Reveals before destruction') }}</label>
            <div class="col-sm-3">
                <input type="number" name="inbound_max_views" class="form-control" min="1" value="{{ $inboundMaxViews }}">
                <p class="form-help">{{ __('How many times an agent can reveal a customer-submitted secret before it is destroyed (burn-after-read).') }}</p>
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{ __('Intake mailbox') }}</label>
            <div class="col-sm-5">
                <select name="inbound_mailbox_id" class="form-control">
                    <option value="0">{{ __('First available mailbox') }}</option>
                    @foreach($mailboxes as $mailbox)
                        <option value="{{ $mailbox->id }}" {{ $inboundMailboxId == $mailbox->id ? 'selected' : '' }}>{{ $mailbox->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <hr>
        <div class="section-heading-sm">{{ __('SMS (SMSeagle)') }}</div>
        <p class="text-help col-sm-offset-3 col-sm-7">{{ __('Optional: deliver passphrases to customers by SMS over a separate channel.') }}
            @if($smsConfigured)<span class="text-success">{{ __('Configured.') }}</span>@endif
        </p>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{ __('SMSeagle base URL') }}</label>
            <div class="col-sm-7">
                <input type="url" name="sms_base_url" class="form-control" value="{{ $smsBaseUrl }}" placeholder="https://sms.example.com">
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{ __('API token') }}</label>
            <div class="col-sm-7">
                <input type="password" name="sms_token" class="form-control" autocomplete="new-password"
                       placeholder="{{ $smsConfigured ? __('Leave blank to keep the current token') : '' }}">
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{ __('Modem number') }}</label>
            <div class="col-sm-3">
                <input type="text" name="sms_modem" class="form-control" value="{{ $smsModem }}" placeholder="1">
            </div>
        </div>

        <div class="form-group margin-top">
            <div class="col-sm-7 col-sm-offset-3">
                <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
            </div>
        </div>
    </form>
</div>
@endsection
