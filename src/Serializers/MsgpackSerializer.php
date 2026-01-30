<?php

namespace Kangangga\Json\Serializers;

use MessagePack\Packer;
use MessagePack\MessagePack;
use MessagePack\BufferUnpacker;
use Illuminate\Database\Query\Expression;
use MessagePack\TypeTransformer\CallbackTransformer;

class MsgpackSerializer implements SerializerInterface
{

    /** @var Packer */
    protected $packer;

    /** @var BufferUnpacker */
    protected $unpacker;

    public function __construct()
    {
        if (!class_exists(MessagePack::class)) {
            throw new \RuntimeException(
                'MessagePack is not installed. Run: composer require rybakit/msgpack'
            );
        }

        $this->packer = new Packer();
        $this->unpacker = new BufferUnpacker();
    }

    public function serialize(array $data): string
    {
        // Recursive check to convert Expressions to string
        $data = $this->prepareData($data);
        return $this->packer->pack($data);
    }

    protected function prepareData($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->prepareData($value);
            }
        } elseif ($data instanceof Expression) {
            $reflection = new \ReflectionClass($data);
            $property = $reflection->getProperty('value');
            return (string) ($property->getValue($data) ?? '');
        } elseif (is_object($data) && method_exists($data, '__toString')) {
            return (string) $data;
        }

        return $data;
    }

    public function unserialize(string $content): array
    {
        if (empty($content)) {
            return [];
        }

        try {
            $this->unpacker->reset($content);
            $data = $this->unpacker->unpack();
            return is_array($data) ? $data : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getExtension(): string
    {
        return 'msgpack';
    }
}
