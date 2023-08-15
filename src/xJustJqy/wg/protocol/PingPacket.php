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

namespace xJustJqy\wg\protocol;

use xJustJqy\wg\codec\WaterGatePacketHandler;
use xJustJqy\wg\codec\WaterGatePackets;
use xJustJqy\wg\protocol\types\PacketHelper;
use xJustJqy\wg\utils\LogLevel;

class PingPacket extends WaterGatePacket
{

    /** @var int */
    private int $pingTime;

    public function encodePayload(): void
    {
        PacketHelper::writeLong($this, $this->pingTime);
    }

    public function decodePayload(): void
    {
        $this->pingTime = PacketHelper::readLong($this);
    }

    /**
     * @param WaterGatePacketHandler $handler
     * @return bool
     */
    public function handle(WaterGatePacketHandler $handler): bool
    {
        return $handler->handlePing($this);
    }

    public function getPacketId(): int
    {
        return WaterGatePackets::PING_PACKET;
    }

    /**
     * @param int $pingTime
     */
    public function setPingTime(int $pingTime): void
    {
        $this->pingTime = $pingTime;
    }

    /**
     * @return int
     */
    public function getPingTime(): int
    {
        return $this->pingTime;
    }

    /**
     * @return int
     */
    public function getLogLevel(): int
    {
        return LogLevel::LEVEL_ALL;
    }
}
