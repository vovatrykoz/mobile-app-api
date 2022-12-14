<?php


class DefaultController extends BaseController
{
    function __construct()
    {
        $this->model = new DefaultModel();
        $this->requestMethod = $_SERVER["REQUEST_METHOD"];
    }

    /**
     * get all the entries in a set
     */
    public function getEntriesFromSet()
    {
        $this->sendResponse($this->getAction(["setId"], "getEntriesFromSet"));
    }

    /**
     * get all the sets assosiated with a user, which is both
     * sets added by the user and sets created by the user
     */
    public function getUserSets()
    {
        $this->sendResponse($this->getAction(["userId"], "getUserSets"));
    }

    /**
     * get the owner of a set
     */
    public function getSetOwner()
    {
        $this->sendResponse($this->getAction(["setId"], "getSetOwner"));
    }
}
