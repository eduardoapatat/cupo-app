<?php

namespace App\Providers;

use App\Enums\StudioPermission;
use App\Models\Studio;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\ExceptionResponse;
use Inertia\Inertia;

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
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureRoutes();
        $this->configureErrorPages();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * One Gate per row of the permission matrix, checked against the user's
     * role in the given studio: Gate::allows('plans.manage', $studio). There
     * is no super admin, so no Gate::before. Access to a studio itself goes
     * through StudioPolicy, which answers 404 to non-members.
     */
    protected function configureAuthorization(): void
    {
        foreach (StudioPermission::cases() as $permission) {
            Gate::define(
                $permission->value,
                fn (User $user, Studio $studio): bool => $user->roleIn($studio)?->allows($permission) ?? false,
            );
        }
    }

    /**
     * Resource routes use Spanish verbs in their URIs (/planes/crear,
     * /planes/{plan}/editar), since product URLs are in Spanish.
     */
    protected function configureRoutes(): void
    {
        Route::resourceVerbs([
            'create' => 'crear',
            'edit' => 'editar',
        ]);
    }

    /**
     * Page visits get a page instead of Laravel's raw error HTML: an expired
     * session (419) goes back with a warning, and access, missing and server
     * errors render the "error" page. Server errors keep the debug page while
     * debugging, and JSON requests keep their JSON errors.
     */
    protected function configureErrorPages(): void
    {
        Inertia::handleExceptionsUsing(function (ExceptionResponse $response): mixed {
            $request = $response->request;
            $status = $response->statusCode();

            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            if ($status === 419) {
                Inertia::flash('toast', ['type' => 'warning', 'message' => __('The page expired. Please try again.')]);

                return back();
            }

            $showsDebugPage = $status >= 500 && config('app.debug');

            if (in_array($status, [403, 404, 500, 503], true) && ! $showsDebugPage) {
                return $response->render('error', ['status' => $status])->withSharedData();
            }

            return null;
        });
    }
}
