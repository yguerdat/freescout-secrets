@extends('layouts.app')

@section('title_full', __('Secrets') . ' - ' . __('Sent secrets'))

@section('sidebar')
    @include('secrets::partials.sidebar', ['active' => 'manage'])
@endsection

@section('content')
<div class="section-heading">{{ __('Sent secrets') }}</div>

<div class="col-xs-12">
    <p class="text-help">{{ __('Audit of secrets created here. Outbound secrets are zero-knowledge — their content and link cannot be recovered, only revoked. Revoking destroys the payload immediately.') }}</p>

    @if($secrets->count() == 0)
        <div class="alert alert-info">{{ __('No secrets yet.') }}</div>
    @else
    <table class="table table-condensed">
        <thead>
            <tr>
                <th>{{ __('Created') }}</th>
                <th>{{ __('Type') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Views') }}</th>
                <th>{{ __('Passphrase') }}</th>
                <th>{{ __('Expires') }}</th>
                <th>{{ __('Ticket') }}</th>
                <th>{{ __('By') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        @foreach($secrets as $s)
            @php
                if ($s->burned_at) { $status = __('Destroyed'); $cls = 'default'; }
                elseif ($s->expires_at && $s->expires_at->isPast()) { $status = __('Expired'); $cls = 'default'; }
                elseif ($s->views >= $s->max_views) { $status = __('Destroyed'); $cls = 'default'; }
                else { $status = __('Active'); $cls = 'success'; }
            @endphp
            <tr>
                <td><span title="{{ $s->created_at }}">{{ $s->created_at->format('d.m.Y H:i') }}</span></td>
                <td>{{ $s->direction === 'inbound' ? __('From customer') : __('To customer') }}</td>
                <td><span class="label label-{{ $cls }}">{{ $status }}</span></td>
                <td>{{ $s->views }} / {{ $s->max_views }}</td>
                <td>{!! $s->passphrase_protected ? '<i class="glyphicon glyphicon-lock text-success"></i>' : '<span class="text-muted">—</span>' !!}</td>
                <td>{{ $s->expires_at ? $s->expires_at->format('d.m.Y H:i') : '—' }}</td>
                <td>
                    @if($s->conversation_id)
                        <a href="/conversation/{{ $s->conversation_id }}" target="_blank">#{{ $s->conversation_id }}</a>
                    @else — @endif
                </td>
                <td>{{ $s->created_by && $users->get($s->created_by) ? $users->get($s->created_by)->getFullName() : '—' }}</td>
                <td class="text-right">
                    @if(!$s->burned_at)
                        <form method="POST" action="{{ route('secrets.delete', $s->id) }}" class="secrets-confirm" style="display:inline"
                              data-confirm="{{ __('Revoke and destroy this secret now?') }}">
                            {{ csrf_field() }}
                            <button type="submit" class="btn btn-xs btn-link text-danger" title="{{ __('Revoke') }}">
                                <i class="glyphicon glyphicon-trash"></i>
                            </button>
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="text-center">{{ $secrets->links() }}</div>
    @endif
</div>
@endsection
