<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WithdrawalAddress\DestroyRequest;
use App\Http\Requests\Admin\WithdrawalAddress\StoreRequest;
use App\Http\Resources\Admin\WithdrawalAddressUserResource;
use App\Models\User;
use App\Models\WithdrawalAddress;
use App\Utils\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AddressWhitelistController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->getTableFilters();
        $search = trim((string) ($filters->search ?? ''));
        $users = User::query()
            ->select(['id', 'name', 'email'])
            ->with(['withdrawalAddresses' => function (HasMany $addresses): void {
                $addresses->withCount('invoices')->latest('id');
            }])
            ->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $query) use ($search): void {
                $query->where('email', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%')
                    ->orWhereHas('withdrawalAddresses', fn (Builder $addresses): Builder => $addresses
                        ->where('address_hash', WithdrawalAddress::hashAddress($search))
                        ->orWhere('name', 'like', '%'.$search.'%'));
            }))
            ->orderBy('email')
            ->paginate(min(50, max(1, $request->integer('per_page', 10))))
            ->withQueryString();

        return Inertia::render('Withdrawal/WhiteList/Index', [
            'filters' => $filters,
            'filtersVariants' => [],
            'users' => WithdrawalAddressUserResource::collection($users),
            'requiresTwoFactor' => $request->user()->google2fa_secret !== null,
        ]);
    }

    public function store(StoreRequest $request): RedirectResponse
    {
        try {
            WithdrawalAddress::create([
                'user_id' => $request->validated('user_id'),
                'name' => $request->addressName(),
                'address' => $request->address(),
                'address_hash' => $request->addressHash(),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['address' => 'Адрес недоступен.']);
        }

        return redirect()->back()->with('message', 'Адрес вывода добавлен.');
    }

    public function destroy(DestroyRequest $request, WithdrawalAddress $withdrawalAddress): RedirectResponse
    {
        Transaction::run(function () use ($withdrawalAddress): void {
            $address = WithdrawalAddress::query()->lockForUpdate()->findOrFail($withdrawalAddress->id);

            if ($address->invoices()->exists()) {
                throw ValidationException::withMessages(['address' => 'Адрес связан с заявками на вывод и не может быть удалён.']);
            }

            $address->delete();
        });

        return redirect()->back()->with('message', 'Адрес вывода удалён.');
    }
}
