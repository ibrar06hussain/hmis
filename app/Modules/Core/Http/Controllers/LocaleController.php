<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function switch(Request $request): RedirectResponse
    {
        $supported = (array) config('hmis.supported_locales', ['en', 'ur']);

        $validated = $request->validate([
            'locale' => ['required', Rule::in($supported)],
        ]);

        $request->session()->put('hmis_locale', (string) $validated['locale']);

        return back();
    }
}
