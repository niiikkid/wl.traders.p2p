<?php

namespace App\Services\Auth;

use App\Contracts\LoginHistoryServiceContract;
use App\Models\User;
use App\Models\UserLoginHistory;
use Illuminate\Http\Request;
use Jenssegers\Agent\Agent;

class LoginHistoryService implements LoginHistoryServiceContract
{
    /**
     * Записывает информацию о входе пользователя в систему
     *
     * @param User $user
     * @param Request $request
     * @param bool $isSuccessful
     * @return UserLoginHistory
     */
    public function recordLogin(User $user, Request $request, bool $isSuccessful = true): UserLoginHistory
    {
        $agent = new Agent();
        $agent->setUserAgent($request->userAgent());

        $deviceType = $this->getDeviceType($agent);
        $browser = $agent->browser() . ' ' . $agent->version($agent->browser());
        $platform = $agent->platform() . ' ' . $agent->version($agent->platform());

        // Получение примерного местоположения по IP (можно использовать сторонний сервис)
        $location = $this->getLocationByIp($request->ip());

        return UserLoginHistory::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_type' => $deviceType,
            'browser' => $browser,
            'operating_system' => $platform,
            'location' => $location,
            'is_successful' => $isSuccessful,
        ]);
    }

    /**
     * Определяет тип устройства
     *
     * @param Agent $agent
     * @return string
     */
    private function getDeviceType(Agent $agent): string
    {
        if ($agent->isPhone()) {
            return 'Телефон';
        }

        if ($agent->isTablet()) {
            return 'Планшет';
        }

        if ($agent->isDesktop()) {
            return 'Компьютер';
        }

        if ($agent->isRobot()) {
            return 'Робот';
        }

        return 'Другое';
    }

    /**
     * Получает примерное местоположение по IP-адресу
     * В реальном приложении здесь можно использовать сторонний сервис геолокации
     *
     * @param string|null $ip
     * @return string
     */
    private function getLocationByIp(?string $ip): string
    {
        // Здесь можно использовать сторонний сервис для определения местоположения
        // Например, ipinfo.io, ipapi.co, geoip-db.com и т.д.
        // В данном примере просто возвращаем IP-адрес
        return $ip ?? 'Неизвестно';
    }
}
