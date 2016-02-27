<?php

namespace LawnGnome\FastCGIReactAdapter;

use React\Promise\PromiseInterface;
use React\SocketClient\TcpConnector;
use React\Stream\Stream;

class TcpClient extends Client {
  protected $connector;

  public function __construct(TcpConnector $connector, string $host, int $port) {
    parent::__construct($host, $port);
    $this->connector = $connector;
  }

  protected function promiseStream(): PromiseInterface {
    return $this->connector->create($this->host, $this->port);
  }
}

// vim: set ts=4 sw=4 et:
