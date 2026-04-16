<?php
declare(strict_types=1);

namespace app;

use think\App;
use think\exception\ValidateException;
use think\Validate;

abstract class BaseController
{
    protected App $app;
    protected \think\Request $request;

    public function __construct(App $app)
    {
        $this->app     = $app;
        $this->request = $this->app->request;
        $this->initialize();
    }

    protected function initialize(): void
    {
    }

    protected function validate(array $data, $validate, array $message = []): bool
    {
        if (is_array($validate)) {
            $v = new Validate();
            $v->rule($validate);
        } else {
            if (strpos($validate, '.')) {
                [$validate, $scene] = explode('.', $validate);
            }
            $class = new $validate();
            if (isset($scene)) {
                $class->scene($scene);
            }
            $v = $class;
        }

        if (!empty($message)) {
            $v->message($message);
        }

        if (!$v->check($data)) {
            throw new ValidateException($v->getError());
        }

        return true;
    }
}
