# WebSocket Notification System for New User Creation

This document explains the implementation of real-time WebSocket notifications that are triggered when new users are created in the Laravel application.

## Overview

The system uses Laravel's broadcasting capabilities with Laravel WebSockets (beyondcode/laravel-websockets) and Pusher protocol to send real-time notifications to admin users whenever a new user is created.

## Installation & Setup

### 1. Install Required Packages

#### Backend Dependencies

```bash
# Install Laravel WebSockets package
composer require beyondcode/laravel-websockets

# Install Pusher PHP Server
composer require pusher/pusher-php-server

# Publish WebSocket configuration (optional)
php artisan vendor:publish --provider="BeyondCode\LaravelWebSockets\WebSocketsServiceProvider" --tag="migrations"

# Run migrations for WebSocket statistics (optional)
php artisan migrate

# Publish WebSocket config file (optional)
php artisan vendor:publish --provider="BeyondCode\LaravelWebSockets\WebSocketsServiceProvider" --tag="config"
```

#### Frontend Dependencies

```bash
# Install Laravel Echo and Pusher JS
npm install laravel-echo pusher-js

# Or using Yarn
yarn add laravel-echo pusher-js
```

### 2. Configure Environment Variables

Update your `.env` file:

```env
# Broadcasting Configuration
BROADCAST_DRIVER=pusher

# Pusher/WebSocket Configuration
PUSHER_APP_ID=local-app-id
PUSHER_APP_KEY=local-app-key
PUSHER_APP_SECRET=local-app-secret
PUSHER_HOST=127.0.0.1
PUSHER_PORT=6001
PUSHER_SCHEME=http
PUSHER_APP_CLUSTER=mt1

# Vite Frontend Variables
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```

### 3. Start Services

```bash
# Start Laravel WebSocket Server
php artisan websockets:serve

# In another terminal, start your Laravel application
php artisan serve

# In another terminal, build frontend assets
npm run dev
# or for production
npm run build
```

## Architecture Components

### 1. Backend Components

#### Event Class: `UserCreatedRecently`
**Location:** `app/Events/UserCreatedRecently.php`

```php
<?php
namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserCreatedRecently implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public User $user) {}

    // Only broadcast if user was created within the last hour
    public function broadcastWhen(): bool
    {
        return $this->user->created_at->gte(now()->subHour());
    }

    // Broadcast to admin role channel
    public function broadcastOn(): array
    {
        return [new PrivateChannel('role.1.notifications')];
    }

    // Event name for frontend
    public function broadcastAs(): string
    {
        return 'UserCreatedRecently';
    }

    // Data sent to frontend
    public function broadcastWith(): array
    {
        return [
            'id' => $this->user->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'created_at' => $this->user->created_at->toDateTimeString(),
            'message' => "New user created recently: {$this->user->name}",
        ];
    }
}
```

**Key Features:**
- Implements `ShouldBroadcast` interface for automatic broadcasting
- Uses `broadcastWhen()` to only notify for users created within the last hour
- Broadcasts to private channel `role.1.notifications` (admin-only)
- Sends user details and formatted message to frontend

#### Observer Class: `UserObserver`
**Location:** `app/Observers/UserObserver.php`

```php
<?php
namespace App\Observers;

use App\Events\UserCreatedRecently;
use App\Models\User;

class UserObserver
{
    public function created(User $user): void
    {
        event(new UserCreatedRecently($user));
    }
}
```

**Purpose:**
- Automatically triggers the `UserCreatedRecently` event whenever a new user is created
- Registered in `AppServiceProvider` to observe all User model events

#### Service Provider Registration
**Location:** `app/Providers/AppServiceProvider.php`

```php
public function boot(): void
{
    User::observe(\App\Observers\UserObserver::class);
}
```

#### Channel Authorization
**Location:** `routes/channels.php`

```php
Broadcast::channel('role.1.notifications', function ($user) {
    // Allow only admins (role = 1)
    Log::info('Checking if user is admin', ['user' => $user, 'role' => $user->role ?? 'no role']);
    return (int) ($user->role ?? 0) === 1;
});
```

**Security:**
- Only users with `role = 1` (admins) can subscribe to the notification channel
- Includes logging for debugging authorization issues

### 2. Frontend Components

#### WebSocket Configuration
**Location:** `resources/js/bootstrap.js`

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
    wsHost: import.meta.env.VITE_PUSHER_HOST ?? `ws-${import.meta.env.VITE_PUSHER_APP_CLUSTER}.pusher.com`,
    wsPort: import.meta.env.VITE_PUSHER_PORT ?? 6001,
    wssPort: import.meta.env.VITE_PUSHER_PORT ?? 6001,
    forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'http') === 'https',
    encrypted: false,
    disableStats: true,
    enabledTransports: ['ws', 'wss'],
});
```

#### Admin Notification Handler
**Location:** `resources/js/admin-notify.js`

```javascript
document.addEventListener('DOMContentLoaded', function() {
    if (window.Echo) {
        console.log('Setting up admin notifications...');
        
        window.Echo.private('role.1.notifications')
            .listen('.UserCreatedRecently', (e) => {
                console.log('User created event received:', e);
                // e contains: id, name, email, created_at, message
                alert(e.message + " (created at: " + e.created_at + ")");
            })
            .error((error) => {
                console.error('WebSocket connection error:', error);
            });
    } else {
        console.error('Echo is not available. Make sure WebSocket connection is properly configured.');
    }
});
```

**Features:**
- Listens to the private channel `role.1.notifications`
- Handles the `UserCreatedRecently` event
- Currently shows an alert (can be customized for better UX)
- Includes error handling for connection issues

#### Frontend Integration
**Location:** `resources/views/layouts/app.blade.php`

```blade
@auth
  @if(auth()->user()->role === 1)
      @vite(['resources/js/admin-notify.js'])
      <script>
          console.log('Welcome Admin!');
      </script>
  @else
      <script src="{{ asset('pro_js/jquery_3.7.1.min.js') }}"></script>
  @endif
@endauth
```

**Logic:**
- Only loads admin notification script for users with `role = 1`
- Ensures non-admin users don't receive unnecessary JavaScript

### 3. Configuration Files

#### Broadcasting Configuration
**Location:** `config/broadcasting.php`

```php
'default' => env('BROADCAST_DRIVER', 'null'),

'connections' => [
    'pusher' => [
        'driver' => 'pusher',
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'app_id' => env('PUSHER_APP_ID'),
        'options' => [
            'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
            'host' => env('PUSHER_HOST', '127.0.0.1'),
            'port' => env('PUSHER_PORT', 6001),
            'scheme' => env('PUSHER_SCHEME', 'http'),
            'encrypted' => false,
            'useTLS' => env('PUSHER_SCHEME', 'http') === 'https',
        ],
    ],
],
```

## Data Flow

1. **User Creation Trigger**
   - New user is created via any method (registration, admin creation, API, etc.)
   - Laravel's Eloquent ORM triggers the `created` event

2. **Observer Activation**
   - `UserObserver::created()` method is automatically called
   - Observer dispatches `UserCreatedRecently` event

3. **Event Broadcasting**
   - Event checks `broadcastWhen()` condition (user created within last hour)
   - If condition passes, event is broadcast to `role.1.notifications` channel
   - Event data is serialized using `broadcastWith()` method

4. **Channel Authorization**
   - WebSocket server checks channel authorization
   - Only users with `role = 1` are allowed to subscribe

5. **Frontend Reception**
   - Admin users' browsers receive the event via WebSocket connection
   - JavaScript handler processes the event data
   - Notification is displayed to admin user

## Complete Setup Guide

### Step 1: Install All Dependencies

```bash
# Backend packages
composer require beyondcode/laravel-websockets pusher/pusher-php-server

# Frontend packages
npm install laravel-echo pusher-js

# Install other dependencies if not already installed
composer install
npm install
```

### Step 2: Publish and Configure WebSockets

```bash
# Publish WebSocket configuration
php artisan vendor:publish --provider="BeyondCode\LaravelWebSockets\WebSocketsServiceProvider" --tag="migrations"

# Run migrations (optional - for statistics)
php artisan migrate

# Publish config file (optional)
php artisan vendor:publish --provider="BeyondCode\LaravelWebSockets\WebSocketsServiceProvider" --tag="config"
```

### Step 3: Environment Configuration

Create/update your `.env` file:

```env
APP_NAME=Laravel
APP_ENV=local
APP_KEY=base64:your-app-key-here
APP_DEBUG=true
APP_URL=http://localhost

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Broadcasting Configuration
BROADCAST_DRIVER=pusher
QUEUE_CONNECTION=sync

# Pusher/WebSocket Configuration
PUSHER_APP_ID=local-app-id
PUSHER_APP_KEY=local-app-key
PUSHER_APP_SECRET=local-app-secret
PUSHER_HOST=127.0.0.1
PUSHER_PORT=6001
PUSHER_SCHEME=http
PUSHER_APP_CLUSTER=mt1

# Vite Frontend Variables
VITE_APP_NAME="${APP_NAME}"
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```

### Step 4: Start All Services

```bash
# Terminal 1: Start WebSocket Server
php artisan websockets:serve

# Terminal 2: Start Laravel Application
php artisan serve

# Terminal 3: Build Frontend Assets
npm run dev

# Optional Terminal 4: Start Queue Worker (if using queues)
php artisan queue:work
```

### Step 5: Create Test Data

```bash
# Create admin user via tinker
php artisan tinker
```

```php
// In tinker console
$admin = App\Models\User::create([
    'name' => 'Admin User',
    'email' => 'admin@example.com',
    'password' => bcrypt('password'),
    'role' => 1, // Admin role
]);

// Create test user to trigger notification
$user = App\Models\User::create([
    'name' => 'Test User',
    'email' => 'test@example.com',
    'password' => bcrypt('password'),
    'role' => 0, // Regular user
]);
```

## Testing the System

### 1. Access WebSocket Dashboard

Visit: `http://localhost:8000/laravel-websockets` (if using default Laravel serve port)

This dashboard shows:
- Connected clients
- Channel subscriptions
- Real-time statistics

### 2. Test User Creation

1. Login as admin user (`role = 1`)
2. Open browser developer console
3. Create a new user (via registration form, tinker, or API)
4. Check console for WebSocket messages
5. Verify alert notification appears

### 3. Debug Commands

```bash
# Check WebSocket server status with debug info
php artisan websockets:serve --debug

# Monitor Laravel logs
tail -f storage/logs/laravel.log

# Test broadcasting manually via tinker
php artisan tinker
>>> broadcast(new App\Events\UserCreatedRecently(App\Models\User::first()));
```

## Customization Options

### 1. Modify Notification Display

Replace the alert in `admin-notify.js`:

```javascript
.listen('.UserCreatedRecently', (e) => {
    // Custom toast notification
    showToast(e.message, 'success');
    
    // Or update notification badge
    updateNotificationBadge();
    
    // Or add to notification list
    addToNotificationList(e);
    
    // Or show modal
    showNotificationModal(e);
});

// Example toast function
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 5000);
}
```

### 2. Change Broadcast Conditions

Modify `broadcastWhen()` in `UserCreatedRecently.php`:

```php
public function broadcastWhen(): bool
{
    // Always broadcast
    return true;
    
    // Only for verified users
    return $this->user->email_verified_at !== null;
    
    // Only for specific roles
    return $this->user->role === 0; // Only regular users
    
    // Custom time window
    return $this->user->created_at->gte(now()->subMinutes(30));
}
```

### 3. Add More User Data

Extend `broadcastWith()` method:

```php
public function broadcastWith(): array
{
    return [
        'id' => $this->user->id,
        'name' => $this->user->name,
        'email' => $this->user->email,
        'role' => $this->user->role,
        'avatar' => $this->user->avatar_url ?? null,
        'created_at' => $this->user->created_at->toDateTimeString(),
        'formatted_date' => $this->user->created_at->diffForHumans(),
        'message' => "New user created: {$this->user->name}",
        'action_url' => route('admin.users.show', $this->user->id),
    ];
}
```

### 4. Multiple Admin Roles

Modify channel authorization in `routes/channels.php`:

```php
Broadcast::channel('role.{roleId}.notifications', function ($user, $roleId) {
    // Allow multiple admin roles
    $adminRoles = [1, 2, 3]; // Admin, Super Admin, Moderator
    return in_array($user->role, $adminRoles) && $user->role >= $roleId;
});

// Or specific role-based channels
Broadcast::channel('admin.notifications', function ($user) {
    return $user->role === 1; // Only admins
});

Broadcast::channel('moderator.notifications', function ($user) {
    return in_array($user->role, [1, 2]); // Admins and moderators
});
```

## Troubleshooting

### Common Issues

1. **WebSocket Connection Failed**
   ```bash
   # Check if WebSocket server is running
   php artisan websockets:serve
   
   # Verify port is not in use
   netstat -an | findstr :6001
   
   # Check firewall settings
   # Ensure port 6001 is open
   ```

2. **No Notifications Received**
   - Verify user has `role = 1`
   - Check browser console for JavaScript errors
   - Confirm channel authorization is working
   - Check Laravel logs for broadcasting errors

3. **Event Not Broadcasting**
   ```bash
   # Ensure correct broadcast driver
   php artisan config:cache
   
   # Check if observer is registered
   php artisan tinker
   >>> App\Models\User::getObservableEvents()
   
   # Test event manually
   >>> event(new App\Events\UserCreatedRecently(App\Models\User::first()));
   ```

4. **Frontend Assets Not Loading**
   ```bash
   # Clear and rebuild assets
   npm run build
   php artisan view:clear
   php artisan config:clear
   ```

### Debug Checklist

- [ ] WebSocket server is running (`php artisan websockets:serve`)
- [ ] Laravel application is running (`php artisan serve`)
- [ ] Frontend assets are built (`npm run dev` or `npm run build`)
- [ ] Environment variables are set correctly
- [ ] User has admin role (`role = 1`)
- [ ] Browser console shows no JavaScript errors
- [ ] WebSocket dashboard shows connections
- [ ] Laravel logs show no errors

## Security Considerations

1. **Channel Authorization**: Only admin users can subscribe to notification channels
2. **Data Filtering**: Sensitive user data (passwords, tokens) are excluded from broadcasts
3. **Rate Limiting**: Consider implementing rate limiting for user creation to prevent spam
4. **SSL/TLS**: Use HTTPS and WSS in production environments
5. **CORS Configuration**: Ensure proper CORS settings for WebSocket connections

## Production Deployment

### 1. Environment Configuration

```env
# Production settings
APP_ENV=production
APP_DEBUG=false
BROADCAST_DRIVER=pusher

# Use secure WebSocket connection
PUSHER_SCHEME=https
PUSHER_PORT=443

# Production WebSocket settings
VITE_PUSHER_SCHEME=https
VITE_PUSHER_PORT=443
```

### 2. Process Management

Use a process manager like Supervisor to keep WebSocket server running:

```ini
[program:websockets]
command=php /path/to/your/app/artisan websockets:serve
directory=/path/to/your/app
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/path/to/your/app/storage/logs/websockets.log
```

### 3. Queue Configuration

For better performance in production:

```env
QUEUE_CONNECTION=redis
```

```bash
# Start queue workers
php artisan queue:work --daemon
```

## Performance Optimization

1. **Queue Broadcasting**: Use Redis/database queues for better performance
2. **Event Filtering**: Use `broadcastWhen()` to reduce unnecessary broadcasts
3. **Connection Pooling**: Configure WebSocket server for high concurrent connections
4. **Caching**: Cache user role information to reduce database queries
5. **Asset Optimization**: Use `npm run build` for production assets

## Dependencies Summary

### Backend Dependencies
```json
{
    "beyondcode/laravel-websockets": "^1.14",
    "pusher/pusher-php-server": "^7.2"
}
```

### Frontend Dependencies
```json
{
    "laravel-echo": "^2.2.0",
    "pusher-js": "^8.4.0"
}
```

## File Structure Summary

```
app/
├── Events/
│   └── UserCreatedRecently.php     # Broadcast event
├── Models/
│   └── User.php                    # User model with role field
├── Observers/
│   └── UserObserver.php            # Triggers events on user creation
└── Providers/
    └── AppServiceProvider.php      # Registers observer

config/
└── broadcasting.php                # Broadcasting configuration

resources/
├── js/
│   ├── admin-notify.js            # Admin notification handler
│   └── bootstrap.js               # WebSocket setup
└── views/
    └── layouts/
        └── app.blade.php          # Includes admin scripts

routes/
└── channels.php                   # Channel authorization

package.json                       # Frontend dependencies
composer.json                      # Backend dependencies
.env                              # Environment configuration
```

This WebSocket notification system provides real-time updates to admin users whenever new users are created, enhancing the administrative experience with immediate feedback on user registration activities.

## Quick Start Commands

```bash
# 1. Install packages
composer require beyondcode/laravel-websockets pusher/pusher-php-server
npm install laravel-echo pusher-js

# 2. Configure environment
cp .env.example .env
# Edit .env with WebSocket settings

# 3. Start services
php artisan websockets:serve &
php artisan serve &
npm run dev

# 4. Test the system
php artisan tinker
# Create admin and test users as shown above
```