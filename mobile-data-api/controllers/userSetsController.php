<?php


class UserSetsController extends BaseController
{
    var $userModel;
    var $requestMethod;

    function __construct() {
        $this->userModel = new UserSetsModel();
        $this->strErrorDesc = '';
        $this->requestMethod = $_SERVER["REQUEST_METHOD"];
        $this->arrQueryStringParams = $this->getQueryStringParams();
    }

    //general function for GET requests
    function getAction($params = [], $funcName = '') {
        //create an empty json obj
        $responseData = json_encode((object)null);

        if (strtoupper($this->requestMethod) == 'GET') {
            try {
                if($params != []) {
                    foreach($params as $paramName) {
                        $paramArray[$paramName] = $this->getQueryParam($paramName);
                    }
                    
                    $arrInfo = $this->userModel->{$funcName}($paramArray);
                }

                else
                    $arrInfo = $this->userModel->{$funcName}();

                if ($arrInfo) {
                    $responseData = json_encode($arrInfo);
                    
                }
            } catch (Error $e) {
                echo $e;
                exit;
            }
        }

        return $responseData;
    }


    //get all the cars a user is selling
    public function getUserSets()
    {
        $this->sendResponse($this->getAction(["userId"], "getUserSets"));
    }
}