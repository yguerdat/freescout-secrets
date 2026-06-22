@php $iterations = (int) config('secrets.pbkdf2_iterations', 310000); @endphp
<div id="secrets-inbound-panel" class="secrets-inbound-panel"
     data-iterations="{{ $iterations }}"
     data-csrf="{{ csrf_token() }}"
     data-t-need_pass="{{ __('Enter the passphrase the customer gave you.') }}"
     data-t-revealing="{{ __('Revealing…') }}"
     data-t-revealed="{{ __('Revealed. Copy it now — it will not be shown again here.') }}"
     data-t-gone="{{ __('This secret is no longer available (expired or already destroyed).') }}"
     data-t-wrong="{{ __('Could not decrypt — the passphrase may be wrong.') }}">

    <div class="secrets-inbound-head">
        <i class="glyphicon glyphicon-lock"></i> {{ __('Encrypted secret from the customer') }}
    </div>

    @foreach($secrets as $secret)
        <div class="secrets-inbound-row">
            @if($secret->isAlive())
                @if($secret->passphrase_protected)
                    <div class="secrets-inbound-passwrap">
                        <input type="password" class="secrets-inbound-pass form-control input-sm"
                               autocomplete="off" placeholder="{{ __('Passphrase from the customer') }}">
                    </div>
                @endif
                <button type="button"
                        class="btn btn-default btn-sm secrets-reveal-inbound"
                        data-reveal-url="{{ route('secrets.reveal_inbound', $secret->id) }}"
                        data-passphrase-protected="{{ $secret->passphrase_protected ? '1' : '0' }}">
                    {{ __('Reveal secret') }}
                </button>
                <span class="secrets-inbound-status text-help"></span>
                <textarea class="secrets-inbound-output form-control" readonly rows="4"
                          spellcheck="false" style="display:none; margin-top:8px"></textarea>
            @else
                <span class="text-help">{{ __('This secret is no longer available (expired or already destroyed).') }}</span>
            @endif
        </div>
    @endforeach
</div>
