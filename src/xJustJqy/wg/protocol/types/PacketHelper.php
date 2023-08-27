<?php
/*
 * Copyright 2020 Alemiz
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 */

namespace xJustJqy\wg\protocol\types;

use xJustJqy\wg\protocol\WaterGatePacket;
use Closure;
use function count;
use function strlen;

class PacketHelper
{

    /**
     * @param WaterGatePacket $buf
     * @param string $array
     */
    public static function writeByteArray(WaterGatePacket $buf, string $array): void
    {
        $buf->putInt(strlen($array));
        $buf->put($array);
    }

    /**
     * @param WaterGatePacket $buf
     * @return string
     */
    public static function readByteArray(WaterGatePacket $buf): string
    {
        return $buf->getBuffer();
    }

    /**
     * @param WaterGatePacket $buf
     * @param int $int
     */
    public static function writeInt(WaterGatePacket $buf, int $int): void
    {
        $buf->putInt($int);
    }

    /**
     * @param WaterGatePacket $packet
     * @return int
     */
    public static function readInt(WaterGatePacket $packet): int
    {
        return $packet->getInt();
    }

    /**
     * @param WaterGatePacket $buf
     * @param int $int
     */
    public static function writeLong(WaterGatePacket $buf, int $int): void
    {
        $buf->putLong($int);
    }

    /**
     * @param WaterGatePacket $packet
     * @return int
     */
    public static function readLong(WaterGatePacket $packet): int
    {
        return $packet->getLong();
    }

    /**
     * @param WaterGatePacket $buf
     * @param string $string
     */
    public static function writeString(WaterGatePacket $buf, string $string): void
    {
        self::writeByteArray($buf, $string);
    }

    /**
     * @param WaterGatePacket $buf
     * @return string
     */
    public static function readString(WaterGatePacket $buf): string
    {
        return self::readByteArray($buf);
    }

    /**
     * @param WaterGatePacket $buf
     * @param bool $bool
     */
    public static function writeBoolean(WaterGatePacket $buf, bool $bool): void
    {
        $buf->putByte($bool ? 1 : 0);
    }

    /**
     * @param WaterGatePacket $buf
     * @return bool
     */
    public static function readBoolean(WaterGatePacket $buf): bool
    {
        return $buf->getByte() === 1;
    }

    /**
     * @param WaterGatePacket $buf
     * @param Closure $function
     * @return array
     */
    public static function readArray(WaterGatePacket $buf, Closure $function): array
    {
        $length = self::readInt($buf);
        $array = [];
        for ($i = 0; $i < $length; $i++) {
            $array[] = $function($buf);
        }
        return $array;
    }

    /**
     * @param WaterGatePacket $buf
     * @param array $array
     * @param Closure $consumer
     */
    public static function writeArray(WaterGatePacket $buf, array $array, Closure $consumer): void
    {
        self::writeInt($buf, count($array));
        foreach ($array as $value) {
            $consumer($buf, $value);
        }
    }
}
