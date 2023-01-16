<?php

namespace Coretik\Services\Notices\Observers;

use Coretik\Services\Notices\Iterators\FilterValidIterator;

class Admin implements \SplObserver
{
    public function update(\SplSubject $container): void
    {
        if (!\is_admin()) {
            return;
        }

        if (0 === $container->getIterator()->count()) {
            return;
        }


        $alive_notices = new FilterValidIterator($container->getIterator());

        if (iterator_count($alive_notices) === 0 || \did_action('admin_notices')) {
            $alive_notices_array = \iterator_to_array($alive_notices);
            $container->storage()->set(new \ArrayIterator($alive_notices_array));
            return;
        }

        \add_action('admin_notices', function () use ($alive_notices, $container) {
            foreach ($alive_notices as $notice) {
                $notice->display();
            }

            $updated = new FilterValidIterator($alive_notices->getInnerIterator());
            $container->storage()->set(new \ArrayIterator(iterator_to_array($updated)));
        });
    }
}
