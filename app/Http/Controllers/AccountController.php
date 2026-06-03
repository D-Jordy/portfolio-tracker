<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(): Response
    {
        $accounts = auth()->user()
            ->accounts()
            ->orderBy('name')
            ->get(['id', 'name', 'broker', 'import_watermark']);

        return Inertia::render('Accounts/Index', [
            'accounts' => $accounts,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Accounts/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'   => ['required', 'string', 'max:100'],
            'broker' => ['required', 'string', 'max:50'],
        ]);

        $account = auth()->user()->accounts()->create($validated);

        return redirect()->route('accounts.import.show', $account)
            ->with('success', 'Account created. Upload your first CSV below.');
    }

    public function destroy(Account $account): RedirectResponse
    {
        Gate::authorize('delete', $account);
        $account->delete();

        return redirect()->route('accounts.index');
    }
}
