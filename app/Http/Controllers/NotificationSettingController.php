<?php

namespace App\Http\Controllers;

use App\Models\NotificationSetting;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Benachrichtigungswahl je Benutzer und aktiver Firma.
 */
class NotificationSettingController extends Controller
{
    public function update(Request $request, CompanyContext $context): RedirectResponse
    {
        $validated = $request->validate([
            'daily_email' => ['required', 'boolean'],
        ], [], ['daily_email' => 'tägliche E-Mail']);

        NotificationSetting::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'company_id' => $context->requireId(),
            ],
            ['daily_email' => $validated['daily_email']],
        );

        return back()->with('success', 'Benachrichtigungen aktualisiert.');
    }
}
