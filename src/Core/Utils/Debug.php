<?php

namespace Coretik\Core\Utils;

use Coretik\Core\Collection;

use function Globalis\WP\Cubi\mysql_enable_nocache_mod;
use function Globalis\WP\Cubi\mysql_disable_nocache_mod;
use function Globalis\WP\Cubi\time_start;
use function Globalis\WP\Cubi\time_elapsed;
use function Globalis\WP\Cubi\memory_get_usage_mb;
use function Globalis\WP\Cubi\memory_get_peak_usage_mb;
use function Globalis\WP\Cubi\memory_usage_format;

class Debug
{
    public static function benchmark(array|callable $action, bool $echo = true, bool $withBuffer = true)
    {
        mysql_enable_nocache_mod();

        $table = app()->get('ux.table')
                    ->setColumns(['Scenario', 'Time elapsed', 'Memory usage (mb)', 'Memory peak (mb)', 'Result'])
                    ->withFooter(false);

        $data = [];
        foreach (Arr::wrap($action) as $scenario => $callback) {
            time_start($scenario);
            $memory_usage_before = \memory_get_usage(false);
            if (function_exists('\memory_reset_peak_usage')) {
                \memory_reset_peak_usage();
            }

            \ob_start();
            $result = $callback();
            $buffer = \ob_get_clean();

            if ($withBuffer && !empty($buffer)) {
                $result .= (!empty($result) ? '<br/><br/>' : '') . '<b>>>>> Buffer:</b><br/>' . $buffer;
            }

            $time = time_elapsed($scenario);
            $memory_usage_after = \memory_get_usage(false);
            $memory_usage = memory_usage_format(($memory_usage_after - $memory_usage_before) / 1024 / 1024, 'MB', true);
            $memory_peak = memory_get_peak_usage_mb();
            $data[] = [$scenario, $time, $memory_usage, $memory_peak, $result];
        }

        $table->setData($data);
        mysql_disable_nocache_mod();

        if (!$echo) {
            return $table;
        }

        $table->render();
    }
}
