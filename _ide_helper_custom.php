<?php

namespace Illuminate\Http
{
    class Response
    {
        /** @return \Illuminate\Http\JsonResponse */
        public function success($data = [], int $status_code = 200) {}

        /** @return \Illuminate\Http\JsonResponse */
        public function successWithMessage(string $message, $data = [], int $status_code = 200) {}

        /** @return \Illuminate\Http\JsonResponse */
        public function fail(int $status_code = 400) {}

        /** @return \Illuminate\Http\JsonResponse */
        public function failWithMessage(string $message, int $status_code = 400) {}
    }
}
