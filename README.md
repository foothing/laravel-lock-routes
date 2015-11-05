# Laravel Lock Routes
This is a Laravel 5 package that allows to bind `Lock` permissions
to routes.

It implements a `Lock` provider for [Laravel Wrappr](https://github.com/foothing/laravel-wrappr).

## Install
```
composer require foothing/laravel-lock-routes
```

## Setup
Add the `Wrappr` service provider in your`config/app.php` providers array.
```php
'providers' => [
	// ...
	Foothing\Wrappr\WrapprServiceProvider::class
]
```

Publish package configuration and migration files
```
php artisan vendor:publish --provider="Foothing\Wrappr\WrapprServiceProvider"
```

Update your `config/wrappr.php`
```php
'permissionsProvider' => 'Foothing\Wrappr\Lock\LockProvider',
'usersProvider' => 'Foothing\Wrappr\Providers\Users\DefaultProvider',
```

## Middlewares
There are two middleware use cases. You can use within Laravel Router or
with complex pattern routes. More info in the [Wrappr documentation](https://github.com/foothing/laravel-wrappr).

### Enable Laravel Router Middleware
```php
protected $routeMiddleware = [
	'wrappr.check' => 'Foothing\Wrappr\Middleware\CheckRoute',
];
```

Use the CheckRoute Middleware to control access to your routes
like the following **routes.php**:
```php
Route::get('api/users', ['middleware:wrappr.check:admin.users', function() {
	// Access is allowed for the users with the 'admin.users' permission
}]);
```

The `CheckRoute` Middleware accepts 3 arguments:
- the required permission
- an optional resource name, i.e. 'user'
- an optional resource identifier (integer)

Example:
```php
Route::get('api/users/{id?}', ['middleware:wrappr.check:read.users,user,1', function() {
	// Access is allowed for the users with the 'read.users' permission on
	// the 'user' resource with the {id} identifier
}]);
```

Also, the Middleware can handle your route arguments. Consider the following
```php
Route::get('api/users/{id?}', ['middleware:wrappr.check:read.users,user,{id}', function() {
	// Access is allowed for the users with the 'read.users' permission on
	// the 'user' resource with the {id} identifier
}]);
```
When you pass a resource identifier within the brackets, the middleware will
try to retrieve the value from the http request automatically.

### Enable custom routes Middleware
When you're not able to fine-control at routes definition level, there's
an alternative way of handling permissions. Think about a global
RESTful controller like the following:

```php
Route::controller('api/v1/{args?}', 'FooController');
```

Assume that your controller applies a variable pattern to handle
the routes, like for example
```php
GET /api/v1/resources/users
GET /api/v1/resources/posts
POST /api/v1/services/publish/post
```
In this case you won't be able to bind permissions with the previous method, so
the `CheckPath` middleware comes to help. In order to enable this behaviour you need
some additional setup step.

Add the global Middleware to your `App\Http\Kernel` like this
```php
protected $middleware = [
	\Foothing\Wrappr\Middleware\CheckPath::class
];
```

Now you can configure your routes in the config file or programmatically
following [the Wrappr routes documentation](https://github.com/foothing/laravel-wrappr).

## ACL abstraction layer
If you want more decoupling from your app and the acl library (Lock in this case)
you can use the acl manipulation methods that are implemented in the provider.
See example:

```php
$this->provider = \App::make('Foothing\Wrappr\Providers\Permissions\PermissionProviderInterface');

// Returns all the user's permissions
$this->provider->user($user)->all();

// Returns all the role's permissions
$this->provider->role('admin')->all();

// Allow the user to edit post with id = 1
$this->provider->user($user)->grant('edit', 'post', 1);

// Revoke previous permission
$this->provider->user($user)->revoke('edit', 'post', 1);

// Return false.
$this->provider->check($user, 'edit', 'post', 1);
```

## License
[MIT](https://opensource.org/licenses/MIT)