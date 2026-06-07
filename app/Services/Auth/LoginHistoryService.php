<?php

namespace App\Services\Auth;

use App\Contracts\LoginHistoryServiceContract;
use App\Models\User;
use App\Models\UserLoginHistory;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Jenssegers\Agent\Agent;

class LoginHistoryService implements LoginHistoryServiceContract
{
    private const DEV_PUBLIC_IP_POOL = [
        '8.8.8.8',
        '1.1.1.1',
        '208.67.222.222',
        '9.9.9.9',
    ];

    /**
     * Записывает информацию о входе пользователя в систему
     */
    public function isLoggingEnabledFor(User $user): bool
    {
        return (bool) $user->login_history_logging_enabled;
    }

    public function clearUserHistory(User $user): void
    {
        UserLoginHistory::query()
            ->where('user_id', $user->id)
            ->delete();
    }

    public function recordLogin(User $user, Request $request, bool $isSuccessful = true): UserLoginHistory
    {
        $agent = new Agent;
        $agent->setUserAgent($request->userAgent());

        $requestIp = $request->ip();
        $ipForGeolocation = $this->resolveIpForGeolocation($requestIp);
        $deviceType = $this->getDeviceType($agent);
        $browser = $agent->browser().' '.$agent->version($agent->browser());
        $platform = $agent->platform().' '.$agent->version($agent->platform());

        /** @var array{location:?string,country_code:?string,country:?string,region:?string,city:?string} $geoData */
        $geoData = $this->getLocationByIp($ipForGeolocation);

        return UserLoginHistory::create([
            'user_id' => $user->id,
            'ip_address' => $ipForGeolocation ?? $requestIp,
            'user_agent' => $request->userAgent(),
            'device_type' => $deviceType,
            'browser' => $browser,
            'operating_system' => $platform,
            'location' => $geoData['location'],
            'country_code' => $geoData['country_code'],
            'country' => $geoData['country'],
            'region' => $geoData['region'],
            'city' => $geoData['city'],
            'is_successful' => $isSuccessful,
        ]);
    }

    /**
     * Определяет тип устройства
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
     * @return array{location:?string,country_code:?string,country:?string,region:?string,city:?string}
     */
    private function getLocationByIp(?string $ip): array
    {
        $emptyLocation = [
            'location' => null,
            'country_code' => null,
            'country' => null,
            'region' => null,
            'city' => null,
        ];

        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return $emptyLocation;
        }

        $apiKey = (string) config('services.ipgeolocation.api_key');

        if ($apiKey === '') {
            return $emptyLocation;
        }

        $baseUrl = (string) config('services.ipgeolocation.base_url', 'https://api.ipgeolocation.io/v2');

        try {
            $response = Http::timeout(5)
                ->acceptJson()
                ->get(rtrim($baseUrl, '/').'/ipgeo', [
                    'apiKey' => $apiKey,
                    'ip' => $ip,
                ])
                ->throw();

            $data = $response->json();
            $locationData = is_array($data['location'] ?? null) ? $data['location'] : [];
            $country = $this->normalizeGeoField($locationData['country_name'] ?? ($data['country_name'] ?? null));
            $region = $this->normalizeGeoField($locationData['state_prov'] ?? ($data['state_prov'] ?? null));
            $city = $this->normalizeGeoField($locationData['city'] ?? ($data['city'] ?? null));
            $countryCode = $this->normalizeGeoField($locationData['country_code2'] ?? ($data['country_code2'] ?? null));

            $locationParts = array_filter([$city, $region, $country]);

            return [
                'location' => empty($locationParts) ? null : implode(', ', $locationParts),
                'country_code' => $countryCode ? strtoupper($countryCode) : null,
                'country' => $country,
                'region' => $region,
                'city' => $city,
            ];
        } catch (ConnectionException|RequestException) {
            return $emptyLocation;
        }
    }

    private function normalizeGeoField(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function resolveIpForGeolocation(?string $ip): ?string
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        if (! is_local()) {
            return $ip;
        }

        $isPublicIp = (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

        if ($isPublicIp) {
            return $ip;
        }

        return self::DEV_PUBLIC_IP_POOL[array_rand(self::DEV_PUBLIC_IP_POOL)];
    }
}
