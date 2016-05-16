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
define('OP_NEW_USER',1);
define('OP_QUESTION',2);
define('OP_ANSWER',3);
define('OP_INTENT',4);
define('OP_INTENT_CLEAR',5);
class WebSocket implements MessageComponentInterface {
    protected $clients;
    protected $logger;
    protected $db;
    protected $f3;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->logger = new Log('debug.log');
        $this->f3=\Base::instance();

        $this->db = new \Memcached();
        $this->db->addServer("localhost", "11211");
    }

    public function onOpen(ConnectionInterface $conn) {
        // Store the new connection to send messages to later
        $this->clients->attach($conn);
        $this->logger->write(__METHOD__.": New connection! ({$conn->resourceId})\n");
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $numRecv = count($this->clients) - 1;
        /*$this->logger->write(sprintf(__METHOD__.': Connection %d sending message "%s" to %d other connection%s' . "\n"
            , $from->resourceId, $msg, $numRecv, $numRecv == 1 ? '' : 's'));*/

        $this->logger->write("Message received by {$from->resourceId} $msg");

        $data = json_decode($msg, TRUE);
        $this->handleMsg($from,$data);
    }
    private function handleMsg($from,$data)
    {
        if(!isset($data['opcode']))
        {
            $this->logger->write(__METHOD__ . ": Error no opcode: " . print_r($data,true));
            return;
        }
        switch($data['opcode'])
        {
            case OP_NEW_USER:
                $this->newUser($from->resourceId, $data);
                break;
            case OP_QUESTION:
                if (isset($data['nick']) && isset($data['type']))
                {
                    $id                 = $this->newQuestion($from->resourceId, $data);//saves question in memcache
                    $text_student       = $this->getStudentQuestion($id,$data);
                    $text_profesor      = $this->getProfesorQuestion($id,$data);
                    $users = $this->db->get("users");

                    foreach ($this->clients as $client)
                    { // iterate trough clients

                        if(isset($users['websocket'][$client->resourceId])) // is the client registered
                        {
                            $cd=$users['websocket'][$client->resourceId];
                            if( $cd['type']==USER_STUDENT) // is the client a student or professor
                            {
                                $client->send($text_student);
                            }
                            else
                                $client->send($text_profesor);
                        }
                        else
                            $this->logger->write(__METHOD__ . ": OP_QUESTION Error searching for websocket client: " . print_r($data,true));
                    }
                    $this->logger->write(__METHOD__ . ": OP_QUESTION New question added: " . print_r($data,true));
                }
                else
                {
                    $this->logger->write(__METHOD__ . ": OP_QUESTION Error receving data: " . print_r($data,true));
                }
                break;
            case OP_ANSWER:
                if (isset($data['nick']) && isset($data['type']))
                {
                    $ret=$this->newAnswer($from->resourceId, $data);
                    if($ret['error']==true)
                    {
                        $from->send(json_encode($ret));
                        return;
                    }
                    else
                    {
                        $users = $this->db->get("users");
                        $id=$data['qid'];
                        $text = $this->getAnswer($id,$data);
                        foreach ($this->clients as $client)
                        { // iterate trough clients
                            if(isset($users['websocket'][$client->resourceId])) // is the client registered
                                    $client->send($text);
                            else
                                $this->logger->write(__METHOD__ . ": OP_ANSWER Error searching for websocket client: " . print_r($data,true));
                        }
                    }
                }
                else
                {
                    $this->logger->write(__METHOD__ . ": OP_ANSWER Error receving data: " . print_r($data,true));
                }
                break;
            case OP_INTENT:
                $ret=$this->newIntent($from->resourceId,$data);
                $users = $this->db->get("users");
                if($ret) //new list of intent, refresh clients
                {
                    $this->logger->write(__METHOD__ . ": OP_INTENT sending intent list: " . print_r($ret,true));
                    foreach ($this->clients as $client)
                    { // iterate trough clients
                        if(isset($users['websocket'][$client->resourceId])) // is the client registered
                        {
                            $cd = $users['websocket'][ $client->resourceId ];
                            if ($cd['type'] == USER_PROFESOR) // is the client a professor
                            {
                                $client->send(json_encode($ret));
                            }
                        }
                        else
                            $this->logger->write(__METHOD__ . ": OP_INTENT Error searching for websocket client: " . print_r($data,true));
                    }
                }
                break;
            case OP_INTENT_CLEAR:
                $ret=$this->clearIntent($from->resourceId,$data);
                $users = $this->db->get("users");
                if($ret) //new list of intent, refresh clients
                {
                    $this->logger->write(__METHOD__ . ": OP_INTENT_CLEAR sending intent list: " . print_r($ret, TRUE));
                    foreach ($this->clients as $client)
                    { // iterate trough clients
                        if (isset($users['websocket'][ $client->resourceId ])) // is the client registered
                        {
                            $cd = $users['websocket'][ $client->resourceId ];
                            if ($cd['type'] == USER_PROFESOR) // is the client a professor
                            {
                                $client->send(json_encode($ret));
                            }
                        }
                        else
                        {
                            $this->logger->write(__METHOD__ . ": OP_INTENT_CLEAR Error searching for websocket client: " . print_r($data, TRUE));
                        }
                    }
                }
                break;
            default:
                $this->logger->write(__METHOD__ . ": default Error opcode: " . print_r($data,true));
                break;
        }

    }
    private function newUser($from,$data)
    {
        do
        {
            $tmp = $this->db->get("users", NULL, $cas);
            if ($this->db->getResultCode() == \Memcached::RES_NOTFOUND)
            {
                $this->logger->write(__METHOD__ . ": Error users not found: " . print_r($data,true));
            }
            else
            {
                $nick=$data['nick'];
                if(!isset($tmp['users'][$nick]))
                {
                    $this->logger->write(__METHOD__ . ": Error $nick not found: " . print_r($data,true));
                    return false;
                }
                $tmp['users'][$nick]['websocket_id'] = $from;
                $tmp['websocket'][$from]=&$tmp['users'][$nick];
                $this->db->cas($cas, "users", $tmp);
                $this->logger->write(__METHOD__ . ": New user $nick $from: " . print_r($tmp,true));
            }
        } while ($this->db->getResultCode() != \Memcached::RES_SUCCESS);
    }
    private function newQuestion($from,$data)
    {
        $new = array(
            'nick' => $data['nick'],
            'text' => $data['text'],
            'from' => $from,
            'time' => $data['time'],
            'answered' => FALSE
        );
        do
        {
            $tmp = $this->db->get("questions", NULL, $cas);
            if ($this->db->getResultCode() == \Memcached::RES_NOTFOUND)
            {

                $id=0;
                $tmp[$id] = $new;
                $this->db->add("questions", $tmp);
            }
            else
            {
                $id=count($tmp);
                $tmp[$id] = $new;
                $this->db->cas($cas, "questions", $tmp);
            }
        } while ($this->db->getResultCode() != \Memcached::RES_SUCCESS);

        return $id;
    }
    private function newAnswer($from, $data)
    {
        do
        {
            $tmp = $this->db->get("questions", NULL, $cas);
            if ($this->db->getResultCode() == \Memcached::RES_NOTFOUND)
            {
                $this->logger->write(__METHOD__ . ": Error questions is empty: " . print_r($data,true));
                return array('error'=>true,'msg'=>'Internal error');
            }
            else
            {
                if(!isset($tmp[$data['qid']]))
                {
                    $this->logger->write(__METHOD__ . ": Error question not found: " . print_r($data,true));
                    return  array('error'=>true,'msg'=>'Please select a question first');
                }
                if($tmp[$data['qid']]['answered']==true)
                {
                    $this->logger->write(__METHOD__ . ": Error question already answered: " . print_r($data,true));
                    return array('error'=>true,'msg'=>'Question already answered');
                }
                $tmp[$data['qid']]['answer']= array (
                    'websocket_id' => $from,
                    'text' => $data['data'],
                    'nick' => $data['nick'],
                    'time' => $data['time']
                );
                $this->db->cas($cas, "questions", $tmp);
            }
        } while ($this->db->getResultCode() != \Memcached::RES_SUCCESS);
        return array('error'=> false);

    }
    public function getAnswer($id,$data)
    {
        $msg = array(
            'id'    => $id,
            'nick'  => $data['nick'],
            'time'  => $data['time'],
            'text'  => $data['text']
        );

        $this->f3->set('MSG',$msg);
        $ret = array (
            'opcode' => OP_ANSWER,
            'qid'  => $id,
            'html' => \Template::instance()->render('answer.html')
        );
        return json_encode($ret);
    }
    public function getStudentQuestion($id,$data)
    {
        $msg = array(
            'id'    => $id,
            'nick'  => $data['nick'],
            'time'  => $data['time'],
            'text'  => $data['text']
        );

        $this->f3->set('MSG',$msg);
        $ret = array (
            'opcode' => OP_QUESTION,
            'html' => \Template::instance()->render('question_student.html')
        );
        return json_encode($ret);
    }
    public function getProfesorQuestion($id,$data)
    {
        $msg = array(
            'id'    => $id,
            'nick'  => $data['nick'],
            'time'  => $data['time'],
            'text'  => $data['text']
        );

        $this->f3->set('MSG',$msg);
        $ret = array (
            'opcode' => OP_QUESTION,
            'html' => \Template::instance()->render('question_profesor.html'),
            'qid' => $id
        );
        return json_encode($ret);

    }
    public function newIntent($from,$data)
    {
        do
        {
            $tmp = $this->db->get("intent", NULL, $cas);
            if ($this->db->getResultCode() == \Memcached::RES_NOTFOUND)
            {
                $tmp = array();
                $tmp[$data['qid']]=array();
                $tmp[$data['qid']][$data['nick']]=true;
                $this->db->add("intent", $tmp);
            }
            else
            {
                if(isset($tmp[$data['qid']][$data['nick']])) //nick is already in question intent list
                    return false;


                foreach($tmp as $qid => $intents)//clear nick from other questions
                {
                    if(isset($tmp[$qid][$data['nick']]))
                    {
                        unset($tmp[ $qid ][ $data['nick'] ]);
                        break;
                    }
                }
                $tmp[$data['qid']][$data['nick']]=true;
                $this->db->cas($cas, "intent", $tmp);
            }
        } while ($this->db->getResultCode() != \Memcached::RES_SUCCESS);

        return array(
            'opcode' => OP_INTENT,
            'list' => $tmp,
        );
    }
    public function clearIntent($from,$data)
    {
        do
        {
            $tmp = $this->db->get("intent", NULL, $cas);
            if ($this->db->getResultCode() != \Memcached::RES_NOTFOUND)
            {
                unset($tmp[$data['qid']][$data['nick']]); //nick is already in question intent list
                $this->db->cas($cas, "intent", $tmp);
            }
        } while ($this->db->getResultCode() != \Memcached::RES_SUCCESS);

        return array(
            'opcode' => OP_INTENT,
            'list' => $tmp,
        );
    }
    public function onClose(ConnectionInterface $conn) {
        // The connection is closed, remove it, as we can no longer send it messages

        do
        {
            $tmp = $this->db->get("users", NULL, $cas);
            if ($this->db->getResultCode() == \Memcached::RES_NOTFOUND)
            {
                $this->logger->write(__METHOD__ . ": Error users not found: " . print_r($data,true));
            }
            else
            {

                if(!isset($tmp['websocket'][$conn->resourceId]))
                {
                    $this->logger->write(__METHOD__ . ": Error onclose {$conn->resourceId} not found: " . print_r($data,true));
                    return false;
                }
                $nick=$tmp['websocket'][$conn->resourceId]['nick'];
                $tmp['users'][$nick]['websocket_id']=0;
                unset($tmp['websocket'][$conn->resourceId]);
                $this->db->cas($cas, "users", $tmp);
                $this->logger->write(__METHOD__ . ": User removed user $nick {$conn->resourceId}:".print_r($tmp,true));
            }
        } while ($this->db->getResultCode() != \Memcached::RES_SUCCESS);
        $this->logger->write(__METHOD__.": Connection {$conn->resourceId} has disconnected\n");
        $this->clients->detach($conn);
    }


    public function onError(ConnectionInterface $conn, \Exception $e) {
        $this->logger->write(__METHOD__ . ":An error has occurred: {$e->getMessage()}\n");
        $conn->close();
    }
}