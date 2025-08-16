<?php

namespace Kdabrow\CustomEvents\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Kdabrow\CustomEvents\CustomEventsTrait;

class TestModelWithCustomField extends Model
{
    use CustomEventsTrait;

    protected $table = 'test_models';
    protected $fillable = ['custom_status', 'name'];

    protected function casts(): array
    {
        return [
            'custom_status' => TestStatus::class,
        ];
    }

    public function getEventFieldName(): string
    {
        return 'custom_status';
    }
}