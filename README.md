# Custom Events for Laravel Eloquent

Automatically dispatch Laravel events when your model's status field changes. Perfect for tracking workflow states, order processing, and any multi-step business processes.

## Installation

```bash
composer require kdabrow/custom-events
```

## Features

- 🚀 **Automatic Event Dispatching** - Events are fired when status changes
- 🎯 **BackedEnum Support** - Works only with PHP 8.1+ BackedEnums for type safety
- 🔧 **Configurable Field Names** - Customize which field triggers events
- 📝 **Convention-Based Naming** - Automatic event class name generation
- 🛡️ **Safe by Default** - Only dispatches events when classes exist
- ✅ **Laravel 10/11/12 Compatible** - Works with modern Laravel versions

## Quick Start

### 1. Create Your Status Enum

```php
<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
}
```

### 2. Add Trait to Your Model

```php
<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Kdabrow\CustomEvents\CustomEventsTrait;

class Order extends Model
{
    use CustomEventsTrait;

    protected $fillable = ['status', 'total', 'customer_email'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
        ];
    }

    public function getEventFieldName(): string
    {
        return 'status';
    }
}
```

### 3. Create Event Classes

The trait automatically looks for events following this naming pattern:
`\App\Events\{ModelName}{StatusName}`

```php
<?php

namespace App\Events;

use App\Models\Order;
use App\Enums\OrderStatus;
use Illuminate\Foundation\Events\Dispatchable;

class OrderProcessing
{
    use Dispatchable;

    public function __construct(
        public Order $order,
        public OrderStatus $newStatus,
        public ?OrderStatus $oldStatus
    ) {}
}
```

```php
<?php

namespace App\Events;

use App\Models\Order;
use App\Enums\OrderStatus;
use Illuminate\Foundation\Events\Dispatchable;

class OrderShipped
{
    use Dispatchable;

    public function __construct(
        public Order $order,
        public OrderStatus $newStatus,
        public ?OrderStatus $oldStatus
    ) {}
}
```

### 4. Use Your Model

```php
// Create an order - dispatches OrderPending event
$order = Order::create([
    'status' => OrderStatus::PENDING,
    'total' => 99.99,
    'customer_email' => 'customer@example.com'
]);

// Update status - dispatches OrderProcessing event
$order->update(['status' => OrderStatus::PROCESSING]);

// Use the helper method - dispatches OrderShipped event
$order->updateEventField(OrderStatus::SHIPPED);
```

## Advanced Usage

### Custom Status Field Name

If your status field has a different name:

```php
public function getEventFieldName(): string
{
    return 'workflow_state'; // Instead of 'status'
}
```

### Event Registration

Register your event listeners in `EventServiceProvider`:

```php
<?php

namespace App\Providers;

use App\Events\OrderProcessing;
use App\Events\OrderShipped;
use App\Listeners\HandleOrderProcessing;
use App\Listeners\HandleOrderShipped;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        OrderProcessing::class => [
            HandleOrderProcessing::class,
        ],
        OrderShipped::class => [
            HandleOrderShipped::class,
        ],
    ];
}
```

### Testing Events

```php
<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Enums\OrderStatus;
use App\Events\OrderProcessing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_processing_event_is_dispatched()
    {
        Event::fake();

        $order = Order::create([
            'status' => OrderStatus::PENDING,
            'total' => 99.99,
            'customer_email' => 'test@example.com'
        ]);

        $order->update(['status' => OrderStatus::PROCESSING]);

        Event::assertDispatched(OrderProcessing::class, function ($event) use ($order) {
            return $event->order->id === $order->id
                && $event->newStatus === OrderStatus::PROCESSING
                && $event->oldStatus === OrderStatus::PENDING;
        });
    }
}
```

## How It Works

1. **Trait Bootstrapping**: When your model is first used, the trait registers listeners for `created` and `updated` model events.

2. **Status Change Detection**: On each model event, the trait checks if the status field has changed and contains a `BackedEnum`.

3. **Event Name Generation**: Converts enum values to PascalCase and combines with model name:
   - `OrderStatus::PROCESSING` → `OrderProcessing`
   - `JobApplication` + `UNDER_REVIEW` → `JobApplicationUnderReview`

4. **Conditional Dispatching**: Only dispatches events if the corresponding event class exists.

## Event Naming Convention

Events follow this pattern: `\App\Events\{ModelName}{StatusName}`

| Model | Status Enum | Event Class |
|-------|-------------|-------------|
| `Order` | `PENDING` | `App\Events\OrderPending` |
| `Order` | `PROCESSING` | `App\Events\OrderProcessing` |
| `JobApplication` | `UNDER_REVIEW` | `App\Events\JobApplicationUnderReview` |
| `Article` | `IN_REVIEW` | `App\Events\ArticleInReview` |

## Requirements

- PHP 8.2+
- Laravel 10.0+
- Status field must use `BackedEnum`

## Testing

```bash
composer test
```

## License

MIT License. See LICENSE file for details.