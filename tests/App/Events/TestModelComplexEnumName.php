<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Kdabrow\CustomEvents\Tests\Fixtures\TestModel;
use Kdabrow\CustomEvents\Tests\Fixtures\TestComplexEnum;

class TestModelComplexEnumName
{
    use Dispatchable;

    public function __construct(
        public TestModel $testModel,
        public TestComplexEnum $newStatus,
        public ?TestComplexEnum $oldStatus
    ) {}
}