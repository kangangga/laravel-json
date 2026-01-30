<?php

namespace Kangangga\Json\Serializers;

use Symfony\Component\Yaml\Yaml;

class YamlSerializer implements SerializerInterface
{
    public function serialize(array $data): string
    {
        // Dump level 4 for deeper nesting support
        return Yaml::dump($data, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }

    public function unserialize(string $content): array
    {
        $data = Yaml::parse($content);
        return is_array($data) ? $data : [];
    }

    public function getExtension(): string
    {
        return 'yaml';
    }
}
