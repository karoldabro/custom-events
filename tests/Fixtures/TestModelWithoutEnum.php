<?php

namespace Kdabrow\CustomEvents\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Kdabrow\CustomEvents\CustomEventsTrait;

class TestModelWithoutEnum extends Model
{
    use CustomEventsTrait;

    protected $table = 'test_models';
    protected $fillable = ['status', 'name'];

    public function getEventFieldName(): string
    {
        return 'status';
    }
}