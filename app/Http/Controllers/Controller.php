<?php

namespace App\Http\Controllers;
use Illuminate\Routing\Controller as BaseController;

// abstract class Controller
// {
// }

class Controller extends BaseController
{

    public function responseSuccess($data = [], $code = 200, $header = [])
    {
        return response()
            ->json(
                array_merge([
                    'status' => 'success'
                ], $data),
                $code,
                $header
            );
    }

    public function responseError($data = [], $code = 200, $header = [])
    {
        return response()
            ->json(
                array_merge([
                    'status' => 'error'
                ], $data),
                $code,
                $header
            );
    }
}


