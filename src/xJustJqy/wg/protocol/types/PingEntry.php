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

namespace xJustJqy\wg\protocol\types;

use xJustJqy\wg\utils\WaterGateFuture;

class PingEntry
{

    /** @var WaterGateFuture */
    private WaterGateFuture $future;
    /** @var int */
    private int $timeout;

    /**
     * PingEntry constructor.
     * @param WaterGateFuture $future
     * @param int $timeout
     */
    public function __construct(WaterGateFuture $future, int $timeout)
    {
        $this->future = $future;
        $this->timeout = $timeout;
    }

    /**
     * @return WaterGateFuture
     */
    public function getFuture(): WaterGateFuture
    {
        return $this->future;
    }

    /**
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }
}
