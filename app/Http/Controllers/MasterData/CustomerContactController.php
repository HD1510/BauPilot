<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CustomerContactController extends Controller
{
    public function store(Request $request, Customer $customer): RedirectResponse
    {
        Gate::authorize('update', $customer);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
        ], [], ['name' => 'Name', 'phone' => 'Telefon', 'email' => 'E-Mail']);

        $customer->contacts()->create($validated);

        return back()->with('success', 'Ansprechpartner hinzugefügt.');
    }

    public function destroy(Customer $customer, CustomerContact $contact): RedirectResponse
    {
        Gate::authorize('update', $customer);

        abort_unless($contact->customer_id === $customer->id, 404);

        $contact->delete();

        return back()->with('success', 'Ansprechpartner entfernt.');
    }
}
