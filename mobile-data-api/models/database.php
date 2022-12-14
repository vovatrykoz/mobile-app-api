<?php
class Database
{
    protected $connection = null;

    public function __construct()
    {
        try {
            //defined in config.php
            $this->connection = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

            if (mysqli_connect_errno()) {
                throw new Exception("Could not connect to database.");
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function select($query = "", $params = [])
    {
        try {
            $stmt = $this->executeStatement($query, $params);
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            return $result;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        return false;
    }

    function changeDatabase($query = "", $params = [])
    {
        try {
            $stmt = $this->executeStatement($query, $params);
            $result = $this->connection->affected_rows;
            $stmt->close();

            return $result;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        return false;
    }

    public function insert($query = "", $params = [])
    {
        return $this->changeDatabase($query, $params);
    }

    public function update($query = "", $params = [])
    {
        return $this->changeDatabase($query, $params);
    }

    public function delete($query = "", $params = [])
    {
        return $this->changeDatabase($query, $params);
    }

    private function executeStatement($query = "", $params = [])
    {
        try {
            // Prepare a SQL statement using a prepared statement
            $stmt = $this->connection->prepare($query);

            // If the prepared statement fails, throw an exception
            if ($stmt === false) {
                throw new Exception("Unable to do prepared statement: " . $query);
            }

            // If there are parameters to bind to the prepared statement, bind them
            if ($params) {
                // Construct a string that specifies the type of each parameter
                $bindTypeString = '';
                // Create an array containing the values of the parameters
                $bindData = [];

                // Iterate over each parameter and add its type and value to the
                // corresponding strings/arrays
                foreach ($params as $param) {
                    // Determine the type of the parameter
                    switch ($param['paramType']) {
                        case 'i':
                            $type = 'i'; // integer
                            break;
                        case 'd':
                            $type = 'd'; // double
                            break;
                        case 's':
                            $type = 's'; // string
                            break;
                        case 'b':
                            $type = 'b'; // blob
                            break;
                        default:
                            $type = 's'; // default to string
                    }

                    // Append the type to the type string
                    $bindTypeString .= $type;
                    $bindData[] = &$param['paramValue'];
                }

                // Prepend the type string to the array of parameter values
                array_unshift($bindData, $bindTypeString);
                // Bind the parameters to the prepared statement
                call_user_func_array([$stmt, 'bind_param'], $bindData);
            }

            try {
                // Execute the prepared statement
                $stmt->execute();
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }

            // Return the prepared statement object
            return $stmt;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
