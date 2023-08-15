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

namespace xJustJqy\wg\codec;

use xJustJqy\wg\protocol\DisconnectPacket;
use xJustJqy\wg\protocol\ForwardPacket;
use xJustJqy\wg\protocol\HandshakePacket;
use xJustJqy\wg\protocol\PingPacket;
use xJustJqy\wg\protocol\PlayerPingRequestPacket;
use xJustJqy\wg\protocol\PlayerPingResponsePacket;
use xJustJqy\wg\protocol\PongPacket;
use xJustJqy\wg\protocol\ReconnectPacket;
use xJustJqy\wg\protocol\ServerHandshakePacket;
use xJustJqy\wg\protocol\ServerInfoRequestPacket;
use xJustJqy\wg\protocol\ServerInfoResponsePacket;
use xJustJqy\wg\protocol\ServerTransferPacket;
use xJustJqy\wg\protocol\WaterGatePacket;
use xJustJqy\wg\protocol\UnknownPacket;
use pocketmine\utils\Binary;
use function strlen;
use function substr;

class ProtocolCodec
{

    public const WaterGate_MAGIC = 0xa20;

    /** @var WaterGatePacket[] */
    private array $packetPool = [];

    /**
     * ProtocolCodec constructor.
     */
    public function __construct()
    {
        $this->registerPacket(WaterGatePackets::HANDSHAKE_PACKET, new HandshakePacket());
        $this->registerPacket(WaterGatePackets::SERVER_HANDSHAKE_PACKET, new ServerHandshakePacket());
        $this->registerPacket(WaterGatePackets::DISCONNECT_PACKET, new DisconnectPacket());
        $this->registerPacket(WaterGatePackets::PING_PACKET, new PingPacket());
        $this->registerPacket(WaterGatePackets::PONG_PACKET, new PongPacket());
        $this->registerPacket(WaterGatePackets::RECONNECT_PACKET, new ReconnectPacket());
        $this->registerPacket(WaterGatePackets::FORWARD_PACKET, new ForwardPacket());
        $this->registerPacket(WaterGatePackets::SERVER_INFO_REQUEST_PACKET, new ServerInfoRequestPacket());
        $this->registerPacket(WaterGatePackets::SERVER_INFO_RESPONSE_PACKET, new ServerInfoResponsePacket());
        $this->registerPacket(WaterGatePackets::SERVER_TRANSFER_PACKET, new ServerTransferPacket());
        $this->registerPacket(WaterGatePackets::PLAYER_PING_REQUEST_PACKET, new PlayerPingRequestPacket());
        $this->registerPacket(WaterGatePackets::PLAYER_PING_RESPONSE_PACKET, new PlayerPingResponsePacket());
    }

    /**
     * @param int $packetId
     * @param WaterGatePacket $packet
     * @return bool
     */
    public function registerPacket(int $packetId, WaterGatePacket $packet): bool
    {
        if (isset($this->packetPool[$packetId])) {
            return false;
        }
        $this->packetPool[$packetId] = clone $packet;
        return true;
    }

    /**
     * @param int $packetId
     * @return WaterGatePacket|null
     */
    public function getPacketInstance(int $packetId): ?WaterGatePacket
    {
        if (isset($this->packetPool[$packetId])) {
            return clone $this->packetPool[$packetId];
        }
        return null;
    }

    /**
     * @param int $packetId
     * @return WaterGatePacket|null
     */
    public function unregisterPacket(int $packetId): ?WaterGatePacket
    {
        $oldPacket = $this->packetPool[$packetId] ?? null;
        unset($this->packetPool[$packetId]);
        return $oldPacket;
    }

    /**
     * @param WaterGatePacket $packet
     * @return string
     */
    public function tryEncode(WaterGatePacket $packet): string
    {
        $buffer = Binary::writeByte($packet->getPacketId());
        $supportsResponse = $packet->isResponse() || $packet->sendsResponse();
        $buffer .= Binary::writeBool($supportsResponse);
        if ($supportsResponse) {
            $buffer .= Binary::writeInt($packet->getResponseId());
        }

        $packet->rewind();
        $packet->encodePayload();
        $buffer .= $packet->getBuffer();

        $encoded = Binary::writeInt(strlen($buffer));
        $encoded .= $buffer;
        return $encoded;
    }

    /**
     * @param string $encoded
     * @return WaterGatePacket|null
     */
    public function tryDecode(string $encoded): ?WaterGatePacket
    {
        $packetId = Binary::readByte($encoded);
        $offset = 1;

        $packet = $this->getPacketInstance($packetId);
        if ($packet === null) {
            $packet = new UnknownPacket();
            $packet->setPacketId($packetId);
        }

        if (Binary::readBool($encoded[$offset++])) {
            $packet->setResponseId(Binary::readInt(substr($encoded, $offset, 4)));
            $offset += 4;
        }

        $packet->setBuffer(substr($encoded, $offset));
        $packet->decodePayload();
        return $packet;
    }
}
