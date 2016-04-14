<?php

namespace LawnGnome\FastCGIReactAdapter;

use EBernhardson\FastCGI\Client as FCGIClient;
use EBernhardson\FastCGI\CommunicationException;
use React\Promise\{Promise, PromiseInterface};
use React\Stream\Stream;

/**
 * The asynchronous Client.
 *
 * Note that while this class extends EBernhardson\FastCGI\Client, this is
 * solely to reuse the helper methods within that class which are declared
 * protected. This class should be considered to have its own, unique API.
 */
abstract class Client extends FCGIClient {
    /**
     * Implemented by concrete implementations to create a promise that will
     * give a Stream when fulfilled.
     */
    abstract protected function promiseStream(): PromiseInterface;

    /**
     * Returns a promise that will issue a request to an upstream FastCGI server.
     *
     * The parameters are identical to those given to
     * EBernhardson\FastCGI\Client.
     *
     * @return A promise that, when fulfilled, will provide a response
     *         formatted the same way as EBernhardson\FastCGI\Client::response().
     */
    public function requestAsync(array $params, $stdin): PromiseInterface {
        $resolver = function (callable $resolve, callable $reject, callable $notify) use ($params, $stdin) {
            $request = $this->buildRequest($params, $stdin);

            $stream = $this->promiseStream($this->host, $this->port)
                           ->then(function (Stream $stream) use ($request, $resolve) {
                $response = '';
                $stream->on('data', function ($data) use (&$response) {
                    // TODO: investigate if it's worth decoding packets on the
                    // fly.
                    $response .= $data;
                });

                $stream->on('end', function () use (&$response, $resolve) {
                    $ctx = new ResponseContext;
                    foreach ($this->splitPackets($response) as $packet) {
                        if ($ctx->handlePacket($packet)) {
                            break;
                        }
                    }

                    if (!is_array($packet)) {
                        throw new CommunicationException('Bad request');
                    }

                    // Check the protocol status in the end request packet.
                    switch (ord($packet['content'])) {
                        case self::CANT_MPX_CONN:
                            throw new CommunicationException('This app cannot multiplex');
                            break;

                        case self::OVERLOADED:
                            throw new CommunicationException('New request rejected; too busy');
                            break;

                        case self::UNKNOWN_ROLE:
                            throw new CommunicationException('Role value not known');
                            break;

                        case self::REQUEST_COMPLETE:
                            // TODO: convince Erik to accept a PR making this
                            // protected.
                            $formatter = new \ReflectionMethod(FCGIClient::class, 'formatResponse');
                            $formatter->setAccessible(true);
                            $resolve($formatter->invoke(null, $ctx->stdout, $ctx->stderr));
                            break;

                        default:
                            // TODO: again, do we throw an exception here?
                            break;
                    }
                });

                $stream->write($request);
            });
        };

        return new Promise($resolver);
    }

    /**
     * Builds the raw request string to be sent to the FastCGI server.
     */
    protected function buildRequest(array $params, string $stdin): string {
        // Keep alive is always false for now in this implementation.
        // TODO: implement FastCGI socket pooling.
        $keepAlive = false;

        // This is adapted from Client::doRequest(), which isn't particularly
        // reusable, unfortunately.
        $request = $this->buildPacket(self::BEGIN_REQUEST, chr(0).chr(self::RESPONDER).chr((int) $keepAlive).str_repeat(chr(0), 5));

        $paramsRequest = '';
        foreach ($params as $key => $value) {
            $paramsRequest .= $this->buildNvpair($key, $value);
        }
        if ($paramsRequest) {
            $request .= $this->buildPacket(self::PARAMS, $paramsRequest);
        }

        if ($stdin) {
            $request .= $this->buildPacket(self::STDIN, $stdin);
        }
        $request .= $this->buildPacket(self::STDIN, '');

        return $request;
    }

    /**
     * A generator that yields each packet in the raw response string.
     */
    protected function splitPackets(string $response) {
        while (strlen($response) > 0) {
            if (strlen($response) < self::HEADER_LEN) {
                throw new CommunicationException('Malformed response; truncated header');
            }

            $header = substr($response, 0, self::HEADER_LEN);
            $response = substr($response, self::HEADER_LEN);

            $packet = $this->decodePacketHeader($header);
            $len = $packet['contentLength'] + $packet['paddingLength'];
            if ($len > 0) {
                if (strlen($response) < $len) {
                    throw new CommunicationException('Malformed response; truncated body');
                }

                $packet['content'] = substr($response, 0, $packet['contentLength']);
                $response = substr($response, $len);
            } else {
                $packet['content'] = '';
            }

            yield $packet;
        }
    }
}

// vim: set ts=4 sw=4 et:
