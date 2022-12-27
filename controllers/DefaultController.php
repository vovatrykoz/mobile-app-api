<?php


class DefaultController extends BaseController
{
    function __construct()
    {
        $this->model = new DefaultModel();
        $this->requestMethod = $_SERVER["REQUEST_METHOD"];
        $this->arrQueryStringParams = $this->getQueryStringParams();
        $this->strErrorDesc = '';
    }

    /**
     * get all the entries in a set
     */
    public function checkUser()
    {
        $this->sendResponse($this->getAction(["username", "password"], "checkUser"));
    }

    /**
     * get all the entries in a set
     */
    public function postUser()
    {
        $this->sendResponse($this->changeAction(["username", "password"], "insertUser", "POST"));
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

    /**
     * get all sets
     */
    public function getAllSets()
    {
        $this->sendResponse($this->getAction([], "getAllSets"));
    }

    /**
     * insert a new set
     */
    public function postSet()
    {
        $this->sendResponse($this->changeAction(["setName", "creatorId", "setSubject"], "insertSet", "POST"));
    }

    /**
     * insert a new set
     */
    public function updateSet()
    {
        $this->sendResponse($this->changeAction(["setName", "setId", "setSubject"], "updateSet", "PUT"));
    }

    /**
     * insert a new set
     */
    public function deleteSet()
    {
        $this->sendResponse($this->changeAction(["setId"], "deleteSet", "DELETE"));
    }

    /**
     * insert an entry into set
     */
    public function postEntryIntoSet()
    {
        $this->sendResponse($this->changeAction(["entryTerm", "entryDefinition", "setId"], "insertEntryIntoSet", "POST"));
    }

    /**
     * delete an entry from a set
     */
    public function deleteEntryFromSet()
    {
        $this->sendResponse($this->changeAction(["entryId"], "deleteEntryFromSet", "DELETE"));
    }

    /**
     * delete an entry from a set
     */
    public function updateEntry()
    {
        $this->sendResponse($this->changeAction(["entryId", "entryTerm", "entryDefinition"], "updateEntry", "PUT"));
    }
}
