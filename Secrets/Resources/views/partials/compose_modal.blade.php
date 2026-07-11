{{-- Injected on the agent conversation view: lets an agent create a secret
     link and insert it straight into the reply, per GitHub issue #1. --}}
<div id="secrets-compose"
     data-csrf="{{ csrf_token() }}"
     data-store-url="{{ route('secrets.store_outbound') }}"
     data-sms-url="{{ route('secrets.sms') }}"
     data-iterations="{{ (int) config('secrets.pbkdf2_iterations', 310000) }}"
     data-sms-configured="{{ (new \Modules\Secrets\Services\SmsEagleClient())->isConfigured() ? '1' : '0' }}"
     data-btn-label="{{ __('Insert a secret link') }}"
     data-link-text="{{ __('Open the secure link') }}"
     data-t-required="{{ __('Enter the secret to share.') }}"
     data-t-encrypting="{{ __('Encrypting…') }}"
     data-t-error="{{ __('Could not create the secret. Please try again.') }}"
     data-t-inserted="{{ __('Secret link inserted into your reply.') }}"
     data-t-sms_sending="{{ __('Sending…') }}"
     data-t-sms_sent="{{ __('Passphrase sent by SMS.') }}"
     data-t-sms_error="{{ __('SMS failed.') }}"
     style="display:none">

    <div class="modal fade" id="secrets-compose-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title"><i class="glyphicon glyphicon-lock"></i> {{ __('Insert a secret link') }}</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>{{ __('Secret') }}</label>
                        <textarea id="sccm-secret" class="form-control" rows="4" spellcheck="false"
                                  placeholder="{{ __('Paste the password or configuration here') }}"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-sm-4 form-group">
                            <label>{{ __('Expires after') }}</label>
                            <select id="sccm-ttl" class="form-control">
                                <option value="1">{{ __('1 hour') }}</option>
                                <option value="24">{{ __('1 day') }}</option>
                                <option value="72">{{ __('3 days') }}</option>
                                <option value="168" selected>{{ __('7 days') }}</option>
                                <option value="720">{{ __('30 days') }}</option>
                            </select>
                        </div>
                        <div class="col-sm-4 form-group">
                            <label>{{ __('Maximum views') }}</label>
                            <input id="sccm-views" type="number" class="form-control" min="1" value="1">
                        </div>
                        <div class="col-sm-4 form-group">
                            <label>{{ __('Passphrase (optional)') }}</label>
                            <div class="input-group">
                                <input id="sccm-pass" type="text" class="form-control" autocomplete="off" spellcheck="false">
                                <span class="input-group-btn">
                                    <button id="sccm-gen" type="button" class="btn btn-default">{{ __('Generate') }}</button>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div id="sccm-sms-wrap" style="display:none">
                        <label>{{ __('Send the passphrase by SMS') }}</label>
                        <div class="input-group">
                            <input id="sccm-sms-phone" type="tel" class="form-control" placeholder="+41 79 …">
                            <span class="input-group-btn">
                                <button id="sccm-sms-btn" type="button" class="btn btn-default">{{ __('Send SMS') }}</button>
                            </span>
                        </div>
                        <div id="sccm-sms-status" class="text-help" style="margin-top:5px"></div>
                    </div>

                    <div id="sccm-status" class="secrets-status" style="margin-top:8px"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link" data-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="button" id="sccm-create" class="btn btn-primary">{{ __('Create & insert link') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
