<?php

namespace Kdabrow\CustomEvents;

use Illuminate\Support\Str;

trait CustomEventsTrait
{
    public abstract function getEventFieldName(): string;

    public static function bootCustomEventsTrait()
    {
        static::created(function(self $item){
            $item->dispatchCustomEvent($item);
        });

        static::updated(function(self $item){
            $item->dispatchCustomEvent($item);
        });
    }

    private function dispatchCustomEvent(self $item): void
    {
        $key = $item->getEventFieldName();

        if (! $item->getAttribute($key) instanceof \BackedEnum) {
            return;
        }

        if ($item->wasChanged($key) || is_null($item->getOriginal($key))) {
            if (!is_null($eventName = $item->getEventName($item, $item->getAttribute($key)))) {
                $eventName::dispatch($item, $item->getAttribute($key), $item->getOriginal($key));
            }
        }
    }

    private function getEventName(self $item, \BackedEnum $backedEnum): ?string
    {
        $eventName = '\App\Events\\' . class_basename($item) . Str::of($backedEnum->name)->lower()->camel()->ucfirst();

        if (class_exists($eventName)) {
            return $eventName;
        }

        return null;
    }

    public function updateEventField(\BackedEnum $enum): bool
    {
        return $this->update([$this->getEventFieldName() => $enum->value]);
    }
}