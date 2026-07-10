@include('partials/sidebar_menu_toggle')
<div class="sidebar-title">{{ __('Secrets') }}</div>
<ul class="sidebar-menu">
    <li class="{{ ($active ?? '') === 'manage' ? 'active' : '' }}"><a href="{{ route('secrets.manage') }}"><i class="glyphicon glyphicon-list"></i> {{ __('Sent secrets') }}</a></li>
    <li class="{{ ($active ?? '') === 'create' ? 'active' : '' }}"><a href="{{ route('secrets.create') }}"><i class="glyphicon glyphicon-plus"></i> {{ __('Send a secret') }}</a></li>
    @if(auth()->user()->isAdmin())
        <li class="{{ ($active ?? '') === 'settings' ? 'active' : '' }}"><a href="{{ route('secrets.settings') }}"><i class="glyphicon glyphicon-cog"></i> {{ __('Settings') }}</a></li>
    @endif
</ul>
