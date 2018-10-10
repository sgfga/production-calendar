<?php

namespace Maximaster\ProductionCalendar\Tools;

interface RequestInterface
{
    public function query(array $urls);
    public function setOptions(array $options);
}