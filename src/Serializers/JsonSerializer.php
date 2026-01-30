<?php

namespace Kangangga\Json\Serializers;

class JsonSerializer implements SerializerInterface
{
    public function serialize(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function unserialize(string $content): array
    {
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    public function getExtension(): string
    {
        return 'json';
    }
}
