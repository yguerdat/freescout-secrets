<?php

use Modules\Secrets\Http\Middleware\SecurityHeaders;

/*
|--------------------------------------------------------------------------
| Public, unauthenticated pages (served on the dedicated sub-domain).
|--------------------------------------------------------------------------
| No session / CSRF: the public API is stateless and protected by rate limits,
| an unguessable id and (for intake) a honeypot. Strict security headers apply.
*/
Route::group([
    'middleware' => ['web', SecurityHeaders::class],
    'prefix' => \Helper::getSubdirectory(),
    'namespace' => 'Modules\Secrets\Http\Controllers',
], function () {
    // Reveal page for an outbound or inbound secret. The param is named "token"
    // (not "id") to escape FreeScout's global Route::pattern('id', '[0-9]+').
    Route::get('/secrets/s/{token}', 'PublicController@revealPage')
        ->where('token', '[A-Za-z0-9_-]+')->name('secrets.reveal_page');
    // Customer -> agent intake form.
    Route::get('/secrets/new', 'PublicController@inboundForm')->name('secrets.inbound_form');
});

/*
|--------------------------------------------------------------------------
| Public stateless JSON API.
|--------------------------------------------------------------------------
*/
Route::group([
    'middleware' => ['bindings', SecurityHeaders::class],
    'prefix' => \Helper::getSubdirectory(true) . 'api/secrets',
    'namespace' => 'Modules\Secrets\Http\Controllers',
], function () {
    $revealLimit = (int) config('secrets.rate_limit_reveal', 60);
    $createLimit = (int) config('secrets.rate_limit_create', 20);

    // Non-consuming metadata for the reveal page.
    Route::get('/peek/{token}', 'PublicController@peek')
        ->where('token', '[A-Za-z0-9_-]+')
        ->middleware('throttle:' . $revealLimit . ',1')->name('secrets.api.peek');
    // Atomically consume one view and return the ciphertext.
    Route::post('/consume/{token}', 'PublicController@consume')
        ->where('token', '[A-Za-z0-9_-]+')
        ->middleware('throttle:' . $revealLimit . ',1')->name('secrets.api.consume');
    // Module RSA public key for the intake form.
    Route::get('/pubkey', 'PublicController@pubkey')->name('secrets.api.pubkey');
    // Customer submits an encrypted secret -> creates a ticket.
    Route::post('/inbound', 'PublicController@inboundStore')
        ->middleware('throttle:' . $createLimit . ',1')->name('secrets.api.inbound');
});

/*
|--------------------------------------------------------------------------
| Back-office (authenticated) routes.
|--------------------------------------------------------------------------
*/
Route::group([
    'middleware' => 'web',
    'prefix' => \Helper::getSubdirectory(),
    'namespace' => 'Modules\Secrets\Http\Controllers',
], function () {
    // Settings (admins only).
    Route::get('/app/secrets/settings', ['uses' => 'SecretsController@settings', 'middleware' => ['auth', 'roles'], 'roles' => ['admin']])->name('secrets.settings');
    Route::post('/app/secrets/settings', ['uses' => 'SecretsController@settingsSave', 'middleware' => ['auth', 'roles'], 'roles' => ['admin']]);

    // Agent: manage / audit created secrets.
    Route::get('/app/secrets/manage', ['uses' => 'SecretsController@manage', 'middleware' => ['auth']])->name('secrets.manage');
    Route::post('/app/secrets/delete/{token}', ['uses' => 'SecretsController@destroy', 'middleware' => ['auth']])->where('token', '[A-Za-z0-9_-]+')->name('secrets.delete');

    // Agent: create an outbound secret.
    Route::get('/app/secrets/create', ['uses' => 'SecretsController@createPage', 'middleware' => ['auth']])->name('secrets.create');
    Route::post('/app/secrets/store-outbound', ['uses' => 'SecretsController@storeOutbound', 'middleware' => ['auth'], 'laroute' => true])->name('secrets.store_outbound');
    Route::post('/app/secrets/sms', ['uses' => 'SecretsController@sendSms', 'middleware' => ['auth'], 'laroute' => true])->name('secrets.sms');

    // Agent: reveal an inbound secret from the ticket (consumes a view).
    Route::post('/app/secrets/reveal-inbound/{token}', ['uses' => 'SecretsController@revealInbound', 'middleware' => ['auth'], 'laroute' => true])->where('token', '[A-Za-z0-9_-]+')->name('secrets.reveal_inbound');
});
