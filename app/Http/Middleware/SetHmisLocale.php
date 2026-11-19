<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetHmisLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = (array) config('hmis.supported_locales', ['en', 'ur']);
        $locale = (string) $request->session()->get('hmis_locale', config('app.locale', 'en'));

        if (! in_array($locale, $supported, true)) {
            $locale = 'en';
        }

        App::setLocale($locale);
        View::share('isRtl', $locale === 'ur');
        View::share('currentLocale', $locale);

        return $next($request);
    }
}
