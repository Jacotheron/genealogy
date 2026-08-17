<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Exception;
use Filament\Tables\Table;
use Illuminate\Container\EntryNotFoundException;
use Illuminate\Contracts\Container\CircularDependencyException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Opcodes\LogViewer\Facades\LogViewer;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use TallStackUi\Facades\TallStackUi;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ------------------------------------------------------------------------------
        // Configure application settings and services
        // ------------------------------------------------------------------------------
        //        $this->configureUrl();
        $this->configureStrictMode();
        $this->configureLogViewer();
        $this->configureDates();
        $this->configureTallStackUiPersonalization();

        $this->addAboutCommandDetails();

        if (app()->isLocal()) {
            RequestException::dontTruncate();
        }

        // ------------------------------------------------------------------------------
        // Automatically eager load relations when needed for all models
        // ------------------------------------------------------------------------------
        Model::automaticallyEagerLoadRelationships();

        // ------------------------------------------------------------------------------
        // This will prevent any destructive commands from being executed
        // in production environments, such as dropping tables or truncating data.
        // This is a safety measure to prevent accidental data loss.
        // Uncomment the line below to enable this feature.
        // ------------------------------------------------------------------------------
        // DB::prohibitDestructiveCommands(app()->isProduction());

        // ------------------------------------------------------------------------------
        // Enable or disable logging based on application settings
        // ------------------------------------------------------------------------------
        //        if ($this->isDatabaseOnline() && Schema::hasTable('settings')) {
        //            // Cache the applications settings
        //            $this->app->singleton('settings', fn () => Cache::rememberForever('settings', static fn () => Setting::query()
        //                ->pluck('value', 'key')));
        //
        //            $this->logAllQueries();
        //            $this->LogAllQueriesSlow();
        //            $this->logAllQueriesNplusone();
        //        }
        // ------------------------------------------------------------------------------
        Gate::define('viewPulse', static function (User $user) {
            return $user->is_developer;
        });

        // Password Rules
        Password::defaults(function () {
            $local_rule = Password::min(8);

            if ($this->app->isProduction()) {
                return Password::min(10)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised();
            }

            return $local_rule;
        });

        // Model Strictness Violations
        Model::shouldBeStrict();
        if (config('app.env') === 'production') {
            // Helper function to find the true culprit line in your code
            $getAppCaller = static function (): array {
                $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30);
                foreach ($trace as $frame) {
                    if (isset($frame['file']) &&
                        Str::contains($frame['file'], base_path('app/')) &&
                        ! Str::contains($frame['file'], base_path('app/Providers/AppServiceProvider.php'))) {
                        // Remove the absolute base path to keep logs clean
                        return [
                            'file' => str_replace(base_path() . '/', '', $frame['file']),
                            'line' => $frame['line'] ?? 0,
                        ];
                    }
                }

                return ['file' => 'Unknown App File', 'line' => 0];
            };
            $getLivewireContext = static function (): array {
                $context = [];
                // Check if it is a Livewire update request
                if (request()->hasHeader('X-Livewire')) {
                    $components = request()->input('components', []);
                    $names      = array_column($components, 'name');
                    if (! empty($names)) {
                        $context['livewire_components'] = array_unique($names);
                    }
                }

                return $context;
            };
            $shouldLogViolation = static function (string $type, Model $model, string $detail, array $caller) {
                $uniqueString = "strict_violation:$type:{$model->getTable()}:$detail:{$caller['file']}:{$caller['line']}";
                $cacheKey     = 'log_lock:' . md5($uniqueString);
                if (Cache::has($cacheKey)) {
                    return false;
                }

                Cache::put($cacheKey, true, 3600);

                return true;
            };
            Model::handleLazyLoadingViolationUsing(static function (Model $model, mixed $value) use ($getAppCaller, $getLivewireContext, $shouldLogViolation) {
                $caller = $getAppCaller();
                if ($shouldLogViolation('lazy_loading', $model, (string) $value, $caller)) {
                    Log::notice("Strictness Violation: Lazy Loading: {$model->getTable()}.{$model->getKey()}: relationship: $value", [
                        'file'    => $caller['file'],
                        'line'    => $caller['line'],
                        'url'     => request()->fullUrl(),
                        'user'    => auth()->user() ? auth()->user()->id : 'Guest',
                        'context' => $getLivewireContext(),
                    ]);
                }
            });
            Model::handleDiscardedAttributeViolationUsing(static function (Model $model, mixed $value) use ($getAppCaller, $getLivewireContext, $shouldLogViolation) {
                $caller = $getAppCaller();
                $detail = implode(', ', $value);

                if ($shouldLogViolation('discarded_attributes', $model, $detail, $caller)) {
                    Log::notice("Strictness Violation: Discarded Attributes: {$model->getTable()}.{$model->getKey()}: attributes: " . implode(', ', $value), [
                        'file'    => $caller['file'],
                        'line'    => $caller['line'],
                        'url'     => request()->fullUrl(),
                        'user'    => auth()->user() ? auth()->user()->id : 'Guest',
                        'context' => $getLivewireContext(),
                    ]);
                }
            });
            Model::handleMissingAttributeViolationUsing(static function (Model $model, mixed $value) use ($getAppCaller, $getLivewireContext, $shouldLogViolation) {
                $caller = $getAppCaller();
                if ($shouldLogViolation('missing_attribute', $model, (string) $value, $caller)) {
                    Log::notice("Strictness Violation: Missing Attribute: {$model->getTable()}.{$model->getKey()}: attribute: $value", [
                        'file'    => $caller['file'],
                        'line'    => $caller['line'],
                        'url'     => request()->fullUrl(),
                        'user'    => auth()->user() ? auth()->user()->id : 'Guest',
                        'context' => $getLivewireContext(),
                    ]);
                }
            });
        }

        // eager load models when needed
        Model::automaticallyEagerLoadRelationships();

        // URL Scheme Force HTTPS
        URL::forceHttps();

        // Filament Tables Currency
        Table::configureUsing(static function (Table $table) {
            $table->defaultCurrency('ZAR')
                ->defaultNumberLocale('en_ZA');
        });

        // Illuminate Number currency & locale
        Number::useLocale('en_ZA');
        Number::useCurrency('ZAR');

        // Carbon Localized translations
        Carbon::setLocale(config('app.locale'));

        // Prohibit destructive commands in Production
        DB::prohibitDestructiveCommands($this->app->isProduction());

        // For local error, show full errors
        if (app()->isLocal()) {
            RequestException::dontTruncate();
        }
    }

    /**
     * Check if the database connection is available.
     */
    protected function isDatabaseOnline(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Exception) {
            // Log the exception if needed for debugging
            // Log::error('Database connection error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Enforce HTTPS (only in production).
     */
    private function configureUrl(): void
    {
        URL::forceHttps(app()->isProduction());
    }

    /**
     * Use Strict Mode (only on local).
     *
     * 1. Prevent Lazy Loading
     * 2. Prevent Silently Discarding Attributes
     * 3. Prevent Accessing Missing Attributes
     * Reference: https://coderflex.com/blog/laravel-strict-mode-all-what-you-need-to-know
     */
    private function configureStrictMode(): void {}

    /**
     * Configure LogViewer settings, grant access to developers.
     */
    private function configureLogViewer(): void
    {
        LogViewer::auth(static function ($request) {
            $user = $request->user();

            // If user is not authenticated, deny access
            if (! $user) {
                return false;
            }

            // Check if user has is_developer property and it's true
            return $user->is_developer ?? false;
        });
    }

    /**
     * Personalize TallStackUi components.
     *
     * Reference: https://tallstackui.com/docs/personalization/soft
     */
    private function configureTallStackUiPersonalization(): void
    {
        $ui = TallStackUi::customize();

        $ui->alert()->block('wrapper')->replace('rounded-lg', 'rounded-sm');

        $ui->card()
            ->block('wrapper.first')->replace('gap-4', 'gap-2')
            ->block('wrapper.second')->replace([
                'dark:bg-dark-700' => 'dark:bg-neutral-700',
                'rounded-lg'       => 'rounded-sm',
            ])
            ->block('header.wrapper.border')->replace('dark:border-b-dark-600', 'dark:border-b-neutral-600')
            ->block('footer.wrapper')->replace([
                'dark:border-t-dark-600' => 'dark:border-t-neutral-600',
                'rounded-lg'             => 'rounded-sm',
            ]);

        $ui->carousel()
            ->block('images.base')->append('rounded-sm');

        $ui->dropdown()
            ->block('floating.default')->replace('rounded-lg', 'rounded-sm')
            ->block('floating.class')->replace('w-56', 'w-auto')
            ->block('action.icon')->replace('text-gray-400', 'text-primary-500 dark:text-primary-300');

        $ui->form('input')
            ->block('input.wrapper')->replace('rounded-md', 'rounded-sm')
            ->block('input.base')->replace('rounded-md', 'rounded-sm')
            ->block('input.color.background')->replace('dark:bg-dark-800', 'dark:bg-dark-950');

        $ui->form('textarea')
            ->block('input.wrapper')->replace('rounded-md', 'rounded-sm')
            ->block('input.base')->replace('rounded-md', 'rounded-sm')
            ->block('input.color.background')->replace('dark:bg-dark-800', 'dark:bg-dark-950');

        $ui->form('label')
            ->block('text')->replace([
                'text-gray-600'      => 'text-gray-700',
                'dark:text-dark-400' => 'dark:text-neutral-500',
            ]);

        $ui->modal()
            ->block('wrapper.first')->replace('bg-gray-400/75', 'bg-gray-400/10')
            ->block('wrapper.fourth')->replace([
                'dark:bg-dark-700' => 'dark:bg-gray-900',
                'rounded-t-xl'     => 'rounded-t-sm',
            ]);

        $ui->slide()
            ->block('wrapper.first')->replace('bg-gray-400/75', 'bg-gray-400/10')
            ->block('wrapper.fifth')->replace('dark:bg-dark-700', 'dark:bg-gray-900')
            ->block('body')->replace('dark:text-dark-300', 'dark:text-neutral-300')
            ->block('footer')->append('dark:text-secondary-600');

        $ui->tab()
            ->block('base.wrapper')->replace([
                'dark:bg-dark-700' => 'dark:bg-neutral-700',
                'rounded-lg'       => 'rounded-sm',
            ])
            ->block('base.content')->remove('p-4')
            ->block('item.select')->replace('dark:text-dark-300', 'dark:text-neutral-50');

        $ui->table()
            ->block('wrapper')->replace('rounded-lg', 'rounded-sm')
            ->block('table.td')->replace('py-4', 'py-2');

        $ui->select('styled')
            ->block('input.wrapper.base')->replace([
                'dark:bg-dark-800' => 'dark:bg-dark-950',
                'rounded-md'       => 'rounded-sm',
            ]);
    }

    /**
     * Configure the application's dates.
     */
    private function configureDates(): void
    {
        Date::use(CarbonImmutable::class);
    }

    /**
     * Add application details to the About command.
     */
    private function addAboutCommandDetails(): void
    {
        AboutCommand::add('Application', [
            'Name'    => 'Genealogy',
            'Author'  => 'kreaweb.be',
            'GitHub'  => 'https://github.com/MGeurts/genealogy',
            'License' => 'MIT License',
        ]);
    }

    /**
     * Log all queries for debugging purposes.
     */
    private function logAllQueries(): void
    {
        try {
            if (settings('log_all_queries')) {
                DB::listen(static fn ($query) => Log::debug($query->toRawSQL()));
            }
        } catch (EntryNotFoundException|CircularDependencyException|NotFoundExceptionInterface|ContainerExceptionInterface) {
        }
    }

    /**
     * Log all slow queries for debugging purposes.
     */
    private function LogAllQueriesSlow(): void
    {
        try {
            if (settings('log_all_queries_slow')) {
                DB::listen(static function ($query): void {
                    if ($query->time > (int) settings('log_all_queries_slow_threshold')) {
                        Log::warning('An individual database query exceeded ' . settings('log_all_queries_slow_threshold') . ' ms.', [
                            'sql'       => $query->sql,
                            'raw'       => $query->toRawSQL(),
                            'time'      => $query->time,
                            'formatted' => CarbonInterval::milliseconds($query->time)->cascade()->forHumans(['short' => true, 'parts' => 3, 'join' => true]),
                        ]);
                    }
                });
            }
        } catch (EntryNotFoundException|CircularDependencyException|NotFoundExceptionInterface|ContainerExceptionInterface) {
        }
    }

    /**
     * Log all (N+1) queries for debugging purposes.
     */
    private function logAllQueriesNplusone(): void
    {
        try {
            if (settings('log_all_queries_n+1')) {
                Model::handleLazyLoadingViolationUsing(static function ($model, $relation): void {
                    Log::warning(sprintf(
                        'N+1 Query detected in model %s on relation %s.',
                        $model::class,
                        $relation
                    ));
                });
            }
        } catch (EntryNotFoundException|CircularDependencyException|NotFoundExceptionInterface|ContainerExceptionInterface) {
        }
    }
}
