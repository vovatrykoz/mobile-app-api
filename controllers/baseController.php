<?php
class BaseController
{
    var $strErrorDesc;
    var $arrQueryStringParams;
    
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
    protected function getQueryParam($paramName)
    {
        $output = null;

        if (isset($this->arrQueryStringParams[$paramName]) && $this->arrQueryStringParams[$paramName]) {
            $output = $this->arrQueryStringParams[$paramName];
        }

        return $output;
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
