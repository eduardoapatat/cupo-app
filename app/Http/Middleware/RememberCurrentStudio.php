<?php

namespace App\Http\Middleware;

use App\Models\Membership;
use App\Models\Studio;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers the last studio a person opened, so /inicio and the pages
 * outside a studio (such as settings) always have a current studio: the
 * workspace switcher pattern.
 */
class RememberCurrentStudio
{
    public const string SESSION_KEY = 'current_studio_id';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $studio = $request->route('studio');

        if ($request->isMethod('GET') && $response->isSuccessful() && $studio instanceof Studio) {
            $request->session()->put(self::SESSION_KEY, $studio->id);
        }

        return $response;
    }

    /**
     * The user's current membership: the studio they opened last if they
     * still belong to it, or else the first one by name.
     */
    public static function current(Request $request): ?Membership
    {
        $memberships = $request->user()?->memberships()->with('studio')->get()
            ->sortBy(fn (Membership $membership): array => [$membership->studio->name, $membership->studio_id]);

        return $memberships?->firstWhere('studio_id', $request->session()->get(self::SESSION_KEY))
            ?? $memberships?->first();
    }
}
