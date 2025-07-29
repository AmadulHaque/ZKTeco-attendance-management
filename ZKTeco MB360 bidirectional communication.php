/**
 * 
 *  Method 1: Using MB360's Built-in HTTP Callback
 * 
*/

/*
    Integrating ZKTeco MB360 to Call Your Laravel API

    The ZKTeco MB360 can be configured to call your Laravel API when events occur (like attendance punches or door access). Here's how to set up this bidirectional communication:
    Method 1: Using MB360's Built-in HTTP Callback
    1. Configure the MB360 Device

        Access the device web interface:

            Navigate to http://[device-ip] (default IP is 192.168.1.201)

            Login with admin credentials

        Enable HTTP Callback:

            Go to Network Settings → HTTP Settings

            Enable HTTP Notification

            Set your API endpoint: http://your-laravel-app.com/api/mb360/events

            Set HTTP Method: POST

            Configure events to trigger notifications (attendance, door, etc.)

*/

// 2. Create API Endpoint in Laravel
// routes/api.php
Route::post('/mb360/events', [MB360Controller::class, 'handleDeviceEvent']);

// app/Http/Controllers/MB360Controller.php
public function handleDeviceEvent(Request $request)
{
    // Validate request comes from your device
    $validIp = config('zkteco.device_ip');
    if ($request->ip() !== $validIp) {
        Log::warning("Invalid IP accessing MB360 endpoint: " . $request->ip());
        abort(403);
    }

    $eventData = $request->validate([
        'event_id' => 'required|string',
        'event_type' => 'required|in:attendance,door,temperature',
        'user_id' => 'sometimes|string',
        'timestamp' => 'required|numeric',
        'temperature' => 'sometimes|numeric',
        'door_state' => 'sometimes|in:open,closed',
    ]);

    // Process different event types
    switch ($eventData['event_type']) {
        case 'attendance':
            Attendance::create([
                'user_id' => $eventData['user_id'],
                'device_id' => $request->ip(),
                'timestamp' => Carbon::createFromTimestamp($eventData['timestamp']),
                'type' => 'face' // or card/fingerprint
            ]);
            break;
            
        case 'door':
            DoorAccess::create([
                'user_id' => $eventData['user_id'] ?? null,
                'device_id' => $request->ip(),
                'action' => $eventData['door_state'],
                'timestamp' => Carbon::createFromTimestamp($eventData['timestamp'])
            ]);
            break;
            
        case 'temperature':
            if (isset($eventData['temperature'])) {
                TemperatureLog::create([
                    'user_id' => $eventData['user_id'] ?? null,
                    'device_id' => $request->ip(),
                    'value' => $eventData['temperature'],
                    'timestamp' => Carbon::createFromTimestamp($eventData['timestamp'])
                ]);
            }
            break;
    }

    return response()->json(['status' => 'success']);
}



/**
 * 
 * Method 2: Using Device SDK with Active Polling
 * If HTTP callback isn't reliable enough:
 * 
 */
    // app/Console/Commands/PollMB360Events.php
    public function handle()
    {
        $mb360 = new ZKTecoMB360Service(config('zkteco.device_ip'));
        
        if ($mb360->connect()) {
            // Get new events since last poll
            $lastId = Cache::get('last_mb360_event_id', 0);
            $events = $mb360->getNewEvents($lastId);
            
            foreach ($events as $event) {
                // Call your API internally
                Http::post('http://localhost/api/mb360/events', [
                    'event_id' => $event['id'],
                    'event_type' => $event['type'],
                    // ... other event data
                ]);
                
                $lastId = $event['id'];
            }
            
            Cache::put('last_mb360_event_id', $lastId);
            $mb360->disconnect();
        }
    }




/**
 * 
 *  Method 3: Using TCP Socket Communication
 *  For real-time bidirectional communication:
 *  Set up TCP server in Laravel:
 * 
 */


// app/Console/Commands/MB360SocketServer.php
public function handle()
{
    $server = stream_socket_server("tcp://0.0.0.0:8000", $errno, $errstr);
    
    if (!$server) {
        Log::error("MB360 Socket Server failed: $errstr ($errno)");
        return;
    }
    
    while ($conn = stream_socket_accept($server, -1)) {
        $data = fread($conn, 1024);
        
        // Process device message
        $event = json_decode($data, true);
        
        // Dispatch to your application
        ProcessMB360Event::dispatch($event);
        
        // Send response back to device
        fwrite($conn, json_encode(['status' => 'received']));
        fclose($conn);
    }
    
    fclose($server);
}

/*
    Configure MB360 to connect to this socket:

        Use the device's TCP client settings

        Point to your server IP and port 8000





    Troubleshooting

        Connection Issues:

            Verify device can ping your server

            Check firewall rules

            Test with Postman first

        Data Format Problems:

            MB360 may send XML instead of JSON

            Add content-type detection:
            php

    if ($request->isXml()) {
        $data = simplexml_load_string($request->getContent());
    }




*/




