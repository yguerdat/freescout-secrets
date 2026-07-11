<?php

namespace Modules\Secrets\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Secrets\Entities\Secret;
use Modules\Secrets\Services\SecretService;

define('SECRETS_MODULE', 'secrets');

class SecretsServiceProvider extends ServiceProvider
{
    protected $defer = false;

    public function boot()
    {
        $this->registerConfig();
        $this->registerViews();
        $this->registerTranslations();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__ . '/../Http/routes.php');
        $this->hooks();
    }

    public function register()
    {
        //
    }

    public function hooks()
    {
        // Back-office assets (loaded everywhere in the agent UI; the public
        // pages link their own copies through a dedicated layout).
        \Eventy::addFilter('stylesheets', function ($styles) {
            $styles[] = \Module::getPublicPath(SECRETS_MODULE) . '/css/secrets.css';
            return $styles;
        });

        \Eventy::addFilter('javascripts', function ($javascripts) {
            $javascripts[] = \Module::getPublicPath(SECRETS_MODULE) . '/js/crypto.js';
            $javascripts[] = \Module::getPublicPath(SECRETS_MODULE) . '/js/backoffice.js';
            return $javascripts;
        });

        // "New secret" entry in the main navbar (all agents).
        \Eventy::addAction('menu.append', function () {
            if (auth()->check()) {
                echo '<li class="' . (\App\Misc\Helper::isMenuSelected('app/secrets/create') ? 'active' : '') . '">'
                    . '<a href="' . route('secrets.create') . '" title="' . __('Send a secret') . '">'
                    . '<i class="glyphicon glyphicon-lock"></i></a></li>';
            }
        });

        // Settings entry in the Manage menu (admins only).
        \Eventy::addAction('menu.manage.append', function () {
            if (auth()->check() && auth()->user()->isAdmin()) {
                echo '<li class="' . (\App\Misc\Helper::isMenuSelected('secrets') ? 'active' : '') . '">'
                    . '<a href="' . route('secrets.settings') . '">'
                    . '<i class="glyphicon glyphicon-lock"></i> ' . __('Secrets') . '</a></li>';
            }
        });

        // Reveal panel for inbound secrets linked to a conversation.
        \Eventy::addAction('conversation.before_threads', function ($conversation) {
            try {
                // Agents only — never expose the reveal panel in a customer portal.
                if (!auth()->check() || !auth()->user()) {
                    return;
                }
                if (!$conversation || empty($conversation->id)) {
                    return;
                }
                $secrets = Secret::where('conversation_id', $conversation->id)
                    ->where('direction', Secret::DIRECTION_INBOUND)
                    ->orderBy('created_at', 'desc')
                    ->get();

                if ($secrets->count()) {
                    echo view('secrets::partials.inbound_panel', ['secrets' => $secrets])->render();
                }
            } catch (\Throwable $e) {
                \Log::error('Secrets: inbound panel failed: ' . $e->getMessage());
            }
        });

        // Inject the "insert a secret link" composer into the agent reply UI
        // (existing conversation) and into the new-conversation form.
        $renderComposer = function () {
            try {
                if (!auth()->check() || !auth()->user()) {
                    return;
                }
                echo view('secrets::partials.compose_modal')->render();
            } catch (\Throwable $e) {
                \Log::error('Secrets: compose modal failed: ' . $e->getMessage());
            }
        };
        \Eventy::addAction('conversation.after_threads', $renderComposer);
        \Eventy::addAction('new_conversation_form.after', $renderComposer);

        // Trust the dedicated public host (e.g. secrets.example.com) so the
        // reveal page and intake form work on their own sub-domain without
        // editing APP_TRUSTED_HOSTS. We only ever trust the host of the
        // configured public base URL.
        \Eventy::addFilter('app.is_trusted_host', function ($trusted, $host) {
            if ($trusted) {
                return true;
            }
            $base = \Option::get('secrets.public_base_url') ?: config('secrets.public_base_url', '');
            $pubHost = $base ? parse_url($base, PHP_URL_HOST) : '';
            return $pubHost && mb_strtolower($host) === mb_strtolower($pubHost);
        }, 20, 2);

        // Hourly purge of expired / burned secrets.
        \Eventy::addFilter('schedule', function ($schedule) {
            $schedule->call(function () {
                try {
                    (new SecretService())->purge();
                } catch (\Throwable $e) {
                    \Log::error('Secrets: purge failed: ' . $e->getMessage());
                }
            })->hourly()->name('secrets:purge')->withoutOverlapping();

            return $schedule;
        });
    }

    protected function registerConfig()
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('secrets.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'secrets');
    }

    public function registerViews()
    {
        $viewPath = resource_path('views/modules/secrets');
        $sourcePath = __DIR__ . '/../Resources/views';

        $this->publishes([$sourcePath => $viewPath], 'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path . '/modules/secrets';
        }, \Config::get('view.paths')), [$sourcePath]), 'secrets');
    }

    public function registerTranslations()
    {
        $langPath = base_path('Modules/Secrets/Resources/lang');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'secrets');
            $this->loadJsonTranslationsFrom($langPath);
        }
    }
}
