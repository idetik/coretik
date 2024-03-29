<?php

namespace Coretik\Services\Notices\Observers;

use Coretik\Services\Notices\Iterators\FilterValidIterator;
use Coretik\Services\Notices\NoticeSuccess;
use Coretik\Services\Notices\NoticeError;

class WPCli implements \SplObserver
{
    public function update(\SplSubject $container): void
    {
        if (!\defined('WP_CLI') || !WP_CLI) {
            return;
        }

        if (0 === $container->getIterator()->count()) {
            return;
        }

        $alive_notices = new FilterValidIterator($container->getIterator());

        if (iterator_count($alive_notices) === 0) {
            $alive_notices_array = \iterator_to_array($alive_notices);
            $container->storage()->set(new \ArrayIterator($alive_notices_array));
            return;
        }

        foreach ($alive_notices as $notice) {
            switch ($notice::TYPE ?? '') {
                case 'success':
                    \WP_CLI::success((string) $notice);
                    break;
                case 'error':
                    \WP_CLI::error((string) $notice);
                    break;
                case 'warning':
                    \WP_CLI::warning((string) $notice);
                    break;
                case 'info':
                default:
                    \WP_CLI::line((string) $notice);
                    break;
            }
            $notice->setCompleted();
        }

        $updated = new FilterValidIterator($alive_notices->getInnerIterator());
        $container->storage()->set(new \ArrayIterator(iterator_to_array($updated)));
    }
}
