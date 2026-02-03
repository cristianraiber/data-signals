<?php
/**
 * GeoLite2 MMDB Reader
 * Pure PHP implementation for reading MaxMind DB files
 * Based on the MaxMind DB spec: https://maxmind.github.io/MaxMind-DB/
 */

namespace DataSignals;

class GeoLite2_Reader {
    
    private $fileHandle;
    private $metadata;
    private $decoder;
    private $ipV4Start;
    
    private const DATA_SECTION_SEPARATOR_SIZE = 16;
    private const METADATA_START_MARKER = "\xAB\xCD\xEFMaxMind.com";
    
    public function __construct(string $database) {
        if (!is_readable($database)) {
            throw new \InvalidArgumentException("Database file not readable: {$database}");
        }
        
        $this->fileHandle = fopen($database, 'rb');
        if (!$this->fileHandle) {
            throw new \RuntimeException("Could not open database: {$database}");
        }
        
        $this->metadata = $this->readMetadata();
        $this->decoder = new GeoLite2_Decoder($this->fileHandle, $this->metadata['search_tree_size']);
        
        $this->ipV4Start = 0;
        if ($this->metadata['ip_version'] === 6) {
            // For IPv6 databases, we need to find the IPv4 start node
            $node = 0;
            for ($i = 0; $i < 96 && $node < $this->metadata['node_count']; $i++) {
                $node = $this->readNode($node, 0);
            }
            $this->ipV4Start = $node;
        }
    }
    
    public function __destruct() {
        if ($this->fileHandle) {
            fclose($this->fileHandle);
        }
    }
    
    public function metadata(): array {
        return $this->metadata;
    }
    
    public function country(string $ipAddress): ?array {
        $record = $this->get($ipAddress);
        return $record;
    }
    
    public function get(string $ipAddress): ?array {
        if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException("Invalid IP address: {$ipAddress}");
        }
        
        $isIPv4 = filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        $packed = inet_pton($ipAddress);
        
        if ($packed === false) {
            throw new \InvalidArgumentException("Could not parse IP: {$ipAddress}");
        }
        
        $bitCount = $isIPv4 ? 32 : 128;
        $node = $isIPv4 ? $this->ipV4Start : 0;
        
        for ($i = 0; $i < $bitCount && $node < $this->metadata['node_count']; $i++) {
            $bit = $this->getBit($packed, $i);
            $node = $this->readNode($node, $bit);
        }
        
        if ($node === $this->metadata['node_count']) {
            // No record found
            return null;
        }
        
        if ($node > $this->metadata['node_count']) {
            // Found a data record
            $pointer = $node - $this->metadata['node_count'] - self::DATA_SECTION_SEPARATOR_SIZE;
            return $this->decoder->decode($pointer)[0];
        }
        
        return null;
    }
    
    private function readNode(int $nodeNumber, int $index): int {
        $baseOffset = $nodeNumber * $this->metadata['record_size'] / 4;
        $recordSize = $this->metadata['record_size'];
        
        if ($recordSize === 24) {
            fseek($this->fileHandle, $baseOffset + $index * 3);
            $bytes = fread($this->fileHandle, 3);
            return unpack('N', "\x00" . $bytes)[1];
        } elseif ($recordSize === 28) {
            fseek($this->fileHandle, $baseOffset + 3);
            $middle = ord(fread($this->fileHandle, 1));
            
            if ($index === 0) {
                fseek($this->fileHandle, $baseOffset);
                $bytes = fread($this->fileHandle, 3);
                return (($middle & 0xF0) << 20) | unpack('N', "\x00" . $bytes)[1];
            } else {
                fseek($this->fileHandle, $baseOffset + 4);
                $bytes = fread($this->fileHandle, 3);
                return (($middle & 0x0F) << 24) | unpack('N', "\x00" . $bytes)[1];
            }
        } elseif ($recordSize === 32) {
            fseek($this->fileHandle, $baseOffset + $index * 4);
            $bytes = fread($this->fileHandle, 4);
            return unpack('N', $bytes)[1];
        }
        
        throw new \RuntimeException("Unsupported record size: {$recordSize}");
    }
    
    private function getBit(string $packed, int $position): int {
        $byte = ord($packed[(int) ($position / 8)]);
        $bit = 1 << (7 - ($position % 8));
        return ($byte & $bit) ? 1 : 0;
    }
    
    private function readMetadata(): array {
        $fileSize = fstat($this->fileHandle)['size'];
        $marker = self::METADATA_START_MARKER;
        $markerLen = strlen($marker);
        
        // Search for metadata marker from end of file
        $searchSize = min(128 * 1024, $fileSize); // Search last 128KB
        fseek($this->fileHandle, -$searchSize, SEEK_END);
        $buffer = fread($this->fileHandle, $searchSize);
        
        $pos = strrpos($buffer, $marker);
        if ($pos === false) {
            throw new \RuntimeException("Could not find metadata marker in database");
        }
        
        $metadataStart = $fileSize - $searchSize + $pos + $markerLen;
        fseek($this->fileHandle, $metadataStart);
        
        // Read metadata
        $decoder = new GeoLite2_Decoder($this->fileHandle, 0);
        $metadata = $decoder->decode(0)[0];
        
        // Calculate search tree size
        $metadata['search_tree_size'] = 
            ($metadata['record_size'] * 2 / 8) * $metadata['node_count'];
        
        return $metadata;
    }
}

/**
 * MMDB Data Decoder
 */
class GeoLite2_Decoder {
    
    private $fileHandle;
    private $pointerBase;
    
    private const TYPE_EXTENDED = 0;
    private const TYPE_POINTER = 1;
    private const TYPE_UTF8_STRING = 2;
    private const TYPE_DOUBLE = 3;
    private const TYPE_BYTES = 4;
    private const TYPE_UINT16 = 5;
    private const TYPE_UINT32 = 6;
    private const TYPE_MAP = 7;
    private const TYPE_INT32 = 8;
    private const TYPE_UINT64 = 9;
    private const TYPE_UINT128 = 10;
    private const TYPE_ARRAY = 11;
    private const TYPE_CONTAINER = 12;
    private const TYPE_END_MARKER = 13;
    private const TYPE_BOOLEAN = 14;
    private const TYPE_FLOAT = 15;
    
    public function __construct($fileHandle, int $pointerBase) {
        $this->fileHandle = $fileHandle;
        $this->pointerBase = $pointerBase;
    }
    
    public function decode(int $offset): array {
        fseek($this->fileHandle, $this->pointerBase + $offset + 16); // +16 for separator
        
        $ctrlByte = ord(fread($this->fileHandle, 1));
        $type = $ctrlByte >> 5;
        
        if ($type === self::TYPE_EXTENDED) {
            $nextByte = ord(fread($this->fileHandle, 1));
            $type = $nextByte + 7;
        }
        
        $size = $ctrlByte & 0x1F;
        if ($size >= 29) {
            $size = $this->readVariableSize($size);
        }
        
        return $this->decodeByType($type, $size);
    }
    
    private function readVariableSize(int $size): int {
        if ($size === 29) {
            return 29 + ord(fread($this->fileHandle, 1));
        } elseif ($size === 30) {
            return 285 + unpack('n', fread($this->fileHandle, 2))[1];
        } else {
            return 65821 + unpack('N', "\x00" . fread($this->fileHandle, 3))[1];
        }
    }
    
    private function decodeByType(int $type, int $size): array {
        $newOffset = ftell($this->fileHandle) - $this->pointerBase - 16;
        
        switch ($type) {
            case self::TYPE_POINTER:
                $pointer = $this->decodePointer($size);
                $pos = ftell($this->fileHandle);
                $result = $this->decode($pointer);
                fseek($this->fileHandle, $pos);
                return [$result[0], $newOffset];
                
            case self::TYPE_UTF8_STRING:
                $str = $size > 0 ? fread($this->fileHandle, $size) : '';
                return [$str, $newOffset + $size];
                
            case self::TYPE_DOUBLE:
                $bytes = fread($this->fileHandle, 8);
                return [unpack('E', $bytes)[1], $newOffset + 8];
                
            case self::TYPE_BYTES:
                $bytes = $size > 0 ? fread($this->fileHandle, $size) : '';
                return [$bytes, $newOffset + $size];
                
            case self::TYPE_UINT16:
            case self::TYPE_UINT32:
            case self::TYPE_UINT64:
            case self::TYPE_UINT128:
                return [$this->decodeUint($size), $newOffset + $size];
                
            case self::TYPE_INT32:
                $bytes = fread($this->fileHandle, $size);
                $value = unpack('N', str_pad($bytes, 4, "\x00", STR_PAD_LEFT))[1];
                if ($value >= 0x80000000) {
                    $value -= 0x100000000;
                }
                return [$value, $newOffset + $size];
                
            case self::TYPE_MAP:
                return $this->decodeMap($size);
                
            case self::TYPE_ARRAY:
                return $this->decodeArray($size);
                
            case self::TYPE_BOOLEAN:
                return [$size !== 0, $newOffset];
                
            case self::TYPE_FLOAT:
                $bytes = fread($this->fileHandle, 4);
                return [unpack('G', $bytes)[1], $newOffset + 4];
                
            default:
                return [null, $newOffset];
        }
    }
    
    private function decodePointer(int $size): int {
        $pointerSize = (($size >> 3) & 0x3) + 1;
        $packed = chr($size & 0x7) . fread($this->fileHandle, $pointerSize);
        
        switch ($pointerSize) {
            case 1:
                return unpack('n', $packed)[1];
            case 2:
                return unpack('N', "\x00" . $packed)[1] + 2048;
            case 3:
                return unpack('N', $packed)[1] + 526336;
            case 4:
                return unpack('N', substr($packed, 1))[1];
        }
        
        return 0;
    }
    
    private function decodeUint(int $size): int {
        if ($size === 0) {
            return 0;
        }
        
        $bytes = fread($this->fileHandle, $size);
        $padded = str_pad($bytes, 8, "\x00", STR_PAD_LEFT);
        return unpack('J', $padded)[1];
    }
    
    private function decodeMap(int $size): array {
        $map = [];
        $offset = ftell($this->fileHandle) - $this->pointerBase - 16;
        
        for ($i = 0; $i < $size; $i++) {
            [$key, $offset] = $this->decode($offset);
            [$value, $offset] = $this->decode($offset);
            $map[$key] = $value;
        }
        
        return [$map, $offset];
    }
    
    private function decodeArray(int $size): array {
        $array = [];
        $offset = ftell($this->fileHandle) - $this->pointerBase - 16;
        
        for ($i = 0; $i < $size; $i++) {
            [$value, $offset] = $this->decode($offset);
            $array[] = $value;
        }
        
        return [$array, $offset];
    }
}
