<?php

namespace Kangangga\Json\Eloquent;


/**
 * @mixin \Illuminate\Database\Eloquent\Model
 * @mixin \Illuminate\Database\Eloquent\Builder
 * @mixin \Illuminate\Database\Eloquent\Concerns\HasRelationships
 */
trait HybridRelations
{
    /**
     * Define a one-to-one relationship.
     *
     * @param  string  $related
     * @param  string|null  $foreignKey
     * @param  string|null  $localKey
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function hasOne($related, $foreignKey = null, $localKey = null)
    {
        // Check if this is a hybrid relation (JSON to SQL or vice versa)
        $instance = $this->newRelatedInstance($related);

        $localIsJson = $this->hasJsonConnection();
        $relatedIsJson = $instance->getConnection() instanceof \Kangangga\Json\Connection;

        if ($localIsJson && !$relatedIsJson) {
            // JSON to SQL relation
            $foreignKey = $foreignKey ?: $this->getForeignKey();
            $localKey = $localKey ?: $this->getKeyName();
        } elseif (!$localIsJson && $relatedIsJson) {
            // SQL to JSON relation
            $foreignKey = $foreignKey ?: $this->getForeignKey();
            $localKey = $localKey ?: $this->getKeyName();
        }

        return parent::hasOne($related, $foreignKey, $localKey);
    }

    /**
     * Define a one-to-many relationship.
     *
     * @param  string  $related
     * @param  string|null  $foreignKey
     * @param  string|null  $localKey
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function hasMany($related, $foreignKey = null, $localKey = null)
    {
        $instance = $this->newRelatedInstance($related);

        $localIsJson = $this->hasJsonConnection();
        $relatedIsJson = $instance->getConnection() instanceof \Kangangga\Json\Connection;

        if ($localIsJson && !$relatedIsJson) {
            $foreignKey = $foreignKey ?: $this->getForeignKey();
            $localKey = $localKey ?: $this->getKeyName();
        } elseif (!$localIsJson && $relatedIsJson) {
            $foreignKey = $foreignKey ?: $this->getForeignKey();
            $localKey = $localKey ?: $this->getKeyName();
        }

        return parent::hasMany($related, $foreignKey, $localKey);
    }


    /**
     * Define an inverse one-to-one or many relationship.
     *
     * @param  string  $related
     * @param  string|null  $foreignKey
     * @param  string|null  $ownerKey
     * @param  string|null  $relation
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function belongsTo($related, $foreignKey = null, $ownerKey = null, $relation = null)
    {
        if (is_null($relation)) {
            $relation = $this->guessBelongsToRelation();
        }

        /** @var \Illuminate\Database\Eloquent\Model $instance */
        $instance = $this->newRelatedInstance($related);

        if (config('json.hybrid_force_default_connection', false)) {
            if (!in_array(\Kangangga\Json\Eloquent\JsonModel::class, class_uses_recursive($instance))) {
                $instance->setConnection(config('database.default'));
            }
        }

        if (is_null($foreignKey)) {
            $foreignKey = \Illuminate\Support\Str::snake($relation) . '_' . $instance->getKeyName();
        }

        $ownerKey = $ownerKey ?: $instance->getKeyName();

        // return parent::belongsTo($related, $foreignKey, $ownerKey, $relation);

        return $this->newBelongsTo(
            $instance->newQuery(),
            $this,
            $foreignKey,
            $ownerKey,
            $relation
        );
    }

    protected function hasJsonConnection()
    {
        return in_array(\Kangangga\Json\Eloquent\JsonModel::class, class_uses_recursive($this));
    }
}
