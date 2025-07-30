<?php

namespace App\Services\ZKTeco;

use Exception;
use Illuminate\Support\Facades\Log;

class MB360Service
{
    private $host;
    private $port;
    private $socket;
    
    public function __construct($host = '192.168.10.23', $port = 80)
    {
        $this->host = $host;
        $this->port = $port;
    }



    public function connectHTTP()
    {
        try{
            $url = "http://{$this->host}/cgi-bin/AttLog.cgi";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $response = curl_exec($ch);
            curl_close($ch);
            return $response;
        }catch(Exception $e){
            dd($e->getMessage());
        }
    }

    
    /**
     * Connect to MB360 device
     */
    public function connect()
    {
        try {
            $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            $result = socket_connect($this->socket, $this->host, $this->port);
            
            if (!$result) {
                throw new Exception("Cannot connect to MB360 device");
            }

            return true;
        } catch (Exception $e) {
            dd($e->getMessage());
        }
    }
    
    /**
     * Disconnect from device
     */
    public function disconnect()
    {
        if ($this->socket) {
            socket_close($this->socket);
        }
    }
    
    /**
     * Send command to device
     */
    private function sendCommand($command)
    {
        if (!$this->socket) {
            throw new Exception("Not connected to device");
        }
        
        socket_write($this->socket, $command, strlen($command));
        $response = socket_read($this->socket, 2048);
        
        return $response;
    }
    
    /**
     * Get attendance records from device
     */
    public function getAttendanceRecords()
    {
        try {
            if (!$this->connect()) {
                return false;
            }
            
            // Command to get attendance data (adjust based on MB360 protocol)
            $command = "GET_ATTENDANCE_DATA\r\n";
            $response = $this->sendCommand($command);
            
            $this->disconnect();
            
            return $this->parseAttendanceData($response);
        } catch (Exception $e) {
            Log::error("Error getting attendance records: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Parse attendance data response
     */
    private function parseAttendanceData($data)
    {
        $records = [];
        $lines = explode("\n", trim($data));
        
        foreach ($lines as $line) {
            if (!empty($line)) {
                // Parse each line based on MB360 data format
                // Format: UserID,DateTime,VerifyMode,Status
                $parts = explode(',', $line);
                if (count($parts) >= 4) {
                    $records[] = [
                        'user_id' => $parts[0],
                        'datetime' => $parts[1],
                        'verify_mode' => $parts[2], // 1=Fingerprint, 2=Face, 3=Card
                        'status' => $parts[3] // 0=Check In, 1=Check Out
                    ];
                }
            }
        }
        
        return $records;
    }
    
    /**
     * Add user to device
     */
    public function addUser($userId, $name, $fingerprintTemplate = null, $faceTemplate = null)
    {
        try {
            if (!$this->connect()) {
                return false;
            }
            
            $command = "ADD_USER:{$userId},{$name}";
            if ($fingerprintTemplate) {
                $command .= ",{$fingerprintTemplate}";
            }
            if ($faceTemplate) {
                $command .= ",{$faceTemplate}";
            }
            $command .= "\r\n";
            
            $response = $this->sendCommand($command);
            $this->disconnect();
            
            return strpos($response, 'SUCCESS') !== false;
        } catch (Exception $e) {
            Log::error("Error adding user: " . $e->getMessage());
            return false;
        }
    }
}
