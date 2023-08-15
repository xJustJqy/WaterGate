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

namespace xJustJqy\wg\client;

use xJustJqy\wg\codec\WaterGatePacketHandler;
use xJustJqy\wg\handler\HandshakePacketHandler;
use xJustJqy\wg\protocol\DisconnectPacket;
use xJustJqy\wg\protocol\HandshakePacket;
use xJustJqy\wg\protocol\PingPacket;
use xJustJqy\wg\protocol\PongPacket;
use xJustJqy\wg\protocol\WaterGatePacket;
use xJustJqy\wg\protocol\types\PingEntry;
use xJustJqy\wg\utils\PacketResponse;
use xJustJqy\wg\utils\WaterGateException;
use xJustJqy\wg\utils\WaterGateFuture;
use Exception;
use pocketmine\plugin\PluginLogger;
use function get_class;
use function microtime;

class ClientSession
{

    /** @var WaterGateClient */
    private WaterGateClient $client;
    /** @var WaterGateConnection */
    private WaterGateConnection $connection;

    /** @var int */
    private int $responseCounter = 0;
    /** @var PacketResponse[] */
    private array $pendingResponses = [];

    /** @var WaterGatePacketHandler|null */
    private ?WaterGatePacketHandler $packetHandler;

    /** @var PingEntry|null */
    private ?PingEntry $pingEntry = null;

    /** @var int */
    private int $logInputLevel = 0;
    /** @var int */
    private int $logOutputLevel = 0;

    /**
     * ClientSession constructor.
     * @param WaterGateClient $client
     * @param string $address
     * @param int $port
     */
    public function __construct(WaterGateClient $client, string $address, int $port)
    {
        $this->client = $client;
        $server = $client->getServer();
        $this->packetHandler = new HandshakePacketHandler($this);
        $this->connection = new WaterGateConnection($server->getLogger(), $address, $port, $client->getHandshakeData());
    }

    public function onConnect(): void
    {
        $packet = new HandshakePacket();
        $packet->setHandshakeData($this->client->getHandshakeData());
        $this->sendPacket($packet);

        $this->connection->setState(WaterGateConnection::STATE_AUTHENTICATING);
        $this->client->onSessionConnected();
    }

    public function onTick(): void
    {
        if (!$this->isConnected()) {
            return;
        }

        if ($this->connection->getState() === WaterGateConnection::STATE_CONNECTED) {
            $this->onConnect();
        }

        while (($payload = $this->connection->inputRead()) !== null && !empty($payload)) {
            $codec = $this->client->getProtocolCodec();
            try {
                $packet = $codec->tryDecode($payload);
                if ($packet !== null) {
                    $this->onPacket($packet);
                }
            } catch (Exception $e) {
                $this->getLogger()->error("§cCan not decode WaterGate packet!");
                $this->getLogger()->logException($e);
            }
        }


        $currentTime = microtime(true) * 1000;
        if ($this->pingEntry !== null && $currentTime >= $this->pingEntry->getTimeout()) {
            $this->pingEntry->getFuture()->completeExceptionally(new WaterGateException("Ping Timeout!"));
            $this->pingEntry = null;
        }
    }

    /**
     * @param WaterGatePacket $packet
     */
    private function onPacket(WaterGatePacket $packet): void
    {
        $handled = $this->packetHandler !== null && $packet->handle($this->packetHandler);

        if ($packet->isResponse() && isset($this->pendingResponses[$packet->getResponseId()])) {
            $response = $this->pendingResponses[$packet->getResponseId()];
            $response->complete($packet);
            unset($this->pendingResponses[$packet->getResponseId()]);
            $handled = true;
        }

        $customHandler = $this->client->getCustomHandler();
        if (!$handled && $customHandler !== null) {
            try {
                if ($packet->handle($customHandler)) {
                    $handled = true;
                }
            } catch (Exception $e) {
                $this->getLogger()->error("Error occurred in custom packet handler!");
                $this->getLogger()->logException($e);
            }
        }

        if (!$handled) {
            $this->getLogger()->debug("Unhandled packet " . get_class($packet));
        }

        if ($this->logInputLevel >= $packet->getLogLevel()) {
            $this->getLogger()->debug("Received " . get_class($packet));
        }
    }

    /**
     * @param WaterGatePacket $packet
     * @return PacketResponse|null
     */
    public function responsePacket(WaterGatePacket $packet): ?PacketResponse
    {
        if (!$packet->sendsResponse()) {
            return null;
        }

        $responseId = $this->responseCounter++;
        $packet->setResponseId($responseId);
        $this->sendPacket($packet);

        if (!isset($this->pendingResponses[$responseId])) {
            $this->pendingResponses[$responseId] = new PacketResponse();
        }
        return $this->pendingResponses[$responseId];
    }

    /**
     * @param WaterGatePacket $packet
     */
    public function sendPacket(WaterGatePacket $packet): void
    {
        if (!$this->isConnected()) {
            return;
        }

        $codec = $this->client->getProtocolCodec();
        try {
            $payload = $codec->tryEncode($packet);
            if (!empty($payload)) {
                $this->connection->writeBuffer($payload);
            }
        } catch (Exception $e) {
            $this->getLogger()->error("§cCan not encode WaterGate packet " . get_class($packet) . "!");
            $this->getLogger()->logException($e);
            return;
        }

        if ($this->logInputLevel >= $packet->getLogLevel()) {
            $this->getLogger()->debug("Sent " . get_class($packet));
        }
    }

    /**
     * @param int $timeout
     * @return WaterGateFuture
     * @noinspection PhpExpressionResultUnusedInspection
     */
    public function pingServer(int $timeout): WaterGateFuture
    {
        $this->pingEntry?->getFuture();

        $now = (int) microtime(true) * 1000;
        $entry = new PingEntry(new WaterGateFuture(), $now + $timeout);

        $packet = new PingPacket();
        $packet->setPingTime($now);
        $this->sendPacket($packet);
        return ($this->pingEntry = $entry)->getFuture();
    }

    /**
     * @param PongPacket $packet
     */
    public function onPongReceive(PongPacket $packet): void
    {
        if ($this->pingEntry === null) {
            return;
        }
        $packet->setPongTime((int) microtime(true) * 1000);
        $this->pingEntry->getFuture()->complete($packet);
        $this->pingEntry = null;
    }

    /**
     * @param string $reason
     */
    public function onDisconnect(string $reason): void
    {
        $this->getLogger()->info("§bWaterGate server has been disconnected! Reason: " . $reason);
        $this->client->onSessionDisconnected();
        $this->close();
    }

    /**
     * @param string $reason
     * @param bool $send
     */
    public function reconnect(string $reason, bool $send): void
    {
        if ($send) {
            $packet = new DisconnectPacket();
            $packet->setReason($reason);
            $this->sendPacket($packet);
        }
        $this->getLogger()->info("§bReconnecting to server! Reason: " . $reason);
        $this->close();
        $this->client->connect();
    }

    /**
     * @param string $reason
     */
    public function disconnect(string $reason): void
    {
        if ($this->connection->isClosed()) {
            return;
        }
        $this->getLogger()->info("§bClosing WaterGate connection! Reason: " . $reason);

        $packet = new DisconnectPacket();
        $packet->setReason($reason);
        $this->sendPacket($packet);
        $this->close();
    }

    /**
     * @return bool
     */
    public function close(): bool
    {
        if ($this->connection->isClosed()) {
            return false;
        }

        $this->connection->close();
        return true;
    }

    /**
     * @return bool
     */
    public function isConnected(): bool
    {
        return !$this->connection->isClosed();
    }

    /**
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return $this->connection->getState() === WaterGateConnection::STATE_AUTHENTICATED;
    }

    /**
     * @return WaterGatePacketHandler|null
     */
    public function getPacketHandler(): ?WaterGatePacketHandler
    {
        return $this->packetHandler;
    }

    /**
     * @param WaterGatePacketHandler|null $packetHandler
     */
    public function setPacketHandler(?WaterGatePacketHandler $packetHandler): void
    {
        $this->packetHandler = $packetHandler;
    }

    /**
     * @return WaterGateClient
     */
    public function getClient(): WaterGateClient
    {
        return $this->client;
    }

    /**
     * @return PluginLogger
     */
    public function getLogger(): PluginLogger
    {
        return $this->client->getLogger();
    }

    /**
     * @return WaterGateConnection
     */
    public function getConnection(): WaterGateConnection
    {
        return $this->connection;
    }

    /**
     * @param int $logInputLevel
     */
    public function setLogInputLevel(int $logInputLevel): void
    {
        $this->logInputLevel = $logInputLevel;
    }

    /**
     * @return int
     */
    public function getLogInputLevel(): int
    {
        return $this->logInputLevel;
    }

    /**
     * @param int $logOutputLevel
     */
    public function setLogOutputLevel(int $logOutputLevel): void
    {
        $this->logOutputLevel = $logOutputLevel;
    }

    /**
     * @return int
     */
    public function getLogOutputLevel(): int
    {
        return $this->logOutputLevel;
    }
}
