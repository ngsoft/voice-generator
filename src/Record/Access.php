<?php

namespace Record;

use Enum\AccessGroup;
use Enum\Permission;
use Interfaces\HasOpenApiDataModel;
use Sql\ActiveRecord;
use Sql\Event\CreateEntity;
use Sql\Event\EntityEvent;
use Sql\Event\UpdateEntity;
use Traits\HasData;
use Traits\HasId;
use Traits\HasUpdatedAt;
use Traits\OpenApiHelperTrait;
use View\AccessResponse;

class Access extends ActiveRecord implements HasOpenApiDataModel
{
    use HasId, HasUpdatedAt, HasData, OpenApiHelperTrait {
        HasUpdatedAt::initialize insteadof HasData;
    }

    protected static $mapping           = ['user_id' => 'user'];
    protected static $hidden            = ['user'];
    protected static $nullable          = ['user'];
    protected static $types             = ['user' => User::class, 'permission' => Permission::class, 'access_group' => AccessGroup::class];

    protected ?User $user               = null;
    protected Permission $permission    = Permission::Read;
    protected AccessGroup $access_group = AccessGroup::Api;

    public static function getOpenApiDataModel(): string
    {
        return AccessResponse::class;
    }

    /**
     * @param array{user?:int|User,permission?:int|Permission,access_group?:AccessGroup|string,data?:array<string,mixed>} $data
     * @param ?static                                                                                                     $instance
     *
     * @return static
     */
    public static function make(array $data = [], $instance = null)
    {
        if (isset($data['permission']))
        {
            if ($data['permission'] instanceof Permission)
            {
                $data['permission'] = $data['permission']->value;
            }
        }

        if (isset($data['access_group']))
        {
            if ($data['access_group'] instanceof AccessGroup)
            {
                $data['access_group'] = $data['access_group']->value;
            }
        }

        if (isset($data['user']))
        {
            if ($data['user'] instanceof User)
            {
                $data['user'] = $data['user']->getId();
            }
        }

        if (isset($data['data']))
        {
            if ( ! is_string($data['data']))
            {
                $data['data'] = json_encode($data['data']);
            }
        }

        return parent::make($data, $instance);
    }

    public function export()
    {
        return array_replace(parent::export(), [
            'permission_type' => strtolower($this->permission->name),
            'user_id'         => $this->user?->getId(),
        ]);
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): Access
    {
        $this->user = $user;
        return $this;
    }

    public function getPermission(): Permission
    {
        return $this->permission;
    }

    public function setPermission(Permission $permission): Access
    {
        $this->permission = $permission;
        return $this;
    }

    public function getAccessGroup(): AccessGroup
    {
        return $this->access_group;
    }

    public function setAccessGroup(AccessGroup $access_group): Access
    {
        $this->access_group = $access_group;
        return $this;
    }

    /**
     * @param Access $other
     *
     * @return bool
     */
    public function isSame(Access $other): bool
    {
        if ($this->equals($other))
        {
            return true;
        }

        if ($this->access_group === $other->access_group && $this->permission === $other->permission)
        {
            if (null === $other->user || null === $this->user || $this->user->getId() === $other->user->getId())
            {
                return true;
            }
        }
        return false;
    }

    protected function initialize()
    {
        if ( ! isset(static::$types['updated_at']))
        {
            static::$hidden[]            = 'updated_at';
            static::$types['updated_at'] = \DateTimeImmutable::class;
            static::$hidden[]            = 'created_at';
            static::$types['created_at'] = \DateTimeImmutable::class;
        }
        $this->initialize_metadata();

        $this->subscribe(
            [CreateEntity::class, UpdateEntity::class],
            fn (EntityEvent $event) => null === $this->user && $this::delete($this), // remove orphaned data
            false
        );
    }
}
