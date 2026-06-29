<?php

namespace App\Http\Controllers;

use App\Http\Requests\WithdrawalAddress\StoreWithdrawalAddressRequest;
use App\Models\User;
use App\Models\WithdrawalAddress;
use Illuminate\Http\RedirectResponse;

class WithdrawalAddressController extends Controller
{
    public function store(StoreWithdrawalAddressRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        WithdrawalAddress::create([
            'user_id' => $user->id,
            'name' => $request->addressName(),
            'address' => $request->address(),
            'address_hash' => $request->addressHash(),
        ]);

        return redirect()->back()->with('success', 'Адрес вывода добавлен.');
    }
}
