<?php

namespace App\Http\Middleware;

use App\Enums\StudioRole;
use App\Models\Membership;
use App\Models\Studio;
use App\Navigation\MenuItem;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'studio' => fn (): ?array => $this->studio($request),
            'studios' => fn (): array => $this->studios($request),
        ];
    }

    /**
     * Every studio the user belongs to, by name, for the studio switcher.
     *
     * @return list<array{name: string, slug: string, roleLabel: string, href: string}>
     */
    protected function studios(Request $request): array
    {
        $memberships = $request->user()?->memberships()->with('studio')->get()
            ->sortBy(fn (Membership $membership): array => [mb_strtolower($membership->studio->name), $membership->studio_id]);

        return array_values($memberships?->map(fn (Membership $membership): array => [
            'name' => $membership->studio->name,
            'slug' => $membership->studio->slug,
            'roleLabel' => $membership->role->label(),
            'href' => route('studios.home', $membership->studio, absolute: false),
        ])->all() ?? []);
    }

    /**
     * The studio the sidebar shows, with the member's role and menu: the
     * studio of the page or, outside a studio (or on a 404 for someone
     * else's), the user's current one (see RememberCurrentStudio::current()).
     *
     * @return array{name: string, slug: string, role: string, roleLabel: string, menu: list<array{key: string, label: string, href: string|null}>}|null
     */
    protected function studio(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        $pageStudio = $request->route('studio');
        $role = $pageStudio instanceof Studio ? $user->roleIn($pageStudio) : null;

        if ($pageStudio instanceof Studio && $role !== null) {
            return $this->studioProps($pageStudio, $role);
        }

        $membership = RememberCurrentStudio::current($request);

        return $membership === null ? null : $this->studioProps($membership->studio, $membership->role);
    }

    /**
     * @return array{name: string, slug: string, role: string, roleLabel: string, menu: list<array{key: string, label: string, href: string|null}>}
     */
    protected function studioProps(Studio $studio, StudioRole $role): array
    {
        return [
            'name' => $studio->name,
            'slug' => $studio->slug,
            'role' => $role->value,
            'roleLabel' => $role->label(),
            'menu' => array_map(fn (MenuItem $item): array => $item->toNavigation($studio), $role->menu()),
        ];
    }
}
