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

class ServerInfoRequestPacket extends WaterGatePacket
{

    /** @var string */
    private string $serverName;
    /** @var bool */
    private bool $selfInfo;

    public function encodePayload(): void
    {
        PacketHelper::writeString($this, $this->serverName);
        PacketHelper::writeBoolean($this, $this->selfInfo);
    }

    public function decodePayload(): void
    {
        $this->serverName = PacketHelper::readString($this);
        $this->selfInfo = PacketHelper::readBoolean($this);
    }

    /**
     * @param WaterGatePacketHandler $handler
     * @return bool
     */
    public function handle(WaterGatePacketHandler $handler): bool
    {
        return $handler->handleServerInfoRequest($this);
    }

    public function getPacketId(): int
    {
        return WaterGatePackets::SERVER_INFO_REQUEST_PACKET;
    }

    /**
     * @return bool
     */
    public function sendsResponse(): bool
    {
        return true;
    }

    /**
     * @param string $serverName
     */
    public function setServerName(string $serverName): void
    {
        $this->serverName = $serverName;
    }

    /**
     * @return string
     */
    public function getServerName(): string
    {
        return $this->serverName;
    }

    /**
     * @param bool $selfInfo
     */
    public function setSelfInfo(bool $selfInfo): void
    {
        $this->selfInfo = $selfInfo;
    }

    /**
     * @return bool
     */
    public function isSelfInfo(): bool
    {
        return $this->selfInfo;
    }
}
