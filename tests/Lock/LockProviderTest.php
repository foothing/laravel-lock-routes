<?php namespace Tests\Foothing\Wrappr\Lock;

use Tests\Foothing\Wrappr\Mocks\User;

class LockProviderTest extends \Orchestra\Testbench\TestCase {

    protected $provider;

    protected $driver;

	protected function getEnvironmentSetUp($app) {
		$app['config']->set('database.default', 'testbench');
		$app['config']->set('database.connections.testbench', [
			'driver'   	=> 'mysql',
			'host' 		=> 'localhost',
			'database' 	=> 'routes',
			'username'	=> 'routes',
			'password'	=> 'routes',
			'charset'   => 'utf8',
			'collation' => 'utf8_unicode_ci',
			'prefix'    => '',
			'strict'    => false,
		]);
	}

	protected function getPackageProviders($app) {
		$app['config']->set('lock.driver', 'database');
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
        \DB::table('lock_permissions')->truncate();
    }

    function test_grant_is_not_necessary() { }

    function test_revoke_user() {
        $user = new User();
        $this->provider->user($user)->revoke('drink', 'beer', 1);
        $this->provider->user($user)->grant('drink', 'beer', 1);
        $this->assertEquals(1, $this->provider->user($user)->all()->countAllowed());
        $this->assertEquals(0, $this->provider->user($user)->all()->countDenied());
        $this->provider->user($user)->revoke('drink', 'beer', 1);
        $this->assertEquals(0, $this->provider->user($user)->all()->countAllowed());
        $this->assertEquals(0, $this->provider->user($user)->all()->countDenied());
    }

    function test_revoke_role() {
        $this->provider->role('admin')->revoke('drink', 'beer', 1);
        $this->provider->role('admin')->grant('drink', 'beer', 1);
        $this->assertEquals(1, $this->provider->role('admin')->all()->countAllowed());
        $this->assertEquals(0, $this->provider->role('admin')->all()->countDenied());
        $this->provider->role('admin')->revoke('drink', 'beer', 1);
        $this->assertEquals(0, $this->provider->role('admin')->all()->countAllowed());
        $this->assertEquals(0, $this->provider->role('admin')->all()->countDenied());
    }

    function test_revoke_specific_resource() {
        $user = new User();
        $this->provider->user($user)->grant('drink', 'beer', 1);
        $this->provider->user($user)->grant('drink', 'coffee');
        $permissions = $this->provider->user($user)->all();
        $this->assertEquals(2, $permissions->countAllowed());
        $this->provider->user($user)->revoke('drink', 'beer', 1);
        $this->provider->user($user)->revoke('drink', 'coffee');
        $permissions = $this->provider->user($user)->all();
        $this->assertEquals(1, $permissions->countDenied());
    }

    function test_user_all_returns_empty() {
        $user = new User();
        $permissions = $this->provider->user($user)->all();
        $this->assertEquals(0, $permissions->countAllowed());
        $this->assertEquals(0, $permissions->countDenied());
    }

    function test_user_all_returns_allowed() {
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

    function test_user_all_returns_denied() {
        $user = new User();
        $this->provider->user($user)->grant('drink', 'beer', 1);
        $this->provider->user($user)->grant('drink', 'coffee');
        $this->provider->user($user)->grant('eat');
        $this->provider->user($user)->revoke('wakeup');
        $permissions = $this->provider->user($user)->all();

        $this->assertEquals(1, $permissions->countDenied());
        $this->assertEquals('wakeup', $permissions->getDenied(0)->name);
        $this->assertNull($permissions->getDenied(0)->resourceName);
        $this->assertNull($permissions->getDenied(0)->resourceId);

        $this->provider->user($user)->revoke('drink', 'beer', 1);
        $this->provider->user($user)->revoke('drink', 'coffee');
        $this->provider->user($user)->revoke('eat');
        $permissions = $this->provider->user($user)->all();
        $this->assertEquals(3, $permissions->countDenied());
    }

    function test_role_all_returns_empty() {
        $permissions = $this->provider->role('admin')->all();
        $this->assertEquals(0, $permissions->countAllowed());
        $this->assertEquals(0, $permissions->countDenied());
    }

    function test_role_all_returns_allowed() {
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

	public function tearDown() {
		\Mockery::close();
	}
}