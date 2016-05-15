<?php
    /**
     * Created by PhpStorm.
     * User: bruj0
     * Date: 3/19/2016
     * Time: 11:58 PM
     */
class MyData
{

    protected $db;

    public function __construct(DB\SQL $db)
    {
        $this->db = $db;
    }


}