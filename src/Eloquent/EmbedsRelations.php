<?php

namespace Kangangga\Json\Eloquent;

trait EmbedsRelations
{
    /**
     * Define an embedded one-to-one relationship.
     *
     * @param  string  $related
     * @param  string|null  $localKey
     * @param  string|null  $foreignKey
     * @param  string|null  $relation
     * @return \Illuminate\Database\Eloquent\Relations\EmbedsOne
     */
    public function embedsOne($related, $localKey = null, $foreignKey = null, $relation = null)
    {
        // Determine the relation name
        if (is_null($relation)) {
            $relation = $this->guessBelongsToRelation();
        }

        // Create a new instance of the related model
        $instance = $this->newRelatedInstance($related);

        // Set the local key
        $localKey = $localKey ?: $relation;

        // Set the foreign key
        $foreignKey = $foreignKey ?: 'parent_id';

        return $this->newEmbedsOne($instance->newQuery(), $this, $localKey, $foreignKey, $relation);
    }

    /**
     * Define an embedded one-to-many relationship.
     *
     * @param  string  $related
     * @param  string|null  $localKey
     * @param  string|null  $foreignKey
     * @param  string|null  $relation
     * @return \Illuminate\Database\Eloquent\Relations\EmbedsMany
     */
    public function embedsMany($related, $localKey = null, $foreignKey = null, $relation = null)
    {
        // Determine the relation name
        if (is_null($relation)) {
            $relation = $this->guessBelongsToRelation();
        }

        // Create a new instance of the related model
        $instance = $this->newRelatedInstance($related);

        // Set the local key
        $localKey = $localKey ?: $relation;

        // Set the foreign key
        $foreignKey = $foreignKey ?: 'parent_id';

        return $this->newEmbedsMany($instance->newQuery(), $this, $localKey, $foreignKey, $relation);
    }

    /**
     * Get an attribute from the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        if (!$key) {
            return;
        }

        // Check if the attribute is an embedded relation
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        // Get the attribute value
        $value = $this->getAttributeFromArray($key);

        // Check if this is an embedded document
        if (is_array($value) && !empty($value)) {
            // Try to determine if this is an embedded relation
            $method = 'embeds' . \Illuminate\Support\Str::studly($key);

            if (method_exists($this, $method)) {
                return $this->getRelationValue($key);
            }
        }

        return parent::getAttribute($key);
    }

    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return $this
     */
    public function setAttribute($key, $value)
    {
        // Check if this is an embedded relation
        if (method_exists($this, 'embeds' . \Illuminate\Support\Str::studly($key))) {
            $this->relations[$key] = $value;

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Instantiate a new EmbedsOne relationship.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  string  $localKey
     * @param  string  $foreignKey
     * @param  string  $relation
     * @return mixed
     */
    protected function newEmbedsOne($query, $parent, $localKey, $foreignKey, $relation)
    {
        // For now, return a simple relation
        // You can extend this to create a custom EmbedsOne relation class
        return new \Illuminate\Database\Eloquent\Relations\HasOne($query, $parent, $foreignKey, $localKey);
    }

    /**
     * Instantiate a new EmbedsMany relationship.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  string  $localKey
     * @param  string  $foreignKey
     * @param  string  $relation
     * @return mixed
     */
    protected function newEmbedsMany($query, $parent, $localKey, $foreignKey, $relation)
    {
        // For now, return a simple relation
        // You can extend this to create a custom EmbedsMany relation class
        return new \Illuminate\Database\Eloquent\Relations\HasMany($query, $parent, $foreignKey, $localKey);
    }
}
