<?php

namespace Kangangga\Json\Serializers;

class JsonGzipSerializer implements SerializerInterface
{
    protected int $compressionLevel;

    public function __construct(int $compressionLevel = 6)
    {
        $this->compressionLevel = $compressionLevel;
    }

    public function serialize(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        return gzencode($json, $this->compressionLevel);
    }

    public function unserialize(string $content): array
    {
        $json = gzdecode($content);

        if ($json === false) {
            return [];
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    public function getExtension(): string
    {
        return 'json.gz';
    }
}
