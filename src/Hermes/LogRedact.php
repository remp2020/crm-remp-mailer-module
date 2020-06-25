<?php

namespace Crm\RempMailerModule\Hermes;

class LogRedact
{
    public static function add($filters)
    {
        return function ($record) use ($filters) {
            foreach ($filters as $filter) {
                if (isset($record['context']['payload']['params'][$filter])) {
                    $record['context']['payload']['params'][$filter] = '******';
                }
            }
            return $record;
        };
    }
}
