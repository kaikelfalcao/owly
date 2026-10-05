<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Platform\Audit\Audit;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, Audit $audit): RedirectResponse
    {
        $request->user()->fill($request->validated());
        $nameChanged = $request->user()->isDirty('name');
        $emailChanged = $request->user()->isDirty('email');

        if ($emailChanged) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        // Nome e e-mail são dado pessoal: a auditoria guarda que mudaram, não o valor.
        if ($nameChanged) {
            $audit->record('accounts.name_changed', $request->user(), changes: ['name' => Audit::HIDDEN]);
        }

        if ($emailChanged) {
            $audit->record('accounts.email_changed', $request->user(), changes: ['email' => Audit::HIDDEN]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }
}
