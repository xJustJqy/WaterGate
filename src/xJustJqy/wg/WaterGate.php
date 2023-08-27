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

namespace xJustJqy\wg;

use xJustJqy\wg\client\WaterGateClient;
use xJustJqy\wg\client\WaterDogPlayer;
use xJustJqy\wg\events\ClientCreationEvent;
use xJustJqy\wg\protocol\ServerInfoRequestPacket;
use xJustJqy\wg\protocol\ServerTransferPacket;
use xJustJqy\wg\protocol\types\HandshakeData;
use xJustJqy\wg\utils\PacketResponse;
use pocketmine\event\player\PlayerCreationEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\network\mcpe\JwtUtils;
use xJustJqy\wg\codec\WaterGatePacketHandler;
use xJustJqy\wg\protocol\UnknownPacket;

class WaterGate extends PluginBase implements Listener
{

    public const WaterGate_VERSION = 2;

    /** @var WaterGate */
    private static WaterGate $instance;

    /** @var WaterGateClient[] */
    protected array $clients = [];

    /** @var int */
    private int $tickInterval;

    /** @var string */
    private string $defaultClient;

    /** @var int */
    private int $logLevel;

    /** @var bool */
    private bool $autoStart;

    public function onEnable(): void
    {
        self::$instance = $this;
        $this->tickInterval = $this->getConfig()->get("tickInterval");
        $this->defaultClient = $this->getConfig()->get("defaultClient");
        $this->logLevel = $this->getConfig()->get("logLevel");
        $this->autoStart = $this->getConfig()->get("autoStart");

        $this->clients = [];
        foreach ($this->getConfig()->get("connections") as $clientName => $ignore) {
            $this->createClient($clientName);
        }
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onDisable(): void
    {
        foreach ($this->clients as $client) {
            $client->shutdown();
        }
    }

    /** @var WaterGatePacketHandler[] */
    private static array $handlers = [];

    public static function addHandler(WaterGatePacketHandler $handle)
    {
        self::$handlers[] = $handle;
    }

    public static function onData(UnknownPacket $data)
    {
        foreach (self::$handlers as $handler) {
            $handler->handleUnknown($data);
        }
    }

    private static array $toFix = [];

    /**
     * @priority LOWEST
     */
    public function playerOverride(PlayerCreationEvent $event)
    {
        $event->setPlayerClass(WaterDogPlayer::class);
    }

    /** 
     * @priority LOWEST
     */
    public function onLogin(\pocketmine\event\player\PlayerLoginEvent $event)
    {
        $player = $event->getPlayer();
        $data = $this->getServer()->getOfflinePlayerData($player->getName());
        if (in_array($player->getNetworkSession()->getPort(), array_keys(self::$toFix))) {
            $fix = self::$toFix[$player->getNetworkSession()->getPort()];
            $player->fixXUID($fix[0]);
            $player->fixIP($fix[1]);
            unset(self::$toFix[$player->getNetworkSession()->getPort()]);
        }
        if (!is_null($data) && $data->getString(Player::TAG_LAST_KNOWN_XUID) !== $player->getXuid()) {
            $event->setKickMessage("XUID does not match (possible impersonation attempt)");
        }
    }

    /** 
     * @priority LOWEST
     */
    public function dataPacketRecieve(DataPacketReceiveEvent $event)
    {
        $pk = $event->getPacket();
        if ($pk instanceof LoginPacket) {
            $parsed = JwtUtils::parse($pk->clientDataJwt);
            if (isset($parsed[1]["Waterdog_XUID"])) {
                self::$toFix[$event->getOrigin()->getPort()] = [$parsed[1]["Waterdog_XUID"], $parsed[1]["Waterdog_IP"]];
            }
        }
    }

    /**
     * @param string $clientName
     */
    private function createClient(string $clientName): void
    {
        if (!isset($this->getConfig()->get("connections")[$clientName])) {
            $this->getLogger()->warning("§cCan not load client " . $clientName . "! Wrong config!");
            return;
        }

        $config = $this->getConfig()->get("connections")[$clientName];
        $handshakeData = new HandshakeData($clientName, $config["password"], HandshakeData::SOFTWARE_POCKETMINE, self::WaterGate_VERSION);
        $client = new WaterGateClient($config["address"], (int) $config["port"], $handshakeData, $this);
        $this->onClientCreation($clientName, $client);
    }

    /**
     * @param string $clientName
     * @param WaterGateClient $client
     */
    public function onClientCreation(string $clientName, WaterGateClient $client): void
    {
        if (isset($this->clients[$clientName])) {
            return;
        }

        $event = new ClientCreationEvent($client, $this);
        $event->call();

        if ($event->isCancelled()) {
            return;
        }
        if ($this->autoStart) {
            $client->connect();
        }
        $this->clients[$clientName] = $client;
    }

    /**
     * @return WaterGate
     */
    public static function getInstance(): WaterGate
    {
        return self::$instance;
    }

    /**
     * @return int
     */
    public function getTickInterval(): int
    {
        return $this->tickInterval;
    }

    public function getClient(string $clientName): ?WaterGateClient
    {
        return $this->clients[$clientName] ?? null;
    }

    /**
     * @return WaterGateClient|null
     */
    public function getDefaultClient(): ?WaterGateClient
    {
        return $this->getClient($this->defaultClient);
    }

    /**
     * @return WaterGateClient[]
     */
    public function getClients(): array
    {
        return $this->clients;
    }

    /**
     * @param int $logLevel
     */
    public function setLogLevel(int $logLevel): void
    {
        $this->logLevel = $logLevel;
    }

    /**
     * @return int
     */
    public function getLogLevel(): int
    {
        return $this->logLevel;
    }

    /**
     * Transfer player to another server.
     * @param Player $player instance to be transferred.
     * @param string $targetServer server where player will be sent.
     * @param string|null $clientName client name that will be used.
     */
    public function transferPlayer(Player $player, string $targetServer, ?string $clientName = null): void
    {
        $client = $this->getClient($clientName ?? $this->defaultClient);
        if ($client === null) {
            return;
        }
        $packet = new ServerTransferPacket();
        $packet->setPlayerName($player->getName());
        $packet->setTargetServer($targetServer);
        $client->sendPacket($packet);
    }

    /**
     * Get info about another server or master server.
     * @param string $serverName name of server that info will be send. In selfMode it can be custom.
     * @param bool $selfMode if send info of master server, WaterGate server.
     * @param string|null $clientName client name that will be used.
     * @return PacketResponse|null future that can be used to get response data.
     */
    public function serverInfo(string $serverName, bool $selfMode, ?string $clientName = null): ?PacketResponse
    {
        $client = $this->getClient($clientName ?? $this->defaultClient);
        if ($client === null) {
            return null;
        }
        $packet = new ServerInfoRequestPacket();
        $packet->setServerName($serverName);
        $packet->setSelfInfo($selfMode);
        return $client->responsePacket($packet);
    }
}
