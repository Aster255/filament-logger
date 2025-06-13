<?php

namespace Aster255\FilamentLogger\Loggers;

use Filament\Facades\Filament;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Activitylog\ActivityLogger;
use Spatie\Activitylog\ActivityLogStatus;

abstract class AbstractModelLogger
{
    protected abstract function getLogName(): string;

    protected function getUserName(?Authenticatable $user): string
    {
        if (blank($user) || $user instanceof GenericUser) {
            return 'Anonymous';
        }

        return Filament::getUserName($user);
    }

    protected function getModelName(Model $model)
    {
        return Str::of(class_basename($model))->headline();
    }


    protected function activityLogger(string $logName = null): ActivityLogger
    {
        $defaultLogName = $this->getLogName();

        $logStatus = app(ActivityLogStatus::class);

        return app(ActivityLogger::class)
            ->useLog($logName ?? $defaultLogName)
            ->setLogStatus($logStatus);
    }

    protected function getLoggableAttributes(Model $model, mixed $values = []): array
    {
        if (!is_array($values)) {
            return [];
        }

        if (count($model->getVisible()) > 0) {
            $values = array_intersect_key($values, array_flip($model->getVisible()));
        }

        if (count($model->getHidden()) > 0) {
            $values = array_diff_key($values, array_flip($model->getHidden()));
        }

        return $values;
    }

    protected function log(Model $model, string $event, ?string $description = null, mixed $attributes = null)
    {
        if (is_null($description)) {
            $description = $this->getModelName($model) . ' ' . $event;
        }

        if (auth()->check()) {
            $description .= ' by ' . $this->getUserName(auth()->user());
        }

        $this->activityLogger()
            ->event($event)
            ->performedOn($model)
            ->withProperties($this->getLoggableAttributes($model, $attributes))
            ->log($description);
    }

    public function created(Model $model)
    {
        $this->log($model, 'Created', attributes: $model->getAttributes());
    }

    public function updated(Model $model)
    {
        $changes = $model->getChanges();

        // Ignore the changes to remember_token and updated_at
        if (count($changes) === 1 && (array_key_exists('remember_token', $changes) || array_key_exists('updated_at', $changes))) {
            return;
        }

        // Prepare attributes for log
        $attributes = collect($model->getOriginal())
            ->only(array_keys($changes))
            ->reject(fn($value, $key) => $key === 'updated_at');

        $this->log(
            $model,
            'Updated',
            attributes: [
                'attributes' => collect($changes)->only($attributes->keys())->all(),
                'old' => $attributes->all(),
            ]
        );
    }

    public function deleted(Model $model)
    {
        $this->log($model, 'Deleted');
    }
}
