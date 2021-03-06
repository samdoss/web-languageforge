<?php

namespace Api\Model\Shared\Rights;

class ProjectRoles extends RolesBase
{
    const MANAGER = 'project_manager';
    const CONTRIBUTOR = 'contributor';
    const NONE = 'none';

    public static function getRolesList() {
        $roles = array(
            self::MANAGER => "Manager",
            self::CONTRIBUTOR => "Contributor"
        );
        return $roles;
    }
}
