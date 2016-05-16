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
        session_start();

        if($_POST && ( isset($_POST['nickname_prof']) || isset($_POST['nickname_student'])) )
        {
            $prof               = $this->f3->get('POST.nickname_prof');
            $student            = $this->f3->get('POST.nickname_student');
            $_SESSION['nick']   = ($prof) ? $prof : $student;
            $_SESSION['type']   = ($prof) ? USER_PROFESOR : USER_STUDENT;
            if($this->addNewUser($_SESSION['nick'],$_SESSION['type'])===false)
            {
                $this->f3->set('ERROR', "Username already taken, choose another");
                return $this->index();
            }
        }

        if(isset($_SESSION['type']))
        {
            $this->f3->set('nick', $_SESSION['nick']);
            if($_SESSION['type']==USER_PROFESOR)
                echo Template::instance()->render('chat_profesor.html');
            else
                echo Template::instance()->render('chat_student.html');
            return;
        }

        echo "Error, not enough data to continue";
    }
    public function logout()
    {
        session_start();
        $nick=$_SESSION['nick'];
        do
        {
            $tmp = $this->db->get("users", NULL, $cas);
            if ($this->db->getResultCode() != Memcached::RES_NOTFOUND)
            {
                $ws_id=$tmp['users'][$nick]['websocket_id'];
                unset($tmp['users'][$nick]);
                unset($tmp['websocket'][$ws_id]);
                $this->db->cas($cas, "users", $tmp);
            }
        } while ($this->db->getResultCode() != Memcached::RES_SUCCESS);

        session_destroy();
        return $this->index();
    }
    public function addNewUser($nick,$type)
    {
        $new = array(
            'nick' => $nick,
            'type' => $type
        );
        do
        {
            $tmp = $this->db->get("users", NULL, $cas);
            if ($this->db->getResultCode() == Memcached::RES_NOTFOUND)
            {
                $tmp = array();
                $tmp['users'] = array (
                    $nick => $new
                );
                $tmp['websocket']=array();
                $this->db->add("users", $tmp);
            }
            else
            {
                if(isset($tmp['users'][$nick]))
                        return false;

                $tmp['users'][ $nick ] = $new;
                $this->db->cas($cas, "users", $tmp);
            }
        } while ($this->db->getResultCode() != Memcached::RES_SUCCESS);

        return true;
    }
}