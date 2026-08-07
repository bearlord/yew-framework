<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Security\Beans;

class Principal
{
    protected array $roles = [];

    protected array $permissions = [];

    protected ?string $username = null;

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function addRole(string $role): void
    {
        $this->roles[] = $role;
    }

    public function addRoles(array $roles): void
    {
        $this->roles = array_merge($this->roles, $roles);
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles);
    }

    public function hasAnyRole(array $roles): bool
    {
        return !empty(array_intersect($roles, $this->roles));
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function addPermission(string $permission): void
    {
        $this->permissions[] = $permission;
    }

    public function addPermissions(array $permissions): void
    {
        $this->permissions = array_merge($this->permissions, $permissions);
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions);
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }
}
