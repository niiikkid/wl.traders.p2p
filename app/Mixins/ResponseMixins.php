<?php

namespace App\Mixins;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ResponseMixins
{
    public function success()
    {
        return function ($data = [], int $status_code = 200) {
            $response = [
                'success' => true,
                'data' => $data,
            ];

            if ($data instanceof AnonymousResourceCollection) {
                $response = array_merge(
                    $response,
                    $data->response()->getData(true)
                );
            }

            return $this->json($response, $status_code);
        };
    }

    public function successWithMessage()
    {
        return function (string $message, $data = [], int $status_code = 200) {
            $response = [
                'success' => true,
                'message' => $message,
                'data' => $data,
            ];

            if ($data instanceof AnonymousResourceCollection) {
                $response = array_merge(
                    $response,
                    $data->response()->getData(true)
                );
            }

            return $this->json($response, $status_code);
        };
    }

    public function fail()
    {
        return function (int $status_code = 400) {
            return $this->json([
                'success' => false,
            ], $status_code);
        };
    }

    public function failWithMessage()
    {
        return function (string $message, int $status_code = 400) {
            return $this->json([
                'success' => false,
                'message' => $message,
            ], $status_code);
        };
    }
}
