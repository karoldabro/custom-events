<?php

namespace Kdabrow\CustomEvents\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kdabrow\CustomEvents\CustomEventsTrait;

class TestModel extends Model
{
    use CustomEventsTrait;

    protected $table = 'test_models';
    protected $fillable = ['status', 'name'];

    protected function casts(): array
    {
        return [
            'status' => TestStatus::class,
        ];
    }

    public function getEventFieldName(): string
    {
        return 'status';
    }
}