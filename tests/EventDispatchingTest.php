<?php

namespace Kdabrow\CustomEvents\Tests;

use Kdabrow\CustomEvents\Tests\Fixtures\TestModelWithSoftDeletes;
use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Event;
use Kdabrow\CustomEvents\Tests\Fixtures\TestModel;
use Kdabrow\CustomEvents\Tests\Fixtures\TestStatus;
use App\Events\TestModelInitiated;
use App\Events\TestModelProcessing;

class EventDispatchingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    public function testEventsAreActuallyDispatchedOnCreate(): void
    {
        $createdEvents = [];
        
        Event::listen(TestModelInitiated::class, function ($event) use (&$createdEvents) {
            $createdEvents[] = [
                'model_id' => $event->testModel->id,
                'new_status' => $event->newStatus,
                'old_status' => $event->oldStatus,
            ];
        });

        $model = TestModel::create([
            'name' => 'Test Model',
            'status' => TestStatus::INITIATED
        ]);

        $this->assertEquals(TestStatus::INITIATED, $model->status);
        
        // Debug what we actually received
        $this->addToAssertionCount(1); // Prevents risky test warning
		$this->assertCount(1, $createdEvents);
		$this->assertEquals($model->id, $createdEvents[0]['model_id']);
		$this->assertEquals(TestStatus::INITIATED, $createdEvents[0]['new_status']);
		$this->assertNull($createdEvents[0]['old_status']);
    }

    public function testEventsAreActuallyDispatchedOnUpdate(): void
    {
        $model = TestModel::create([
            'name' => 'Test Model',
            'status' => TestStatus::INITIATED
        ]);

        $updateEvents = [];
        
        Event::listen(TestModelProcessing::class, function ($event) use (&$updateEvents) {
            $updateEvents[] = [
                'model_id' => $event->testModel->id,
                'new_status' => $event->newStatus,
                'old_status' => $event->oldStatus,
            ];
        });

        $originalStatus = $model->status;
        $model->update(['status' => TestStatus::PROCESSING]);

        $this->assertEquals(TestStatus::PROCESSING, $model->status);
        
        // Debug what we actually received
        $this->addToAssertionCount(1); // Prevents risky test warning

		// We might get multiple events, so let's check if at least one is correct
		$validEvent = false;
		foreach ($updateEvents as $event) {
			if ($event['model_id'] === $model->id
				&& $event['new_status'] === TestStatus::PROCESSING
				&& $event['old_status'] === $originalStatus) {
				$validEvent = true;
				break;
			}
		}
		$this->assertTrue($validEvent, 'No valid event found among ' . count($updateEvents) . ' dispatched events');
    }

	public function testEventsAreNotDispatchedOnDelete(): void
	{
		/** @var TestModel $model */
		$model = TestModel::create([
			'name' => 'Test Model',
			'status' => TestStatus::INITIATED
		]);

		$deleteEvents = [];

		Event::listen(TestModelInitiated::class, function ($event) use (&$deleteEvents) {
			$deleteEvents[] = [
				'model_id' => $event->testModel->id,
				'new_status' => $event->newStatus,
				'old_status' => $event->oldStatus,
			];
		});

		$model->delete();

		$this->assertEmpty($deleteEvents);
	}

    public function testEventsAreNotDispatchedOnSoftDelete(): void
    {
		/** @var TestModelWithSoftDeletes $model */
        $model = TestModelWithSoftDeletes::create([
            'name' => 'Test Model',
            'status' => TestStatus::INITIATED
        ]);

        $deleteEvents = [];
        
        Event::listen(TestModelInitiated::class, function ($event) use (&$deleteEvents) {
            $deleteEvents[] = [
                'model_id' => $event->testModel->id,
                'new_status' => $event->newStatus,
                'old_status' => $event->oldStatus,
            ];
        });

        $model->delete();

		$this->assertEmpty($deleteEvents);
    }
}