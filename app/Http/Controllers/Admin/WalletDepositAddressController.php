<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NetworkEnum;
use App\Enums\WalletDepositInvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\WalletDepositAddressResource;
use App\Http\Resources\Admin\WalletDepositInvoiceResource;
use App\Models\WalletDepositAddress;
use App\Models\WalletDepositInvoice;
use App\Rules\ValidateTRC20Address;
use App\Services\WalletDeposit\Features\DepositAddressAllocator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WalletDepositAddressController extends Controller
{
    public function index(Request $request): Response
    {
        $addresses = WalletDepositAddress::query()
            ->withCount(['invoices' => function ($query): void {
                $query->whereIn('status', [
                    WalletDepositInvoiceStatus::PENDING->value,
                    WalletDepositInvoiceStatus::PROCESSING->value,
                ])->where('expires_at', '>', now());
            }])
            ->orderByDesc('id')
            ->get();

        $invoices = WalletDepositInvoice::query()
            ->with(['wallet.user', 'wallet.merchant', 'resolvedBy'])
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('status', $request->string('status')->toString());
            })
            ->when($request->filled('user'), function ($query) use ($request): void {
                $query->whereRelation('wallet.user', 'email', 'like', '%'.$request->string('user')->toString().'%');
            })
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        return Inertia::render('Admin/WalletDeposit/Index', [
            'addresses' => WalletDepositAddressResource::collection($addresses)->resolve(),
            'invoices' => WalletDepositInvoiceResource::collection($invoices),
            'statuses' => array_map(
                fn (WalletDepositInvoiceStatus $status): string => $status->value,
                WalletDepositInvoiceStatus::cases(),
            ),
            'settings' => [
                'invoice_expires_in_minutes' => (int) config('services.wallet_deposit.invoice_expires_in_minutes', 30),
                'min_confirmations' => (int) config('services.wallet_deposit.min_confirmations', 10),
                'amount_collision_percent' => (float) config('services.wallet_deposit.amount_collision_percent', 5),
                'manual_review_page_size' => (int) config('services.wallet_deposit.manual_review_page_size', 50),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'address' => ['required', 'string', 'min:34', 'max:34', new ValidateTRC20Address, Rule::unique('wallet_deposit_addresses', 'address')],
            'label' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        WalletDepositAddress::create([
            'currency' => DepositAddressAllocator::CURRENCY,
            'network' => NetworkEnum::TRX,
            'address' => $validated['address'],
            'label' => $validated['label'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->back()->with('success', 'Адрес добавлен в пул.');
    }

    public function update(Request $request, WalletDepositAddress $walletDepositAddress): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $walletDepositAddress->update([
            'label' => $validated['label'] ?? null,
            'is_active' => $validated['is_active'],
        ]);

        return redirect()->back()->with('success', 'Адрес обновлён.');
    }

    public function refreshBalance(WalletDepositAddress $walletDepositAddress): RedirectResponse
    {
        services()->walletDeposit()->refreshAddressBalance($walletDepositAddress);

        return redirect()->back();
    }
}
