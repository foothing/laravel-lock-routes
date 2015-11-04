<?php namespace Tests\Foothing\Wrappr\Mocks;

use BeatSwitch\Lock\Callers\Caller;

class User implements Caller {

    /**
     * The type of caller
     *
     * @return string
     */
    public function getCallerType() {
        return 'users';
    }

    /**
     * The unique ID to identify the caller with
     *
     * @return int
     */
    public function getCallerId() {
        return 1;
    }

    /**
     * The caller's roles
     *
     * @return array
     */
    public function getCallerRoles() {
        return ['admin'];
    }
}