<?php
/**
 * Created by ramakandra@gmail.com
 */
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
    }

}