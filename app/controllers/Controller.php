<?php
/**
 * Created by ramakandra@gmail.com
 */
define('USER_PROFESOR',1);
define('USER_STUDENT',2);
class Controller {
    protected $f3;
    protected $db;
    function beforeroute() {
        //$this->f3->set('message','');
    }
    function afterroute() {
        //echo Template::instance()->render('index.html');
    }
    function __construct() {
        $f3=Base::instance();
        $this->f3=$f3;
        $host    =  $this->f3->get('MEMCACHE_HOST');
        $port    =  $this->f3->get('MEMCACHE_PORT');
        $session_save_path = "$host:$port";
        ini_set('session.save_handler', 'memcached');
        ini_set('session.save_path', $session_save_path);

        $this->db = new Memcached();
        $this->db->addServer($host, $port);
    }

}