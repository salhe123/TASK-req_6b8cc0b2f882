<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;

class Index extends BaseController
{
    public function index()
    {
        return view('index/index');
    }

    public function health()
    {
        return json_success([
            'status'  => 'ok',
            'version' => '1.0.0',
            'time'    => date('Y-m-d H:i:s'),
        ]);
    }
}
