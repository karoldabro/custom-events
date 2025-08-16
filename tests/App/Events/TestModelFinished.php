<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Kdabrow\CustomEvents\Tests\Fixtures\TestModel;
use Kdabrow\CustomEvents\Tests\Fixtures\TestStatus;

class TestModelFinished
{
    use Dispatchable;

    public function __construct(
        public TestModel $testModel,
        public TestStatus $newStatus,
        public ?TestStatus $oldStatus
    ) {}
}