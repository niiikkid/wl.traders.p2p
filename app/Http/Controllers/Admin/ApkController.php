<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\App\UploadApkRequest;
use App\Services\AppApkService;
use Inertia\Inertia;

class ApkController extends Controller
{
    public function __construct(private readonly AppApkService $appApkService)
    {
    }

    public function index()
    {
        $isUploaded = $this->appApkService->exists();

        return Inertia::render('Admin/App/Index', [
            'isUploaded' => $isUploaded,
            'lastUploadedAt' => $this->appApkService->lastUploadedAt(),
            'downloadUrl' => $isUploaded ? route('app.download') : null,
        ]);
    }

    public function store(UploadApkRequest $request)
    {
        $this->appApkService->replace($request->file('apk'));

        return redirect()
            ->route('admin.app.index')
            ->with('message', 'APK успешно обновлено');
    }
}
