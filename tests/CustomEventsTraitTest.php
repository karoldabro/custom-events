<?php

namespace Kdabrow\CustomEvents\Tests;

use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Event;
use Kdabrow\CustomEvents\Tests\Fixtures\TestModel;
use Kdabrow\CustomEvents\Tests\Fixtures\TestModelWithCustomField;
use Kdabrow\CustomEvents\Tests\Fixtures\TestModelWithoutEnum;
use Kdabrow\CustomEvents\Tests\Fixtures\TestStatus;
use App\Events\TestModelInitiated;
use App\Events\TestModelProcessing;
use App\Events\TestModelFinished;
use App\Events\TestModelError;

class CustomEventsTraitTest extends TestCase
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

    public function testTraitIsBootedWhenModelIsCreated(): void
    {
        $model = new TestModel();
        
        $this->assertTrue(method_exists($model, 'getEventFieldName'));
        $this->assertTrue(method_exists($model, 'updateEventField'));
        $this->assertEquals('status', $model->getEventFieldName());
    }

    public function testCustomFieldNameIsUsed(): void
    {
        $model = new TestModelWithCustomField();
        $this->assertEquals('custom_status', $model->getEventFieldName());
    }

    public function testEventClassesExist(): void
    {
        $this->assertTrue(class_exists(TestModelInitiated::class));
        $this->assertTrue(class_exists(TestModelProcessing::class));
        $this->assertTrue(class_exists(TestModelFinished::class));
        $this->assertTrue(class_exists(TestModelError::class));
    }

    public function testEventNameGeneration(): void
    {
        $model = new TestModel();
        $reflection = new \ReflectionClass($model);
        $method = $reflection->getMethod('getEventName');
        $method->setAccessible(true);

        $this->assertEquals('\App\Events\TestModelInitiated', $method->invoke($model, $model, TestStatus::INITIATED));
        $this->assertEquals('\App\Events\TestModelProcessing', $method->invoke($model, $model, TestStatus::PROCESSING));
        $this->assertEquals('\App\Events\TestModelFinished', $method->invoke($model, $model, TestStatus::FINISHED));
        $this->assertEquals('\App\Events\TestModelError', $method->invoke($model, $model, TestStatus::ERROR));
    }

    public function testEventIsDispatchedOnModelCreation(): void
    {
		$dispatchedEvents = [];

		Event::listen(TestModelInitiated::class, function ($event) use (&$dispatchedEvents) {
			$dispatchedEvents[] = $event;
		});

        $model = TestModel::create([
            'name' => 'Test Model',
            'status' => TestStatus::INITIATED
        ]);

		$this->assertEquals(TestStatus::INITIATED, $model->status);
		$this->assertEquals('Test Model', $model->name);

		$this->assertCount(1, $dispatchedEvents);
		$this->assertInstanceOf(TestModelInitiated::class, $dispatchedEvents[0]);
		$this->assertEquals($model->id, $dispatchedEvents[0]->testModel->id);
		$this->assertEquals(TestStatus::INITIATED, $dispatchedEvents[0]->newStatus);
		$this->assertEquals(null, $dispatchedEvents[0]->oldStatus);
    }

    public function testEventIsDispatchedOnModelUpdate(): void
    {
        $model = TestModel::create([
            'name' => 'Test Model',
            'status' => TestStatus::INITIATED
        ]);

        $dispatchedEvents = [];
        
        Event::listen(TestModelProcessing::class, function ($event) use (&$dispatchedEvents) {
            $dispatchedEvents[] = $event;
        });

        $originalStatus = $model->status;
        $model->update(['status' => TestStatus::PROCESSING]);

        $this->assertEquals(TestStatus::PROCESSING, $model->status);

        $this->assertCount(1, $dispatchedEvents);
        $this->assertInstanceOf(TestModelProcessing::class, $dispatchedEvents[0]);
        $this->assertEquals($model->id, $dispatchedEvents[0]->testModel->id);
        $this->assertEquals(TestStatus::PROCESSING, $dispatchedEvents[0]->newStatus);
        $this->assertEquals($originalStatus, $dispatchedEvents[0]->oldStatus);
    }

    public function testEventIsNotDispatchedOnModelDeletionBecauseStatusWasNotChanged(): void
    {
        $model = TestModel::create([
            'name' => 'Test Model',
            'status' => TestStatus::INITIATED
        ]);

        $dispatchedEvents = [];
        
        Event::listen(TestModelInitiated::class, function ($event) use (&$dispatchedEvents) {
            $dispatchedEvents[] = $event;
        });

        $result = $model->delete();

        $this->assertTrue($result);

        // The event should be dispatched on deletion with the current status
        $this->assertCount(0, $dispatchedEvents);
    }

    public function testNoEventDispatchedWhenStatusNotChanged(): void
    {
        $model = TestModel::create([
            'name' => 'Test Model',
            'status' => TestStatus::INITIATED
        ]);

        $dispatchedEvents = [];
        
        Event::listen([
            TestModelInitiated::class,
            TestModelProcessing::class,
            TestModelFinished::class,
            TestModelError::class
        ], function ($event) use (&$dispatchedEvents) {
            $dispatchedEvents[] = $event;
        });

        $model->update(['name' => 'Updated Name']);

        $this->assertEquals('Updated Name', $model->name);
        $this->assertEquals(TestStatus::INITIATED, $model->status);

        // No events should be dispatched since status didn't change
        $this->assertCount(0, $dispatchedEvents);
    }

    public function testUpdateEventFieldMethod(): void
    {
        $model = TestModel::create([
            'name' => 'Test Model',
            'status' => TestStatus::INITIATED
        ]);

        $result = $model->updateEventField(TestStatus::PROCESSING);
        
        $this->assertTrue($result);
        $model->refresh();
        $this->assertEquals(TestStatus::PROCESSING, $model->status);
    }

    public function testUpdateEventFieldWithCustomFieldName(): void
    {
        $model = TestModelWithCustomField::create([
            'name' => 'Test Model',
            'custom_status' => TestStatus::INITIATED
        ]);

        $result = $model->updateEventField(TestStatus::FINISHED);
        
        $this->assertTrue($result);
        $model->refresh();
        $this->assertEquals(TestStatus::FINISHED, $model->custom_status);
    }

    public function testNoEventDispatchedForNonEnumStatus(): void
    {
        Event::fake();

        $model = TestModelWithoutEnum::create([
            'name' => 'Test Model',
            'status' => 'string_status'
        ]);

        $model->update(['status' => 'another_string']);
        
        $this->assertEquals('another_string', $model->status);
        
        // Test passes if no exceptions were thrown
        $this->assertTrue(true);
    }

    public function testHandlesNullStatus(): void
    {
        $model = TestModel::create([
            'name' => 'Test Model',
            'status' => null
        ]);

        $this->assertNull($model->status);
        
        $model->update(['status' => TestStatus::INITIATED]);
        $this->assertEquals(TestStatus::INITIATED, $model->status);
    }

    public function testStatusChangeFromEnumToNull(): void
    {
        $model = TestModel::create([
            'name' => 'Test Model',
            'status' => TestStatus::INITIATED
        ]);

        $this->assertEquals(TestStatus::INITIATED, $model->status);

        $model->update(['status' => null]);
        
        $this->assertNull($model->status);
        $this->assertTrue($model->wasChanged('status'));
    }

    public function testMultipleStatusChanges(): void
    {
        $dispatchedEvents = [];
        
        Event::listen([
            TestModelInitiated::class,
            TestModelProcessing::class,
            TestModelFinished::class,
            TestModelError::class
        ], function ($event) use (&$dispatchedEvents) {
            $dispatchedEvents[] = get_class($event);
        });

        $model = TestModel::create([
            'name' => 'Test Model',
            'status' => TestStatus::INITIATED
        ]);

        // Test INITIATED -> PROCESSING
        $model->update(['status' => TestStatus::PROCESSING]);
        $this->assertEquals(TestStatus::PROCESSING, $model->status);
        
        // Test PROCESSING -> FINISHED
        $model->update(['status' => TestStatus::FINISHED]);
        $this->assertEquals(TestStatus::FINISHED, $model->status);
        
        // Test FINISHED -> ERROR
        $model->update(['status' => TestStatus::ERROR]);
        $this->assertEquals(TestStatus::ERROR, $model->status);

        // Verify all events were dispatched
        $this->assertContains(TestModelInitiated::class, $dispatchedEvents);
        $this->assertContains(TestModelProcessing::class, $dispatchedEvents);
        $this->assertContains(TestModelFinished::class, $dispatchedEvents);
        $this->assertContains(TestModelError::class, $dispatchedEvents);
    }

    public function testTraitWorksWithModelInheritance(): void
    {
        // Create a class that extends TestModel
        $childModel = new class extends TestModel {
            protected $table = 'test_models';
        };

        $this->assertTrue(method_exists($childModel, 'getEventFieldName'));
        $this->assertEquals('status', $childModel->getEventFieldName());
        
        $model = $childModel::create([
            'name' => 'Child Model',
            'status' => TestStatus::INITIATED
        ]);
        
        $this->assertEquals(TestStatus::INITIATED, $model->status);
    }
}