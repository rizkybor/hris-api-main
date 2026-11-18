<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use InvalidArgumentException;

/**
 * Minimal property list reader for Apple plists: the binary format
 * (bplist00, what Xcode writes into an app's Info.plist) and the XML
 * format (provisioning profiles). Dictionaries become associative arrays,
 * arrays lists, dates ISO 8601 strings (UTC), data raw byte strings.
 * Read-only; enough to inspect an .ipa, not a general plist library.
 */
class Plist
{
    public static function parse(string $data): mixed
    {
        if (str_starts_with($data, 'bplist00')) {
            return (new self)->parseBinary($data);
        }
        if (str_contains($data, '<plist')) {
            return self::parseXml($data);
        }

        throw new InvalidArgumentException('Not a property list.');
    }

    /**
     * The XML plist inside a CMS-signed envelope (embedded.mobileprovision):
     * the text from "<?xml" to "</plist>".
     */
    public static function parseSignedXml(string $data): mixed
    {
        $start = strpos($data, '<?xml');
        $end = strpos($data, '</plist>');
        if ($start === false || $end === false || $end < $start) {
            throw new InvalidArgumentException('No property list in the file.');
        }

        return self::parseXml(substr($data, $start, $end - $start + strlen('</plist>')));
    }

    public static function parseXml(string $xml): mixed
    {
        $document = new DOMDocument;
        // No network, no entity expansion.
        if (! @$document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
            throw new InvalidArgumentException('Unreadable XML property list.');
        }
        $root = $document->documentElement;
        if (! $root || $root->nodeName !== 'plist') {
            throw new InvalidArgumentException('Not a property list.');
        }
        foreach ($root->childNodes as $node) {
            if ($node instanceof DOMElement) {
                return self::xmlValue($node);
            }
        }

        return null;
    }

    private static function xmlValue(DOMElement $node): mixed
    {
        switch ($node->nodeName) {
            case 'dict':
                $result = [];
                $key = null;
                foreach ($node->childNodes as $child) {
                    if (! $child instanceof DOMElement) {
                        continue;
                    }
                    if ($child->nodeName === 'key') {
                        $key = $child->textContent;
                    } elseif ($key !== null) {
                        $result[$key] = self::xmlValue($child);
                        $key = null;
                    }
                }

                return $result;
            case 'array':
                $result = [];
                foreach ($node->childNodes as $child) {
                    if ($child instanceof DOMElement) {
                        $result[] = self::xmlValue($child);
                    }
                }

                return $result;
            case 'string':
                return $node->textContent;
            case 'integer':
                return (int) trim($node->textContent);
            case 'real':
                return (float) trim($node->textContent);
            case 'true':
                return true;
            case 'false':
                return false;
            case 'date':
                return gmdate('Y-m-d\TH:i:s\Z', strtotime(trim($node->textContent)) ?: 0);
            case 'data':
                return base64_decode(preg_replace('/\s+/', '', $node->textContent) ?? '', true) ?: '';
            default:
                return null;
        }
    }

    // ------------------------------------------------------------------
    // Binary format (bplist00)
    // ------------------------------------------------------------------

    private string $data;

    private int $refSize;

    /** @var int[] */
    private array $offsets = [];

    private int $depth = 0;

    private function parseBinary(string $data): mixed
    {
        $length = strlen($data);
        if ($length < 40) {
            throw new InvalidArgumentException('Truncated binary property list.');
        }
        $this->data = $data;
        $trailer = substr($data, -32);
        $offsetSize = ord($trailer[6]);
        $this->refSize = ord($trailer[7]);
        $count = $this->uint(substr($trailer, 8, 8));
        $top = $this->uint(substr($trailer, 16, 8));
        $tableOffset = $this->uint(substr($trailer, 24, 8));

        if ($offsetSize < 1 || $offsetSize > 8 || $this->refSize < 1 || $this->refSize > 8
            || $count < 1 || $top >= $count || $tableOffset + $count * $offsetSize > $length - 32) {
            throw new InvalidArgumentException('Corrupt binary property list.');
        }
        for ($i = 0; $i < $count; $i++) {
            $this->offsets[] = $this->uint(substr($data, $tableOffset + $i * $offsetSize, $offsetSize));
        }

        return $this->object($top);
    }

    private function object(int $ref): mixed
    {
        if (! isset($this->offsets[$ref]) || ++$this->depth > 64) {
            throw new InvalidArgumentException('Corrupt binary property list.');
        }
        try {
            return $this->readObject($this->offsets[$ref]);
        } finally {
            $this->depth--;
        }
    }

    private function readObject(int $offset): mixed
    {
        $marker = ord($this->data[$offset] ?? "\0");
        $type = $marker >> 4;
        $info = $marker & 0x0F;

        switch ($type) {
            case 0x0:
                return match ($info) {
                    0x8 => false,
                    0x9 => true,
                    default => null,
                };
            case 0x1: // integer, 2^info bytes, big endian
                $bytes = substr($this->data, $offset + 1, 1 << $info);

                return $info >= 3 ? $this->int64(substr($bytes, -8)) : $this->uint($bytes);
            case 0x2: // real
                $bytes = substr($this->data, $offset + 1, 1 << $info);

                return $info === 2 ? unpack('G', $bytes)[1] : unpack('E', $bytes)[1];
            case 0x3: // date: seconds since 2001-01-01 UTC, float64
                $seconds = unpack('E', substr($this->data, $offset + 1, 8))[1];

                return gmdate('Y-m-d\TH:i:s\Z', (int) round(978307200 + $seconds));
            case 0x4: // data
                [$length, $start] = $this->length($info, $offset);

                return substr($this->data, $start, $length);
            case 0x5: // ASCII string
                [$length, $start] = $this->length($info, $offset);

                return substr($this->data, $start, $length);
            case 0x6: // UTF-16BE string, length in characters
                [$length, $start] = $this->length($info, $offset);

                return mb_convert_encoding(substr($this->data, $start, $length * 2), 'UTF-8', 'UTF-16BE');
            case 0x8: // UID
                return $this->uint(substr($this->data, $offset + 1, $info + 1));
            case 0xA: // array
                [$length, $start] = $this->length($info, $offset);
                $result = [];
                for ($i = 0; $i < $length; $i++) {
                    $result[] = $this->object($this->ref($start + $i * $this->refSize));
                }

                return $result;
            case 0xD: // dictionary: keys refs, then value refs
                [$length, $start] = $this->length($info, $offset);
                $result = [];
                for ($i = 0; $i < $length; $i++) {
                    $key = $this->object($this->ref($start + $i * $this->refSize));
                    $value = $this->object($this->ref($start + ($length + $i) * $this->refSize));
                    $result[(string) $key] = $value;
                }

                return $result;
            default:
                throw new InvalidArgumentException('Unsupported binary property list object.');
        }
    }

    /**
     * Object length: the low nibble, or (0xF) an integer object after the marker.
     *
     * @return array{int, int} length and the offset of the content
     */
    private function length(int $info, int $offset): array
    {
        if ($info !== 0x0F) {
            return [$info, $offset + 1];
        }
        $intMarker = ord($this->data[$offset + 1] ?? "\0");
        if (($intMarker >> 4) !== 0x1) {
            throw new InvalidArgumentException('Corrupt binary property list.');
        }
        $size = 1 << ($intMarker & 0x0F);

        return [$this->uint(substr($this->data, $offset + 2, $size)), $offset + 2 + $size];
    }

    private function ref(int $position): int
    {
        return $this->uint(substr($this->data, $position, $this->refSize));
    }

    private function uint(string $bytes): int
    {
        $value = 0;
        foreach (str_split($bytes) as $byte) {
            $value = ($value << 8) | ord($byte);
        }

        return $value;
    }

    private function int64(string $bytes): int
    {
        return unpack('J', str_pad($bytes, 8, "\0", STR_PAD_LEFT))[1];
    }
}
