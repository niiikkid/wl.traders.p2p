<?php

namespace App\Contracts;

use App\Models\AntiFraudSetting;

interface AntiFraudSettingServiceContract
{
    public function getForMerchant(int $merchantId): ?AntiFraudSetting;

    public function create(array $data): AntiFraudSetting;

    public function update(AntiFraudSetting $setting, array $data): AntiFraudSetting;

    public function delete(AntiFraudSetting $setting): void;
}
