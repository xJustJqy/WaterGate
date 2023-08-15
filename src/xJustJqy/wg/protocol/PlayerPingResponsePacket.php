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

class PlayerPingResponsePacket extends WaterGatePacket
{

    /** @var string */
    private string $playerName;
    /** @var int */
    private int $upstreamPing;
    /** @var int */
    private int $downstreamPing;

    public function encodePayload(): void
    {
        PacketHelper::writeString($this, $this->playerName);
        PacketHelper::writeLong($this, $this->upstreamPing);
        PacketHelper::writeLong($this, $this->downstreamPing);
    }

    public function decodePayload(): void
    {
        $this->playerName = PacketHelper::readString($this);
        $this->upstreamPing = PacketHelper::readLong($this);
        $this->downstreamPing = PacketHelper::readLong($this);
    }

    /**
     * @param WaterGatePacketHandler $handler
     * @return bool
     */
    public function handle(WaterGatePacketHandler $handler): bool
    {
        return $handler->handlePlayerPingResponse($this);
    }

    public function getPacketId(): int
    {
        return WaterGatePackets::PLAYER_PING_RESPONSE_PACKET;
    }

    /**
     * @return bool
     */
    public function isResponse(): bool
    {
        return true;
    }

    /**
     * @param string $playerName
     * @return void
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


    /**
     * @param int $upstreamPing
     * @return void
     */
    public function setUpstreamPing(int $upstreamPing): void
    {
        $this->upstreamPing = $upstreamPing;
    }

    /**
     * @return int
     */
    public function getUpstreamPing(): int
    {
        return $this->upstreamPing;
    }

    /**
     * @param int $downstreamPing
     * @return void
     */
    public function setDownstreamPing(int $downstreamPing): void
    {
        $this->downstreamPing = $downstreamPing;
    }

    /**
     * @return int
     */
    public function getDownstreamPing(): int
    {
        return $this->downstreamPing;
    }
}
