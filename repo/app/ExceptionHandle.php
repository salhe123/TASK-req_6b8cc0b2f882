<?php
namespace app;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Response;
use Throwable;

class ExceptionHandle extends Handle
{
    protected $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
    ];

    public function report(Throwable $exception): void
    {
        parent::report($exception);
    }

    public function render($request, Throwable $e): Response
    {
        if ($e instanceof ValidateException) {
            return json_error($e->getMessage(), 400);
        }

        if ($e instanceof ModelNotFoundException || $e instanceof DataNotFoundException) {
            return json_error('Resource not found', 404);
        }

        if ($e instanceof HttpException) {
            return json_error($e->getMessage(), $e->getStatusCode());
        }

        if (env('APP_DEBUG')) {
            return parent::render($request, $e);
        }

        return json_error('Internal system error', 500);
    }
}
