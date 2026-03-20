<?php

namespace Record;

use Enum\Role;
use Interfaces\HasOpenApiDataModel;
use Sql\ActiveRecord;
use Sql\Event\CreateEntity;
use Traits\HasData;
use Traits\HasId;
use Traits\HasUpdatedAt;
use Traits\OpenApiHelperTrait;
use View\UserResponse;

class User extends ActiveRecord implements HasOpenApiDataModel
{
    use HasId, HasUpdatedAt, HasData, OpenApiHelperTrait {
        HasUpdatedAt::initialize insteadof HasData;
    }

    protected static $hidden     = ['password'];
    protected static $types      = ['enabled' => 'bool', 'roles' => 'json'];
    protected static $nullable   = ['full_name'];

    protected ?string $login     = null;
    protected ?string $password  = null;
    protected ?string $full_name = null;

    protected array $roles       = [Role::USER];
    protected bool $enabled      = true;

    /**
     * @var ?Access[]
     */
    private ?array $access       = null;

    public static function getOpenApiDataModel(): string
    {
        return UserResponse::class;
    }

    /**
     * @param array{login?:string,password?:string,roles?:Role|Role[],enabled?:bool,full_name?:string} $data
     * @param ?static                                                                                  $instance
     *
     * @return User
     */
    public static function make(array $data = [], $instance = null)
    {
        if ( ! empty($data['roles']) && (is_array($data['roles']) || $data['roles'] instanceof Role))
        {
            $roles = var_get('roles', $data);
            unset($data['roles']);
            return parent::make($data, $instance)->setRoles(is_array($roles) ? $roles : [$roles]);
        }

        return parent::make($data, $instance);
    }

    public function export()
    {
        return array_replace(
            parent::export(),
            ['permissions' => $this->getAccess(), 'data' => $this->data]
        );
    }

    public function getLogin(): ?string
    {
        return $this->login;
    }

    public function setLogin(?string $login): static
    {
        $this->login = $login;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = password_hash($password, PASSWORD_DEFAULT);
        return $this;
    }

    public function checkPassword(string $password): bool
    {
        if ( ! $this->password)
        {
            return false;
        }

        // bcrypt clear passwords
        if ( ! str_starts_with($this->password, '$'))
        {
            $this->setPassword($this->password);

            if ($this->id)
            {
                $this->save();
            }
        }

        return password_verify($password, $this->password);
    }

    public function getFullName(): ?string
    {
        return $this->full_name;
    }

    public function setFullName(?string $full_name): static
    {
        $this->full_name = $full_name;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * @param Role[] $roles
     *
     * @return static
     */
    public function setRoles(array $roles): static
    {
        $result      = [Role::USER];

        foreach ($roles as $role)
        {
            if (is_string($role))
            {
                $role = Role::from($role);
            }

            if (false === $role instanceof Role)
            {
                throw new \InvalidArgumentException('invalid role provided');
            }

            foreach ($role->getGroup() as $sub)
            {
                if ( ! in_array($sub, $result))
                {
                    $result[] = $sub;
                }
            }
        }

        $this->roles = $result;
        return $this;
    }

    public function addRole(Role $role): static
    {
        $roles = $this->getRoles();

        if ( ! in_array($role, $roles))
        {
            $roles[] = $role;
        }
        return $this->setRoles($roles);
    }

    public function removeRole(Role $role): static
    {
        return $this->setRoles(array_filter($this->getRoles(), fn ($item) => $item !== $role));
    }

    public function hasRole(Role $role): bool
    {
        return in_array($role, $this->getRoles());
    }

    /**
     * @return Access[]
     */
    public function getAccess(): array
    {
        return $this->access ??= Access::find(['user_id' => $this->id], ['access_group', 'permission' => 'desc']);
    }

    public function addAccess(Access $access): static
    {
        foreach ($this->getAccess() as $item)
        {
            if ($item->isSame($access))
            {
                return $this;
            }
        }
        $access->setUser($this);
        $this->access = null;
        return $this;
    }

    public function removeAccess(Access $access): static
    {
        if ($access->getUser()?->getId() === $this->getId())
        {
            $access->setUser(null);
            $this->access = null;
        }
        return $this;
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
        $this->created_at = date_create_immutable();
        $this->updated_at = date_create_immutable();
        $this->initialize_metadata();
        // hash plain text password
        $this->subscribe(
            CreateEntity::class,
            fn () => $this->password && ! str_starts_with($this->password, '$') && $this->setPassword($this->password),
            true
        );
    }
}
