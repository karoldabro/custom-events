<?php

namespace Kdabrow\CustomEvents\Tests\Fixtures;

enum TestStatus: string
{
    case INITIATED = 'initiated';
    case PROCESSING = 'processing';
    case FINISHED = 'finished';
    case ERROR = 'error';
}