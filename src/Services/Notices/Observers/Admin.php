<?php

namespace Coretik\Services\Notices\Observers;

use Coretik\Services\Notices\Iterators\FilterValidIterator;

class Admin implements \SplObserver
{
    // @todo :
    // !Did action hook notice => hook admin notice
    // Sinon => bdd option notice
    // param persistant ?
    public function update(\SplSubject $container)
    {
        $iterator = new FilterValidIterator($container->getIterator());
        if ($iterator->count() === 0) {
            $container->storage->set($iterator);
            return;
        }

        if (\did_action('admin_notices')) {
            $container->storage->set($iterator);
            return;
        }

        \add_action('admin_notices', function () use ($iterator) {
            foreach ($iterator as $notice) {
                $notice->display();
            }
        });
    }
}
