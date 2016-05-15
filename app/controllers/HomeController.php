<?php
    /**
     * Created by PhpStorm.
     * User: bruj0
     * Date: 3/19/2016
     * Time: 11:57 PM
     */
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
//use Chat/Websocket;
class HomeController extends Controller
{

    public function __construct()
    {
        parent::__construct();

        $this->logger = new Log('debug.log');
        $this->url    =  $this->f3->get('URL');
    }
    public function server()
    {
        $server = IoServer::factory(
            new HttpServer(
                new WsServer(
                    new Chat\WebSocket()
                )
            ),
            8080
        );
        $server->run();
    }
    public function index()
    {
        echo Template::instance()->render('index.html');
    }
    public function chat()
    {
        echo Template::instance()->render('chat.html');
    }
}