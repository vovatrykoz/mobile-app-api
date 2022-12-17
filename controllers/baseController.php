<?php
class BaseController
{
    var $strErrorDesc;
    var $arrQueryStringParams;
    var $model;
    var $requestMethod;

    function __construct()
    {
        $this->model = new Database();
        
    }

    /**
     * Get URI elements.
     * 
     * @return array
     */
    protected function getUriSegments()
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = explode('/', $uri);

        return $uri;
    }

    /**
     * Get querystring params.
     * 
     * @return array
     */
    protected function getQueryStringParams()
    {
        parse_str($_SERVER['QUERY_STRING'], $query);
        return $query;
    }

    /**
     * Send API output.
     *
     * @param mixed  $data
     * @param string $httpHeader
     */
    protected function sendOutput($data, $httpHeaders = array())
    {
        if (is_array($httpHeaders) && count($httpHeaders)) {
            foreach ($httpHeaders as $httpHeader) {
                header($httpHeader);
            }
        }

        echo $data;
        exit;
    }

    //extracts value of the $paramName
    protected function getQueryParams($paramName)
    {
        $output = null;

        if (isset($this->arrQueryStringParams[$paramName]) && $this->arrQueryStringParams[$paramName]) {
            $output = $this->arrQueryStringParams[$paramName];
        }

        return $output;
    }

    /**
     * general function used for handling all possible get requests
     */
    protected function getAction($params = [], $funcName = '')
    {
        //create an empty json obj
        $responseData = json_encode((object)null);

        if (strtoupper($this->requestMethod) == 'GET') {
            try {
                if ($params != []) {
                    //retrieve all the parameters that the user has provided
                    foreach ($params as $paramName) {
                        $paramArray[$paramName] = $this->getQueryParams($paramName);
                    }

                    //forward the request with one or more parameters to the data access layer
                    $arrInfo = $this->model->{$funcName}($paramArray);
                } else
                    //forward the request without any parameters to the data access layer
                    $arrInfo = $this->model->{$funcName}();

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

    /**
     * general function for POST, PUT and DELETE requests
     */
    function changeAction($params = [], $funcName = '', $actionType = '')
    {
        //create an empty json obj
        $responseData = json_encode((object)null);
        $paramArray = [];

        if (strtoupper($this->requestMethod) == $actionType) {
            try {
                foreach ($params as $paramName) {
                    $paramArray[$paramName] = $this->getQueryParams($paramName);
                }

                $affected_rows = $this->model->$funcName($paramArray);
                $responseData = json_encode(array("affected_rows" => $affected_rows));
            } catch (Error $e) {
                echo $e;
                exit;
            }
        }

        return $responseData;
    }

    protected function sendResponse($responseData)
    {
        // send output
        if (!$this->strErrorDesc) {
            $this->sendOutput($responseData, array('Content-Type: application/json', 'HTTP/1.1 200 OK'));
        } else {
            $this->sendOutput(
                json_encode(array('error' => $this->strErrorDesc)),
                array('Content-Type: application/json', $this->strErrorHeader)
            );
        }
    }
}
