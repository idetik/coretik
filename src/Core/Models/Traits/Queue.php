<?php

// @todo service externe

// namespace Coretik\Core\Models\Traits;

// trait Queue
// {
//     protected static $queue;
//     protected static $queueSettings = [
//         'option_key' => 'coretik-queue'
//     ];

//     protected static function hooksQueue()
//     {
//         $queue = self::getQueue();
//         if (empty($queue)) {
//             return;
//         }
//         $self_queue = $queue[static::POST_TYPE] ?? [];
//         foreach ($self_queue as $data) {
//             $callback = $data['callback'];
//             $hook = $data['hook'];
//             add_action($data['hook'], function () use ($callback, $hook) {
//                 $callback(...func_get_args());
//                 self::dequeue($hook);
//             }, $data['priority'], count($data['args']));
//         }
//     }

//     public function enqueue($time, callable $callback, $args = [], $priority = 10)
//     {
//         $hook = $this->name() . '/' . $this->id() . '/queue/' . uniqid();
//         $queue = self::getQueue();
//         $queue[$this->name()][$hook] = [
//             'hook' => $hook,
//             'callback' => $callback,
//             'priority' => $priority,
//             'args' => $args
//         ];
//         self::updateQueue($queue);
//         wp_schedule_single_event($time, $hook, $args);
//     }

//     protected static function dequeue($hook)
//     {
//         $queue = self::getQueue();
//         if (!isset($queue[static::POST_TYPE])) {
//             return;
//         }
//         $self_queue = $queue[static::POST_TYPE];
//         if (array_key_exists($hook, $self_queue)) {
//             unset($self_queue[$hook]);
//             $queue[static::POST_TYPE] = $self_queue;
//             self::updateQueue($queue);
//         }
//     }

//     protected static function getQueue(): array
//     {
//         return get_option(static::$queueSettings['option_key']) ?: [];
//     }

//     protected static function updateQueue($queue)
//     {
//         update_option(static::$queueSettings['option_key'], $queue);
//     }
// }
