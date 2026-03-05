<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DetailType;
use App\Http\Controllers\Controller;
use App\Models\PaymentDetail;
use App\Models\PaymentGateway;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FilterController extends Controller
{
    /**
     * Возвращает список типов реквизитов
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDetailTypes()
    {
        $detailTypes = [];

        foreach (DetailType::cases() as $type) {
            $detailTypes[] = [
                'value' => $type->value,
                'label' => match ($type) {
                    DetailType::CARD => 'Карта',
                    DetailType::PHONE => 'СБП',
                    DetailType::MOBILE_COMMERCE => 'Моб. коммерция',
                    DetailType::ACCOUNT_NUMBER => 'Номер счета',
                    DetailType::NSPK => 'NSPK (ссылка)',
                    default => $type->value,
                }
            ];
        }

        return response()->json($detailTypes);
    }

    /**
     * Поиск платежных методов для фильтрации
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchPaymentGateways(Request $request)
    {
        $query = $request->input('query', '');

        $paymentGateways = PaymentGateway::query()
            ->where('name', 'like', "%{$query}%")
            ->orWhere('code', 'like', "%{$query}%")
            ->orWhere('id', 'like', "%{$query}%")
            ->select(['id', 'name', 'code'])
            ->limit(10)
            ->get()
            ->map(function ($gateway) {
                return [
                    'value' => $gateway->id,
                    'label' => $gateway->name . ' (' . $gateway->code . ')'
                ];
            });

        return response()->json($paymentGateways);
    }

    /**
     * Поиск пользователей для фильтрации
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchUsers(Request $request)
    {
        $query = $request->input('query', '');

        $users = User::query()
            ->where('name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->orWhere('id', 'like', "%{$query}%")
            ->select(['id', 'name', 'email'])
            ->limit(10)
            ->get()
            ->map(function ($user) {
                return [
                    'value' => $user->id,
                    'label' => $user->name . ' (' . $user->email . ')'
                ];
            });

        return response()->json($users);
    }
}
