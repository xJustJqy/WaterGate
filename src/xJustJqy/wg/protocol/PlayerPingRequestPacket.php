<?php
/*
 * Copyright 2022 Alemiz
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

class PlayerPingRequestPacket extends WaterGatePacket
{

    /** @var string */
    private string $playerName;

    public function encodePayload(): void
    {
        PacketHelper::writeString($this, $this->playerName);
    }

    public function decodePayload(): void
    {
        $this->playerName = PacketHelper::readString($this);
    }

    /**
     * @param WaterGatePacketHandler $handler
     * @return bool
     */
    public function handle(WaterGatePacketHandler $handler): bool
    {
        return $handler->handlePlayerPingRequest($this);
    }

    public function getPacketId(): int
    {
        return WaterGatePackets::PLAYER_PING_REQUEST_PACKET;
    }

    /**
     * @return bool
     */
    public function sendsResponse(): bool
    {
        return true;
    }

    /**
     * @param string $playerName
     */
    public function setPlayerName(string $playerName): void
    {
        $this->playerName = $playerName;
    }

    /**
     * @return string
     */
    public function getPlayerName(): string
    {
        return $this->playerName;
    }
}
