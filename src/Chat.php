<?php
namespace MyApp;

// Include the Ratchet library
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

// Create a class that implements the MessageComponentInterface
class Chat implements MessageComponentInterface {
    // Set the maximum inactive time for a lobby to 24 hours
    const MAX_INACTIVE_TIME = 86400;

    protected $lobbies;
    
    public function __construct() {
        $this->lobbies = [];
    }
    

    /*
    //Make sure to update the regex when modifying
    private function generateLobbyCode(){
        $code = null;
        while (true) {
            $shuffle_string = substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 6);
            $code = "";
            
            for($x = 0; $x < 6; $x++)
            {
                $code .= $shuffle_string[random_int(0, strlen($shuffle_string)-1)];
            }

            if (!isset($this->lobbies[$code])) {
                break;
            }
        }
        return $code;
    }
    */

    private function generateLobbyCode(){
        $code = null;
        do{
            $code = strval(random_int(100000, 999999));
        }while(isset($this->lobbies[$code]));
        
        return $code;
    }

    private function broadcastMessage($code, $msg){
        // Iterate over the clients in the lobby and send the broadcast message
        foreach ($this->lobbies[$code]['clients'] as $client) {
            $client->send(json_encode(['action'=>'broadcast', 'id'=>0, 'code'=>$code, 'message'=>$msg, 'error'=>false]));
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

  
    public function onOpen(ConnectionInterface $conn) {
        $lobbyCodes = [];

        // onOpen is also called when an existing connection is reestablished after being lost.
        foreach ($this->lobbies as $code => $lobby) {
            if ($lobby['clients']->contains($conn)) {
                // Update the last active time of the lobby
                $this->lobbies[$code]['lastActiveTime'] = time();
                $lobbyCodes[] = $code;
                break;
            }
        }

        $conn->send(json_encode(['action'=>'init', 'id'=>0, 'previous'=>$lobbyCodes, 'error'=>false]));
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        // Check if the message is a command to create a new lobby with a password if set
        if (preg_match('/^\/(create|closed_create)(?:\s+(.+))?/', $msg, $matches)) {
            // Generate a random 6-digit code if none is provided
            $code = $this->generateLobbyCode();
            $public = $matches[1];
            $password = $matches[2] ?? null;

            // Add the lobby to the list of lobbies
            // Initialize the common elements of the lobby array
            $commonElements = [
                'clients' => new \SplObjectStorage,
                'owner' => $from,
                'lastActiveTime' => time(),
                'aliases' => [],
                'hasPassword' => false,
                'isClosed' => false
            ];

            if ($password) {
                // Add the password-specific elements to the common elements array
                $commonElements['hasPassword']  = true;
                $commonElements['password']     = $password;
            }

            if($public === 'closed_create')
            {
                $commonElements['isClosed']     = true;
                $commonElements['waiting_room'] = new \SplObjectStorage;
            }

            // Initialize the lobby array using the common elements array
            $this->lobbies[$code] = $commonElements;
            // Add the connection to the lobby
            $this->lobbies[$code]['clients']->attach($from);
            // Send a message to the client with the lobby code
            $from->send(json_encode(['action'=>'create', 'id'=>0, 'message'=>"Lobby created with code $code", 'code'=>$code, 'error'=>false]));
        } 
        //A-Z0-9
        // Check if the message is a command to join a lobby
        elseif (preg_match('/^\/join\s+([0-9]{6})(?:\s+(.+))?$/', $msg, $matches)) {
            $code = $matches[1];
            $password = $matches[2] ?? null;
            if (isset($this->lobbies[$code])) {
                if($this->lobbies[$code]['clients']->contains($from) || ($this->lobbies[$code]['isClosed'] && $this->lobbies[$code]['waiting_room']->contains($from)))
                {
                    $from->send(json_encode(['action'=>'join', 'id'=>0, 'message'=>"You are already registered in this lobby!", 'error'=>true]));
                    return;
                }

                if ($this->lobbies[$code]['hasPassword'] && $password !== $this->lobbies[$code]['password']) {
                    $from->send(json_encode(['action'=>'join', 'id'=>1, 'message'=>"Wrong password", 'error'=>true]));
                    return;
                }

                $this->lobbies[$code]['lastActiveTime'] = time();

                if($this->lobbies[$code]['isClosed'])
                {
                    // Add the connection to the lobby
                    $this->lobbies[$code]['waiting_room']->attach($from);

                    // Send a message to the client with the lobby code
                    $from->send(json_encode(['action'=>'join', 'id'=>0, 'message'=>"You have been placed in queue.", 'error'=>false]));
                    $this->lobbies[$code]['owner']->send(json_encode(['action'=>'notification', 'id'=>0, 'message'=>"Someone joined the queue!", 'error'=>false]));
                }
                else{
                    // Add the connection to the lobby
                    $this->lobbies[$code]['clients']->attach($from);

                    // Send a message to the client with the lobby code
                    $from->send(json_encode(['action'=>'join', 'id'=>1, 'message'=>"Joined lobby with code $code", 'error'=>false]));
                    $this->lobbies[$code]['owner']->send(json_encode(['action'=>'notification', 'id'=>1, 'message'=>"Someone joined the lobby!", 'error'=>false]));
                }


            } else {
                $from->send(json_encode(['action'=>'join', 'id'=>2, 'message'=>"Lobby with code $code not found", 'error'=>true]));
            }
        }
        // Check if the message is a command to close the lobby
        elseif (preg_match('/^\/close\s+([0-9]{6})$/', $msg, $matches)) {
            $code = $matches[1];
            if (isset($this->lobbies[$code]) && $this->lobbies[$code]['owner'] === $from) {
                $this->broadcastMessage($code, "Lobby with code $code has been closed!");
                // Remove the lobby from the list of lobbies
                unset($this->lobbies[$code]);
            }
        }
          // Check if the message is a command to set the alias
        elseif (preg_match('/^\/setalias\s+([0-9]{6})\s+(.+)$/', $msg, $matches)) {
            $code = $matches[1];
            $alias = $matches[2];
            if(isset($this->lobbies[$code])){
                if ($this->lobbies[$code]['clients']->contains($from) || ($this->lobbies[$code]['isClosed'] && $this->lobbies[$code]['waiting_room']->contains($from))) {
                    if($this->isAliasInUse($code, $alias)){
                        $from->send(json_encode(['action'=>'alias', 'id'=>0, 'message'=>"Alias `$alias` already in use!", 'error'=>true]));
                    }
                    else{
                        // Set the alias for the connection
                        $this->lobbies[$code]['aliases'][$from->resourceId] = $alias;
                        // Send a message to the client indicating that the alias has been set
                        $from->send(json_encode(['action'=>'alias', 'id'=>0, 'message'=>"Your alias has been set to $alias", 'error'=>false]));
                        $this->lobbies[$code]['lastActiveTime'] = time();
                    }
                }
            }

        } 
        elseif(preg_match('/^\/risehand\s+([0-9]{6})(?:\s+(.+))?$/', $msg, $matches))
        {
            $code = $matches[1];
            $message = $matches[2] ?? '';
            //If the user is a member of the lobby
            if ($this->isValidMember($code, $from)) {
                $lobby = $this->lobbies[$code];
                $alias = $lobby['aliases'][$from->resourceId] ?? null;
                if(!isset($alias))
                {
                    $from->send(json_encode(['action'=>'risehand', 'id'=>0, 'message'=>"Please set a alias.", 'error'=>true]));
                }
                else{
                    $lobby['owner']->send(json_encode(['action'=>'risehand', 'id'=>0, 'user'=>$alias, 'message'=>$message, 'error'=>false]));
                    $from->send(json_encode(['action'=>'risehand', 'id'=>1, 'message'=>"Success: Your hand is raised!", 'error'=>false]));
                    $lobby['lastActiveTime'] = time();
                }
            }
        }
        // Check if the message is a command to check queue of a lobby
        elseif (preg_match('/^\/queue\s+([0-9]{6})$/', $msg, $matches)) {
            $code = $matches[1];
            if (isset($this->lobbies[$code]) && $this->lobbies[$code]['isClosed'] && $this->lobbies[$code]['owner'] === $from) {
                $queueList = [];

                foreach ($this->lobbies[$code]['waiting_room'] as $conn) 
                {
                    $alias = $this->lobbies[$code]['aliases'][$conn->resourceId] ?? 'unknown';
                    $queueList[] = ['alias' => $alias, 'conn' => $conn->resourceId];
                }

                // Send the waiting list to the lobby owner
                $this->lobbies[$code]['owner']->send(json_encode(['action'=>'queue', 'id'=>0, 'list'=>$queueList, 'error'=>false]));
            }
        }
        // Check if the message is a command to check queue of a lobby
        elseif (preg_match('/^\/members\s+([0-9]{6})$/', $msg, $matches)) {
            $code = $matches[1];
            if (isset($this->lobbies[$code]) && $this->lobbies[$code]['owner'] === $from) {
                $memberList = [];

                foreach ($this->lobbies[$code]['clients'] as $conn) 
                {
                    $alias = $this->lobbies[$code]['aliases'][$conn->resourceId] ?? 'unknown';
                    $memberList[] = ['alias' => $alias, 'user_type' => ($conn === $from) ? 'owner' : 'member', 'conn' => $conn->resourceId];
                }

                // Send the waiting list to the lobby owner
                $this->lobbies[$code]['owner']->send(json_encode(['action'=>'member_list', 'id'=>0, 'list'=>$memberList, 'error'=>false]));
            }
        }
        // Check if the message is a command to close the lobby
        elseif (preg_match('/^\/accept\s+([0-9]{6})(?:\s+(\d+))$/', $msg, $matches)) {
            $code = $matches[1];
            $connectionID = $matches[2];
            if (isset($this->lobbies[$code]) && $this->lobbies[$code]['isClosed'] && $this->lobbies[$code]['owner'] === $from) {
                foreach ($this->lobbies[$code]['waiting_room'] as $index => $conn) 
                {
                    //Two equal-signs, will automatically make the two operands the same type.
                    if ($conn->resourceId == $connectionID) {
                        $this->lobbies[$code]['clients']->attach($conn);
                        $this->lobbies[$code]['waiting_room']->detach($conn);

                        $this->lobbies[$code]['owner']->send(json_encode(['action'=>'accept', 'id'=>0, 'message' => "$connectionID has been added!", 'error'=>false]));
                        break;
                    }

                }

                // Send the waiting list to the lobby owner
            }
        }
    }


    public function onClose(ConnectionInterface $conn) {
        // Remove the client from any lobbies they might be in
        foreach ($this->lobbies as $code => $lobby) {
            if ($lobby['isClosed']) {
                // Check if the connection is in the lobby
                if ($lobby['waiting_room']->contains($conn)) {
                    // Remove the connection from the lobby
                    $lobby['waiting_room']->detach($conn);
                }
            } else {
                // Check if the connection is in the lobby
                if ($lobby['clients']->contains($conn)) {
                    // Remove the connection from the lobby
                    $lobby['clients']->detach($conn);
                }
            }
        }

        echo "Connection {$conn->resourceId} has disconnected\n";
    }
  
    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
  
        $conn->close();
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
  
}