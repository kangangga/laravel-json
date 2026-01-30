<?php

namespace Kangangga\Json\Eloquent;

use Illuminate\Database\Eloquent\Model as BaseModel;



/**
 * @method static \Illuminate\Database\Eloquent\Builder<static> withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static> onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static> withoutTrashed()
 * @method static static restoreOrCreate(array<string, mixed> $attributes = [], array<string, mixed> $values = [])
 * @method static static createOrRestore(array<string, mixed> $attributes = [], array<string, mixed> $values = [])
 * @method static static truncate()
 * @method static static query()
 */
abstract class Model extends BaseModel
{
    use JsonModel;

    protected $connection = 'json';

    protected static array $jsonModelClasses = [];

    final public static function isJsonModel(string|object $class): bool
    {
        if (is_object($class)) {
            $class = $class::class;
        }

        if (array_key_exists($class, self::$jsonModelClasses)) {
            return self::$jsonModelClasses[$class];
        }

        // We know all child classes of this class are document models.
        if (is_subclass_of($class, self::class)) {
            return self::$jsonModelClasses[$class] = true;
        }

        // Document models must be subclasses of Laravel's base model class.
        if (! is_subclass_of($class, BaseModel::class)) {
            return self::$jsonModelClasses[$class] = false;
        }

        // Document models must use the DocumentModel trait.
        return self::$jsonModelClasses[$class] = array_key_exists(JsonModel::class, class_uses_recursive($class));
    }
}
