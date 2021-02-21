<?php

namespace Coretik\Core\Query\Clause;

use Coretik\Core\Query\Interfaces\DateClauseInterface;

class DateClause extends WhereClause implements DateClauseInterface
{
    protected $column;
    protected $inclusive;
    protected $year;
    protected $month;
    protected $week;
    protected $day;
    protected $hour;
    protected $minute;
    protected $second;
    protected $after;
    protected $before;

    public function __construct(string $column, $date, string $compare = '=', bool $inclusive = true)
    {
        switch (true) {
            case $date instanceof \DateTime:
                $this->year = $date->format('Y');
                $this->month = $date->format('m');
                $this->week = $date->format('W');
                $this->day = $date->format('d');
                $this->hour = $date->format('H');
                $this->minute = $date->format('i');
                $this->second = $date->format('s');
                break;
            case is_array($date):
                foreach ($date as $prop => $value) {
                    if (\property_exists($this, $prop)) {
                        $this->$prop = $value;
                    }
                }
                break;
        }
        parent::__construct($column, $date, $compare);
        $this->inclusive = $inclusive;
    }

    public function year(): int
    {
        return $this->year;
    }

    public function month(): int
    {
        return $this->month;
    }

    public function week(): int
    {
        return $this->week;
    }

    public function day(): int
    {
        return $this->day;
    }

    public function hour(): int
    {
        return $this->hour;
    }

    public function minute(): int
    {
        return $this->minute;
    }

    public function second(): int
    {
        return $this->second;
    }

    public function after()
    {
        return $this->after;
    }

    public function before()
    {
        return $this->before;
    }
}
