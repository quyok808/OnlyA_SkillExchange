<?php

use App\Models\Connection;

class ConnectionService
{

    public function getAllConnections()
    {
        return Connection::all();
    }

    public function getConnectionById($id)
    {
        return Connection::findOrFail($id);
    }
}
