<?php namespace Tests\Foothing\Wrappr\Lock;

use Tests\Foothing\Wrappr\Mocks\User;

class LockProviderTest extends \Orchestra\Testbench\TestCase
{
    protected $provider;

    protected $driver;

    protected function getEnvironmentSetUp($app) {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'    => 'mysql',
            'host'      => 'localhost',
            'database'  => 'routes',
            'username'  => 'routes',
            'password'  => 'routes',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
        ]);
    }

    protected function getPackageProviders($app) {
        $app['config']->set('lock.driver', 'database');
        $app['config']->set('lock.table', 'lock_permissions');
        $app['config']->set('wrappr.permissionsProvider', 'Foothing\Wrappr\Lock\LockProvider');
        $app['config']->set('wrappr.usersProvider', 'Foothing\Wrappr\Providers\Users\DefaultProvider');
        return ['Foothing\Wrappr\WrapprServiceProvider', 'BeatSwitch\Lock\Integrations\Laravel\LockServiceProvider'];
    }

    public function setUp() {
        parent::setUp();
        $this->artisan('migrate', [
            '--database'	=>	'testbench',
            '--realpath'	=> 	realpath(__DIR__ . '/../../vendor/beatswitch/lock-laravel/src/migrations')
        ]);
        $this->provider = $this->app->make('Foothing\Wrappr\Providers\Permissions\PermissionProviderInterface');
    }

    public function testGrantUser()
    {
        \Mockery::mock('BeatSwitch\Lock\Callers\CallerLock')->shouldReceive('allow');
        $this->provider->user(new User())->grant('pwn');
    }

    public function testGrantRole()
    {
        \Mockery::mock('BeatSwitch\Lock\Roles\RoleLock')->shouldReceive('allow');
        $this->provider->role('admin')->grant('pwn');
    }

    public function testCheck()
    {
        \Mockery::mock('BeatSwitch\Lock\Callers\CallerLock')->shouldReceive('can');
        $this->provider->check(new User(), 'drink', 'beer', 1);
    }

    public function testUserRevoke()
    {
        \Mockery::mock('BeatSwitch\Lock\Callers\CallerLock')->shouldReceive('deny');
        $this->provider->user(new User())->revoke('drink', 'beer', 1);
    }

    public function testRoleRevoke()
    {
        \Mockery::mock('BeatSwitch\Lock\Roles\RoleLock')->shouldReceive('deny');
        $this->provider->user(new User())->revoke('drink', 'beer', 1);
    }

    public function testUserCan()
    {
        \Mockery::mock('BeatSwitch\Lock\Callers\CallerLock')->shouldReceive('can');
        $this->provider->user(new User())->can('drink', 'beer', 1);
    }

    public function testRoleCan()
    {
        \Mockery::mock('BeatSwitch\Lock\Roles\RoleLock')->shouldReceive('can');
        $this->provider->role('admin')->can('drink', 'beer', 1);
    }

    public function testUserAll()
    {
        $user = new User();
        $this->provider->user($user)->revoke('drink', 'beer', 1);
        $this->provider->user($user)->grant('drink', 'beer', 1);
        $this->assertEquals(1, $this->provider->user($user)->all()->countAllowed());
        $this->provider->user($user)->revoke('drink', 'beer', 1);
        $this->assertEquals(0, $this->provider->user($user)->all()->countAllowed());
    }

    public function testRoleAll()
    {
        $this->provider->role('admin')->revoke('drink', 'beer', 1);
        $this->provider->role('admin')->grant('drink', 'beer', 1);
        $this->assertEquals(1, $this->provider->role('admin')->all()->countAllowed());
        $this->provider->role('admin')->revoke('drink', 'beer', 1);
        $this->assertEquals(0, $this->provider->role('admin')->all()->countAllowed());
    }

    public function testUserAllReturnsEmpty()
    {
        $user = new User();
        $permissions = $this->provider->user($user)->all();
        $this->assertEquals(0, $permissions->countAllowed());
    }

    public function testUserAllReturnsAllowed()
    {
        $user = new User();
        $this->provider->user($user)->grant('drink', 'beer', 1);
        $this->provider->user($user)->grant('drink', 'coffee');
        $this->provider->user($user)->grant('eat');
        $permissions = $this->provider->user($user)->all();

        $this->assertEquals(3, $permissions->countAllowed());
        $this->assertEquals('drink', $permissions->getAllowed(0)->name);
        $this->assertEquals('beer', $permissions->getAllowed(0)->resourceName);
        $this->assertEquals(1, $permissions->getAllowed(0)->resourceId);
        $this->assertEquals('drink', $permissions->getAllowed(1)->name);
        $this->assertEquals('coffee', $permissions->getAllowed(1)->resourceName);
        $this->assertEquals('eat', $permissions->getAllowed(2)->name);
    }

    public function testRoleAllReturnsEmpty()
    {
        $permissions = $this->provider->role('admin')->all();
        $this->assertEquals(0, $permissions->countAllowed());
    }

    public function testRoleAllReturnsAllowed() {
        $this->provider->role('admin')->grant('drink', 'beer', 1);
        $this->provider->role('admin')->grant('drink', 'coffee');
        $this->provider->role('admin')->grant('eat');
        $permissions = $this->provider->role('admin')->all();

        $this->assertEquals(3, $permissions->countAllowed());
        $this->assertEquals('drink', $permissions->getAllowed(0)->name);
        $this->assertEquals('beer', $permissions->getAllowed(0)->resourceName);
        $this->assertEquals(1, $permissions->getAllowed(0)->resourceId);
        $this->assertEquals('drink', $permissions->getAllowed(1)->name);
        $this->assertEquals('coffee', $permissions->getAllowed(1)->resourceName);
        $this->assertEquals('eat', $permissions->getAllowed(2)->name);
    }

    public function tearDown()
    {
        \Mockery::close();
        \DB::table('lock_permissions')->truncate();
    }
}
