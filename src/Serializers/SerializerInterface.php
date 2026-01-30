<?php

namespace Kangangga\Json\Serializers;

interface SerializerInterface
{
    /**
     * Serialize data to string.
     */
    public function serialize(array $data): string;

    /**
     * Unserialize string to data.
     */
    public function unserialize(string $content): array;

    /**
     * Get file extension for this serializer.
     */
    public function getExtension(): string;
}
