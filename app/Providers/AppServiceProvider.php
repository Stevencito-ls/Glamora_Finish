<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\SecurityActionTriggered;
use App\Listeners\SecurityLogListener;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\User;
use App\Models\WholesaleProfile;
use App\Policies\OrderPolicy;
use App\Policies\OrderReturnPolicy;
use App\Policies\ProductPolicy;
use App\Policies\UserPolicy;
use App\Policies\WholesaleProfilePolicy;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\HandleRequests;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(OrderReturn::class, OrderReturnPolicy::class);
        Gate::policy(WholesaleProfile::class, WholesaleProfilePolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        Gate::before(static function (mixed $user, string $ability, array $arguments = []): ?bool {
            if (! $user instanceof User) {
                return null;
            }

            if (! (bool) $user->is_active) {
                return false;
            }

            if ($user->hasRole('SuperAdmin')) {
                if (
                    ($arguments[0] ?? null) instanceof User
                    && in_array($ability, ['delete', 'toggleActive', 'assignRole'], true)
                ) {
                    return null;
                }

                return true;
            }

            return null;
        });

        $this->registerSecurityListeners();
        $this->configureLivewireSubdirectoryRouting();
    }

    private function registerSecurityListeners(): void
    {
        Event::listen(Login::class, [SecurityLogListener::class, 'handleLogin']);
        Event::listen(Logout::class, [SecurityLogListener::class, 'handleLogout']);
        Event::listen(Failed::class, [SecurityLogListener::class, 'handleFailed']);
        Event::listen(Registered::class, [SecurityLogListener::class, 'handleRegistered']);
        Event::listen(SecurityActionTriggered::class, [SecurityLogListener::class, 'handleSecurityAction']);
    }

    private function configureLivewireSubdirectoryRouting(): void
    {
        $handleRequests = new class extends HandleRequests
        {
            public function getUpdateUri(): string
            {
                $uri = parent::getUpdateUri();
                $basePath = $this->resolveSubdirectoryBasePath();

                if ($basePath === '' || str_starts_with($uri, $basePath . '/')) {
                    return $uri;
                }

                return $basePath . $uri;
            }

            private function resolveSubdirectoryBasePath(): string
            {
                $requestBasePath = rtrim((string) request()->getBasePath(), '/');

                if ($requestBasePath !== '') {
                    return $requestBasePath;
                }

                if (app()->runningInConsole() && ! app()->runningUnitTests()) {
                    $appPath = parse_url((string) config('app.url'), PHP_URL_PATH);
                    $normalized = is_string($appPath) ? '/' . trim($appPath, '/') : '';

                    return $normalized === '/' ? '' : $normalized;
                }

                return '';
            }
        };

        $this->app->instance(HandleRequests::class, $handleRequests);

        Livewire::setUpdateRoute(static function (mixed $handle) {
            return Route::post('/livewire/update', $handle)
                ->middleware('web')
                ->name('glamora.livewire.update');
        });

        Livewire::setScriptRoute(static function (mixed $handle) {
            return config('app.debug')
                ? Route::get('/livewire/livewire.js', $handle)
                : Route::get('/livewire/livewire.min.js', $handle);
        });

        if (! $this->app->runningInConsole() && request()->getBasePath() !== '') {
            $scriptPath = config('app.debug') ? 'livewire/livewire.js' : 'livewire/livewire.min.js';
            config(['livewire.asset_url' => asset($scriptPath)]);
        }
    }
}
