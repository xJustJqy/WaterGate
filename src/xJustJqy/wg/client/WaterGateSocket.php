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

use Exception;
use RuntimeException;
use function socket_create;
use function socket_last_error;
use function socket_strerror;
use const AF_INET;
use const SOCK_STREAM;
use const SOL_TCP;
use pmmp\thread\ThreadSafe;

class WaterGateSocket extends ThreadSafe
{

    /** @var WaterGateConnection */
    private WaterGateConnection $conn;

    /** @var string */
    private string $address;
    /** @var int */
    private int $port;

    /**
     * WaterGateSocket constructor.
     * @param WaterGateConnection $conn
     * @param string $address
     * @param int $port
     */
    public function __construct(WaterGateConnection $conn, string $address, int $port)
    {
        $this->conn = $conn;
        $this->address = $address;
        $this->port = $port;
    }

    /**
     * @return bool
     */
    public function connect(int $attempts = 0): bool
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        try {
            if ($socket === false) {
                throw new RuntimeException(socket_strerror(socket_last_error()));
            }
            if (!@socket_connect($socket, $this->address, $this->port)) {
                throw new RuntimeException(socket_strerror(socket_last_error()));
            }

            socket_set_nonblock($socket);
            socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);
        } catch (Exception $e) {
            $this->conn->getLogger()->debug("Failed to connect to StarGate server! Attempt #" . $attempts + 1);
            return $this->connect($attempts + 1);
        }

        $this->conn->socket = $socket;
        return true;
    }

    public function close(): void
    {
        socket_close($this->conn->socket);
    }
}
