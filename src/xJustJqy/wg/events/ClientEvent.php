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

namespace xJustJqy\wg\events;

use xJustJqy\wg\client\ClientSession;
use xJustJqy\wg\client\WaterGateClient;
use xJustJqy\wg\WaterGate;
use pocketmine\event\Event;

abstract class ClientEvent extends Event
{

    /** @var WaterGate */
    private WaterGate $plugin;

    /** @var WaterGateClient */
    private WaterGateClient $client;

    /**
     * ClientEvent constructor.
     * @param WaterGateClient $client
     * @param WaterGate $plugin
     */
    public function __construct(WaterGateClient $client, WaterGate $plugin)
    {
        $this->client = $client;
        $this->plugin = $plugin;
    }


    /**
     * @return WaterGateClient
     */
    public function getClient(): WaterGateClient
    {
        return $this->client;
    }

    /**
     * @return ClientSession|null
     */
    public function getSession(): ?ClientSession
    {
        return $this->client->getSession();
    }

    /**
     * @return WaterGate
     */
    public function getPlugin(): WaterGate
    {
        return $this->plugin;
    }
}
