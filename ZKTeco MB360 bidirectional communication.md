```markdown
# ZKTeco MB360 Integration with Laravel API

This guide provides complete steps to configure your ZKTeco MB360 device to call your Laravel API when events occur.

## Prerequisites
- ZKTeco MB360 device connected to network
- Laravel application deployed and accessible via HTTPS
- Admin access to MB360 web interface
- PHP 8.0+ with Laravel 9+

## Step 1: Configure Device Network Settings

1. Access the device web interface:
   ```
   http://[DEVICE_IP] (default: 192.168.1.201)
   ```
2. Login with admin credentials (default: Admin/123456)

3. Navigate to:
   ```
   Network Settings → Basic Settings
   ```
   - Ensure device has static IP
   - Verify internet connectivity

## Step 2: Enable HTTP Callback on MB360

1. Go to:
   ```
   Advanced Settings → HTTP Settings
   ```

2. Configure HTTP Notification:
   ```
   ☑ Enable HTTP Notification
   Notification URL: https://yourdomain.com/api/mb360/events
   Method: POST
   Content-Type: application/json
   ```

3. Set Event Triggers:
   ```
   ☑ Attendance Events
   ☑ Door Events  
   ☑ Temperature Alerts
   ```

4. Add HTTP Headers (for authentication):
   ```
   X-Device-Id: MB360_001
   Authorization: Bearer [YOUR_SECRET_KEY]
   ```

5. Click "Save" and restart device

## Step 3: Create Laravel API Endpoint

1. Create new route in `routes/api.php`:
   ```php
   Route::post('/mb360/events', [MB360Controller::class, 'handleEvent'])
       ->middleware('auth.device');
   ```

2. Create middleware for device authentication:
   ```bash
   php artisan make:middleware DeviceAuth
   ```

   ```php
   // app/Http/Middleware/DeviceAuth.php
   public function handle($request, Closure $next)
   {
       if ($request->header('Authorization') !== 'Bearer '.config('services.mb360.secret')) {
           return response()->json(['error' => 'Unauthorized'], 401);
       }
       
       return $next($request);
   }
   ```

3. Register middleware in `app/Http/Kernel.php`:
   ```php
   protected $routeMiddleware = [
       'auth.device' => \App\Http\Middleware\DeviceAuth::class,
       // ...
   ];
   ```

## Step 4: Implement Event Handler

Create controller:
```bash
php artisan make:controller MB360Controller
```

```php
// app/Http/Controllers/MB360Controller.php
public function handleEvent(Request $request)
{
    $validated = $request->validate([
        'event_id' => 'required|string',
        'event_type' => 'required|in:attendance,door,temperature',
        'user_id' => 'nullable|string',
        'timestamp' => 'required|numeric',
        'data' => 'nullable|array'
    ]);

    // Process event based on type
    switch ($validated['event_type']) {
        case 'attendance':
            $this->processAttendance($validated);
            break;
            
        case 'door':
            $this->processDoorEvent($validated);
            break;
            
        case 'temperature':
            $this->processTemperature($validated);
            break;
    }

    return response()->json(['status' => 'success']);
}

private function processAttendance(array $data)
{
    Attendance::create([
        'device_id' => $request->header('X-Device-Id'),
        'user_id' => $data['user_id'],
        'timestamp' => Carbon::createFromTimestamp($data['timestamp']),
        'type' => $data['data']['verify_type'] ?? 'face'
    ]);
}

// Add other processing methods...
```

## Step 5: Configure Laravel Environment

1. Add to `.env`:
   ```
   MB360_DEVICE_SECRET=your_shared_secret_here
   MB360_ALLOWED_IPS=192.168.1.201
   ```

2. Add to `config/services.php`:
   ```php
   'mb360' => [
       'secret' => env('MB360_DEVICE_SECRET'),
       'allowed_ips' => explode(',', env('MB360_ALLOWED_IPS'))
   ],
   ```

## Step 6: Test the Integration

1. Manually trigger test event from device:
   ```
   Device Menu → Test → HTTP Notification Test
   ```

2. Check Laravel logs:
   ```bash
   tail -f storage/logs/laravel.log
   ```

3. Verify database records were created

## Step 7: Implement Response Handling (Optional)

Configure device to handle API responses:

1. In device HTTP settings:
   ```
   Success Condition: Contains "success"
   Retry Attempts: 3
   Retry Interval: 5 seconds
   ```

2. Update Laravel to return specific codes:
   ```php
   return response()->json([
       'status' => 'success',
       'device_should_alert' => false,
       'message' => 'Event processed'
   ]);
   ```

## Step 8: Schedule Regular Sync (Backup)

Create fallback scheduled command:

```bash
php artisan make:command SyncMB360Events
```

```php
// app/Console/Commands/SyncMB360Events.php
public function handle()
{
    $device = new ZKTecoMB360(config('services.mb360.ip'));
    
    if ($device->connect()) {
        $lastSynced = Cache::get('last_mb360_sync', 0);
        $events = $device->getEventsSince($lastSynced);
        
        foreach ($events as $event) {
            Http::withHeaders([
                'Authorization' => 'Bearer '.config('services.mb360.secret')
            ])->post(config('app.url').'/api/mb360/events', $event);
            
            $lastSynced = $event['timestamp'];
        }
        
        Cache::put('last_mb360_sync', $lastSynced);
        $device->disconnect();
    }
}
```

## Troubleshooting

| Issue | Solution |
|-------|----------|
| No events received | Verify device can reach your API endpoint |
| 401 Unauthorized | Check secret key matches in device and Laravel config |
| Timeout errors | Increase timeout in device HTTP settings |
| Data not saving | Check Laravel validation rules |
| Duplicate events | Implement event_id deduplication in handler |

## Security Recommendations

1. Use HTTPS for all communications
2. Rotate shared secrets periodically
3. Implement IP whitelisting
4. Log all incoming requests
5. Rate limit API endpoints

```
