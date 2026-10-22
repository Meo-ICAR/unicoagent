<?php

namespace App\Http\Controllers;

use App\Support\CurrentCompany;
use App\Support\TenantUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanySwitchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        if ($request->user() && TenantUser::isSuperAdmin()) {
            CurrentCompany::set($request->filled('company_id') ? $request->integer('company_id') : null);
        }

        return back();
    }
}
