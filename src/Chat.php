<?php
/*
    TODO: Prob check and replace SplObjectStorage with associated array
*/
namespace MyApp;

// Include the Ratchet library
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\WebSocket\WsConnection;


// Create a class that implements the MessageComponentInterface
class Chat implements MessageComponentInterface {
    // Set the maximum inactive time for a lobby to 24 hours
    const MAX_INACTIVE_TIME = 86400;
    const MAX_FAILURE_COUNT = 3;

    protected $lobbies;
    protected $connections;
    
    public function __construct() {
        $this->lobbies = [];
        $this->connections = new \SplObjectStorage;
    }

    private function generateLobbyCode(){
        $code = null;
        do{
            $code = strval(random_int(100000, 999999));
        }while(isset($this->lobbies[$code]));
        
        return $code;
    }

    private function isValidCode($code)
    {
        return preg_match('/^\d{6}$/', $code) === 1;
    }

    private function broadcastMessage($id, $code, $msg){
        // Iterate over the clients in the lobby and send the broadcast message
        foreach ($this->lobbies[$code]['clients'] as $client) {
            $client->send(json_encode(['action'=>'broadcast', 'id'=>$id, 'code'=>$code, 'message'=>$msg, 'error'=>false]));
        }

        if($this->lobbies[$code]['isClosed'] === true)
        {
            foreach ($this->lobbies[$code]['waiting_room'] as $client) {
                $client->send(json_encode(['action'=>'broadcast', 'id'=>$id, 'code'=>$code, 'message'=>$msg, 'error'=>false]));
            }
        }
    }

    private function isValidMember($code, $conn)
    {
        if (isset($this->lobbies[$code]) && $this->lobbies[$code]['clients']->contains($conn)) {
            return true;
        }
        return false;
    }

    private function isAliasInUse($code, $alias)
    {
        if(isset($this->lobbies[$code]))
        {
            // Check if the desired alias is already in use
            foreach ($this->lobbies[$code]['aliases'] as $currentAlias) {
                if ($currentAlias === $alias) {
                    // The desired alias is already in use
                    return true;
                }
            }
        }

        // The desired alias is not in use
        return false;
    }

    private function setAlias($code, $alias, $conn)
    {
        if(isset($this->lobbies[$code])){
            if ($this->lobbies[$code]['clients']->contains($conn) || ($this->lobbies[$code]['isClosed'] && $this->lobbies[$code]['waiting_room']->contains($conn))) {
                if($this->isAliasInUse($code, $alias)){
                    $conn->send(json_encode(['action'=>'alias', 'id'=>0, 'code'=>$code, 'message'=>"Alias `$alias` already in use!", 'error'=>true]));
                }
                else{
                    // Set the alias for the connection
                    $this->lobbies[$code]['aliases'][$conn->resourceId] = $alias;
                    // Send a message to the client indicating that the alias has been set
                    $conn->send(json_encode(['action'=>'alias', 'id'=>0, 'code'=>$code, 'alias'=>$alias, 'message'=>"Your alias has been set to $alias", 'error'=>false]));
                    $this->lobbies[$code]['owner']->send(json_encode(['action'=>'alias', 'id'=>1, 'code'=>$code, 'userID' => $conn->resourceId, 'user'=>$alias, 'message'=>'Alias has been updated!', 'error'=>false]));

                    $this->lobbies[$code]['lastActiveTime'] = time();
                }
            }
        }
    }

    protected function removeClientFromLobby(ConnectionInterface $conn) {
        // Iterate over all lobbies and remove the client if it is a member
        foreach ($this->lobbies as $code => $lobby) {
            if ($lobby['clients']->contains($conn)) {
                $lobby['clients']->detach($conn);
                //Notifying the lobby owner that someone left the lobby.
                $this->lobbies[$code]['owner']->send(json_encode(['action'=>'left', 'id'=>0, 'code'=>$code, 'clientID'=>$conn->resourceId, 'message'=>"Someone left the lobby!", 'error'=>false]));
            }
            // Remove the client from the waiting room if it is a member
            else if ($lobby['isClosed'] && $lobby['waiting_room']->contains($conn)) {
                $lobby['waiting_room']->detach($conn);
                //Notifying the lobby owner that someone left the que.
                $this->lobbies[$code]['owner']->send(json_encode(['action'=>'left', 'id'=>1, 'code'=>$code, 'clientID'=>$conn->resourceId, 'message'=>"Someone left the queue!", 'error'=>false]));
            }


        }
    }

    public function onOpen(ConnectionInterface $conn) {
        $lobbyCodes = [];
        $this->connections->attach($conn);

        // onOpen is also called when an existing connection is reestablished after being lost.
        foreach ($this->lobbies as $code => $lobby) {
            if ($lobby['clients']->contains($conn)) {
                // Update the last active time of the lobby
                $this->lobbies[$code]['lastActiveTime'] = time();
                $lobbyCodes[] = $code;
                break;
            }
        }

        //Setting the heartbeat response variable that will be used to indicate the client is still responsive.
        $conn->heartbeatResponse = true;
        $conn->failureCount = 0;

        $conn->send(json_encode(['action'=>'init', 'id'=>0, 'previous'=>$lobbyCodes, 'error'=>false]));
        echo "New connection! ({$conn->resourceId})\n";
    }

    //Maybe remove lobby is user is owner
    public function onClose(ConnectionInterface $conn) {
        $this->removeClientFromLobby($conn);
        $this->connections->detach($conn);

        echo "Connection {$conn->resourceId} has disconnected\n";
    }
    
    public function onError(ConnectionInterface $connection, \Exception $e){
        echo "ONERROR\n";
        echo "ERROR OCCURED: " . $e;
    }
    
    public function onMessage(ConnectionInterface $from, $msg) {
        //the json header
        //$conn->httpResponse->setHeader('Content-Type', 'application/json');

        $from->heartbeatResponse = true;
        // Parse the incoming JSON data
        $data = json_decode($msg);

        if ($data === null) {
            // $msg is not valid JSON data
            // Handle the error or do something else
            return;
        }

        switch ($data->action) {
            case 'create':
            {
                // Generate a random 6-digit code if none is provided
                $code = $this->generateLobbyCode();

                $is_public = (isset($data->is_public) && is_bool($data->is_public)) ? $data->is_public : true;


                $password = (isset($data->password) && is_string($data->password)) ? $data->password : null;

                // Add the lobby to the list of lobbies
                // Initialize the common elements of the lobby array
                $commonElements = [
                    'clients' => new \SplObjectStorage,
                    'owner' => $from,
                    'lastActiveTime' => time(),
                    'aliases' => [],
                    'risedHands' => [],
                    'hasPassword' => false,
                    'isClosed' => false
                ];

                if ($password) {
                    // Add the password-specific elements to the common elements array
                    $commonElements['hasPassword']  = true;
                    $commonElements['password']     = $password;
                }

                if($is_public === false)
                {
                    $commonElements['isClosed']     = true;
                    $commonElements['waiting_room'] = new \SplObjectStorage;
                }

                // Initialize the lobby array using the common elements array
                $this->lobbies[$code] = $commonElements;
                // Add the connection to the lobby
                $this->lobbies[$code]['clients']->attach($from);
                // Send a message to the client with the lobby code
                $from->send(json_encode(['action'=>'create', 'id'=>0, 'code'=>$code, 'message'=>"Lobby created with code $code", 'error'=>false]));
                break;
            }
            // Check if the message is a command to join a lobby
            case 'join':
                {
                    $code = $data->code;
                    if($code === null || is_string($code) == false || $this->isValidCode($code) == false)
                        return;
                    $password = (isset($data->password) && is_string($data->password)) ? $data->password : null;
                    $alias = (isset($data->alias) && is_string($data->alias)) ? $data->alias : null;

                    if (isset($this->lobbies[$code])) {
                        if($this->lobbies[$code]['clients']->contains($from) || ($this->lobbies[$code]['isClosed'] && $this->lobbies[$code]['waiting_room']->contains($from)))
                        {
                            $from->send(json_encode(['action'=>'join', 'id'=>0, 'code'=>$code, 'message'=>"You are already registered in this lobby!", 'error'=>true]));
                            return;
                        }
        
                        if ($this->lobbies[$code]['hasPassword'] && $password !== $this->lobbies[$code]['password']) {
                            $from->send(json_encode(['action'=>'join', 'id'=>1, 'code'=>$code, 'message'=>"Wrong password", 'error'=>true]));
                            return;
                        }
        
                        $this->lobbies[$code]['lastActiveTime'] = time();
        
                        if($this->lobbies[$code]['isClosed'])
                        {
                            // Add the connection to the lobby
                            $this->lobbies[$code]['waiting_room']->attach($from);
        
                            // Send a message to the client with the lobby code
                            $from->send(json_encode(['action'=>'join', 'id'=>0, 'code'=>$code, 'message'=>"You have been placed in queue.", 'error'=>false]));
                            $this->lobbies[$code]['owner']->send(json_encode(['action'=>'notification', 'id'=>0, 'code'=>$code, 'clientID'=>$from->resourceId, 'message'=>"Someone joined the queue!", 'error'=>false]));
                        }
                        else{
                            // Add the connection to the lobby
                            $this->lobbies[$code]['clients']->attach($from);
        
                            // Send a message to the client with the lobby code
                            $from->send(json_encode(['action'=>'join', 'id'=>1, 'code'=>$code, 'message'=>"Joined lobby with code $code", 'error'=>false]));
                            $this->lobbies[$code]['owner']->send(json_encode(['action'=>'notification', 'id'=>1, 'code'=>$code, 'clientID'=>$from->resourceId, 'message'=>"Someone joined the lobby!", 'error'=>false]));
                        }
        
                        if($alias)
                        {
                            $this->setAlias($code, $alias, $from);
                        }

                    } else {
                        $from->send(json_encode(['action'=>'join', 'id'=>2, 'code'=>$code, 'message'=>"Lobby with code $code not found", 'error'=>true]));
                    }
                    break;
                }
                // Check if the message is a command to close the lobby
                case 'close':
                    {
                        $code = $data->code;
                        if($code === null || is_string($code) == false || $this->isValidCode($code) == false)
                            return;
                        if (isset($this->lobbies[$code]) && $this->lobbies[$code]['owner'] === $from) {
                            $this->broadcastMessage(0, $code, "Lobby with code $code has been closed!");
                            // Remove the lobby from the list of lobbies
                            unset($this->lobbies[$code]);
                        }
                    }
                // Check if the message is a command to set the alias
                case 'setalias':
                    {
                        $code = $data->code;
                        $alias = (isset($data->alias) && is_string($data->alias)) ? $data->alias : null;
                        if(($code === null || $alias === null) || $this->isValidCode($code) == false)
                            return;

                        $this->setAlias($code, $alias, $from);
                        break;
                    }
                case 'risehand':
                    {
                        $code = $data->code;
                        $message = (isset($data->message) && is_string($data->message)) ? $data->message : '';
                        if($code === null || is_string($code) == false || $this->isValidCode($code) == false)
                            return;

                        //If the user is a member of the lobby
                        if ($this->isValidMember($code, $from)) {
                            $lobby = $this->lobbies[$code];
                            $alias = $lobby['aliases'][$from->resourceId] ?? null;
                            if(!isset($alias))
                            {
                                $from->send(json_encode(['action'=>'risehand', 'id'=>0, 'code'=>$code, 'message'=>"Please set a alias.", 'error'=>true]));
                            }
                            else{
                                $metadata = array('userID'=>$from->resourceId, 'alias'=>$alias, 'message'=>$message);
                                $this->lobbies[$code]['risedHands'][$from->resourceId] = $metadata;
                                $lobby['owner']->send(json_encode(['action'=>'risehand', 'id'=>0, 'code'=>$code, 'metadata'=>$metadata, 'error'=>false]));
                                $from->send(json_encode(['action'=>'risehand', 'id'=>1, 'code'=>$code, 'message'=>"Success: Your hand is raised!", 'error'=>false]));
                                $lobby['lastActiveTime'] = time();
                            }
                        }
                        break;
                    }
                case 'lowerhand':
                    {
                        $code = $data->code;

                        $userID = (isset($data->userID) && is_numeric($data->userID)) ? (int)$data->userID : $from->resourceId;
                        if($code === null || is_string($code) == false || $this->isValidCode($code) == false)
                            return;

                        if ($this->isValidMember($code, $from) && isset($this->lobbies[$code]['risedHands'][$userID])){
                            
                            //isset($this->lobbies[$code]['risedHands'][$userID])
                            if(isset($matches[2]))
                            {
            
                                if($this->lobbies[$code]['owner'] === $from){
                                    unset($this->lobbies[$code]['risedHands'][$userID]);
                                    $this->lobbies[$code]['owner']->send(json_encode(['action'=>'lowerhand', 'id'=>0, 'code'=>$code, 'userID'=>$userID, 'message'=>"Hand lowered successfully!", 'error'=>false]));
                                    //User might have left the lobby since then
                                    foreach ($this->lobbies[$code]['clients'] as $currentConn) {
                                        if ($currentConn->resourceId === $userID) {
                                            $currentConn->send(json_encode(['action'=>'lowerhand', 'id'=>1, 'code'=>$code, 'message'=>"Your hand have been lowered!", 'error'=>false]));
                                            break;
                                        }
                                    }
                                }
                                else{
                                    //...
                                }
                            }
                            else{
                                unset($this->lobbies[$code]['risedHands'][$userID]);
                                $this->lobbies[$code]['owner']->send(json_encode(['action'=>'lowerhand', 'id'=>2, 'code'=>$code, 'userID'=>$userID, 'message'=>"Someone just lowered their hand!", 'error'=>false]));
                                $from->send(json_encode(['action'=>'lowerhand', 'id'=>3, 'code'=>$code, 'message'=>"Success: Your hand is lowered!", 'error'=>false]));
            
                            }
            
                        }
                        break;
                    }
                case 'queue':
                    {
                        $code = $data->code;
                        if($code === null || is_string($code) == false || $this->isValidCode($code) == false)
                            return;

                        if (isset($this->lobbies[$code]) && $this->lobbies[$code]['isClosed'] && $this->lobbies[$code]['owner'] === $from) {
                            $queueList = [];
            
                            foreach ($this->lobbies[$code]['waiting_room'] as $conn) 
                            {
                                $alias = $this->lobbies[$code]['aliases'][$conn->resourceId] ?? 'unknown';
                                $queueList[] = ['alias' => $alias, 'user_type' => ($conn === $this->lobbies[$code]['owner']) ? 'owner' : 'member', 'connectionID' => $conn->resourceId];
                            }
            
                            // Send the waiting list to the lobby owner
                            $this->lobbies[$code]['owner']->send(json_encode(['action'=>'queue', 'id'=>0, 'code'=>$code, 'list'=>$queueList, 'error'=>false]));
                        }
                        break;
                    }
                // Check if the message is a command to check queue of a lobby
                case 'members':
                    {
                        $code = $data->code;
                        if($code === null || is_string($code) == false || $this->isValidCode($code) == false)
                            return;

                        if (isset($this->lobbies[$code]) && $this->lobbies[$code]['owner'] === $from) {
                            $memberList = [];
            
                            foreach ($this->lobbies[$code]['clients'] as $conn) 
                            {
                                $alias = $this->lobbies[$code]['aliases'][$conn->resourceId] ?? 'unknown';
                                $memberList[] = ['alias' => $alias, 'user_type' => ($conn === $from) ? 'owner' : 'member', 'connectionID' => $conn->resourceId];
                            }
            
                            // Send the waiting list to the lobby owner
                            $this->lobbies[$code]['owner']->send(json_encode(['action'=>'member_list', 'id'=>0, 'code'=>$code, 'list'=>$memberList, 'error'=>false]));
                        }
                        break;
                    }
                // Check if the message is a command to accept member in queue
                case 'accept':
                    {

                        $code = $data->code;
                        $connectionID = $data->userID;
                        if(($code === null || $connectionID === null) || $this->isValidCode($code) == false || $this->is_numeric($connectionID) == false)
                            return;

                        if (isset($this->lobbies[$code]) && $this->lobbies[$code]['isClosed'] && $this->lobbies[$code]['owner'] === $from) {
                            foreach ($this->lobbies[$code]['waiting_room'] as $index => $conn) 
                            {
                                //Two equal-signs, will automatically make the two operands the same type.
                                if ($conn->resourceId == $connectionID) {
                                    $this->lobbies[$code]['clients']->attach($conn);
                                    $this->lobbies[$code]['waiting_room']->detach($conn);
            
                                    $conn->send(json_encode(['action'=>'accept', 'id'=>0, 'code'=>$code, 'message' => "You have been added as a member!", 'error'=>false]));
                                    $this->lobbies[$code]['owner']->send(json_encode(['action'=>'accept', 'id'=>1, 'code'=>$code, 'userID' => $connectionID, 'message' => "$connectionID has been added!", 'error'=>false]));
            
                                    break;
                                }
            
                            }
            
                            // Send the waiting list to the lobby owner
                        }
                        break;
                    }
        }

    }

  
    // Create a function to remove inactive lobbies
    public function removeInactiveLobbies() {
        echo "Clearing inactive lobbies\n";
        // Iterate over the lobbies and remove those that have been inactive for more than MAX_INACTIVE_TIME
        foreach ($this->lobbies as $code => $lobby) {
            if (time() - $lobby['lastActiveTime'] > self::MAX_INACTIVE_TIME) {
                unset($this->lobbies[$code]);
            }
        }
    }

    // Create a function to implement simple heartbeat to remove old connections (failed to close correctly)
    public function heartbeat() {
        echo "heartbeat to remove old connections\n";
        // Iterate over the lobbies and remove those that have been inactive for more than MAX_INACTIVE_TIME
        foreach ($this->connections as $index => $conn) {
            if (!isset($conn->heartbeatResponse)) {
                echo "heartbeat response not set! (hmm)\n";
                $conn->heartbeatResponse = true;
                continue;
            }

            //The client responded to the heartbeat! keep it alive
            if($conn->heartbeatResponse === true)
            {
                $conn->heartbeatResponse = false;
                $conn->failureCount = 0;
                $conn->send("ping");
            }
            //The client didn't respond to the last heartbeat!
            else
            {
                if($conn->failureCount >= self::MAX_FAILURE_COUNT)
                {
                    echo "Connection is getting closed!";
                    //Close the connection
                    $this->removeClientFromLobby($conn);
                    $this->connections->detach($conn);
                    $conn->close();
                    
                }
                else
                {
                    $conn->failureCount++;
                    $conn->send("ping");
                }
            }
        }
    }
  
}