<?php

namespace App\Concerns;

use Illuminate\Support\Str;

/**
 * Fills the "slug" column from "name" when the model is created, adding a
 * numeric suffix (-2, -3...) when the slug is taken. The slug never changes
 * afterwards, so links keep working when the name changes.
 */
trait HasUniqueSlug
{
    protected static function bootHasUniqueSlug(): void
    {
        static::creating(function (self $model): void {
            if (blank($model->getAttribute('slug'))) {
                $model->setAttribute('slug', $model->uniqueSlug());
            }
        });
    }

    protected function uniqueSlug(): string
    {
        $base = Str::slug($this->getAttribute('name'));
        $slug = $base;

        for ($suffix = 2; static::query()->where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
