<?php

namespace Coretik\Core\Models\Traits;

use Coretik\Core\Utils\Classes;
use Coretik\Core\Models\Exceptions\AdapterNotFoundException;
use Coretik\Core\Models\Interfaces\MetableAdapterInterface;
use Carbon\Carbon;

trait AcfFields
{
    use Metable;

    protected function initializeAcfFields()
    {
        if (!$this->adapter instanceof MetableAdapterInterface) {
            throw new AdapterNotFoundException('Adapter of "' . __CLASS__ . '" does not implement MetableAdapterInterface.');
        }
    }

    public function getField(string $key)
    {
        return \get_field($key, $this->id);
    }

    public function getFieldAsDateTime(string $prop): Carbon
    {
        $object = \get_field_object($prop, $this->id, true, true);
        if (empty($object) || empty($object['value'])) {
            return null;
        }
        return $this->asDateTime(\DateTime::createFromFormat(
            $object['return_format'],
            $object['value'],
            app()->get('settings')->timezone
        ));
    }

    public function getUnFormattedField(string $prop)
    {
        return \get_field($prop, $this->id, false);
    }
}
