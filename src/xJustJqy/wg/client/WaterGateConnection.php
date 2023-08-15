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
use xJustJqy\wg\protocol\types\HandshakeData;
use xJustJqy\wg\utils\WaterGateException;
use pmmp\thread\ThreadSafeArray;
use pocketmine\thread\log\ThreadSafeLogger;
use pocketmine\thread\Thread;
use pocketmine\utils\Binary;
use function socket_read;
use function strlen;
use function substr;
use const PHP_BINARY_READ;

class WaterGateConnection extends Thread
{

    public const STATE_DISCONNECTED = 0;
    public const STATE_CONNECTING = 1;
    public const STATE_CONNECTED = 2;
    public const STATE_AUTHENTICATING = 3;
    public const STATE_AUTHENTICATED = 4;
    public const STATE_SHUTDOWN = 5;

    /** @var ThreadSafeLogger */
    private ThreadSafeLogger $logger;
    /** @var WaterGateSocket */
    private WaterGateSocket $WaterGateSocket;

    /** @var resource */
    public $socket;

    /** @var string */
    private string $address;
    /** @var int */
    private int $port;
    /** @var HandshakeData */
    private HandshakeData $handshakeData;

    /** @var ThreadSafeArray */
    private ThreadSafeArray $input;
    /** @var ThreadSafeArray */
    private ThreadSafeArray $output;

    /** @var string */
    private string $buffer = "";

    /** @var int */
    private int $state = self::STATE_DISCONNECTED;

    /**
     * WaterGateConnection constructor.
     * @param ThreadSafeLogger $logger
     * @param string $address
     * @param int $port
     * @param HandshakeData $handshakeData
     */
    public function __construct(ThreadSafeLogger $logger, string $address, int $port, HandshakeData $handshakeData)
    {
        $this->logger = $logger;
        $this->address = $address;
        $this->port = $port;
        $this->handshakeData = $handshakeData;
        $this->WaterGateSocket = new WaterGateSocket($this, $this->address, $this->port);

        $this->input = new ThreadSafeArray();
        $this->output = new ThreadSafeArray();
        $this->start(PTHREADS_INHERIT_NONE);
    }

    public function onRun(): void
    {
        $this->registerClassLoaders();
        gc_enable();
        error_reporting(-1);
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');

        register_shutdown_function([$this, 'shutdown']);
        //set_error_handler([$this, 'errorHandler'], E_ALL);

        $this->state = self::STATE_CONNECTING;
        $this->logger->debug("Connecting to WaterGate server " . $this->address);

        if (!$this->WaterGateSocket->connect()) {
            $this->state = self::STATE_DISCONNECTED;
            return;
        }
        //socket_getpeername($this->socket, $this->address, $this->port);

        $this->state = self::STATE_CONNECTED;
        $this->operate();
    }

    private function operate(): void
    {
        while ($this->state !== self::STATE_DISCONNECTED) {
            $start = microtime(true);
            $this->onTick();
            $time = microtime(true);
            if (($diff = $time - $start) < 0.02) {
                time_sleep_until($time + 0.025 - $diff);
            }
        }
        $this->onTick();
        $this->shutdown();
    }

    private function onTick(): void
    {
        $error = socket_last_error();
        socket_clear_error($this->socket);

        if ($error === 10057 || $error === 10054 || $error === 10053) {
            error:
            $this->getLogger()->info("§cConnection with WaterGate server has disconnected unexpectedly!");
            $this->close();
            return;
        }

        $data = @socket_read($this->socket, 65536, PHP_BINARY_READ);
        if ($data !== "") {
            $this->buffer .= $data;
        }

        while (($packet = $this->outRead()) !== null && $packet !== "") {
            if (@socket_write($this->socket, $packet) === false) {
                goto error;
            }
        }

        $this->readBuffer();
    }

    /**
     * @param string $buffer
     * @param int $len
     * @param int $offset
     * @return int
     */
    private function verifyHeader(string $buffer, int $len, int $offset): int
    {
        if (($offset + 2) > $len) {
            // No PacketId + Response info
            return 0;
        }

        $index = 1; // PacketID
        $supportsResponse = Binary::readBool($buffer[$offset + $index++]);
        if ($supportsResponse) {
            $index += 4; // Skip ResponseID
        }
        return $index;
    }

    private function readBuffer(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        $offset = 0;
        $len = strlen($this->buffer);
        while ($offset < $len) {
            if ($offset > ($len - 6)) {
                // Tried to decode invalid buffer
                break;
            }

            $magic = Binary::readShort(substr($this->buffer, $offset, 2));
            if ($magic !== ProtocolCodec::WaterGate_MAGIC) {
                throw new WaterGateException("'Magic does not match!");
            }
            $offset += 2;

            $length = Binary::readInt(substr($this->buffer, $offset, 4));
            $offset += 4;

            if (($offset + $length) > $len) {
                // Received incomplete packet
                $offset -= 2;
                break;
            }

            $payload = substr($this->buffer, $offset, $length);
            $offset += $length;
            $this->inputWrite($payload);
        }

        if ($offset < $len) {
            $this->buffer = substr($this->buffer, $offset);
        } else {
            $this->buffer = "";
        }
    }

    /**
     * @param string $payload
     */
    public function writeBuffer(string $payload): void
    {
        $buf = Binary::writeShort(ProtocolCodec::WaterGate_MAGIC);
        $buf .= $payload;
        $this->outWrite($buf);
    }

    public function close(): void
    {
        if ($this->state === self::STATE_DISCONNECTED) {
            return;
        }
        $this->state = self::STATE_DISCONNECTED;
        $this->logger->debug("Closed WaterGate session " . $this->address);
    }

    public function shutdown(): void
    {
        if ($this->state === self::STATE_SHUTDOWN) {
            return;
        }
        $this->state = self::STATE_SHUTDOWN;
        $this->WaterGateSocket->close();
    }

    /**
     * @return bool
     */
    public function isClosed(): bool
    {
        return $this->state === self::STATE_DISCONNECTED || $this->state === self::STATE_SHUTDOWN;
    }

    /**
     * @return int
     */
    public function getState(): int
    {
        return $this->state;
    }

    /**
     * @param int $state
     */
    public function setState(int $state): void
    {
        $this->state = $state;
    }

    /**
     * @return string|null
     */
    public function inputRead(): ?string
    {
        return $this->input->shift();
    }

    /**
     * @param string $string
     */
    public function inputWrite(string $string): void
    {
        $this->input[] = $string;
    }

    /**
     * @return string|null
     */
    public function outRead(): ?string
    {
        return $this->output->shift();
    }

    /**
     * @param string $string
     */
    public function outWrite(string $string): void
    {
        $this->output[] = $string;
    }

    public function quit(): void
    {
        $this->close();
        parent::quit();
    }

    /**
     * @return WaterGateSocket
     */
    public function getWaterGateSocket(): WaterGateSocket
    {
        return $this->WaterGateSocket;
    }

    /**
     * @return resource
     */
    public function getSocket()
    {
        return $this->socket;
    }

    /**
     * @return ThreadSafeLogger
     */
    public function getLogger(): ThreadSafeLogger
    {
        return $this->logger;
    }

    public function getClientName(): string
    {
        return $this->handshakeData->getClientName();
    }

    public function getThreadName(): string
    {
        return "WaterGate-Atlantis";
    }

    public function setGarbage(): void
    {
    }
}
