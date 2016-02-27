<?php

namespace LawnGnome\FastCGIReactAdapter;

use EBernhardson\FastCGI\Client as FCGIClient;

/**
 * A basic wrapper class encapsulating the stderr and stdout returned in a
 * FastCGI response.
 */
class ResponseContext {
    /**
     * @var string
     */
    public $stderr = '';

    /**
     * @var string
     */
    public $stdout = '';

    /**
     * Handles a response packet.
     *
     * @param packet The packet to parse.
     * @return True if the packet is an END_REQUEST packet, false otherwise.
     */
    public function handlePacket(array $packet): bool {
        switch ($packet['type']) {
            case FCGIClient::END_REQUEST:
            case 0:
                return true;

            case FCGIClient::STDOUT:
                $this->stdout .= $packet['content'];
                break;

            case FCGIClient::STDERR:
                $this->stderr .= $packet['content'];
                break;

            default:
                // TODO: do we throw an exception here?
                break;
        }

        return false;
    }
}

// vim: set ts=4 sw=4 et:
