<?php

namespace FancyCrud\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class AcceptedLanguagesMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->acceptsHtml()) {
            // Get the locale from the session
            $locale = Session::get('locale');
        } else {
            $locale = $request->header('Accept-Language', 'en');
        }

        if (! in_array($locale, config('app.supported_locales'))) {
            $locale = config('app.locale');
        }

        // Set the locale for the current application
        if ($locale) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
