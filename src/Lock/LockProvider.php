<?php namespace Foothing\Wrappr\Lock;

use BeatSwitch\Lock\Manager;
use Foothing\Wrappr\Permissions\Collection;
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

    protected $user;

    protected $role;

	function __construct(Manager $manager) {
		$this->manager = $manager;
	}

	function check($user, $permissions, $resourceName = null, $resourceId = null) {
		return $this->manager->caller($user)->can($permissions, $resourceName, (int)$resourceId);
	}

    function user($user) {
        $this->subject = 'user';
        $this->user = $user;
        return $this;
    }

    function role($role) {
        $this->subject = 'role';
        $this->role = $role;
        return $this;
    }

    /**
     * Return all permissions for the given subject.
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

        // Override the driver behaviour here. The idea is that when a permission
        // is very specific and bind an user to a specific resource,
        // keep it stored as a restriction makes no sense.
        // @TODO Maybe a lock driver implementation on purpose.

        if ($resourceName && $resourceId && $this->subject == 'user') {
           $this->revokeFromUser($permissions, $resourceName, $resourceId);
        }

        else if ($resourceName && $resourceId && $this->subject == 'role') {
            $this->revokeFromRole($permissions, $resourceName, $resourceId);
        }

        // When a permission is at higher level (i.e. 'admin' or 'create posts')
        // we can rely on the driver implementation.
        else {
            $this->lock()->deny($permissions, $resourceName, $resourceId);
        }
    }

    protected function revokeFromUser($permissions, $resourceName, $resourceId) {
        \DB::table('lock_permissions')
            ->where('caller_id', $this->user->getCallerId())
            ->whereIn('action', (array)$permissions)
            ->where('resource_type', $resourceName)
            ->where('resource_id', $resourceId)
            ->delete();
    }

    protected function revokeFromRole($permissions, $resourceName, $resourceId) {
        \DB::table('lock_permissions')
            ->where('role', $this->role)
            ->whereIn('action', (array)$permissions)
            ->where('resource_type', $resourceName)
            ->where('resource_id', $resourceId)
            ->delete();
    }

    protected function lock() {
        if ( $this->subject == 'user' ) {
           return $this->manager->caller( $this->user );
        } else if ( $this->subject == 'role' ) {
           return $this->manager->role( $this->role );
        } else {
            throw new \Exception("Caller not allowed: $this->subject");
        }
    }

    protected function getUserPermissions($user) {
        $permissions = \DB::table('lock_permissions')
            ->groupBy('type')
            ->groupBy('action')
            ->groupBy('resource_type')
            ->groupBy('resource_id')
            ->orderBy('role')
            ->get();

        $lock = $this->manager->caller($user);

        $collection = new Collection();

        foreach ($permissions as $permission) {
            if ($lock->can($permission->action, $permission->resource_type, (int)$permission->resource_id)) {
                $collection->allow($permission->action, $permission->resource_type, $permission->resource_id);
            } else {
                $collection->deny($permission->action, $permission->resource_type, $permission->resource_id);
            }
        }
        return $collection;
    }

    protected function getRolePermissions($role) {
        $permissions = \DB::table('lock_permissions')
            ->groupBy('action')
            ->groupBy('resource_type')
            ->groupBy('resource_id')
            ->orderBy('role')
            ->get();

        $collection = new Collection();

        foreach ($permissions as $permission) {
            if ($this->manager->role($role)->can($permission->action, $permission->resource_type, (int)$permission->resource_id)) {
                $collection->allow($permission->action, $permission->resource_type, $permission->resource_id);
            } else {
                $collection->deny($permission->action, $permission->resource_type, $permission->resource_id);
            }
        }

        return $collection;
    }
}
