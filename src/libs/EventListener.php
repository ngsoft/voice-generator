<?php
/** @noinspection ALL */
namespace Observable{




interface Observable
{

    /**
     * @param Event $event
     * @return Event
     */
    public function dispatchEvent(Event $event);


    /**
     * @param non-empty-string $type
     * @param callable $listener
     * @return void
     */
    public function addEventListener($type, callable $listener);

}

}
namespace Observable{



class Event
{
    /** @var string */
    protected $type;

    /** @var mixed */
    protected $detail;
    /** @var bool */
    protected $propagationStopped = false;

    /** @var ?Observable */
    protected $observer = null;

    /** @return bool */
    final public function isPropagationStopped()
    {
        return $this->propagationStopped;
    }

    /** @return static */
    final public function stopPropagation()
    {
        $this->propagationStopped = true;
        return $this;
    }

    /**
     * @param non-empty-string $type
     * @param mixed $detail
     */
    public function __construct($type, $detail = null)
    {
        if (!$type || !is_string($type)) {
            throw new \InvalidArgumentException('$type must be a non empty string');
        }

        $this->type = $type;
        $this->detail = $detail;
    }

    /** @return string */
    final public function getType()
    {
        return $this->type;
    }


    /** @return mixed */
    final public function getDetail()
    {
        return $this->detail;
    }

    /**
     * @param mixed $detail
     * @return static
     */
    final public function setDetail($detail)
    {
        $this->detail = $detail;
        return $this;
    }

    /**
     * @return ?Observable
     */
    final public function getObserver()
    {
        return $this->observer;
    }

    /**
     * @param Observable $observer
     * @return Event
     */
    final public function setObserver(Observable $observer)
    {
        $this->observer = $observer;
        return $this;
    }


    /**
     * @param non-empty-string $type
     * @param mixed $detail
     * @return static
     */
    public static function newEvent($type, $detail = null)
    {
        return new static($type, $detail);
    }

}

}
namespace Observable{



final class EventDispatcher implements Observable
{

    private $listeners = [];


    public function dispatchEvent(Event $event)
    {

        if (!$event->isPropagationStopped()) {
            $event->setObserver($this);
            $type = $event->getType();
            if (!empty($this->listeners[$type])) {
                krsort($this->listeners[$type]);
                foreach ($this->listeners[$type] as $listenersSortedByPriority) {
                    foreach ($listenersSortedByPriority as $listener) {
                        $listener($event);
                        if ($event->isPropagationStopped()) {
                            break 2;
                        }
                    }
                }
            }
        }

        return $event;
    }

    /**
     * @param non-empty-string $type
     * @param callable $listener
     * @param int $priority greater priority will be executed first
     * @return void
     */
    public function addEventListener($type, callable $listener, $priority = 100)
    {
        if (!$type || !is_string($type)) {
            throw new \InvalidArgumentException('$type must be a non empty string');
        }

        if (!isset($this->listeners[$type])) {
            $this->listeners[$type] = [];
        }
        if (!isset($this->listeners[$type][$priority])) {
            $this->listeners[$type][$priority] = [];
        }
        $this->listeners[$type][$priority][] = $listener;

    }
}

}
namespace {

class EventListener
{
    /** @var null|Observable\EventDispatcher */
    protected static $instance = null;

    /**
     * @return Observable\EventDispatcher
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new Observable\EventDispatcher();
        }

        return self::$instance;
    }

    /**
     * @param non-empty-string $eventType
     * @param callable $listener
     * @param int $priority greater priority will be executed first
     * @return void
     */
    public static function addEventListener($eventType, callable $listener, $priority = 100)
    {
        self::getInstance()->addEventListener($eventType, $listener, $priority);
    }

    /**
     * @param non-empty-string $eventType
     * @param mixed $data
     * @return \Observable\Event
     */
    public static function dispatchEvent($eventType, $data = null)
    {
        return self::getInstance()->dispatchEvent(new Observable\Event($eventType, $data));
    }

}

}