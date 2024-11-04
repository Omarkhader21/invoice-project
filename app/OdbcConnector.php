<?php

namespace App;

use Illuminate\Database\Connectors\Connector;
use Illuminate\Database\Connectors\ConnectorInterface;
use PDO;

class OdbcConnector extends Connector implements ConnectorInterface
{
    public function connect(array $config)
    {
        // Use the DSN name directly, as configured in the database config file
        $dsn = "odbc:DSN={$config['dsn']};PWD={$config['password']}";

        // Get additional options (e.g., PDO options)
        $options = $this->getOptions($config);

        // Establish the PDO connection with DSN, username, and password
        return $this->createConnection($dsn, $config, $options);
    }

    public function createConnection($dsn, array $config, array $options)
    {
        // Pass username and password, if needed (typically blank for Access)
        return new PDO($dsn, $config['username'] ?? null, $config['password'] ?? null, $options);
    }
}
