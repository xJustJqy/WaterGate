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

use xJustJqy\wg\codec\ProtocolCodec;
use xJustJqy\wg\events\ClientAuthenticatedEvent;
use xJustJqy\wg\events\ClientConnectedEvent;
use xJustJqy\wg\events\ClientDisconnectedEvent;
use xJustJqy\wg\handler\SessionHandler;
use xJustJqy\wg\protocol\DisconnectPacket;
use xJustJqy\wg\protocol\WaterGatePacket;
use xJustJqy\wg\protocol\types\HandshakeData;
use xJustJqy\wg\WaterGate;

use xJustJqy\wg\utils\PacketResponse;
use pocketmine\plugin\PluginLogger;
use pocketmine\scheduler\Task;
use pocketmine\Server;

class WaterGateClient extends Task
{

    /** @var WaterGate */
    private WaterGate $loader;
    /** @var Server */
    private Server $server;
    /** @var PluginLogger */
    private PluginLogger $logger;

    /** @var ProtocolCodec */
    private ProtocolCodec $protocolCodec;

    /** @var HandshakeData */
    private HandshakeData $handshakeData;
    /** @var string */
    protected string $address;
    /** @var int */
    protected int $port;

    /** @var ClientSession|null */
    private ?ClientSession $session = null;

    /** @var SessionHandler|null */
    private ?SessionHandler $customHandler = null;

    /**
     * WaterGateClient constructor.
     * @param string $address
     * @param int $port
     * @param HandshakeData $handshakeData
     * @param WaterGate $plugin
     */
    public function __construct(string $address, int $port, HandshakeData $handshakeData, WaterGate $plugin)
    {
        $this->loader = $plugin;
        $this->server = $plugin->getServer();
        $this->logger = $plugin->getLogger();
        $this->protocolCodec = new ProtocolCodec();

        $this->address = $address;
        $this->port = $port;
        $this->handshakeData = $handshakeData;
        $this->loader->getScheduler()->scheduleDelayedRepeatingTask($this, 20, $this->loader->getTickInterval());
    }

    public function connect(): void
    {
        if ($this->isConnected()) {
            return;
        }
        $this->session = new ClientSession($this, $this->address, $this->port);
    }


    public function onRun(): void
    {
        if ($this->session !== null && $this->isConnected()) {
            $this->session->onTick();
        }
    }

    public function onSessionConnected(): void
    {
        $this->logger->info("§bClient " . $this->getClientName() . " has connected!");
        $event = new ClientConnectedEvent($this, $this->loader);
        $event->call();

        if ($this->session !== null) {
            $this->session->setLogInputLevel($this->loader->getLogLevel());
            $this->session->setLogOutputLevel($this->loader->getLogLevel());
        }
    }

    public function onSessionAuthenticated(): void
    {
        $event = new ClientAuthenticatedEvent($this, $this->loader);
        $event->call();
        if ($this->session !== null && $event->isCancelled()) {
            $this->session->disconnect($event->getCancelMessage());
        }
    }

    public function onSessionDisconnected(): void
    {
        $event = new ClientDisconnectedEvent($this, $this->loader);
        $event->call();
    }

    /**
     * @param WaterGatePacket $packet
     */
    public function sendPacket(WaterGatePacket $packet): void
    {
        $this->session?->sendPacket($packet);
    }

    /**
     * @param WaterGatePacket $packet
     * @return PacketResponse|null
     */
    public function responsePacket(WaterGatePacket $packet): ?PacketResponse
    {
        return $this->session?->responsePacket($packet);
    }

    public function shutdown(): void
    {
        if (!$this->isConnected()) {
            return;
        }
        $this->session?->disconnect(DisconnectPacket::CLIENT_SHUTDOWN);
    }

    /**
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->session !== null && $this->session->isConnected();
    }

    /**
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return $this->session !== null && $this->session->isAuthenticated();
    }

    /**
     * @return HandshakeData
     */
    public function getHandshakeData(): HandshakeData
    {
        return $this->handshakeData;
    }

    /**
     * @return string
     */
    public function getClientName(): string
    {
        return $this->handshakeData->getClientName();
    }

    /**
     * @return ClientSession|null
     */
    public function getSession(): ?ClientSession
    {
        return $this->session;
    }

    /**
     * @return Server
     */
    public function getServer(): Server
    {
        return $this->server;
    }

    /**
     * @return PluginLogger
     */
    public function getLogger(): PluginLogger
    {
        return $this->logger;
    }

    /**
     * @return ProtocolCodec
     */
    public function getProtocolCodec(): ProtocolCodec
    {
        return $this->protocolCodec;
    }

    /**
     * @return SessionHandler|null
     */
    public function getCustomHandler(): ?SessionHandler
    {
        return $this->customHandler;
    }

    /**
     * @param SessionHandler $customHandler
     */
    public function setCustomHandler(SessionHandler $customHandler): void
    {
        $this->customHandler = $customHandler;
    }
}
