<?php
declare(strict_types=1);

namespace Npf\Core {

    use JetBrains\PhpStorm\Pure;


    /**
     * Class EventEmitter
     * @package Core
     */
    class Event
    {
        private array $listeners = [];
        private array $timerListener = [];
        private array $scheduleListener = [];
        private array $termListener = [];
        private array $eventParams = [];
        private int $timerOut = 0;
        private int $tick = 0;
        private float $timerLastTimestamp = 0;

        /**
         * EventEmitter constructor.
         * @param array $eventParams the params will be pass when a emit.
         */
        public function __construct(array $eventParams = [])
        {
            $this->setParams($eventParams);
        }

        /**
         * Setup Emitter Parameter
         * @param array $eventParams the params will be pass when a emit.
         * @return Event
         */
        final public function setParams(array $eventParams): self
        {
            $this->eventParams = $eventParams;
            return $this;
        }

        /**
         * Execute/Fire an terminal Event
         */
        final public function launchSignalListener(): void
        {
            //Register Signal for event
            declare(ticks=1);
            pcntl_signal(SIGKILL, [$this, 'emitTermSignal']);
            pcntl_signal(SIGINT, [$this, 'emitTermSignal']);
            pcntl_signal(SIGQUIT, [$this, 'emitTermSignal']);
            pcntl_signal(SIGSTOP, [$this, 'emitTermSignal']);
            pcntl_signal(SIGTERM, [$this, 'emitTermSignal']);
            pcntl_signal(SIGHUP, [$this, 'emitTermSignal']);
            pcntl_signal(SIGUSR1, [$this, 'emitTermSignal']);
            pcntl_signal(SIGUSR2, [$this, 'emitTermSignal']);
            pcntl_signal(SIGPIPE, [$this, 'emitTermSignal']);
            pcntl_signal(SIGPOLL, [$this, 'emitTermSignal']);
            pcntl_signal(SIGXCPU, [$this, 'emitTermSignal']);
        }

        /**
         * @param callable $listener Event Listener to register
         * @param int $priority Event Priority
         * @return self
         */
        final public function onTermSignal(callable $listener, int $priority = 0): self
        {
            $this->termListener[] = ["listener" => $listener, "priority" => $priority];
            Common::multiArraySort($this->termListener, ["priority" => [SORT_DESC, SORT_NATURAL]]);
            return $this;
        }

        /**
         * Register Once Time Listener
         * @param string $event Event Name to register
         * @param callable $listener Event Listener to register
         * @param int $priority Event Priority
         * @return Event
         */
        final public function once(string $event, callable $listener, int $priority = 0): self
        {
            return self::on($event, $listener, 1, $priority);
        }

        /**
         * @param string $event Event Name to register
         * @param callable $listener Event Listener to register
         * @param int|string $times Event available fire times, 0 = not limit.
         * @param int $priority Event Priority
         * @return Event
         */
        final public function on(string $event,
                                 callable $listener,
                                 int|string $times = 0,
                                 int $priority = 0): self
        {
            if (!empty($event)) {
                if (!isset($this->listeners[$event]))
                    $this->listeners[$event] = [];
                $this->listeners[$event][] = ["listener" => $listener, "times" => $times, "priority" => $priority];
                Common::multiArraySort($this->listeners[$event], ["priority" => [SORT_DESC, SORT_NATURAL]]);
            }
            return $this;
        }

        /**
         * Turn off one off event listener if listener is same.
         * @param string $event Event Name to turn off
         * @param callable $listener Event Listener to turn off
         * @param bool $all Remove only match or all match event listener
         * @return Event
         */
        final public function off(string $event,
                                  callable $listener,
                                  bool $all = true): self
        {
            if (!empty($event) && isset($this->listeners[$event])) {
                foreach ($this->listeners[$event] as $key => $event)
                    if ($event['listener'] === $listener) {
                        unset($this->listeners[$event][$key]);
                        if (!$all)
                            break;
                    }
            }
            return $this;
        }

        /**
         * Remove Event
         * @param string $event Event Name to remove
         * @return Event
         */
        final public function removeEvent(string $event): self
        {
            if (!empty($event) && isset($this->listeners[$event]))
                unset($this->listeners[$event]);
            return $this;
        }

        /**
         * Event on every n tick
         * @param callable $listener
         * @param int $tick
         * @param int|string $times
         * @param int $priority
         * @return self
         */
        final public function onTick(callable $listener,
                                     int $tick = 1,
                                     int|string $times = 0,
                                     int $priority = 0): self
        {
            $event = 'timerTick';
            if (!isset($this->timerListener[$event]) && !empty($interval))
                $this->timerListener[$event] = [];

            $this->timerListener[$event][] = ["listener" => $listener, "tick" => $tick, "times" => $times, "priority" => $priority];
            Common::multiArraySort($this->timerListener[$event], ["priority" => [SORT_DESC, SORT_NATURAL]]);
            return $this;
        }

        /**
         * 支持 (*), 但不支持 (* / N)
         * Event on Schedule (like cron)
         * @param string $schedule
         * @param callable $listener
         * @param int|string $times
         * @param int $priority
         * @return self
         */
        final public function onSchedule(string $schedule,
                                         callable $listener,
                                         int|string $times = 0,
                                         int $priority = 0): self
        {
            if (self::scheduleValidate($schedule)) {
                if (!isset($this->scheduleListener[$schedule]))
                    $this->scheduleListener[$schedule] = [];
                $this->scheduleListener[$schedule][] = ["listener" => $listener, "times" => $times, "priority" => $priority];
                Common::multiArraySort($this->scheduleListener[$schedule], ["priority" => [SORT_DESC, SORT_NATURAL]]);
            }
            return $this;
        }

        /**
         * Validate schedule format is valid or not.
         * @param string $schedule
         * @return boolean
         */
        private function scheduleValidate(string $schedule): bool
        {
            $parts = explode(" ", $schedule);
            if (6 !== count($parts))
                return false;
            foreach ($parts as $part)
                if (preg_match("/[^,\\-*0-9]|(,,)|(--)/", $part))
                    return false;
            return true;
        }

        /**
         * Launch
         * @param int $timeout Timer Stop Timing
         * @param int $interval Timer Interval
         * @return self
         * @internal param $event
         */
        final public function launchTimer(int $timeout, int $interval = 1000): self
        {
            $this->timerOut = $timeout;
            $this->timerLastTimestamp = ceil(Common::timestamp(true));

            $this->emit('timerStart');
            while ($this->timerTick())
                usleep($interval * 1000);
            $this->emit('timerStop');

            return $this;
        }

        /**
         * Execute/Fire an event
         * @param string $eventName
         * @param array $args
         * @return self
         */
        final public function emit(string $eventName, array $args = []): self
        {
            if (!empty($eventName) && isset($this->listeners[$eventName])) {
                foreach ($this->listeners[$eventName] as $key => &$event)
                    if (isset($event['listener'])) {
                        $result = $this->eventFire($event['listener'], $args);
                        $event['times']--;
                        if ($event['times'] === 0)
                            unset($this->listeners[$event][$key]);
                        if ($result === false)
                            break;
                    }
            }
            return $this;
        }

        /**
         * @param callable $callable
         * @param mixed $args
         * @return mixed
         */
        private function eventFire(callable $callable, array|string|int|float|bool $args = []): mixed
        {
            if (!is_array($args))
                $args = [$args];
            return call_user_func_array($callable, array_merge($this->eventParams, $args));
        }

        /**
         * Timer Tick
         * @return bool
         */
        private function timerTick(): bool
        {
            $elapsed = $this->elapsed();
            if ($elapsed <= $this->timerOut) {
                $now = ceil(Common::timestamp(true));
                $this->emitSchedule();
                $offset = $now - $this->timerLastTimestamp - 1;
                $this->timerEmit($now, (int)$offset);
                $this->timerLastTimestamp = $now;
                $this->tick++;
                return true;
            } else {
                return false;
            }
        }

        /**
         * get elapsed milli seconds
         * @param bool $milliSecond
         * @param bool $current Start Current or initial time
         * @return float
         */
        #[Pure] final public function elapsed(bool $milliSecond = false, bool $current = false): float
        {
            $now = Common::timestamp($current);
            return (true === $milliSecond) ? floor((microtime(true) - $now) * 1000) : floor(microtime(true) - $now);
        }

        /**
         * Execute/Fire an schedule
         * @return self
         */
        final public function emitSchedule(): self
        {
            if (!empty($this->scheduleListener)) {
                foreach ($this->scheduleListener as $schedule => &$events)
                    if ($this->scheduleMatch($schedule)) {
                        foreach ($events as $key => &$event)
                            if (isset($event['listener'])) {
                                $result = $this->eventFire($event['listener'], $schedule);
                                $event['times']--;
                                if (0 === $event['times'])
                                    unset($events[$key]);
                                if ($result === false)
                                    break;
                            }
                    }
            }
            return $this;
        }

        /**
         * @param string $schedule
         * @return bool
         */
        private function scheduleMatch(string $schedule): bool
        {
            $date = explode(":", date("s:i:H:d:m:N"));
            $schedule = explode(" ", $schedule);
            for ($i = 0; $i < 6; $i++) {
                if ("*" === $schedule[$i] || $date[$i] === $schedule[$i]) {
                    continue;
                } elseif (str_contains($schedule[$i], ',')) {
                    if (in_array(ltrim($date[$i], '0'), explode(",", $schedule[$i]), true)) {
                        continue;
                    }
                } elseif (str_contains($schedule[$i], '-')) {
                    $parts = explode("-", $schedule[$i]);
                    if (in_array((int)ltrim($date[$i], '0'), range($parts[0], $parts[1]), true)) {
                        continue;
                    }
                }
                return false;
            }
            return true;
        }

        /**
         * Execute/Fire an event
         * @param float $timestamp
         * @param int $offset
         * @return void
         * @internal param string $event Event Name
         */
        private function timerEmit(float $timestamp, int $offset): void
        {
            if (isset($this->timerListener['timerTick'])) {
                foreach ($this->timerListener['timerTick'] as $key => $event)
                    if (isset($event['listener']) && is_callable($event['listener'])) {
                        if (!isset($event['tick']) || (int)$event['tick'] < 1)
                            $emit = true;
                        elseif ($this->tick % (int)$event['tick'] === 0)
                            $emit = true;
                        else
                            $emit = false;
                        if ($emit) {
                            $result = $this->eventFire($event['listener'], [$event, $timestamp, $offset]);
                            $event['times']--;
                            if (0 === $event['times'])
                                unset($this->timerListener[$event][$key]);
                            if ($result === false)
                                break;
                        }
                    }
            }
        }

        /**
         * Execute/Fire an terminal Event
         */
        private function emitTermSignal(): void
        {
            $this->eventParams[] = 0;
            foreach ($this->termListener as $event)
                if (isset($event['listener'])) {
                    $result = $this->eventFire($event['listener']);
                    if ($result === false)
                        break;
                }
        }
    }
}