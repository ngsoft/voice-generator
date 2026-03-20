<?php

use Interfaces\ActionInterface;

class Action
{
    protected static $actions = [];

    /**
     * @return array<string,string>
     */
    public static function getActions()
    {
        return self::$actions;
    }

    /**
     * Add actions to the stack if it does not exist.
     *
     * @param array<string,ActionInterface|class-string<ActionInterface>|string>|string[] $actions
     */
    public static function addActions(array $actions)
    {
        $values        = self::$actions;

        foreach ($actions as $alias => $action)
        {
            if ( ! is_string($alias))
            {
                $alias = $action;
            }

            $action = ltrim($action, '/');

            if ( ! isset($values[$alias]) && ! empty($action))
            {
                $values[$alias] = $action;
            }
        }
        self::$actions = $values;
    }

    /**
     * Initialize actions.
     *
     * @param array<string,ActionInterface|class-string<ActionInterface>|string>|string[] $actions
     */
    public static function setActions(array $actions)
    {
        $values        = [];

        foreach ($actions as $alias => $action)
        {
            if ( ! is_string($alias))
            {
                $alias = $action;
            }

            if ( ! empty($action))
            {
                $values[$alias] = $action;
            }
        }
        self::$actions = $values;
    }

    /**
     * @param string $action
     *
     * @return bool
     */
    public static function actionExists($action)
    {
        return null !== self::getAlias($action);
    }

    /**
     * @param string $action
     *
     * @return null|ActionInterface|string
     */
    public static function getActionPath($action)
    {
        $result = self::getAlias($action);

        if ( ! $result)
        {
            return null;
        }

        if (is_subclass_of($result, ActionInterface::class) && ActionInterface::class !== $result)
        {
            return $result;
        }

        $path   = normalize_path(__DIR__ . '/action')
            . '/'
            . $result . '.php';

        if (is_file($path))
        {
            return $path;
        }
        return null;
    }

    /**
     * @param string $action
     *
     * @return null|string
     */
    public static function getView($action)
    {
        $result = self::getAlias($action) ?? $action;

        if ( ! $result)
        {
            return null;
        }

        $path   = normalize_path(resolve_path('%project_root%/view'))
            . '/'
            . $result . '.php';

        if (is_file($path))
        {
            return $result;
        }

        return null;
    }

    /**
     * @param string $action
     *
     * @return ?string
     */
    protected static function getAlias($action)
    {
        if ( ! is_string($action))
        {
            return null;
        }

        foreach (self::$actions as $alias => $result)
        {
            // slashes cannot be used as it is a path
            if ($alias !== ltrim($alias, '%#~'))
            {
                if (preg_match($alias, $action))
                {
                    return $result;
                }
            } elseif (strtolower($action) === strtolower($alias))
            {
                return $result;
            }
        }
        return null;
    }
}
