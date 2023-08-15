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
use xJustJqy\wg\protocol\types\HandshakeData;
use xJustJqy\wg\utils\LogLevel;

class HandshakePacket extends WaterGatePacket
{

    /** @var HandshakeData */
    private HandshakeData $handshakeData;

    public function encodePayload(): void
    {
        HandshakeData::encodeData($this, $this->handshakeData);
    }

    public function decodePayload(): void
    {
        $this->handshakeData = HandshakeData::decodeData($this);
    }

    /**
     * @param WaterGatePacketHandler $handler
     * @return bool
     */
    public function handle(WaterGatePacketHandler $handler): bool
    {
        return $handler->handleHandshake($this);
    }

    public function getPacketId(): int
    {
        return WaterGatePackets::HANDSHAKE_PACKET;
    }

    /**
     * @param HandshakeData $handshakeData
     */
    public function setHandshakeData(HandshakeData $handshakeData): void
    {
        $this->handshakeData = $handshakeData;
    }

    /**
     * @return HandshakeData
     */
    public function getHandshakeData(): HandshakeData
    {
        return $this->handshakeData;
    }

    /**
     * @return int
     */
    public function getLogLevel(): int
    {
        return LogLevel::LEVEL_ALL;
    }
}
