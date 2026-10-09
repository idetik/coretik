<?php

namespace Coretik\Core\Models\Traits;

/**
 * Instance events: listeners are kept by the instance, not registered as WordPress hooks,
 * so they are freed with it.
 */
trait Hooks
{
    /**
     * @var array<string, array<int, array<int, array{0: callable, 1: int}>>> Listeners by event and priority
     */
    private array $listeners = [];

    /**
     * Listen to an event of this instance.
     * Like add_action(): lower priorities run first, the callback receives up to $count_args arguments ($this, $args).
     */
    public function on(string $hook_name, $callback, $priority = 10, $count_args = 1)
    {
        $this->listeners[$hook_name][(int)$priority][] = [$callback, (int)$count_args];
        return $this;
    }

    /**
     * Stop listening to an event of this instance: one callback, or all of them.
     */
    public function off(string $hook_name, $callback = null)
    {
        if (null === $callback) {
            unset($this->listeners[$hook_name]);
            return $this;
        }

        foreach ($this->listeners[$hook_name] ?? [] as $priority => $listeners) {
            $this->listeners[$hook_name][$priority] = \array_values(\array_filter(
                $listeners,
                fn ($listener) => $listener[0] !== $callback
            ));
        }
        return $this;
    }

    public function trigger(string $hook_name, array $args = [])
    {
        $listeners = $this->listeners[$hook_name] ?? [];
        \ksort($listeners);

        foreach ($listeners as $callbacks) {
            foreach ($callbacks as [$callback, $count_args]) {
                \call_user_func_array($callback, \array_slice([$this, $args], 0, $count_args));
            }
        }

        $globalHook = $this->globalHookName($hook_name);
        if (null !== $globalHook) {
            \do_action($globalHook, $this, $args);
        }

        return $this;
    }

    /**
     * WordPress action fired for every instance on each event, null for none.
     */
    protected function globalHookName(string $hook_name): ?string
    {
        return null;
    }
}
