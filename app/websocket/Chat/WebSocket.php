<?php
    /**
     * Created by PhpStorm.
     * User: bruj0
     * Date: 5/14/2016
     * Time: 7:44 PM
     */
namespace Chat;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Log;
class WebSocket implements MessageComponentInterface {
    protected $clients;
    protected $logger;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->logger = new Log('debug.log');
    }

    public function onOpen(ConnectionInterface $conn) {
        // Store the new connection to send messages to later
        $this->clients->attach($conn);

        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $numRecv = count($this->clients) - 1;
        $this->logger->write(sprintf('Connection %d sending message "%s" to %d other connection%s' . "\n"
            , $from->resourceId, $msg, $numRecv, $numRecv == 1 ? '' : 's'));

        foreach ($this->clients as $client) {
            if ($from !== $client) {
                // The sender is not the receiver, send to each client connected
                $client->send($msg);
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        // The connection is closed, remove it, as we can no longer send it messages
        $this->clients->detach($conn);

        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";

        $conn->close();
    }
}