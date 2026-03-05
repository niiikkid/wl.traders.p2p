<?php

namespace App\Http\Controllers;

use App\Services\AppApkService;

class ApkController extends Controller
{
    public function __construct(private readonly AppApkService $appApkService)
    {
    }

    public function download()
    {
        return $this->appApkService->downloadResponse();
    }
}
