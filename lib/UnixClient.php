<?php

namespace LawnGnome\FastCGIReactAdapter;

use React\Promise\PromiseInterface;
use React\SocketClient\UnixConnector;
use React\Stream\Stream;

class UnixClient extends Client {
  protected $connector;

  public function __construct(UnixConnector $connector, string $socket) {
    parent::__construct($socket);
    $this->connector = $connector;
  }

  protected function promiseStream(): PromiseInterface {
    return $this->connector->create($this->socketPath);
  }
}

// vim: set ts=4 sw=4 et:
