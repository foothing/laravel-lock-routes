<?php namespace Foothing\Wrappr\Lock;

use BeatSwitch\Lock\Manager;
use BeatSwitch\Lock\Permissions\Restriction;
use BeatSwitch\Lock\Roles\SimpleRole;
use Foothing\Wrappr\Permissions\Collection;
use Foothing\Wrappr\Permissions\Permission;
use Foothing\Wrappr\Providers\Permissions\AbstractProvider;

class LockProvider extends AbstractProvider {
    /**
     * @var \BeatSwitch\Lock\Manager
     */
    protected $manager;

    /**
     * @var string
     */
    protected $subject;

    /**
     * The subject user.
     * @var object
     */
    protected $user;

    /**
     * The subject role.
     * @var string
     */
    protected $role;

	function __construct(Manager $manager) {
		$this->manager = $manager;
	}

    /**
     * Check the given user has the given permission.
     *
     * @param      $user
     * @param      $permissions
     * @param null $resourceName
     * @param null $resourceId
     *
     * @return bool|mixed
     */
    function check($user, $permissions, $resourceName = null, $resourceId = null) {
		return $this->manager->caller($user)->can($permissions, $resourceName, (int)$resourceId);
	}

    /**
     * Perform check on the subject.
     *
     * @param      $permissions
     * @param null $resourceName
     * @param null $resourceId
     *
     * @return bool
     */
    function can($permissions, $resourceName = null, $resourceId = null) {
        return $this->caller()->can($permissions, $resourceName, $resourceId);
    }

    /**
     * Fluent method to work on users.
     * @param  $user
     * @return $this
     */
    function user($user) {
        $this->subject = 'user';
        $this->user = $user;
        return $this;
    }

    /**
     * Fluent method to work on roles.
     * @param  $role
     * @return $this
     */
    function role($role) {
        $this->subject = 'role';
        $this->role = $role;
        return $this;
    }

    /**
     * Return all specific permissions for the given subject.
     *
     * @return mixed
     */
    function all() {
        if ( $this->subject == 'user' ) {
            return $this->getUserPermissions( $this->user );
        } else if ( $this->subject == 'role' ) {
            return $this->getRolePermissions( $this->role );
        }
    }

    /**
     * Grant the given permissions to the given subject.
     *
     * @param      $permissions
     * @param null $resourceName
     * @param null $resourceId
     *
     * @return mixed
     */
    function grant($permissions, $resourceName = null, $resourceId = null) {
        return $this->lock()->allow($permissions, $resourceName, $resourceId);
    }

    /**
     * Revoke the given permissions from the given subject.
     *
     * @param      $permissions
     * @param null $resourceName
     * @param null $resourceId
     *
     * @return mixed
     */
    function revoke($permissions, $resourceName = null, $resourceId = null) {
        return $this->lock()->deny($permissions, $resourceName, $resourceId);
    }

    /**
     * Return the caller to work on.
     *
     * @return \BeatSwitch\Lock\Callers\CallerLock|\BeatSwitch\Lock\Roles\RoleLock|null
     */
    protected function caller() {
        if ($this->subject == 'user') {
            return $this->manager->caller($this->user);
        } elseif ($this->subject == 'role') {
            return $this->manager->role($this->role);
        }
        return null;
    }

    /**
     * Return the lock instance to work on.
     *
     * @return \BeatSwitch\Lock\Callers\CallerLock|\BeatSwitch\Lock\Roles\RoleLock
     * @throws \Exception
     */
    protected function lock() {
        if ( $this->subject == 'user' ) {
           return $this->manager->caller( $this->user );
        } else if ( $this->subject == 'role' ) {
           return $this->manager->role( $this->role );
        } else {
            throw new \Exception("Caller not allowed: $this->subject");
        }
    }

    /**
     * Return a collection of user based permissions.
     *
     * @param $user
     *
     * @return Collection
     */
    protected function getUserPermissions($user) {
        $permissions = $this->manager->getDriver()->getCallerPermissions($user);
        $collection = new Collection();
        foreach ($permissions as $lockPermission) {
            if ($lockPermission instanceof Restriction) {
                continue;
            }
            $collection->allow($lockPermission->getAction(), $lockPermission->getResourceType(), $lockPermission->getResourceId());
        }
        return $collection;
    }

    /**
     * Return a collection of role based permissions.
     *
     * @param $roleName
     *
     * @return Collection
     */
    protected function getRolePermissions($roleName) {
        $role = new SimpleRole($roleName);
        $permissions = $this->manager->getDriver()->getRolePermissions($role);
        $collection = new Collection();
        foreach ($permissions as $lockPermission) {
            if ($lockPermission instanceof Restriction) {
                continue;
            }
            $collection->allow($lockPermission->getAction(), $lockPermission->getResourceType(), $lockPermission->getResourceId());
        }
        return $collection;
    }
}
