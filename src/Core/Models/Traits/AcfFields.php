<?php

namespace Coretik\Core\Models\Traits;

use Carbon\Carbon;
use Coretik\Core\Utils\Classes;
use Coretik\Core\Models\Exceptions\AdapterNotFoundException;
use Coretik\Core\Models\Interfaces\MetableAdapterInterface;
use Coretik\Core\Models\Wp\ {
    UserModel,
    TermModel,
    CommentModel,
};

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
        return \get_field($key, $this->acfId());
    }

    public function getFieldAsDateTime(string $prop): Carbon
    {
        $object = \get_field_object($prop, $this->acfId(), true, true);
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
        return \get_field($prop, $this->acfId(), false);
    }

    public function acfId(): string
    {
        return match (true) {
            $this instanceof TermModel => sprintf('term_%s', $this->id),
            $this instanceof UserModel => sprintf('user_%s', $this->id),
            default => $this->id,
        };
    }
}
