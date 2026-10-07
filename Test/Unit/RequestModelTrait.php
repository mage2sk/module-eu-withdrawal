<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Test\Unit;

use Panth\EuWithdrawal\Model\Request;

/**
 * Creates real Request models without touching the database layer.
 */
trait RequestModelTrait
{
    protected function makeRequest(array $data = []): Request
    {
        $model = (new \ReflectionClass(Request::class))->newInstanceWithoutConstructor();
        $model->setIdFieldName('request_id');
        $model->setData($data);
        return $model;
    }
}
